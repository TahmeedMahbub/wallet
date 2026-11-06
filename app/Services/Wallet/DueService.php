<?php

namespace App\Services\Wallet;

use App\Enums\DueDirection;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Due;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Months;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DueService
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly PostingWriter $writer,
    ) {}

    /**
     * Money given to someone. Not an expense: it moves to "You'll get back".
     *
     * @param  array{person: string, amount: int, account_id: int, due_date?: string|null, note?: string|null, occurred_at?: string|CarbonInterface|null}  $data
     *
     * @throws ValidationException
     */
    public function lend(User $user, array $data, ?string $idempotencyKey = null): Due
    {
        return $this->open($user, DueDirection::Lent, $data, $idempotencyKey);
    }

    /**
     * A loan taken. Not income: it adds to "You need to pay".
     *
     * @param  array{person: string, amount: int, account_id: int, due_date?: string|null, note?: string|null, occurred_at?: string|CarbonInterface|null}  $data
     *
     * @throws ValidationException
     */
    public function borrow(User $user, array $data, ?string $idempotencyKey = null): Due
    {
        return $this->open($user, DueDirection::Borrowed, $data, $idempotencyKey);
    }

    /**
     * Record a (partial) payment on a due. It may not be more than what is left.
     *
     * @throws ValidationException
     */
    public function recordPayment(User $user, Due $due, int $amount, Account $account, string|CarbonInterface|null $at = null, ?string $idempotencyKey = null): Transaction
    {
        return $this->unitOfWork->run($user, function () use ($user, $due, $amount, $account, $at): Transaction {
            $due = Due::query()->findOrFail($due->getKey());
            $account = Account::query()->findOrFail($account->getKey());

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Enter an amount.']);
            }

            if ($amount > $this->remaining($due)) {
                throw ValidationException::withMessages(['amount' => 'That is more than what is left to settle.']);
            }

            $delta = $due->direction === DueDirection::Lent ? $amount : -$amount;

            $transaction = $this->writer->create(TransactionType::DuePayment, $amount, Months::fromInput($user, $at), [
                'account_id' => $account->getKey(),
                'due_id' => $due->getKey(),
            ], [$account->getKey() => $delta]);

            $this->writer->assertNotOverdrawn([$account->getKey()], 'amount', $amount);

            return $transaction;
        }, $idempotencyKey);
    }

    /**
     * Paid so far (poisha).
     */
    public function paid(Due $due): int
    {
        return (int) Transaction::query()->where('type', TransactionType::DuePayment)->where('due_id', $due->getKey())->sum('amount');
    }

    public function remaining(Due $due): int
    {
        return max(0, (int) $due->amount - $this->paid($due));
    }

    /**
     * Dues of one direction with progress, newest first.
     *
     * @return Collection<int, array{due: Due, paid: int, remaining: int, status: string}>
     */
    public function list(User $user, DueDirection $direction): Collection
    {
        $paid = Transaction::query()->where('type', TransactionType::DuePayment)
            ->selectRaw('due_id, SUM(amount) as total')->groupBy('due_id')->pluck('total', 'due_id');
        $today = Months::today($user)->toDateString();

        return Due::query()->where('direction', $direction)->orderByDesc('id')->get()->map(function (Due $due) use ($paid, $today): array {
            $paidAmount = (int) ($paid[$due->getKey()] ?? 0);
            $remaining = max(0, (int) $due->amount - $paidAmount);
            $status = $remaining === 0
                ? 'settled'
                : ($due->due_date !== null && $due->due_date->toDateString() < $today ? 'late' : ($paidAmount > 0 ? 'partly' : 'open'));

            return ['due' => $due, 'paid' => $paidAmount, 'remaining' => $remaining, 'status' => $status];
        });
    }

    /**
     * What is still owed in one direction (poisha).
     */
    public function outstanding(User $user, DueDirection $direction): int
    {
        return (int) $this->list($user, $direction)->sum('remaining');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function open(User $user, DueDirection $direction, array $data, ?string $idempotencyKey): Due
    {
        return $this->unitOfWork->run($user, function () use ($user, $direction, $data): Due {
            $person = trim((string) ($data['person'] ?? ''));
            $amount = (int) ($data['amount'] ?? 0);

            if ($person === '') {
                throw ValidationException::withMessages(['person' => 'Enter a name.']);
            }

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Enter an amount.']);
            }

            $account = Account::query()->findOrFail($data['account_id']);

            $due = Due::create([
                'direction' => $direction,
                'person' => mb_substr($person, 0, 120),
                'amount' => $amount,
                'note' => ($data['note'] ?? null) ?: null,
                'due_date' => ($data['due_date'] ?? null) ?: null,
            ]);

            $lent = $direction === DueDirection::Lent;

            $this->writer->create($lent ? TransactionType::Lend : TransactionType::Borrow, $amount, Months::fromInput($user, $data['occurred_at'] ?? null), [
                'account_id' => $account->getKey(),
                'due_id' => $due->getKey(),
                'note' => $due->note,
            ], [$account->getKey() => $lent ? -$amount : $amount]);

            $this->writer->assertNotOverdrawn([$account->getKey()], 'amount', $amount);

            return $due;
        }, $idempotencyKey);
    }
}

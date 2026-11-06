<?php

namespace App\Services\Wallet;

use App\Enums\GoalKind;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\SavingPlace;
use App\Models\SavingReason;
use App\Models\SavingsGoal;
use App\Models\SavingsMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Months;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SavingsService
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly PostingWriter $writer,
        private readonly BalanceService $balances,
    ) {}

    /**
     * Suggested monthly amount: target divided by months, rounded up to the next ৳100
     * (Figma: ৳25,000 over 6 months suggests ৳4,200).
     */
    public static function suggestMonthly(int $target, int $months): int
    {
        if ($target <= 0 || $months <= 0) {
            return 0;
        }

        return (int) (ceil($target / $months / 10_000) * 10_000);
    }

    /**
     * @param  array{reason_id: int, place_id: int, kind: GoalKind, target_amount: int, months: int|null, monthly_amount: int, auto_save: bool}  $data
     *
     * @throws ValidationException
     */
    public function createGoal(User $user, array $data, ?string $idempotencyKey = null): SavingsGoal
    {
        return $this->unitOfWork->run($user, function () use ($data): SavingsGoal {
            SavingReason::query()->findOrFail($data['reason_id']);
            SavingPlace::query()->findOrFail($data['place_id']);

            if (($data['target_amount'] ?? 0) <= 0) {
                throw ValidationException::withMessages(['target_amount' => 'Enter a target amount to continue.']);
            }

            /** @var GoalKind $kind */
            $kind = $data['kind'];
            $months = $kind->hasMonths() ? ($data['months'] ?? null) : null;

            if ($kind->hasMonths() && ($months === null || $months <= 0)) {
                throw ValidationException::withMessages(['months' => 'Enter how many months.']);
            }

            if (($data['monthly_amount'] ?? 0) <= 0) {
                throw ValidationException::withMessages(['monthly_amount' => 'Enter a monthly amount.']);
            }

            return SavingsGoal::create([
                'reason_id' => $data['reason_id'],
                'place_id' => $data['place_id'],
                'kind' => $kind,
                'target_amount' => $data['target_amount'],
                'months' => $months,
                'monthly_amount' => $data['monthly_amount'],
                'auto_save' => (bool) $data['auto_save'],
            ]);
        }, $idempotencyKey);
    }

    /**
     * Money currently kept in a goal (poisha).
     */
    public function goalBalance(int $goalId): int
    {
        return (int) SavingsMovement::query()->where('goal_id', $goalId)->sum('amount');
    }

    /**
     * Goals with their balances.
     *
     * @return Collection<int, array{goal: SavingsGoal, balance: int, reached: bool, percent: int}>
     */
    public function goals(): Collection
    {
        $balances = SavingsMovement::query()
            ->selectRaw('goal_id, SUM(amount) as total')
            ->groupBy('goal_id')
            ->pluck('total', 'goal_id');

        return SavingsGoal::query()->with(['reason', 'place'])->orderBy('id')->get()->map(function (SavingsGoal $goal) use ($balances): array {
            $balance = (int) ($balances[$goal->getKey()] ?? 0);

            return [
                'goal' => $goal,
                'balance' => $balance,
                'reached' => $goal->target_amount > 0 && $balance >= $goal->target_amount,
                'percent' => $goal->target_amount > 0 ? min(100, (int) floor($balance * 100 / $goal->target_amount)) : 0,
            ];
        });
    }

    /**
     * Total saved across all goals (poisha).
     */
    public function total(): int
    {
        return (int) SavingsMovement::query()->sum('amount');
    }

    /**
     * Money kept in each place, only places that hold money.
     *
     * @return Collection<int, array{place: SavingPlace, total: int}>
     */
    public function byPlace(): Collection
    {
        return $this->goals()
            ->groupBy(fn (array $row): int => $row['goal']->place_id)
            ->map(fn (Collection $rows): array => ['place' => $rows->first()['goal']->place, 'total' => (int) $rows->sum('balance')])
            ->filter(fn (array $row): bool => $row['total'] > 0)
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Add extra money from an account to one goal.
     *
     * @throws ValidationException
     */
    public function deposit(User $user, SavingsGoal $goal, Account $account, int $amount, ?CarbonInterface $at = null, ?string $idempotencyKey = null): Transaction
    {
        return $this->unitOfWork->run($user, function () use ($goal, $account, $amount, $at): Transaction {
            $this->assertPositive($amount);
            $goal = SavingsGoal::query()->findOrFail($goal->getKey());
            $account = Account::query()->findOrFail($account->getKey());

            $transaction = $this->writer->create(TransactionType::SavingDeposit, $amount, $at ?? now(), [
                'account_id' => $account->getKey(),
            ], [$account->getKey() => -$amount]);

            SavingsMovement::create(['goal_id' => $goal->getKey(), 'transaction_id' => $transaction->getKey(), 'amount' => $amount]);
            $this->writer->assertNotOverdrawn([$account->getKey()], 'amount', $amount);

            return $transaction;
        }, $idempotencyKey);
    }

    /**
     * Move money from a goal back into an account. It is not an expense until it is spent.
     *
     * @throws ValidationException
     */
    public function withdraw(User $user, SavingsGoal $goal, Account $account, int $amount, ?CarbonInterface $at = null, ?string $idempotencyKey = null): Transaction
    {
        return $this->unitOfWork->run($user, function () use ($goal, $account, $amount, $at): Transaction {
            $this->assertPositive($amount);
            $goal = SavingsGoal::query()->findOrFail($goal->getKey());
            $account = Account::query()->findOrFail($account->getKey());

            if ($amount > $this->goalBalance($goal->getKey())) {
                throw ValidationException::withMessages(['amount' => 'This goal does not hold that much.']);
            }

            $transaction = $this->writer->create(TransactionType::SavingWithdrawal, $amount, $at ?? now(), [
                'account_id' => $account->getKey(),
            ], [$account->getKey() => $amount]);

            SavingsMovement::create(['goal_id' => $goal->getKey(), 'transaction_id' => $transaction->getKey(), 'amount' => -$amount]);

            return $transaction;
        }, $idempotencyKey);
    }

    /**
     * Move each auto-save goal's monthly amount out of the account the salary
     * landed in. All-or-nothing: if the account cannot cover the whole plan, the
     * income is kept, nothing is saved, and the user adds money by hand.
     * Goals that already reached their target are skipped.
     *
     * Must be called inside a UnitOfWork.
     *
     * @return string|null 'ran', 'skipped', or null when there is nothing to auto-save
     */
    public function autoSave(User $user, Transaction $income): ?string
    {
        $goals = $this->goals()->filter(fn (array $row): bool => $row['goal']->auto_save && ! $row['reached']);

        if ($goals->isEmpty()) {
            return null;
        }

        $total = (int) $goals->sum(fn (array $row): int => (int) $row['goal']->monthly_amount);

        if ($this->balances->of($income->account_id) < $total) {
            $income->update(['auto_save_status' => 'skipped']);

            return 'skipped';
        }

        $deposit = $this->writer->create(TransactionType::SavingDeposit, $total, $income->occurred_at, [
            'account_id' => $income->account_id,
            'parent_transaction_id' => $income->getKey(),
            'note' => 'Auto-saved to '.$goals->count().' '.($goals->count() === 1 ? 'goal' : 'goals'),
        ], [$income->account_id => -$total]);

        foreach ($goals as $row) {
            SavingsMovement::create([
                'goal_id' => $row['goal']->getKey(),
                'transaction_id' => $deposit->getKey(),
                'amount' => (int) $row['goal']->monthly_amount,
            ]);
        }

        $income->update(['auto_save_status' => 'ran']);

        return 'ran';
    }

    /**
     * Monthly amount that auto-saves on the next salary (poisha).
     */
    public function nextAutoSaveTotal(): int
    {
        return (int) $this->goals()
            ->filter(fn (array $row): bool => $row['goal']->auto_save && ! $row['reached'])
            ->sum(fn (array $row): int => (int) $row['goal']->monthly_amount);
    }

    /**
     * Money saved this month: deposits minus withdrawals, in the user's month.
     */
    public function savedInMonth(User $user, CarbonInterface $month): int
    {
        [$from, $to] = Months::utcRange($user, $month);

        $deposits = (int) Transaction::query()->where('type', TransactionType::SavingDeposit)
            ->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to)->sum('amount');
        $withdrawals = (int) Transaction::query()->where('type', TransactionType::SavingWithdrawal)
            ->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to)->sum('amount');

        return $deposits - $withdrawals;
    }

    private function assertPositive(int $amount): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount.']);
        }
    }
}

<?php

namespace App\Services\Wallet;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Due;
use App\Models\IncomeSource;
use App\Models\LedgerPosting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Wallet\Data\BudgetRow;
use App\Support\Months;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Expenses, income and transfers: create, edit and delete, with balances kept
 * correct through ledger postings.
 */
class LedgerService
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly PostingWriter $writer,
        private readonly BudgetService $budgets,
        private readonly SavingsService $savings,
    ) {}

    /**
     * @param  array<string, mixed>  $data  amount (poisha), account_id, category_id, area, reason, occurred_at
     * @return array{transaction: Transaction, budget: BudgetRow|null}
     *
     * @throws ValidationException
     */
    public function expense(User $user, array $data, ?string $idempotencyKey = null): array
    {
        return $this->unitOfWork->run($user, function () use ($user, $data): array {
            $amount = $this->amount($data);
            $account = Account::query()->findOrFail($data['account_id'] ?? 0);
            $category = Category::query()->findOrFail($data['category_id'] ?? 0);
            $at = Months::fromInput($user, $data['occurred_at'] ?? null);

            $transaction = $this->writer->create(TransactionType::Expense, $amount, $at, [
                'account_id' => $account->getKey(),
                'category_id' => $category->getKey(),
                'area' => $this->text($data['area'] ?? null),
                'reason' => $this->text($data['reason'] ?? null),
            ], [$account->getKey() => -$amount]);

            $this->writer->assertNotOverdrawn([$account->getKey()], 'amount', $amount);

            return ['transaction' => $transaction, 'budget' => $this->budgets->status($user, $category->getKey(), $at)];
        }, $idempotencyKey);
    }

    /**
     * Income. When the source is Salary, every goal with auto-save on gets its
     * monthly amount moved right away from the account the money landed in.
     *
     * @param  array<string, mixed>  $data  amount, account_id, income_source_id, note, occurred_at
     * @return array{transaction: Transaction, auto_save: string|null}
     *
     * @throws ValidationException
     */
    public function income(User $user, array $data, ?string $idempotencyKey = null): array
    {
        return $this->unitOfWork->run($user, function () use ($user, $data): array {
            $amount = $this->amount($data);
            $account = Account::query()->findOrFail($data['account_id'] ?? 0);
            $source = IncomeSource::query()->findOrFail($data['income_source_id'] ?? 0);
            $at = Months::fromInput($user, $data['occurred_at'] ?? null);

            $transaction = $this->writer->create(TransactionType::Income, $amount, $at, [
                'account_id' => $account->getKey(),
                'income_source_id' => $source->getKey(),
                'note' => $this->text($data['note'] ?? null),
            ], [$account->getKey() => $amount]);

            $autoSave = $source->is_salary ? $this->savings->autoSave($user, $transaction) : null;

            return ['transaction' => $transaction->refresh(), 'auto_save' => $autoSave];
        }, $idempotencyKey);
    }

    /**
     * Move money between two accounts. Total money stays the same, so it is never income or expense.
     *
     * @param  array<string, mixed>  $data  amount, from_account_id, to_account_id, note, occurred_at
     *
     * @throws ValidationException
     */
    public function transfer(User $user, array $data, ?string $idempotencyKey = null): Transaction
    {
        return $this->unitOfWork->run($user, function () use ($user, $data): Transaction {
            $amount = $this->amount($data);
            $from = Account::query()->findOrFail($data['from_account_id'] ?? 0);
            $to = Account::query()->findOrFail($data['to_account_id'] ?? 0);

            if ($from->is($to)) {
                throw ValidationException::withMessages(['to_account_id' => 'Pick two different accounts.']);
            }

            $transaction = $this->writer->create(TransactionType::Transfer, $amount, Months::fromInput($user, $data['occurred_at'] ?? null), [
                'account_id' => $from->getKey(),
                'to_account_id' => $to->getKey(),
                'note' => $this->text($data['note'] ?? null),
            ], [$from->getKey() => -$amount, $to->getKey() => $amount]);

            $this->writer->assertNotOverdrawn([$from->getKey()], 'amount', $amount);

            return $transaction;
        }, $idempotencyKey);
    }

    /**
     * Edit an expense, income or transfer; balances are recalculated. Savings,
     * lend/borrow and balance entries can only be deleted. Editing an old salary
     * never runs auto-save again.
     *
     * @param  array<string, mixed>  $data  same fields as when the entry was created
     * @return array{transaction: Transaction, budget: BudgetRow|null}
     *
     * @throws ValidationException
     */
    public function update(User $user, Transaction $transaction, array $data): array
    {
        return $this->unitOfWork->run($user, function () use ($user, $transaction, $data): array {
            $transaction = Transaction::query()->findOrFail($transaction->getKey());

            if (! $transaction->type->isEditable()) {
                throw new LogicException('This entry can only be deleted.');
            }

            $amount = $this->amount($data);
            $at = Months::fromInput($user, $data['occurred_at'] ?? null);
            $touched = LedgerPosting::query()->where('transaction_id', $transaction->getKey())->pluck('account_id')->all();
            $budget = null;

            switch ($transaction->type) {
                case TransactionType::Expense:
                    $account = Account::query()->findOrFail($data['account_id'] ?? 0);
                    $category = Category::query()->findOrFail($data['category_id'] ?? 0);
                    $transaction->update([
                        'amount' => $amount, 'occurred_at' => $at,
                        'account_id' => $account->getKey(), 'category_id' => $category->getKey(),
                        'area' => $this->text($data['area'] ?? null), 'reason' => $this->text($data['reason'] ?? null),
                    ]);
                    $this->writer->replacePostings($transaction, [$account->getKey() => -$amount]);
                    $touched[] = $account->getKey();
                    $budget = $this->budgets->status($user, $category->getKey(), $at);
                    break;

                case TransactionType::Income:
                    $account = Account::query()->findOrFail($data['account_id'] ?? 0);
                    $source = IncomeSource::query()->findOrFail($data['income_source_id'] ?? 0);
                    $transaction->update([
                        'amount' => $amount, 'occurred_at' => $at,
                        'account_id' => $account->getKey(), 'income_source_id' => $source->getKey(),
                        'note' => $this->text($data['note'] ?? null),
                    ]);
                    $this->writer->replacePostings($transaction, [$account->getKey() => $amount]);
                    $touched[] = $account->getKey();
                    break;

                default:
                    $from = Account::query()->findOrFail($data['from_account_id'] ?? 0);
                    $to = Account::query()->findOrFail($data['to_account_id'] ?? 0);

                    if ($from->is($to)) {
                        throw ValidationException::withMessages(['to_account_id' => 'Pick two different accounts.']);
                    }

                    $transaction->update([
                        'amount' => $amount, 'occurred_at' => $at,
                        'account_id' => $from->getKey(), 'to_account_id' => $to->getKey(),
                        'note' => $this->text($data['note'] ?? null),
                    ]);
                    $this->writer->replacePostings($transaction, [$from->getKey() => -$amount, $to->getKey() => $amount]);
                    $touched[] = $from->getKey();
                    $touched[] = $to->getKey();
            }

            $this->writer->assertNotOverdrawn($touched);

            return ['transaction' => $transaction->refresh(), 'budget' => $budget];
        });
    }

    /**
     * Delete an entry and reverse its effect on balances.
     *
     * Deleting a lend or borrow also removes it from Dues, together with the
     * payments recorded against it. Deleting a salary does not undo its
     * auto-save entry; the entry is kept.
     *
     * @throws ValidationException
     */
    public function delete(User $user, Transaction $transaction): void
    {
        $this->unitOfWork->run($user, function () use ($transaction): void {
            $transaction = Transaction::query()->findOrFail($transaction->getKey());
            $touched = [];

            if (in_array($transaction->type, [TransactionType::Lend, TransactionType::Borrow], true) && $transaction->due_id !== null) {
                $due = Due::query()->find($transaction->due_id);

                $payments = Transaction::query()->where('type', TransactionType::DuePayment)->where('due_id', $transaction->due_id)->get();
                foreach ($payments as $payment) {
                    $touched = [...$touched, ...LedgerPosting::query()->where('transaction_id', $payment->getKey())->pluck('account_id')->all()];
                    $this->writer->delete($payment);
                }

                $touched = [...$touched, ...LedgerPosting::query()->where('transaction_id', $transaction->getKey())->pluck('account_id')->all()];
                $this->writer->delete($transaction);
                $due?->delete();
            } else {
                $touched = LedgerPosting::query()->where('transaction_id', $transaction->getKey())->pluck('account_id')->all();
                $this->writer->delete($transaction);
            }

            $this->writer->assertNotOverdrawn($touched, 'delete');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function amount(array $data): int
    {
        $amount = (int) ($data['amount'] ?? 0);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount.']);
        }

        return $amount;
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 255);
    }
}

<?php

namespace App\Services\Wallet;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\LedgerPosting;
use App\Models\SavingsMovement;
use App\Models\Transaction;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * The only place that writes transactions and their ledger postings, so every
 * entry changes balances in one consistent way.
 */
class PostingWriter
{
    public function __construct(private readonly BalanceService $balances) {}

    /**
     * Create a transaction and its account postings.
     *
     * @param  array<string, mixed>  $attributes  extra columns (category_id, note, due_id, ...)
     * @param  array<int, int>  $postings  account id => signed amount in poisha
     */
    public function create(TransactionType $type, int $amount, CarbonInterface $occurredAt, array $attributes, array $postings): Transaction
    {
        $transaction = Transaction::create([
            ...$attributes,
            'type' => $type,
            'amount' => $amount,
            'occurred_at' => $occurredAt,
        ]);

        $this->writePostings($transaction, $postings);

        return $transaction;
    }

    /**
     * Replace the postings of an existing transaction.
     *
     * @param  array<int, int>  $postings  account id => signed amount in poisha
     */
    public function replacePostings(Transaction $transaction, array $postings): void
    {
        LedgerPosting::query()->where('transaction_id', $transaction->getKey())->delete();
        $this->writePostings($transaction, $postings);
    }

    /**
     * Remove a transaction and everything it did to balances and goals.
     * Entries created by it (such as an auto-save) are kept but detached.
     */
    public function delete(Transaction $transaction): void
    {
        Transaction::query()->where('parent_transaction_id', $transaction->getKey())->update(['parent_transaction_id' => null]);
        SavingsMovement::query()->where('transaction_id', $transaction->getKey())->delete();
        LedgerPosting::query()->where('transaction_id', $transaction->getKey())->delete();
        $transaction->delete();
    }

    /**
     * Refuse the change if it would leave an account with less than nothing.
     * Only the final balance is checked, not past running balances.
     *
     * @param  array<int, int>  $accountIds
     *
     * @throws ValidationException
     */
    public function assertNotOverdrawn(array $accountIds, string $field = 'amount', int $attempted = 0): void
    {
        foreach (array_unique($accountIds) as $accountId) {
            $balance = $this->balances->of($accountId);

            if ($balance < 0) {
                $account = Account::query()->withTrashed()->find($accountId);

                throw ValidationException::withMessages([
                    $field => $attempted > 0
                        ? 'Not enough money in '.($account?->name ?? 'this account').' ('.Money::format($balance + $attempted).'). Set its balance in Accounts first.'
                        : 'Not enough money in '.($account?->name ?? 'this account').'. This would leave it '.Money::format(abs($balance)).' below zero.',
                ]);
            }
        }
    }

    /**
     * @param  array<int, int>  $postings
     */
    private function writePostings(Transaction $transaction, array $postings): void
    {
        foreach ($postings as $accountId => $delta) {
            if ($delta === 0) {
                continue;
            }

            LedgerPosting::create([
                'transaction_id' => $transaction->getKey(),
                'account_id' => $accountId,
                'delta' => $delta,
            ]);
        }
    }
}

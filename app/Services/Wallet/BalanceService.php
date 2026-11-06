<?php

namespace App\Services\Wallet;

use App\Models\Account;
use App\Models\LedgerPosting;
use Illuminate\Support\Collection;

/**
 * Account balances are never stored: they are the sum of ledger postings, so
 * they cannot drift from the entries.
 */
class BalanceService
{
    /**
     * Balance of every account that has postings, keyed by account id (poisha).
     *
     * @return Collection<int, int>
     */
    public function all(): Collection
    {
        return LedgerPosting::query()
            ->selectRaw('account_id, SUM(delta) as balance')
            ->groupBy('account_id')
            ->pluck('balance', 'account_id')
            ->map(fn ($balance): int => (int) $balance);
    }

    public function of(int $accountId): int
    {
        return (int) LedgerPosting::query()->where('account_id', $accountId)->sum('delta');
    }

    /**
     * Money in the wallet: the sum of all active accounts.
     */
    public function wallet(): int
    {
        $activeIds = Account::query()->pluck('id');

        return (int) LedgerPosting::query()->whereIn('account_id', $activeIds)->sum('delta');
    }
}

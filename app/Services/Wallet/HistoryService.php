<?php

namespace App\Services\Wallet;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Months;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The entries list: newest first, with the balance of the account right after each entry.
 */
class HistoryService
{
    /**
     * Entries of a month, optionally filtered, with "balance after" per entry.
     *
     * @param  array<int, TransactionType>|null  $types  limit to these types
     * @return Collection<int, Transaction>
     */
    public function entries(User $user, CarbonInterface $month, ?array $types = null, ?int $categoryId = null, ?int $limit = null): Collection
    {
        [$from, $to] = Months::utcRange($user, $month);

        $query = Transaction::query()
            ->with(['account', 'toAccount', 'category', 'incomeSource', 'due', 'movements'])
            ->where('occurred_at', '>=', $from)
            ->where('occurred_at', '<', $to)
            ->when($types, fn ($q) => $q->whereIn('type', array_map(fn (TransactionType $type): string => $type->value, $types)))
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->when($limit, fn ($q) => $q->limit($limit));

        $entries = $query->get();

        return $this->withBalanceAfter($user, $entries);
    }

    /**
     * Attach `balance_after`: the balance of the entry's account right after it.
     *
     * @param  Collection<int, Transaction>  $entries
     * @return Collection<int, Transaction>
     */
    private function withBalanceAfter(User $user, Collection $entries): Collection
    {
        if ($entries->isEmpty()) {
            return $entries;
        }

        $ids = $entries->pluck('id')->all();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $rows = DB::select(
            "select x.transaction_id, x.account_id, x.balance_after from (
                select p.transaction_id, p.account_id,
                       sum(p.delta) over (partition by p.account_id order by t.occurred_at, t.id) as balance_after
                from ledger_postings p
                join transactions t on t.id = p.transaction_id and t.user_id = p.user_id
                where p.user_id = ?
             ) x where x.transaction_id in ($placeholders)",
            [$user->getKey(), ...$ids],
        );

        $balances = collect($rows)->mapWithKeys(fn (object $row): array => [$row->transaction_id.':'.$row->account_id => (int) $row->balance_after]);

        return $entries->each(function (Transaction $entry) use ($balances): void {
            $entry->setAttribute('balance_after', $balances[$entry->getKey().':'.$entry->account_id] ?? null);
        });
    }
}

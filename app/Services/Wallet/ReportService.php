<?php

namespace App\Services\Wallet;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\CategoryBudget;
use App\Models\IncomeSource;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Months;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Monthly report: where the income went, expenses by category, income by source.
 */
class ReportService
{
    public function __construct(private readonly MonthService $months) {}

    /**
     * @return array{
     *     income: int, saved: int, spent: int, not_used: int, savings_rate: float|null,
     *     expenses: Collection<int, array{name: string, total: int, percent: float, limit: int|null, over: bool}>,
     *     sources: Collection<int, array{name: string, total: int, percent: float}>
     * }
     */
    public function month(User $user, CarbonInterface $month): array
    {
        [$from, $to] = Months::utcRange($user, $month);
        $summary = $this->months->summary($user, $month);

        $byCategory = Transaction::query()
            ->where('type', TransactionType::Expense)->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to)
            ->selectRaw('category_id, SUM(amount) as total')->groupBy('category_id')->pluck('total', 'category_id');
        $names = Category::query()->withTrashed()->whereIn('id', $byCategory->keys())->pluck('name', 'id');
        $limits = CategoryBudget::query()->pluck('monthly_limit', 'category_id');
        $spent = $summary->spent;

        $expenses = $byCategory->map(fn ($total, $categoryId): array => [
            'name' => (string) ($names[$categoryId] ?? 'Others'),
            'total' => (int) $total,
            'percent' => $spent > 0 ? round($total * 100 / $spent, 1) : 0.0,
            'limit' => isset($limits[$categoryId]) ? (int) $limits[$categoryId] : null,
            'over' => isset($limits[$categoryId]) && $total > $limits[$categoryId],
        ])->sortByDesc('total')->values();

        $bySource = Transaction::query()
            ->where('type', TransactionType::Income)->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to)
            ->selectRaw('income_source_id, SUM(amount) as total')->groupBy('income_source_id')->pluck('total', 'income_source_id');
        $sourceNames = IncomeSource::query()->withTrashed()->whereIn('id', $bySource->keys())->pluck('name', 'id');

        $sources = $bySource->map(fn ($total, $sourceId): array => [
            'name' => (string) ($sourceNames[$sourceId] ?? 'Others'),
            'total' => (int) $total,
            'percent' => $summary->income > 0 ? round($total * 100 / $summary->income, 1) : 0.0,
        ])->sortByDesc('total')->values();

        return [
            'income' => $summary->income,
            'saved' => $summary->saved,
            'spent' => $summary->spent,
            'not_used' => $summary->income - $summary->saved - $summary->spent,
            'savings_rate' => $summary->income > 0 ? round($summary->saved * 100 / $summary->income, 1) : null,
            'expenses' => $expenses,
            'sources' => $sources,
        ];
    }
}

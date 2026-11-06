<?php

namespace App\Services\Wallet;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\CategoryBudget;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Wallet\Data\BudgetRow;
use App\Support\Months;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BudgetService
{
    public function __construct(private readonly UnitOfWork $unitOfWork) {}

    /**
     * Save category limits. A null or zero limit removes that category's budget,
     * so clearing every field removes all budgets.
     *
     * @param  array<int, int|null>  $limits  category id => limit in poisha
     *
     * @throws ValidationException
     */
    public function set(User $user, array $limits): void
    {
        $this->unitOfWork->run($user, function () use ($limits): void {
            foreach ($limits as $categoryId => $limit) {
                $category = Category::query()->findOrFail($categoryId);

                if ($limit === null || $limit <= 0) {
                    CategoryBudget::query()->where('category_id', $category->getKey())->delete();

                    continue;
                }

                CategoryBudget::query()->updateOrCreate(
                    ['category_id' => $category->getKey()],
                    ['monthly_limit' => $limit],
                );
            }
        });
    }

    /**
     * Budgets with this month's spending, highest percent used first.
     *
     * @return Collection<int, BudgetRow>
     */
    public function rows(User $user, CarbonInterface $month): Collection
    {
        $spent = $this->spentByCategory($user, $month);

        return CategoryBudget::query()
            ->with('category')
            ->get()
            ->map(fn (CategoryBudget $budget): BudgetRow => new BudgetRow(
                categoryId: $budget->category_id,
                name: $budget->category->name,
                limit: (int) $budget->monthly_limit,
                spent: (int) ($spent[$budget->category_id] ?? 0),
            ))
            ->sortByDesc(fn (BudgetRow $row): int => $row->limit > 0 ? (int) round($row->spent * 10000 / $row->limit) : 0)
            ->values();
    }

    /**
     * Spending per category in the user's month containing $month (poisha).
     *
     * @return Collection<int, int>
     */
    public function spentByCategory(User $user, CarbonInterface $month): Collection
    {
        [$from, $to] = Months::utcRange($user, $month);

        return Transaction::query()
            ->where('type', TransactionType::Expense)
            ->where('occurred_at', '>=', $from)
            ->where('occurred_at', '<', $to)
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id')
            ->map(fn ($total): int => (int) $total);
    }

    /**
     * The budget status of one category for the month containing $moment, or
     * null when that category has no budget.
     */
    public function status(User $user, int $categoryId, CarbonInterface $moment): ?BudgetRow
    {
        return $this->rows($user, $moment)->first(fn (BudgetRow $row): bool => $row->categoryId === $categoryId);
    }

    /**
     * Total of all category limits (poisha).
     */
    public function totalLimits(): int
    {
        return (int) CategoryBudget::query()->sum('monthly_limit');
    }
}

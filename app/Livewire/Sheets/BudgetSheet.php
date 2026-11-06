<?php

namespace App\Livewire\Sheets;

use App\Livewire\Concerns\InteractsWithSheet;
use App\Models\Category;
use App\Models\CategoryBudget;
use App\Services\Wallet\BudgetService;
use App\Services\Wallet\MonthService;
use App\Support\Money;
use Livewire\Attributes\On;
use Livewire\Component;

class BudgetSheet extends Component
{
    use InteractsWithSheet;

    /**
     * Limit text per category id.
     *
     * @var array<int, string>
     */
    public array $limits = [];

    #[On('open-budgets')]
    public function openSheet(): void
    {
        $this->beginForm();
        $existing = CategoryBudget::query()->pluck('monthly_limit', 'category_id');

        $this->limits = Category::query()->orderBy('position')->orderBy('id')->pluck('id')
            ->mapWithKeys(fn (int $id): array => [$id => isset($existing[$id]) ? Money::toGroupedInput((int) $existing[$id]) : ''])
            ->all();
    }

    public function save(BudgetService $budgets): void
    {
        $parsed = [];

        foreach ($this->limits as $categoryId => $text) {
            $parsed[(int) $categoryId] = trim((string) $text) === '' ? null : (Money::parse($text) ?? 0);
        }

        $saved = $this->attempt(fn () => $budgets->set($this->user(), $parsed));

        if ($saved) {
            $this->finish('Budgets saved.');
        }
    }

    public function render()
    {
        $categories = collect();
        $spent = collect();
        $total = 0;
        $spendingBudget = 0;

        if ($this->open) {
            $categories = Category::query()->orderBy('position')->orderBy('id')->get();
            $spent = app(BudgetService::class)->spentByCategory($this->user(), now());
            $total = collect($this->limits)->sum(fn ($text): int => max(0, Money::parse((string) $text) ?? 0));
            $spendingBudget = app(MonthService::class)->summary($this->user())->spendingBudget();
        }

        return view('livewire.sheets.budget-sheet', [
            'categories' => $categories,
            'spent' => $spent,
            'total' => $total,
            'spendingBudget' => $spendingBudget,
        ]);
    }
}

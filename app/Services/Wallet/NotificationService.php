<?php

namespace App\Services\Wallet;

use App\Enums\DueDirection;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Wallet\Data\BudgetRow;
use App\Support\Money;
use App\Support\Months;
use Illuminate\Support\Collection;

/**
 * Alerts are worked out from the user's own entries, most urgent first:
 * warn (red): over budget, late dues; care (yellow): close to a limit, spending
 * too fast; info (blue): reminders; good (green): done or reached.
 */
class NotificationService
{
    public const Warn = 'warn';

    public const Care = 'care';

    public const Info = 'info';

    public const Good = 'good';

    public function __construct(
        private readonly BudgetService $budgets,
        private readonly MonthService $months,
        private readonly SavingsService $savings,
        private readonly DueService $dues,
    ) {}

    /**
     * @return Collection<int, array{level: string, title: string, body: string, route: string}>
     */
    public function items(User $user): Collection
    {
        $now = Months::today($user);
        $items = collect();
        $summary = $this->months->summary($user, $now);

        foreach ($this->budgets->rows($user, $now) as $row) {
            if ($row->state() === BudgetRow::Over) {
                $items->push($this->item(self::Warn, $row->name.' budget exceeded', Money::format($row->spent).' of '.Money::format($row->limit).'.', 'home'));
            }
        }

        if ($summary->hasIncome() && $summary->isOverBudget()) {
            $items->push($this->item(self::Warn, 'Over budget this month', 'You are '.Money::format(abs($summary->left())).' over budget this month. This now eats into your savings.', 'transactions'));
        }

        foreach ([DueDirection::Lent, DueDirection::Borrowed] as $direction) {
            foreach ($this->dues->list($user, $direction)->where('status', 'late') as $row) {
                $due = $row['due'];
                $date = $due->due_date->format('M j');
                $items->push($direction === DueDirection::Lent
                    ? $this->item(self::Warn, $due->person.' is late', $due->person.' owes you '.Money::format($row['remaining']).'. It was due '.$date.'.', 'dues')
                    : $this->item(self::Warn, 'Payment to '.$due->person.' is late', 'You owe '.$due->person.' '.Money::format($row['remaining']).'. It was due '.$date.'.', 'dues'));
            }
        }

        foreach ($this->budgets->rows($user, $now) as $row) {
            if ($row->state() === BudgetRow::Warning) {
                $items->push($this->item(self::Care, $row->name.' at '.$row->percent().'% of budget', Money::format($row->spent).' of '.Money::format($row->limit).' · '.Money::format($row->left()).' left.', 'home'));
            }
        }

        if ($summary->isPaceWarning()) {
            $items->push($this->item(self::Care, 'Spending faster than the month', 'You have used '.$summary->usedPercent().'% of your budget, but only '.$summary->elapsedPercent().'% of the month has passed.', 'transactions'));
        }

        $monthIncome = Transaction::query()->where('type', TransactionType::Income);
        [$from, $to] = Months::utcRange($user, $now);
        $salaryThisMonth = (clone $monthIncome)->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to)
            ->whereHas('incomeSource', fn ($query) => $query->where('is_salary', true))->exists();
        $skipped = (clone $monthIncome)->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to)->where('auto_save_status', 'skipped')->exists();

        if ($skipped) {
            $items->push($this->item(self::Care, 'Auto-save could not run', 'The account did not have enough money. Add money to your goals by hand.', 'savings'));
        }

        $autoSaveTotal = $this->savings->nextAutoSaveTotal();

        if (! $salaryThisMonth && $autoSaveTotal > 0) {
            $items->push($this->item(self::Info, 'Salary not added yet', 'Add this month\'s salary so '.Money::format($autoSaveTotal).' can auto-save to your goals.', 'home'));
        }

        $autoSaved = (int) Transaction::query()->where('type', TransactionType::SavingDeposit)->whereNotNull('parent_transaction_id')
            ->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to)->sum('amount');

        if ($autoSaved > 0) {
            $items->push($this->item(self::Good, 'Auto-save done', Money::format($autoSaved).' moved to your goals this month.', 'savings'));
        }

        foreach ($this->savings->goals()->where('reached', true) as $row) {
            $items->push($this->item(self::Good, 'Goal reached: '.$row['goal']->reason->name, Money::format($row['balance']).' saved. Well done.', 'savings'));
        }

        return $items->values();
    }

    /**
     * How many alerts need attention: the red dot on the bell is shown for
     * warn, care and info alerts, never for good news.
     */
    public function attentionCount(User $user): int
    {
        return $this->items($user)->whereIn('level', [self::Warn, self::Care, self::Info])->count();
    }

    /**
     * @return array{level: string, title: string, body: string, route: string}
     */
    private function item(string $level, string $title, string $body, string $route): array
    {
        return ['level' => $level, 'title' => $title, 'body' => $body, 'route' => $route];
    }
}

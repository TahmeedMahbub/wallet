<?php

namespace App\Services\Wallet\Data;

/**
 * The month at a glance. Spending budget = this month's income minus money
 * saved this month; left to spend = spending budget minus spending.
 */
final readonly class MonthSummary
{
    public function __construct(
        public int $income,
        public int $saved,
        public int $spent,
        public int $daysInMonth,
        public int $dayOfMonth,
    ) {}

    public function hasIncome(): bool
    {
        return $this->income > 0;
    }

    public function spendingBudget(): int
    {
        return $this->income - $this->saved;
    }

    public function left(): int
    {
        return $this->spendingBudget() - $this->spent;
    }

    public function daysLeft(): int
    {
        return $this->daysInMonth - $this->dayOfMonth;
    }

    /**
     * About how much can be spent each remaining day, to the nearest taka. On the
     * last day the full remaining amount is shown. Never negative.
     */
    public function perDay(): int
    {
        $left = max(0, $this->left());

        if ($this->daysLeft() <= 0) {
            return $left;
        }

        return (int) (round($left / $this->daysLeft() / 100) * 100);
    }

    /**
     * Percent of the spending budget used (not capped).
     */
    public function usedPercent(): int
    {
        return $this->spendingBudget() > 0 ? (int) round($this->spent * 100 / $this->spendingBudget()) : ($this->spent > 0 ? 100 : 0);
    }

    /**
     * Used percent for the bar and label, never more than 100.
     */
    public function shownPercent(): int
    {
        return min(100, $this->usedPercent());
    }

    /**
     * Percent of the month that has gone, for the yellow "today" marker.
     */
    public function elapsedPercent(): int
    {
        return (int) round($this->dayOfMonth * 100 / $this->daysInMonth);
    }

    public function isOverBudget(): bool
    {
        return $this->left() < 0;
    }

    /**
     * Spending is ahead of the month: used percent exceeds elapsed percent by more than 10 points.
     * Not shown when already over budget (that has its own message).
     */
    public function isPaceWarning(): bool
    {
        return $this->hasIncome() && ! $this->isOverBudget() && ($this->usedPercent() - $this->elapsedPercent()) > 10;
    }
}

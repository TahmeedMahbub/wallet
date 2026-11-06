<?php

namespace App\Services\Wallet;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Wallet\Data\MonthSummary;
use App\Support\Months;
use Carbon\CarbonInterface;

class MonthService
{
    public function __construct(private readonly SavingsService $savings) {}

    /**
     * Totals for the user's calendar month containing $month (this month by default).
     */
    public function summary(User $user, ?CarbonInterface $month = null): MonthSummary
    {
        $today = Months::today($user);
        $month ??= $today;
        [$from, $to] = Months::utcRange($user, $month);
        $start = Months::start($user, $month);
        $isCurrent = $start->isSameMonth($today);

        $sum = fn (TransactionType $type): int => (int) Transaction::query()
            ->where('type', $type)->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to)->sum('amount');

        return new MonthSummary(
            income: $sum(TransactionType::Income),
            saved: $this->savings->savedInMonth($user, $month),
            spent: $sum(TransactionType::Expense),
            daysInMonth: $start->daysInMonth,
            dayOfMonth: $isCurrent ? $today->day : ($start->isBefore($today) ? $start->daysInMonth : 1),
        );
    }
}

<?php

namespace Tests\Unit;

use App\Services\Wallet\Data\BudgetRow;
use App\Services\Wallet\Data\MonthSummary;
use PHPUnit\Framework\TestCase;

/**
 * The expected numbers are the ones shown in the Figma screens.
 */
class MonthSummaryTest extends TestCase
{
    private function taka(int $taka): int
    {
        return $taka * 100;
    }

    public function test_home_with_data_matches_figma(): void
    {
        // October 2026: 14 days left means day 17 of 31. Income 55,000, saved 15,000, spent 21,550.
        $month = new MonthSummary($this->taka(55_000), $this->taka(15_000), $this->taka(21_550), 31, 17);

        $this->assertSame($this->taka(40_000), $month->spendingBudget());
        $this->assertSame($this->taka(18_450), $month->left());
        $this->assertSame(14, $month->daysLeft());
        $this->assertSame($this->taka(1_318), $month->perDay());
        $this->assertSame(54, $month->usedPercent());
        $this->assertSame(55, $month->elapsedPercent());
        $this->assertFalse($month->isPaceWarning());
        $this->assertFalse($month->isOverBudget());
    }

    public function test_the_pace_warning_matches_figma(): void
    {
        // 78% of the budget used but only 55% of the month gone.
        $month = new MonthSummary($this->taka(55_000), $this->taka(15_000), $this->taka(31_200), 31, 17);

        $this->assertSame(78, $month->usedPercent());
        $this->assertSame($this->taka(8_800), $month->left());
        $this->assertSame($this->taka(629), $month->perDay());
        $this->assertTrue($month->isPaceWarning());
    }

    public function test_the_pace_warning_needs_more_than_ten_points_ahead(): void
    {
        $atTen = new MonthSummary($this->taka(1_000), 0, $this->taka(650), 100, 55); // 65% used vs 55% elapsed
        $overTen = new MonthSummary($this->taka(1_000), 0, $this->taka(660), 100, 55); // 66% vs 55%

        $this->assertFalse($atTen->isPaceWarning());
        $this->assertTrue($overTen->isPaceWarning());
    }

    public function test_over_budget_matches_figma(): void
    {
        // 43,200 spent of a 40,000 budget with 6 days left.
        $month = new MonthSummary($this->taka(55_000), $this->taka(15_000), $this->taka(43_200), 31, 25);

        $this->assertTrue($month->isOverBudget());
        $this->assertSame(-$this->taka(3_200), $month->left());
        $this->assertSame(0, $month->perDay());
        $this->assertSame(100, $month->shownPercent());
        $this->assertGreaterThan(100, $month->usedPercent());
        $this->assertFalse($month->isPaceWarning(), 'over budget has its own message');
    }

    public function test_the_last_day_shows_the_full_remaining_amount(): void
    {
        $month = new MonthSummary($this->taka(40_000), 0, $this->taka(35_000), 31, 31);

        $this->assertSame(0, $month->daysLeft());
        $this->assertSame($this->taka(5_000), $month->perDay());
    }

    public function test_a_month_without_income_has_no_budget_and_no_pace_warning(): void
    {
        $month = new MonthSummary(0, 0, 0, 30, 10);

        $this->assertFalse($month->hasIncome());
        $this->assertSame(0, $month->usedPercent());
        $this->assertFalse($month->isPaceWarning());
    }

    public function test_budget_thresholds_follow_the_figma_rule(): void
    {
        $row = fn (int $spent): BudgetRow => new BudgetRow(1, 'Food', 10_000, $spent);

        $this->assertSame(BudgetRow::OnTrack, $row(7_999)->state());
        $this->assertSame(BudgetRow::Warning, $row(8_000)->state(), '80% is warning');
        $this->assertSame(BudgetRow::Warning, $row(10_000)->state(), 'exactly at the limit is still warning');
        $this->assertSame(BudgetRow::Over, $row(10_001)->state());
    }

    public function test_budget_percentages_and_left_match_figma(): void
    {
        $food = new BudgetRow(1, 'Food & Groceries', $this->taka(8_000), $this->taka(6_800));
        $bills = new BudgetRow(2, 'Bills & Utilities', $this->taka(4_000), $this->taka(2_300));

        $this->assertSame(85, $food->percent());
        $this->assertSame($this->taka(1_200), $food->left());
        $this->assertSame(58, $bills->percent(), '57.5% rounds up');
    }

    public function test_an_over_budget_bar_is_capped_at_full(): void
    {
        $shopping = new BudgetRow(1, 'Shopping', $this->taka(1_000), $this->taka(1_200));

        $this->assertSame(120, $shopping->percent());
        $this->assertSame(100, $shopping->barPercent());
        $this->assertSame(BudgetRow::Over, $shopping->state());
    }
}

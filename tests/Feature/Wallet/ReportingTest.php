<?php

namespace Tests\Feature\Wallet;

use App\Enums\GoalKind;
use App\Enums\TransactionType;
use App\Models\CategoryBudget;
use App\Models\SavingPlace;
use App\Models\SavingReason;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Wallet\BudgetService;
use App\Services\Wallet\HistoryService;
use App\Services\Wallet\LedgerService;
use App\Services\Wallet\MonthService;
use App\Services\Wallet\ReportService;
use App\Services\Wallet\SavingsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\WalletTestHelpers;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use DatabaseTransactions, WalletTestHelpers;

    private LedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(LedgerService::class);
    }

    private function income(User $user, int $taka, string $source = 'Salary', ?string $at = null): void
    {
        $this->ledger->income($user, [
            'amount' => $taka * 100, 'account_id' => $this->account('Bank')->getKey(),
            'income_source_id' => $this->source($source)->getKey(), 'occurred_at' => $at,
        ]);
    }

    private function spend(User $user, int $taka, string $category, ?string $at = null): void
    {
        $this->ledger->expense($user, [
            'amount' => $taka * 100, 'account_id' => $this->account('Bank')->getKey(),
            'category_id' => $this->category($category)->getKey(), 'occurred_at' => $at,
        ]);
    }

    public function test_setting_budgets_saves_updates_and_clearing_removes_them(): void
    {
        $user = $this->walletUser();
        $budgets = app(BudgetService::class);
        $food = $this->category('Food & Groceries')->getKey();
        $rent = $this->category('Rent')->getKey();

        $budgets->set($user, [$food => 800_000, $rent => 1_200_000]);
        $this->assertSame(2, CategoryBudget::query()->count());
        $this->assertSame(2_000_000, $budgets->totalLimits());

        $budgets->set($user, [$food => 900_000, $rent => null]);
        $this->assertSame(['Food & Groceries' => 900_000], CategoryBudget::query()->with('category')->get()->mapWithKeys(fn ($b) => [$b->category->name => $b->monthly_limit])->all());

        $budgets->set($user, [$food => null]);
        $this->assertSame(0, CategoryBudget::query()->count(), 'clearing every field removes all budgets');
    }

    public function test_budgets_are_sorted_by_percent_used_highest_first(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 100_000);
        app(BudgetService::class)->set($user, [
            $this->category('Food & Groceries')->getKey() => 800_000,
            $this->category('Transport')->getKey() => 300_000,
            $this->category('Bills & Utilities')->getKey() => 400_000,
        ]);
        $this->spend($user, 6_800, 'Food & Groceries');
        $this->spend($user, 1_850, 'Transport');
        $this->spend($user, 2_300, 'Bills & Utilities');

        $rows = app(BudgetService::class)->rows($user, now());

        $this->assertSame(['Food & Groceries', 'Transport', 'Bills & Utilities'], $rows->pluck('name')->all());
        $this->assertSame([85, 62, 58], $rows->map->percent()->all());
    }

    public function test_budgets_only_count_spending_in_their_own_month(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 100_000);
        $food = $this->category('Food & Groceries')->getKey();
        app(BudgetService::class)->set($user, [$food => 800_000]);
        $this->spend($user, 1_000, 'Food & Groceries', now()->subMonthNoOverflow()->toDateTimeString());
        $this->spend($user, 500, 'Food & Groceries');

        $this->assertSame(50_000, app(BudgetService::class)->rows($user, now())->sole()->spent);
    }

    public function test_the_month_summary_uses_income_savings_and_expenses_of_that_month_only(): void
    {
        $user = $this->walletUser();
        $this->income($user, 55_000);
        $this->income($user, 9_000, at: now()->subMonthNoOverflow()->toDateTimeString());
        $this->spend($user, 21_550, 'Rent');
        $this->spend($user, 700, 'Rent', now()->subMonthNoOverflow()->toDateTimeString());

        $summary = app(MonthService::class)->summary($user);

        $this->assertSame(5_500_000, $summary->income);
        $this->assertSame(2_155_000, $summary->spent);
        $this->assertSame(0, $summary->saved);
        $this->assertSame(5_500_000 - 2_155_000, $summary->left());
    }

    public function test_months_follow_the_users_timezone_not_utc(): void
    {
        $user = $this->walletUser(['timezone' => 'Asia/Dhaka']);
        // Typed in Dhaka time. 00:30 on Nov 1 in Dhaka is stored as 18:30 UTC on Oct 31,
        // which a UTC calendar would wrongly count as October.
        $this->income($user, 1_000, at: '2026-10-31 23:30:00');
        $this->income($user, 2_000, at: '2026-11-01 00:30:00');

        $october = app(MonthService::class)->summary($user, CarbonImmutable::parse('2026-10-15', 'Asia/Dhaka'));
        $november = app(MonthService::class)->summary($user, CarbonImmutable::parse('2026-11-15', 'Asia/Dhaka'));

        $this->assertSame(100_000, $october->income);
        $this->assertSame(200_000, $november->income);
        $this->assertSame('2026-10-31 18:30:00', Transaction::query()->orderByDesc('occurred_at')->first()->occurred_at->utc()->toDateTimeString());
    }

    public function test_the_report_matches_the_figma_october_numbers(): void
    {
        $user = $this->walletUser();
        $this->income($user, 55_000);
        $this->ledger->income($user, ['amount' => 100_000, 'account_id' => $this->account('Bank')->getKey(), 'income_source_id' => $this->source('Salami')->getKey()]);
        $this->income($user, 1_000, 'Gift money');
        // saved 15,000 as a manual goal deposit is covered elsewhere; here: use the summary maths via spend only
        $this->spend($user, 12_000, 'Rent');
        $this->spend($user, 6_800, 'Food & Groceries');
        $this->spend($user, 2_300, 'Bills & Utilities');
        $this->spend($user, 1_850, 'Transport');
        $this->spend($user, 1_200, 'Shopping');
        app(BudgetService::class)->set($user, [$this->category('Shopping')->getKey() => 100_000, $this->category('Rent')->getKey() => 1_200_000]);

        $report = app(ReportService::class)->month($user, now());

        $this->assertSame(24_150_00, $report['spent']);
        $this->assertSame(['Rent', 'Food & Groceries', 'Bills & Utilities', 'Transport', 'Shopping'], $report['expenses']->pluck('name')->all());
        $this->assertSame(49.7, $report['expenses'][0]['percent']);
        $this->assertSame(28.2, $report['expenses'][1]['percent']);
        $shopping = $report['expenses']->firstWhere('name', 'Shopping');
        $this->assertTrue($shopping['over'], '৳1,200 of a ৳1,000 limit is over');
        $this->assertSame(['Salary', 'Salami', 'Gift money'], $report['sources']->pluck('name')->all());
        $this->assertSame(96.5, $report['sources'][0]['percent']);
    }

    public function test_the_report_math_for_not_used_and_savings_rate(): void
    {
        $user = $this->walletUser();
        $this->income($user, 55_000);
        $this->fund($user, 'Cash', 0);
        $goal = app(SavingsService::class)->createGoal($user, [
            'reason_id' => SavingReason::query()->value('id'),
            'place_id' => SavingPlace::query()->value('id'),
            'kind' => GoalKind::Monthly, 'target_amount' => 10_000_000, 'months' => 6,
            'monthly_amount' => 1_500_000, 'auto_save' => false,
        ]);
        app(SavingsService::class)->deposit($user, $goal, $this->account('Bank'), 1_500_000);
        $this->spend($user, 24_150, 'Rent');

        $report = app(ReportService::class)->month($user, now());

        $this->assertSame(15_850_00, $report['not_used']);
        $this->assertSame(27.3, $report['savings_rate']);
    }

    public function test_an_empty_month_has_no_savings_rate_and_no_rows(): void
    {
        $user = $this->walletUser();

        $report = app(ReportService::class)->month($user, now()->subMonths(2));

        $this->assertNull($report['savings_rate']);
        $this->assertTrue($report['expenses']->isEmpty());
        $this->assertTrue($report['sources']->isEmpty());
    }

    public function test_history_lists_newest_first_with_the_balance_after_each_entry(): void
    {
        $user = $this->walletUser();
        $this->income($user, 1_000, at: now()->subHours(3)->toDateTimeString());
        $this->spend($user, 200, 'Rent', now()->subHours(2)->toDateTimeString());
        $this->spend($user, 300, 'Food & Groceries', now()->subHour()->toDateTimeString());

        $entries = app(HistoryService::class)->entries($user, now());

        $this->assertSame([TransactionType::Expense, TransactionType::Expense, TransactionType::Income], $entries->pluck('type')->all());
        $this->assertSame([50_000, 80_000, 100_000], $entries->pluck('balance_after')->all());
    }

    public function test_editing_an_old_entry_changes_the_balance_after_of_later_entries(): void
    {
        $user = $this->walletUser();
        $this->income($user, 1_000, at: now()->subHours(3)->toDateTimeString());
        $this->spend($user, 200, 'Rent', now()->subHours(2)->toDateTimeString());
        $entry = Transaction::query()->where('type', TransactionType::Expense)->sole();

        $this->ledger->update($user, $entry, [
            'amount' => 50_000, 'account_id' => $this->account('Bank')->getKey(), 'category_id' => $this->category('Rent')->getKey(),
            'occurred_at' => now()->subHours(2)->toDateTimeString(),
        ]);

        $this->assertSame([50_000, 100_000], app(HistoryService::class)->entries($user, now())->pluck('balance_after')->all());
    }

    public function test_history_can_filter_by_type_and_category(): void
    {
        $user = $this->walletUser();
        $this->income($user, 1_000);
        $this->spend($user, 100, 'Rent');
        $this->spend($user, 100, 'Food & Groceries');

        $history = app(HistoryService::class);

        $this->assertSame(2, $history->entries($user, now(), [TransactionType::Expense])->count());
        $this->assertSame(1, $history->entries($user, now(), [TransactionType::Expense], $this->category('Rent')->getKey())->count());
        $this->assertSame(1, $history->entries($user, now(), [TransactionType::Income])->count());
    }
}

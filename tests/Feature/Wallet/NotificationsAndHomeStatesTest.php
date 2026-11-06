<?php

namespace Tests\Feature\Wallet;

use App\Enums\GoalKind;
use App\Livewire\Pages\Home;
use App\Models\SavingPlace;
use App\Models\SavingReason;
use App\Services\Wallet\BudgetService;
use App\Services\Wallet\DueService;
use App\Services\Wallet\LedgerService;
use App\Services\Wallet\NotificationService;
use App\Services\Wallet\SavingsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\Concerns\WalletTestHelpers;
use Tests\TestCase;

class NotificationsAndHomeStatesTest extends TestCase
{
    use DatabaseTransactions, WalletTestHelpers;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function spend(string $category, int $taka): void
    {
        app(LedgerService::class)->expense(auth()->user(), [
            'amount' => $taka * 100, 'account_id' => $this->account('Bank')->getKey(), 'category_id' => $this->category($category)->getKey(),
        ]);
    }

    private function salary(int $taka): void
    {
        app(LedgerService::class)->income(auth()->user(), [
            'amount' => $taka * 100, 'account_id' => $this->account('Bank')->getKey(), 'income_source_id' => $this->source('Salary')->getKey(),
        ]);
    }

    public function test_a_new_user_has_no_alerts_and_no_red_dot(): void
    {
        $user = $this->walletUser();

        $this->assertTrue(app(NotificationService::class)->items($user)->isEmpty());
        $this->assertSame(0, app(NotificationService::class)->attentionCount($user));
    }

    public function test_alerts_follow_the_figma_wording_and_order(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 12:00:00', 'Asia/Dhaka'));
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 200_000);
        app(SavingsService::class)->createGoal($user, [
            'reason_id' => SavingReason::query()->where('name', 'Tour')->value('id'), 'place_id' => SavingPlace::query()->value('id'),
            'kind' => GoalKind::Monthly, 'target_amount' => 2_500_000, 'months' => 6, 'monthly_amount' => 1_500_000, 'auto_save' => true,
        ]);
        $this->salary(55_000);
        app(BudgetService::class)->set($user, [
            $this->category('Food & Groceries')->getKey() => 800_000, $this->category('Shopping')->getKey() => 300_000,
        ]);
        $this->spend('Food & Groceries', 6_800);
        $this->spend('Shopping', 3_700);
        app(DueService::class)->lend($user, ['person' => 'Efty', 'amount' => 200_000, 'account_id' => $this->account('Bank')->getKey(), 'due_date' => '2026-10-30']);
        Carbon::setTestNow(Carbon::parse('2026-10-31 12:00:00', 'Asia/Dhaka'));

        $items = app(NotificationService::class)->items($user);
        $titles = $items->pluck('title')->all();

        $this->assertSame('warn', $items[0]['level']);
        $this->assertContains('Shopping budget exceeded', $titles);
        $this->assertContains('Efty is late', $titles);
        $this->assertContains('Food & Groceries at 85% of budget', $titles);
        $this->assertContains('Auto-save done', $titles);

        $shopping = $items->firstWhere('title', 'Shopping budget exceeded');
        $this->assertSame('৳3,700 of ৳3,000.', $shopping['body']);
        $this->assertSame('Efty owes you ৳2,000. It was due Oct 30.', $items->firstWhere('title', 'Efty is late')['body']);
        $this->assertSame('৳6,800 of ৳8,000 · ৳1,200 left.', $items->firstWhere('title', 'Food & Groceries at 85% of budget')['body']);
        $this->assertSame('৳15,000 moved to your goals this month.', $items->firstWhere('title', 'Auto-save done')['body']);

        $levels = $items->pluck('level')->all();
        $sorted = $levels;
        usort($sorted, fn ($a, $b) => array_search($a, ['warn', 'care', 'info', 'good']) <=> array_search($b, ['warn', 'care', 'info', 'good']));
        $this->assertSame($sorted, $levels, 'most urgent first: red, yellow, blue, green');
        $this->assertGreaterThan(0, app(NotificationService::class)->attentionCount($user));
    }

    public function test_a_missing_salary_reminds_the_user_when_goals_auto_save(): void
    {
        $user = $this->walletUser();
        app(SavingsService::class)->createGoal($user, [
            'reason_id' => SavingReason::query()->value('id'), 'place_id' => SavingPlace::query()->value('id'),
            'kind' => GoalKind::Monthly, 'target_amount' => 2_500_000, 'months' => 6, 'monthly_amount' => 1_500_000, 'auto_save' => true,
        ]);

        $item = app(NotificationService::class)->items($user)->firstWhere('title', 'Salary not added yet');

        $this->assertSame('info', $item['level']);
        $this->assertSame("Add this month's salary so ৳15,000 can auto-save to your goals.", $item['body']);
    }

    public function test_good_news_does_not_raise_the_red_dot(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 10_000);
        $goal = app(SavingsService::class)->createGoal($user, [
            'reason_id' => SavingReason::query()->where('name', 'Personal Things')->value('id'), 'place_id' => SavingPlace::query()->value('id'),
            'kind' => GoalKind::Monthly, 'target_amount' => 500_000, 'months' => 5, 'monthly_amount' => 100_000, 'auto_save' => false,
        ]);
        app(SavingsService::class)->deposit($user, $goal, $this->account('Bank'), 500_000);

        $items = app(NotificationService::class)->items($user);

        $this->assertSame('good', $items->sole()['level']);
        $this->assertSame('Goal reached: Personal Things', $items->sole()['title']);
        $this->assertSame(0, app(NotificationService::class)->attentionCount($user));
    }

    public function test_a_failed_auto_save_is_reported(): void
    {
        $user = $this->walletUser();
        app(SavingsService::class)->createGoal($user, [
            'reason_id' => SavingReason::query()->value('id'), 'place_id' => SavingPlace::query()->value('id'),
            'kind' => GoalKind::Monthly, 'target_amount' => 2_500_000, 'months' => 6, 'monthly_amount' => 1_500_000, 'auto_save' => true,
        ]);
        $this->salary(3_000);

        $this->assertContains('Auto-save could not run', app(NotificationService::class)->items($user)->pluck('title')->all());
    }

    public function test_home_shows_the_pace_nudge_when_spending_runs_ahead_of_the_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 12:00:00', 'Asia/Dhaka'));
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 200_000);
        app(SavingsService::class)->createGoal($user, [
            'reason_id' => SavingReason::query()->value('id'), 'place_id' => SavingPlace::query()->value('id'),
            'kind' => GoalKind::Monthly, 'target_amount' => 2_500_000, 'months' => 6, 'monthly_amount' => 1_500_000, 'auto_save' => true,
        ]);
        $this->salary(55_000);
        $this->spend('Rent', 31_200);

        Livewire::test(Home::class)
            ->assertSee('You have used 78% of your budget, but only 55% of the month has passed. Slow down a little.')
            ->assertSee('See where it went');
    }

    public function test_home_shows_the_over_budget_message_and_a_negative_amount(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-25 12:00:00', 'Asia/Dhaka'));
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 200_000);
        app(SavingsService::class)->createGoal($user, [
            'reason_id' => SavingReason::query()->value('id'), 'place_id' => SavingPlace::query()->value('id'),
            'kind' => GoalKind::Monthly, 'target_amount' => 2_500_000, 'months' => 6, 'monthly_amount' => 1_500_000, 'auto_save' => true,
        ]);
        $this->salary(55_000);
        $this->spend('Rent', 43_200);

        Livewire::test(Home::class)
            ->assertSee('−৳')->assertSee('over budget this month. This now eats into your savings.')
            ->assertSee('100% used')->assertSee('about ৳0/day');
    }

    public function test_the_start_card_asks_for_balances_and_income_until_income_is_added(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 50_000);

        Livewire::test(Home::class)->assertSee('Start tracking this month')->assertSee('Set balances')->assertSee('Add income')->assertDontSee('Left to spend');

        $this->salary(55_000);

        Livewire::test(Home::class)->assertDontSee('Start tracking this month')->assertSee('Left to spend');
    }
}

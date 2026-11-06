<?php

namespace Tests\Feature\Wallet;

use App\Enums\GoalKind;
use App\Enums\TransactionType;
use App\Models\SavingPlace;
use App\Models\SavingReason;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Wallet\LedgerService;
use App\Services\Wallet\SavingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WalletTestHelpers;
use Tests\TestCase;

class SavingsServiceTest extends TestCase
{
    use DatabaseTransactions, WalletTestHelpers;

    private SavingsService $savings;

    private LedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savings = app(SavingsService::class);
        $this->ledger = app(LedgerService::class);
    }

    private function goal(User $user, string $reason, int $monthlyTaka, bool $autoSave = true, int $targetTaka = 25_000, GoalKind $kind = GoalKind::Monthly): SavingsGoal
    {
        return $this->savings->createGoal($user, [
            'reason_id' => SavingReason::query()->where('name', $reason)->value('id'),
            'place_id' => SavingPlace::query()->where('name', 'bKash Savings')->value('id'),
            'kind' => $kind,
            'target_amount' => $targetTaka * 100,
            'months' => $kind->hasMonths() ? 6 : null,
            'monthly_amount' => $monthlyTaka * 100,
            'auto_save' => $autoSave,
        ]);
    }

    private function salary(User $user, int $taka, string $account = 'Bank'): array
    {
        return $this->ledger->income($user, [
            'amount' => $taka * 100,
            'account_id' => $this->account($account)->getKey(),
            'income_source_id' => $this->source('Salary')->getKey(),
        ]);
    }

    public function test_the_monthly_suggestion_is_target_divided_by_months_rounded_up(): void
    {
        $this->assertSame(420_000, SavingsService::suggestMonthly(2_500_000, 6), '25,000 / 6 = 4,166.67 rounds up to 4,200');
        $this->assertSame(170_000, SavingsService::suggestMonthly(1_000_000, 6));
        $this->assertSame(0, SavingsService::suggestMonthly(0, 6));
        $this->assertSame(0, SavingsService::suggestMonthly(100_000, 0));
    }

    public function test_a_goal_needs_a_target_amount(): void
    {
        $user = $this->walletUser();

        try {
            $this->goal($user, 'Tour', 4_200, targetTaka: 0);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('target_amount', $e->errors());
        }
    }

    public function test_a_lifetime_goal_has_no_months(): void
    {
        $user = $this->walletUser();

        $goal = $this->goal($user, 'Hajj', 2_000, targetTaka: 500_000, kind: GoalKind::Lifetime);

        $this->assertNull($goal->months);
        $this->assertSame(GoalKind::Lifetime, $goal->kind);
    }

    public function test_a_monthly_goal_needs_months(): void
    {
        $user = $this->walletUser();

        $this->expectException(ValidationException::class);

        $this->savings->createGoal($user, [
            'reason_id' => SavingReason::query()->value('id'),
            'place_id' => SavingPlace::query()->value('id'),
            'kind' => GoalKind::Monthly,
            'target_amount' => 100_000,
            'months' => null,
            'monthly_amount' => 10_000,
            'auto_save' => true,
        ]);
    }

    public function test_salary_auto_saves_every_auto_save_goal_and_keeps_the_income(): void
    {
        $user = $this->walletUser();
        $tour = $this->goal($user, 'Tour', 4_200);
        $hajj = $this->goal($user, 'Hajj', 2_000, kind: GoalKind::Lifetime, targetTaka: 500_000);
        $manual = $this->goal($user, 'Zakat', 1_000, autoSave: false);

        $result = $this->salary($user, 55_000);

        $this->assertSame('ran', $result['auto_save']);
        $this->assertSame('ran', $result['transaction']->auto_save_status);
        $this->assertSame(55_000_00 - 6_200_00, $this->balanceOf('Bank'));
        $this->assertSame(4_200_00, $this->savings->goalBalance($tour->getKey()));
        $this->assertSame(2_000_00, $this->savings->goalBalance($hajj->getKey()));
        $this->assertSame(0, $this->savings->goalBalance($manual->getKey()), 'manual goals are not touched');

        $this->assertSame(2, Transaction::query()->whereIn('type', [TransactionType::Income, TransactionType::SavingDeposit])->count(), 'Salary (+) and Auto-saved');
        $deposit = Transaction::query()->where('type', TransactionType::SavingDeposit)->sole();
        $this->assertSame($result['transaction']->getKey(), $deposit->parent_transaction_id);
        $this->assertSame('Auto-saved to 2 goals', $deposit->note);
    }

    public function test_income_that_is_not_salary_does_not_auto_save(): void
    {
        $user = $this->walletUser();
        $this->goal($user, 'Tour', 4_200);

        $result = $this->ledger->income($user, [
            'amount' => 100_000,
            'account_id' => $this->account('Bank')->getKey(),
            'income_source_id' => $this->source('Gift money')->getKey(),
        ]);

        $this->assertNull($result['auto_save']);
        $this->assertSame(0, Transaction::query()->where('type', TransactionType::SavingDeposit)->count());
    }

    public function test_when_the_account_cannot_cover_the_plan_nothing_is_saved_but_the_income_is_kept(): void
    {
        $user = $this->walletUser();
        $this->goal($user, 'Tour', 4_200);

        $result = $this->salary($user, 3_000);

        $this->assertSame('skipped', $result['auto_save']);
        $this->assertSame('skipped', $result['transaction']->auto_save_status);
        $this->assertSame(300_000, $this->balanceOf('Bank'));
        $this->assertSame(0, Transaction::query()->where('type', TransactionType::SavingDeposit)->count());
    }

    public function test_a_goal_that_reached_its_target_is_skipped_by_auto_save(): void
    {
        $user = $this->walletUser();
        $tour = $this->goal($user, 'Tour', 4_200, targetTaka: 4_200);

        $this->salary($user, 10_000);
        $this->assertTrue($this->savings->goals()->first()['reached']);

        $second = $this->salary($user, 10_000);

        $this->assertNull($second['auto_save']);
        $this->assertSame(4_200_00, $this->savings->goalBalance($tour->getKey()));
    }

    public function test_editing_an_old_salary_does_not_run_auto_save_again(): void
    {
        $user = $this->walletUser();
        $this->goal($user, 'Tour', 4_200);
        $income = $this->salary($user, 55_000)['transaction'];

        $this->ledger->update($user, $income, [
            'amount' => 60_000_00,
            'account_id' => $this->account('Bank')->getKey(),
            'income_source_id' => $this->source('Salary')->getKey(),
        ]);

        $this->assertSame(1, Transaction::query()->where('type', TransactionType::SavingDeposit)->count());
        $this->assertSame(60_000_00 - 4_200_00, $this->balanceOf('Bank'));
    }

    public function test_deleting_a_salary_keeps_its_auto_save_entry(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 5_000); // enough that removing the salary does not overdraw the account
        $goal = $this->goal($user, 'Tour', 4_200);
        $income = $this->salary($user, 55_000)['transaction'];

        $this->ledger->delete($user, $income);

        $this->assertSame(1, Transaction::query()->where('type', TransactionType::SavingDeposit)->count());
        $this->assertSame(4_200_00, $this->savings->goalBalance($goal->getKey()));
        $this->assertNull(Transaction::query()->where('type', TransactionType::SavingDeposit)->first()->parent_transaction_id);
    }

    public function test_adding_money_moves_cash_from_an_account_into_a_goal(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 10_000);
        $goal = $this->goal($user, 'Tour', 4_200);

        $this->savings->deposit($user, $goal, $this->account('Bank'), 150_000);

        $this->assertSame(10_000_00 - 150_000, $this->balanceOf('Bank'));
        $this->assertSame(150_000, $this->savings->goalBalance($goal->getKey()));
        $this->assertSame(150_000, $this->savings->total());
    }

    public function test_adding_more_than_the_account_has_is_rejected(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 100);
        $goal = $this->goal($user, 'Tour', 4_200);

        try {
            $this->savings->deposit($user, $goal, $this->account('Bank'), 500_000);
            $this->fail('Expected a validation error.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(0, $this->savings->goalBalance($goal->getKey()));
        $this->assertSame(10_000, $this->balanceOf('Bank'));
    }

    public function test_withdrawing_moves_money_back_and_is_not_an_expense(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 10_000);
        $goal = $this->goal($user, 'Tour', 4_200);
        $this->savings->deposit($user, $goal, $this->account('Bank'), 300_000);

        $this->savings->withdraw($user, $goal, $this->account('bKash'), 100_000);

        $this->assertSame(200_000, $this->savings->goalBalance($goal->getKey()));
        $this->assertSame(100_000, $this->balanceOf('bKash'));
        $this->assertSame(0, Transaction::query()->where('type', TransactionType::Expense)->count());
    }

    public function test_withdrawing_more_than_the_goal_holds_is_rejected(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 10_000);
        $goal = $this->goal($user, 'Tour', 4_200);
        $this->savings->deposit($user, $goal, $this->account('Bank'), 100_000);

        try {
            $this->savings->withdraw($user, $goal, $this->account('Bank'), 100_001);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        $this->assertSame(100_000, $this->savings->goalBalance($goal->getKey()));
    }

    public function test_deleting_a_savings_deposit_returns_the_money_and_the_goal_balance(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 10_000);
        $goal = $this->goal($user, 'Tour', 4_200);
        $deposit = $this->savings->deposit($user, $goal, $this->account('Bank'), 300_000);

        $this->ledger->delete($user, $deposit);

        $this->assertSame(0, $this->savings->goalBalance($goal->getKey()));
        $this->assertSame(10_000_00, $this->balanceOf('Bank'));
    }

    public function test_the_overview_reports_progress_places_and_reached_goals(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 100_000);
        $tour = $this->goal($user, 'Tour', 4_200, targetTaka: 10_000);
        $this->savings->deposit($user, $tour, $this->account('Bank'), 250_000);

        $row = $this->savings->goals()->sole();

        $this->assertSame(25, $row['percent']);
        $this->assertFalse($row['reached']);
        $this->assertSame(250_000, $this->savings->byPlace()->sole()['total']);

        $this->savings->deposit($user, $tour, $this->account('Bank'), 750_000);
        $this->assertTrue($this->savings->goals()->sole()['reached']);
    }

    public function test_money_saved_this_month_counts_deposits_minus_withdrawals(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 100_000);
        $goal = $this->goal($user, 'Tour', 4_200);
        $this->savings->deposit($user, $goal, $this->account('Bank'), 1_500_000);
        $this->savings->withdraw($user, $goal, $this->account('Bank'), 500_000);

        $this->assertSame(1_000_000, $this->savings->savedInMonth($user, now()));
    }
}

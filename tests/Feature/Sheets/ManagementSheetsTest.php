<?php

namespace Tests\Feature\Sheets;

use App\Enums\GoalKind;
use App\Enums\TransactionType;
use App\Livewire\Pages\Home;
use App\Livewire\Pages\Profile;
use App\Livewire\Pages\Savings;
use App\Livewire\Sheets\AccountSheet;
use App\Livewire\Sheets\BudgetSheet;
use App\Livewire\Sheets\EntryDetails;
use App\Livewire\Sheets\GoalMoney;
use App\Livewire\Sheets\GoalWizard;
use App\Livewire\Sheets\PasswordSheet;
use App\Livewire\Sheets\PaymentSheet;
use App\Livewire\Sheets\ProfileSheet;
use App\Models\Account;
use App\Models\CategoryBudget;
use App\Models\SavingPlace;
use App\Models\SavingReason;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Wallet\DueService;
use App\Services\Wallet\LedgerService;
use App\Services\Wallet\SavingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\WalletTestHelpers;
use Tests\TestCase;

class ManagementSheetsTest extends TestCase
{
    use DatabaseTransactions, WalletTestHelpers;

    private function expenseEntry(User $user, int $taka = 300): Transaction
    {
        return app(LedgerService::class)->expense($user, [
            'amount' => $taka * 100, 'account_id' => $this->account('Bank')->getKey(), 'category_id' => $this->category('Rent')->getKey(),
        ])['transaction'];
    }

    public function test_entry_details_show_the_summary_and_delete_needs_a_second_tap(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 1_000);
        $entry = $this->expenseEntry($user);

        $sheet = Livewire::test(EntryDetails::class)->dispatch('open-entry', id: $entry->getKey())
            ->assertSee('Entry details')->assertSee('−৳300')->assertSee('Deleting reverses its effect on your balances.')
            ->call('delete')
            ->assertSet('confirmingDelete', true)
            ->assertSee('Tap again to delete');

        $this->assertNotNull(Transaction::query()->find($entry->getKey()), 'one tap is not enough');

        $sheet->call('delete')->assertDispatched('wallet-updated')->assertSet('open', false);

        $this->assertNull(Transaction::query()->find($entry->getKey()));
        $this->assertSame(100_000, $this->balanceOf('Bank'));
    }

    public function test_entry_details_hand_over_to_the_edit_form_only_for_editable_entries(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 1_000);
        $entry = $this->expenseEntry($user);
        $adjustment = Transaction::query()->where('type', TransactionType::BalanceAdjustment)->firstOrFail();

        Livewire::test(EntryDetails::class)->dispatch('open-entry', id: $entry->getKey())->call('edit')->assertDispatched('open-expense', id: $entry->getKey());
        Livewire::test(EntryDetails::class)->dispatch('open-entry', id: $adjustment->getKey())
            ->assertSee('can only be deleted')->call('edit')->assertNotDispatched('open-expense');
    }

    public function test_a_blocked_delete_explains_why_and_resets_the_confirmation(): void
    {
        $user = $this->walletUser();
        $income = app(LedgerService::class)->income($user, [
            'amount' => 100_000, 'account_id' => $this->account('Bank')->getKey(), 'income_source_id' => $this->source('Salami')->getKey(),
        ])['transaction'];
        $this->expenseEntry($user, 800);

        Livewire::test(EntryDetails::class)->dispatch('open-entry', id: $income->getKey())
            ->call('delete')->call('delete')
            ->assertHasErrors('delete')->assertSet('confirmingDelete', false);

        $this->assertNotNull(Transaction::query()->find($income->getKey()));
    }

    public function test_entry_details_for_a_salary_warn_that_auto_save_stays(): void
    {
        $user = $this->walletUser();
        $salary = app(LedgerService::class)->income($user, [
            'amount' => 100_000, 'account_id' => $this->account('Bank')->getKey(), 'income_source_id' => $this->source('Salary')->getKey(),
        ])['transaction'];

        Livewire::test(EntryDetails::class)->dispatch('open-entry', id: $salary->getKey())->assertSee('does not remove its auto-save entry');
    }

    public function test_an_account_is_added_with_an_opening_balance(): void
    {
        $this->walletUser();

        Livewire::test(AccountSheet::class)->dispatch('open-account')
            ->assertSee('Add account')->assertSee('Opening balance is money you already have')
            ->set('name', 'EBL')->set('type', 'bank')->set('balance', '45,000')
            ->call('save')->assertHasNoErrors()->assertDispatched('account-created')->assertDispatched('wallet-updated');

        $this->assertSame(4_500_000, $this->balanceOf('EBL'));
    }

    public function test_a_duplicate_account_name_shows_an_error_under_the_name(): void
    {
        $this->walletUser();

        Livewire::test(AccountSheet::class)->dispatch('open-account')
            ->set('name', 'cash')->call('save')->assertHasErrors('name');
    }

    public function test_the_real_balance_can_be_set_from_the_manage_sheet(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 500);

        Livewire::test(AccountSheet::class)->dispatch('open-account', id: $this->account('Bank')->getKey())
            ->assertSee('Manage account')->assertSet('balance', '500')
            ->set('balance', '45,000')->call('save');

        $this->assertSame(4_500_000, $this->balanceOf('Bank'));
    }

    public function test_deleting_an_account_with_money_is_blocked_and_an_empty_one_needs_two_taps(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Cash', 100);

        Livewire::test(AccountSheet::class)->dispatch('open-account', id: $this->account('Cash')->getKey())
            ->call('delete')->call('delete')->assertHasErrors('delete');
        $this->assertNotNull(Account::query()->where('name', 'Cash')->first());

        Livewire::test(AccountSheet::class)->dispatch('open-account', id: $this->account('bKash')->getKey())
            ->call('delete')->assertSet('confirmingDelete', true);
        $this->assertNotNull(Account::query()->where('name', 'bKash')->first());

        Livewire::test(AccountSheet::class)->dispatch('open-account', id: $this->account('bKash')->getKey())
            ->call('delete')->call('delete')->assertDispatched('wallet-updated');
        $this->assertNull(Account::query()->where('name', 'bKash')->first());
    }

    public function test_budgets_are_saved_and_cleared_from_the_sheet(): void
    {
        $this->walletUser();
        $food = $this->category('Food & Groceries')->getKey();

        Livewire::test(BudgetSheet::class)->dispatch('open-budgets')
            ->assertSee('Monthly limit for each category. Leave empty for no limit.')
            ->set("limits.$food", '8,000')->call('save')->assertDispatched('toast', message: 'Budgets saved.');
        $this->assertSame(800_000, (int) CategoryBudget::query()->sole()->monthly_limit);

        Livewire::test(BudgetSheet::class)->dispatch('open-budgets')->assertSet("limits.$food", '8,000')
            ->set("limits.$food", '')->call('save');
        $this->assertSame(0, CategoryBudget::query()->count());
    }

    public function test_the_budget_sheet_warns_when_limits_exceed_the_spending_budget(): void
    {
        $user = $this->walletUser();
        app(LedgerService::class)->income($user, ['amount' => 4_000_000, 'account_id' => $this->account('Bank')->getKey(), 'income_source_id' => $this->source('Salami')->getKey()]);
        $food = $this->category('Food & Groceries')->getKey();
        $rent = $this->category('Rent')->getKey();

        Livewire::test(BudgetSheet::class)->dispatch('open-budgets')
            ->set("limits.$food", '12,000')->set("limits.$rent", '15,000')
            ->assertDontSee('That is more than you can spend')
            ->set("limits.$rent", '30,000')
            ->assertSee('Limits add up to ৳42,000. Your spending budget this month is ৳40,000. That is more than you can spend.');
    }

    public function test_a_savings_goal_is_created_in_three_steps_with_a_suggested_monthly_amount(): void
    {
        $this->walletUser();

        Livewire::test(GoalWizard::class)->dispatch('open-goal-wizard')
            ->assertSee('What are you saving for?')
            ->set('reasonId', SavingReason::query()->where('name', 'Tour')->value('id'))
            ->call('next')->assertSet('step', 2)->assertSee('Tour: how much and how long?')
            ->set('target', '25,000')->set('months', '6')
            ->assertSet('monthly', '4,200')->assertSee('Suggested: ৳4,200/month to reach ৳25,000 in 6 months.')
            ->call('next')->assertSet('step', 3)->assertSee('Moves ৳4,200 to this goal every time you add your salary.')
            ->set('placeId', SavingPlace::query()->where('name', 'bKash Savings')->value('id'))
            ->assertSee('Tour · Monthly · target ৳25,000 · ৳4,200/month · kept in bKash Savings · auto-moved on salary day')
            ->call('create')->assertHasNoErrors()->assertDispatched('toast', message: 'Goal created.');

        $goal = SavingsGoal::query()->sole();
        $this->assertSame(420_000, (int) $goal->monthly_amount);
        $this->assertTrue($goal->auto_save);
    }

    public function test_step_two_is_blocked_without_a_target_amount(): void
    {
        $this->walletUser();

        Livewire::test(GoalWizard::class)->dispatch('open-goal-wizard')->call('next')
            ->call('next')->assertSet('step', 2)->assertHasErrors('target');
    }

    public function test_a_lifetime_goal_has_no_months_field_and_no_suggestion(): void
    {
        $this->walletUser();

        Livewire::test(GoalWizard::class)->dispatch('open-goal-wizard')->call('next')
            ->set('kind', 'lifetime')->set('target', '8,00,000')
            ->assertDontSee('IN HOW MANY MONTHS?')->assertDontSee('Suggested:')
            ->set('monthly', '7,000')->call('next')->assertSet('step', 3)
            ->call('create')->assertHasNoErrors();

        $this->assertNull(SavingsGoal::query()->sole()->months);
    }

    public function test_turning_auto_deduct_off_makes_the_goal_manual(): void
    {
        $this->walletUser();

        Livewire::test(GoalWizard::class)->dispatch('open-goal-wizard')->call('next')
            ->set('target', '5,000')->set('months', '5')->call('next')
            ->set('autoSave', false)->assertSee('added manually')->call('create');

        $this->assertFalse(SavingsGoal::query()->sole()->auto_save);
    }

    public function test_a_new_reason_and_place_can_be_added_in_the_wizard(): void
    {
        $this->walletUser();

        Livewire::test(GoalWizard::class)->dispatch('open-goal-wizard')
            ->set('adding', 'reason')->set('newName', 'Wedding')->call('addNew')
            ->assertSet('reasonId', SavingReason::query()->where('name', 'Wedding')->value('id'))
            ->set('adding', 'place')->set('newName', 'Savings account')->call('addNew')
            ->assertSet('placeId', SavingPlace::query()->where('name', 'Savings account')->value('id'));
    }

    private function goal(User $user, int $targetTaka = 25_000): SavingsGoal
    {
        return app(SavingsService::class)->createGoal($user, [
            'reason_id' => SavingReason::query()->where('name', 'Tour')->value('id'), 'place_id' => SavingPlace::query()->value('id'),
            'kind' => GoalKind::Monthly, 'target_amount' => $targetTaka * 100, 'months' => 6, 'monthly_amount' => 420_000, 'auto_save' => false,
        ]);
    }

    public function test_money_is_added_to_and_withdrawn_from_a_goal(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 41_350);
        $goal = $this->goal($user);

        Livewire::test(GoalMoney::class)->dispatch('open-goal-money', goalId: $goal->getKey(), mode: 'add')
            ->assertSee('Add money to goal')->assertSee('Tour: ৳0 saved of ৳25,000.')
            ->set('accountId', $this->account('Bank')->getKey())->set('amount', '2,000')
            ->call('save')->assertDispatched('toast', message: 'Money added to your goal.');

        Livewire::test(GoalMoney::class)->dispatch('open-goal-money', goalId: $goal->getKey(), mode: 'withdraw')
            ->assertSee('Withdraw from goal')
            ->set('accountId', $this->account('bKash')->getKey())->set('amount', '500')->call('save')->assertHasNoErrors();

        $this->assertSame(150_000, app(SavingsService::class)->goalBalance($goal->getKey()));
        $this->assertSame(50_000, $this->balanceOf('bKash'));
    }

    public function test_withdrawing_more_than_the_goal_holds_shows_an_error(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 5_000);
        $goal = $this->goal($user);
        app(SavingsService::class)->deposit($user, $goal, $this->account('Bank'), 100_000);

        Livewire::test(GoalMoney::class)->dispatch('open-goal-money', goalId: $goal->getKey(), mode: 'withdraw')
            ->set('accountId', $this->account('Bank')->getKey())->set('amount', '2,000')->call('save')->assertHasErrors('amount');
    }

    public function test_adding_more_than_the_account_has_shows_a_toast_and_stays_open(): void
    {
        $user = $this->walletUser();
        $goal = $this->goal($user);

        Livewire::test(GoalMoney::class)->dispatch('open-goal-money', goalId: $goal->getKey(), mode: 'add')
            ->set('accountId', $this->account('Bank')->getKey())->set('amount', '2,000')->call('save')
            ->assertSet('open', true)->assertDispatched('toast');
    }

    public function test_payments_can_be_partial_and_cannot_exceed_what_is_left(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 5_000);
        $due = app(DueService::class)->lend($user, ['person' => 'Rina (cousin)', 'amount' => 500_000, 'account_id' => $this->account('Bank')->getKey()]);

        Livewire::test(PaymentSheet::class)->dispatch('open-payment', dueId: $due->getKey())
            ->assertSee('Rina (cousin) owes you ৳5,000. Enter how much was paid now.')->assertSet('amount', '5,000')
            ->set('accountId', $this->account('bKash')->getKey())->set('amount', '2,000')
            ->call('save')->assertDispatched('toast', message: 'Payment recorded. ৳3,000 left.');

        Livewire::test(PaymentSheet::class)->dispatch('open-payment', dueId: $due->getKey())
            ->set('accountId', $this->account('bKash')->getKey())->set('amount', '3,001')
            ->call('save')->assertHasErrors('amount');

        Livewire::test(PaymentSheet::class)->dispatch('open-payment', dueId: $due->getKey())
            ->set('accountId', $this->account('bKash')->getKey())->set('amount', '3,000')
            ->call('save')->assertDispatched('toast', message: 'Settled. This record stays in your history.');
    }

    public function test_the_savings_page_lists_only_goals_with_money_for_withdrawal(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 5_000);
        $funded = $this->goal($user);
        $empty = app(SavingsService::class)->createGoal($user, [
            'reason_id' => SavingReason::query()->where('name', 'Hajj')->value('id'), 'place_id' => SavingPlace::query()->value('id'),
            'kind' => GoalKind::Lifetime, 'target_amount' => 1_000_000, 'months' => null, 'monthly_amount' => 100_000, 'auto_save' => false,
        ]);
        app(SavingsService::class)->deposit($user, $funded, $this->account('Bank'), 100_000);

        Livewire::test(Savings::class)->call('chooseWithdraw')->assertSee('Only goals that hold money are listed.')->assertSee('Tour')->assertDontSee('Hajj ')
            ->call('withdrawFrom', $funded->getKey())->assertDispatched('open-goal-money', goalId: $funded->getKey(), mode: 'withdraw');

        $this->assertNotNull($empty);
    }

    public function test_the_eye_button_and_profile_switch_hide_amounts_on_home(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 5_000);
        app(LedgerService::class)->income($user, ['amount' => 5_500_000, 'account_id' => $this->account('Bank')->getKey(), 'income_source_id' => $this->source('Salary')->getKey()]);

        Livewire::test(Home::class)->assertSee('৳55,000')->call('toggleHidden')->assertSee('৳ ••••')->assertDontSeeText('Bank · ৳60,000');
        $this->assertTrue($user->fresh()->hide_amounts);

        Livewire::test(Profile::class)->call('toggleHidden');
        $this->assertFalse($user->fresh()->hide_amounts);
    }

    public function test_the_profile_name_can_be_changed_and_an_email_change_needs_the_password_and_reverification(): void
    {
        $user = $this->walletUser(['name' => 'Old Name', 'password' => Hash::make('correct-password')]);

        Livewire::test(ProfileSheet::class)->dispatch('open-profile')->set('name', 'New Name')->call('save')->assertDispatched('toast');
        $this->assertSame('New Name', $user->fresh()->name);

        Livewire::test(ProfileSheet::class)->dispatch('open-profile')->set('email', 'new-'.uniqid().'@example.com')->set('password', 'wrong')->call('save')->assertHasErrors('password');

        $new = 'verify-'.uniqid().'@example.com';
        Livewire::test(ProfileSheet::class)->dispatch('open-profile')->set('email', $new)->set('password', 'correct-password')->call('save')->assertRedirect(route('verification.notice'));
        $this->assertSame($new, $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_the_email_of_another_user_cannot_be_taken(): void
    {
        $other = User::factory()->create();
        $this->walletUser();

        Livewire::test(ProfileSheet::class)->dispatch('open-profile')->set('email', $other->email)->call('save')->assertHasErrors('email');
    }

    public function test_the_password_can_be_changed_with_the_current_password(): void
    {
        $user = $this->walletUser(['password' => Hash::make('old-password')]);

        Livewire::test(PasswordSheet::class)->dispatch('open-password')
            ->set('current', 'nope')->set('password', 'brand-new-password')->set('password_confirmation', 'brand-new-password')->call('save')->assertHasErrors('current')
            ->set('current', 'old-password')->set('password', 'short')->set('password_confirmation', 'short')->call('save')->assertHasErrors('password')
            ->set('password', 'brand-new-password')->set('password_confirmation', 'brand-new-password')->call('save')->assertHasNoErrors()->assertDispatched('toast');

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_a_sheet_refuses_records_that_belong_to_another_user(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 1_000);
        $mine = $this->expenseEntry($user);
        $this->walletUser();

        $sheet = Livewire::test(EntryDetails::class)->dispatch('open-entry', id: $mine->getKey());

        $sheet->assertSet('open', false);
        $this->assertNull(Transaction::query()->find($mine->getKey()), 'the other user cannot even look the entry up');
    }
}

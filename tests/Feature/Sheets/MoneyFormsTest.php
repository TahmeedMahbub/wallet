<?php

namespace Tests\Feature\Sheets;

use App\Enums\GoalKind;
use App\Enums\TransactionType;
use App\Livewire\Sheets\AddMenu;
use App\Livewire\Sheets\ExpenseForm;
use App\Livewire\Sheets\IncomeForm;
use App\Livewire\Sheets\LendBorrowForm;
use App\Livewire\Sheets\MoveMoneyForm;
use App\Models\Category;
use App\Models\IncomeSource;
use App\Models\SavingPlace;
use App\Models\SavingReason;
use App\Models\Transaction;
use App\Services\Wallet\BudgetService;
use App\Services\Wallet\LedgerService;
use App\Services\Wallet\SavingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\Concerns\WalletTestHelpers;
use Tests\TestCase;

class MoneyFormsTest extends TestCase
{
    use DatabaseTransactions, WalletTestHelpers;

    public function test_the_add_menu_opens_and_hands_over_to_the_chosen_form(): void
    {
        $this->walletUser();

        Livewire::test(AddMenu::class)
            ->dispatch('open-add-menu')
            ->assertSet('open', true)
            ->assertSee('Expense')->assertSee('Income')->assertSee('Transfer')->assertSee('Save')->assertSee('Lend')->assertSee('Borrow')
            ->call('choose', 'open-expense')
            ->assertSet('open', false)
            ->assertDispatched('open-expense');
    }

    public function test_the_add_menu_refuses_unknown_events(): void
    {
        $this->walletUser();

        Livewire::test(AddMenu::class)->dispatch('open-add-menu')->call('choose', 'delete-everything')->assertNotDispatched('delete-everything');
    }

    public function test_an_expense_is_saved_from_the_form_with_defaults_preselected(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 1_000);

        Livewire::test(ExpenseForm::class)
            ->dispatch('open-expense')
            ->assertSet('open', true)
            ->assertSet('categoryId', Category::query()->orderBy('position')->value('id'))
            ->set('accountId', $this->account('Bank')->getKey())
            ->set('amount', '320')
            ->set('area', 'Office canteen')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('open', false)
            ->assertDispatched('wallet-updated')
            ->assertDispatched('toast');

        $this->assertSame(68_000, $this->balanceOf('Bank'));
        $this->assertSame('Office canteen', Transaction::query()->where('type', TransactionType::Expense)->sole()->area);
    }

    public function test_an_amount_typed_with_indian_grouping_is_understood(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 5_000);

        Livewire::test(ExpenseForm::class)->dispatch('open-expense')
            ->set('accountId', $this->account('Bank')->getKey())->set('amount', '1,200.50')->call('save');

        $this->assertSame(500_000 - 120_050, $this->balanceOf('Bank'));
    }

    public function test_saving_without_an_amount_shows_an_inline_error_and_saves_nothing(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 1_000);

        Livewire::test(ExpenseForm::class)->dispatch('open-expense')
            ->set('accountId', $this->account('Bank')->getKey())
            ->call('save')
            ->assertHasErrors('amount')
            ->assertSet('open', true);

        $this->assertSame(0, Transaction::query()->where('type', TransactionType::Expense)->count());
    }

    public function test_spending_more_than_the_account_has_keeps_the_form_open_and_shows_a_toast(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'bKash', 3_150);

        Livewire::test(ExpenseForm::class)->dispatch('open-expense')
            ->set('accountId', $this->account('bKash')->getKey())->set('amount', '5,000')
            ->call('save')
            ->assertSet('open', true)
            ->assertDispatched('toast', message: 'Not enough money in bKash (৳3,150). Set its balance in Accounts first.')
            ->assertNotDispatched('wallet-updated');

        $this->assertSame(315_000, $this->balanceOf('bKash'));
    }

    public function test_the_toast_warns_when_a_budget_reaches_eighty_five_percent(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 100_000);
        $food = $this->category('Food & Groceries');
        app(BudgetService::class)->set($user, [$food->getKey() => 800_000]);

        Livewire::test(ExpenseForm::class)->dispatch('open-expense')
            ->set('accountId', $this->account('Bank')->getKey())->set('categoryId', $food->getKey())->set('amount', '6,800')
            ->call('save')
            ->assertDispatched('toast', message: 'Expense saved. Food & Groceries has used 85% of its budget. ৳1,200 left.');
    }

    public function test_the_toast_says_when_a_budget_is_over(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 100_000);
        $shopping = $this->category('Shopping');
        app(BudgetService::class)->set($user, [$shopping->getKey() => 100_000]);

        Livewire::test(ExpenseForm::class)->dispatch('open-expense')
            ->set('accountId', $this->account('Bank')->getKey())->set('categoryId', $shopping->getKey())->set('amount', '1,200')
            ->call('save')
            ->assertDispatched('toast', message: 'Expense saved. Shopping is over its budget by ৳200.');
    }

    public function test_a_new_category_can_be_added_on_a_second_sheet_and_is_selected(): void
    {
        $this->walletUser();

        Livewire::test(ExpenseForm::class)->dispatch('open-expense')
            ->set('amount', '50')
            ->set('addingCategory', true)
            ->set('newCategory', 'Stationery')
            ->call('addCategory')
            ->assertSet('addingCategory', false)
            ->assertSet('amount', '50', 'typed values are kept')
            ->assertSet('categoryId', Category::query()->where('name', 'Stationery')->value('id'));
    }

    public function test_a_duplicate_or_empty_category_name_is_rejected(): void
    {
        $this->walletUser();

        Livewire::test(ExpenseForm::class)->dispatch('open-expense')
            ->set('newCategory', 'rent')->call('addCategory')->assertHasErrors('newCategory')
            ->set('newCategory', '  ')->call('addCategory')->assertHasErrors('newCategory');
    }

    public function test_a_double_tap_saves_the_expense_once(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 1_000);

        $form = Livewire::test(ExpenseForm::class)->dispatch('open-expense')
            ->set('accountId', $this->account('Bank')->getKey())->set('amount', '100');
        $form->call('save');
        $form->set('open', true)->call('save');

        $this->assertSame(1, Transaction::query()->where('type', TransactionType::Expense)->count());
        $this->assertSame(90_000, $this->balanceOf('Bank'));
    }

    public function test_an_expense_can_be_edited_through_the_same_form(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 1_000);
        $entry = app(LedgerService::class)->expense($user, [
            'amount' => 30_000, 'account_id' => $this->account('Bank')->getKey(), 'category_id' => $this->category('Rent')->getKey(),
        ])['transaction'];

        Livewire::test(ExpenseForm::class)->dispatch('open-expense', id: $entry->getKey())
            ->assertSet('amount', '300')
            ->assertSee('Edit expense')
            ->set('amount', '250')
            ->call('save')
            ->assertDispatched('toast', message: 'Entry updated.');

        $this->assertSame(25_000, $entry->fresh()->amount);
        $this->assertSame(75_000, $this->balanceOf('Bank'));
    }

    public function test_salary_income_shows_the_auto_save_note_and_reports_the_move(): void
    {
        $user = $this->walletUser();
        app(SavingsService::class)->createGoal($user, [
            'reason_id' => SavingReason::query()->value('id'), 'place_id' => SavingPlace::query()->value('id'),
            'kind' => GoalKind::Monthly, 'target_amount' => 2_500_000, 'months' => 6, 'monthly_amount' => 1_500_000, 'auto_save' => true,
        ]);

        Livewire::test(IncomeForm::class)->dispatch('open-income')
            ->assertSee('Salary: ৳15,000 will move to your savings goals automatically.')
            ->set('accountId', $this->account('Bank')->getKey())
            ->set('amount', '55,000')
            ->call('save')
            ->assertDispatched('toast', message: 'Income saved. ৳15,000 auto-saved to your goals.');
    }

    public function test_income_from_another_source_has_no_auto_save_note(): void
    {
        $user = $this->walletUser();
        app(SavingsService::class)->createGoal($user, [
            'reason_id' => SavingReason::query()->value('id'), 'place_id' => SavingPlace::query()->value('id'),
            'kind' => GoalKind::Monthly, 'target_amount' => 2_500_000, 'months' => 6, 'monthly_amount' => 1_500_000, 'auto_save' => true,
        ]);

        Livewire::test(IncomeForm::class)->dispatch('open-income')
            ->set('sourceId', IncomeSource::query()->where('name', 'Salami')->value('id'))
            ->assertDontSee('will move to your savings goals');
    }

    public function test_an_income_source_can_be_added_inline(): void
    {
        $this->walletUser();

        Livewire::test(IncomeForm::class)->dispatch('open-income')
            ->set('newSource', 'Freelance')->call('addSource')
            ->assertSet('sourceId', IncomeSource::query()->where('name', 'Freelance')->value('id'));
    }

    public function test_moving_money_between_two_accounts(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 5_000);

        Livewire::test(MoveMoneyForm::class)->dispatch('open-move-money')
            ->set('fromAccountId', $this->account('Bank')->getKey())->set('toAccountId', $this->account('bKash')->getKey())->set('amount', '2,000')
            ->call('save')->assertHasNoErrors()->assertDispatched('toast', message: 'Money moved.');

        $this->assertSame(200_000, $this->balanceOf('bKash'));
    }

    public function test_moving_money_to_the_same_account_is_rejected(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 5_000);

        Livewire::test(MoveMoneyForm::class)->dispatch('open-move-money')
            ->set('fromAccountId', $this->account('Bank')->getKey())->set('toAccountId', $this->account('Bank')->getKey())->set('amount', '100')
            ->call('save')->assertHasErrors('toAccountId');

        $this->assertSame(500_000, $this->balanceOf('Bank'));
    }

    public function test_lending_and_borrowing_from_the_form(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 5_000);

        Livewire::test(LendBorrowForm::class)->dispatch('open-lend-borrow', direction: 'lent')
            ->assertSee('Lend money')->assertSee('LEND TO')
            ->set('accountId', $this->account('Bank')->getKey())->set('person', 'Efty')->set('amount', '2,000')->set('note', 'Admission fee')
            ->call('save')->assertHasNoErrors()->assertDispatched('toast', message: 'Lend saved.');

        Livewire::test(LendBorrowForm::class)->dispatch('open-lend-borrow', direction: 'borrowed')
            ->assertSee('Borrow money')->assertSee('BORROW FROM')
            ->set('accountId', $this->account('Cash')->getKey())->set('person', 'Apu')->set('amount', '10,000')
            ->call('save')->assertDispatched('toast', message: 'Borrow saved.');

        $this->assertSame(300_000, $this->balanceOf('Bank'));
        $this->assertSame(1_000_000, $this->balanceOf('Cash'));
    }

    public function test_lending_without_a_name_shows_an_error(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 5_000);

        Livewire::test(LendBorrowForm::class)->dispatch('open-lend-borrow', direction: 'lent')
            ->set('accountId', $this->account('Bank')->getKey())->set('amount', '100')->call('save')->assertHasErrors('person');
    }
}

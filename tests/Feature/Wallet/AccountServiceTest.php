<?php

namespace Tests\Feature\Wallet;

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\Wallet\AccountService;
use App\Services\Wallet\BalanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WalletTestHelpers;
use Tests\TestCase;

class AccountServiceTest extends TestCase
{
    use DatabaseTransactions, WalletTestHelpers;

    private AccountService $accounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accounts = app(AccountService::class);
    }

    public function test_a_new_user_starts_with_default_accounts_at_zero(): void
    {
        $this->walletUser();

        $this->assertSame(['Bank', 'bKash', 'Cash'], Account::query()->orderBy('name')->pluck('name')->all());
        $this->assertSame(0, app(BalanceService::class)->wallet());
    }

    public function test_an_account_can_be_created_with_an_opening_balance_that_is_not_income(): void
    {
        $user = $this->walletUser();

        $account = $this->accounts->create($user, 'EBL', AccountType::Bank, 4_500_000);

        $this->assertSame(4_500_000, app(BalanceService::class)->of($account->getKey()));
        $entry = Transaction::query()->where('account_id', $account->getKey())->sole();
        $this->assertSame(TransactionType::BalanceAdjustment, $entry->type);
    }

    public function test_the_name_must_be_unique_among_active_accounts_regardless_of_case(): void
    {
        $user = $this->walletUser();
        $this->accounts->create($user, 'EBL', AccountType::Bank);

        $this->expectException(ValidationException::class);

        $this->accounts->create($user, 'ebl', AccountType::Bank);
    }

    public function test_a_blank_name_is_rejected(): void
    {
        $user = $this->walletUser();

        $this->expectException(ValidationException::class);

        $this->accounts->create($user, '   ', AccountType::Cash);
    }

    public function test_setting_the_real_balance_records_a_difference_not_income_or_expense(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 50_000);

        $this->accounts->update($user, $this->account('Bank'), 'Bank', AccountType::Bank, 4_500_000);

        $this->assertSame(4_500_000, $this->balanceOf('Bank'));
        $this->assertSame(0, Transaction::query()->whereIn('type', [TransactionType::Income, TransactionType::Expense])->count());
    }

    public function test_an_account_can_be_renamed_and_its_type_changed(): void
    {
        $user = $this->walletUser();

        $account = $this->accounts->update($user, $this->account('Cash'), 'Cash at home', AccountType::Cash);

        $this->assertSame('Cash at home', $account->name);
    }

    public function test_renaming_to_another_accounts_name_is_rejected(): void
    {
        $user = $this->walletUser();

        $this->expectException(ValidationException::class);

        $this->accounts->update($user, $this->account('Cash'), 'bKash', AccountType::Cash);
    }

    public function test_an_account_with_money_cannot_be_deleted(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Cash', 500);

        try {
            $this->accounts->delete($user, $this->account('Cash'));
            $this->fail('Delete should have been blocked.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('delete', $e->errors());
        }

        $this->assertSame(50_000, $this->balanceOf('Cash'));
    }

    public function test_an_empty_account_is_deleted_but_keeps_its_past_entries(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Cash', 500);
        $this->accounts->update($user, $this->account('Cash'), 'Cash', AccountType::Cash, 0);
        $cashId = $this->account('Cash')->getKey();

        $this->accounts->delete($user, $this->account('Cash'));

        $this->assertNull(Account::query()->find($cashId));
        $this->assertSame(2, Transaction::query()->where('account_id', $cashId)->count(), 'history stays');
        $this->assertSame('Cash', Transaction::query()->where('account_id', $cashId)->first()->account->name);
    }

    public function test_the_name_of_a_deleted_account_can_be_used_again(): void
    {
        $user = $this->walletUser();
        $this->accounts->delete($user, $this->account('Cash'));

        $again = $this->accounts->create($user, 'Cash', AccountType::Cash);

        $this->assertNotNull($again->getKey());
    }

    public function test_the_last_account_cannot_be_deleted(): void
    {
        $user = $this->walletUser();
        $this->accounts->delete($user, $this->account('Cash'));
        $this->accounts->delete($user, $this->account('bKash'));

        $this->expectException(ValidationException::class);

        $this->accounts->delete($user, $this->account('Bank'));
    }
}

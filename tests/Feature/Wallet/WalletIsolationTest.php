<?php

namespace Tests\Feature\Wallet;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Due;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Wallet\AccountService;
use App\Services\Wallet\BalanceService;
use App\Services\Wallet\DefaultData;
use App\Services\Wallet\DueService;
use App\Services\Wallet\LedgerService;
use App\Services\Wallet\SavingsService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Two users each have money data; neither can read or change the other's.
 */
class WalletIsolationTest extends TestCase
{
    use DatabaseTransactions;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = $this->makeUser('Alice');
        $this->bob = $this->makeUser('Bob');
    }

    private function makeUser(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        app(DefaultData::class)->seed($user);

        $this->actingAs($user);
        $bank = Account::query()->where('name', 'Bank')->first();
        app(AccountService::class)->update($user, $bank, 'Bank', $bank->type, 1_000_000);
        app(LedgerService::class)->expense($user, [
            'amount' => 10_000, 'account_id' => $bank->getKey(),
            'category_id' => Category::query()->where('name', 'Rent')->value('id'),
            'reason' => $name.' private note',
        ]);
        app(DueService::class)->lend($user, ['person' => $name.' friend', 'amount' => 50_000, 'account_id' => $bank->getKey()]);

        return $user;
    }

    public function test_each_user_only_sees_their_own_records(): void
    {
        $this->actingAs($this->alice);
        $this->assertSame(3, Account::query()->count());
        $this->assertSame('Alice private note', Transaction::query()->where('type', TransactionType::Expense)->sole()->reason);
        $this->assertSame(['Alice friend'], Due::query()->pluck('person')->all());

        $this->actingAs($this->bob);
        $this->assertSame('Bob private note', Transaction::query()->where('type', TransactionType::Expense)->sole()->reason);
        $this->assertSame(['Bob friend'], Due::query()->pluck('person')->all());
    }

    public function test_balances_never_include_another_users_money(): void
    {
        $this->actingAs($this->alice);
        $aliceBank = Account::query()->where('name', 'Bank')->sole();

        $this->assertSame(1_000_000 - 10_000 - 50_000, app(BalanceService::class)->of($aliceBank->getKey()));
        $this->assertSame(940_000, app(BalanceService::class)->wallet());

        $this->actingAs($this->bob);
        $this->assertSame(0, app(BalanceService::class)->of($aliceBank->getKey()), "Bob cannot see Alice's postings");
    }

    public function test_a_user_cannot_spend_from_or_edit_or_delete_anothers_records(): void
    {
        $this->actingAs($this->alice);
        $aliceBank = Account::query()->where('name', 'Bank')->sole();
        $aliceExpense = Transaction::query()->where('type', TransactionType::Expense)->sole();
        $aliceDue = Due::query()->sole();

        $this->actingAs($this->bob);
        $rent = Category::query()->where('name', 'Rent')->sole();
        $ledger = app(LedgerService::class);

        $attempts = [
            fn () => $ledger->expense($this->bob, ['amount' => 100, 'account_id' => $aliceBank->getKey(), 'category_id' => $rent->getKey()]),
            fn () => $ledger->delete($this->bob, $aliceExpense),
            fn () => $ledger->update($this->bob, $aliceExpense, ['amount' => 100, 'account_id' => $aliceBank->getKey(), 'category_id' => $rent->getKey()]),
            fn () => app(DueService::class)->recordPayment($this->bob, $aliceDue, 100, Account::query()->where('name', 'Bank')->sole()),
            fn () => app(AccountService::class)->delete($this->bob, $aliceBank),
            fn () => app(SavingsService::class)->deposit($this->bob, new SavingsGoal(['id' => 1]), $aliceBank, 100),
        ];

        foreach ($attempts as $index => $attempt) {
            try {
                $attempt();
                $this->fail("Attempt $index should have been refused.");
            } catch (ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->actingAs($this->alice);
        $this->assertSame(940_000, app(BalanceService::class)->of($aliceBank->getKey()), "Alice's money is untouched");
        $this->assertNotNull(Transaction::query()->find($aliceExpense->getKey()));
    }

    public function test_the_database_refuses_a_row_pointing_at_another_users_account(): void
    {
        $aliceBank = Account::withoutGlobalScopes()->where('user_id', $this->alice->getKey())->where('name', 'Bank')->value('id');

        $this->expectException(QueryException::class);

        DB::table('transactions')->insert([
            'user_id' => $this->bob->getKey(),
            'type' => 'expense',
            'amount' => 100,
            'occurred_at' => now(),
            'account_id' => $aliceBank,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_the_database_refuses_a_posting_into_another_users_account(): void
    {
        $this->actingAs($this->bob);
        $bobExpense = Transaction::query()->where('type', TransactionType::Expense)->sole();
        $aliceBank = Account::withoutGlobalScopes()->where('user_id', $this->alice->getKey())->where('name', 'Bank')->value('id');

        $this->expectException(QueryException::class);

        DB::table('ledger_postings')->insert([
            'user_id' => $this->bob->getKey(),
            'transaction_id' => $bobExpense->getKey(),
            'account_id' => $aliceBank,
            'delta' => -1,
        ]);
    }

    public function test_nobody_signed_in_sees_no_financial_data(): void
    {
        auth()->logout();

        $this->assertSame(0, Account::query()->count());
        $this->assertSame(0, Transaction::query()->count());
        $this->assertSame(0, Due::query()->count());
    }

    public function test_idempotency_keys_are_per_user(): void
    {
        $this->actingAs($this->alice);
        $aliceBank = Account::query()->where('name', 'Bank')->sole();
        $rent = Category::query()->where('name', 'Rent')->sole();
        $data = ['amount' => 100, 'account_id' => $aliceBank->getKey(), 'category_id' => $rent->getKey()];
        app(LedgerService::class)->expense($this->alice, $data, 'same-key');

        $this->actingAs($this->bob);
        $bobData = ['amount' => 100, 'account_id' => Account::query()->where('name', 'Bank')->sole()->getKey(), 'category_id' => Category::query()->where('name', 'Rent')->sole()->getKey()];
        $result = app(LedgerService::class)->expense($this->bob, $bobData, 'same-key');

        $this->assertSame($this->bob->getKey(), $result['transaction']->user_id);
    }
}

<?php

namespace Tests\Feature\Wallet;

use App\Enums\TransactionType;
use App\Exceptions\DuplicateSubmission;
use App\Models\Account;
use App\Models\LedgerPosting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Wallet\BudgetService;
use App\Services\Wallet\Data\BudgetRow;
use App\Services\Wallet\DefaultData;
use App\Services\Wallet\LedgerService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Concerns\WalletTestHelpers;
use Tests\TestCase;

class LedgerServiceTest extends TestCase
{
    use DatabaseTransactions, WalletTestHelpers;

    private LedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(LedgerService::class);
    }

    private function expenseData(int $taka, string $account = 'Bank', string $category = 'Food & Groceries', array $extra = []): array
    {
        return [
            'amount' => $taka * 100,
            'account_id' => $this->account($account)->getKey(),
            'category_id' => $this->category($category)->getKey(),
            ...$extra,
        ];
    }

    public function test_an_expense_lowers_the_account_balance_and_is_recorded(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 1_000);

        $result = $this->ledger->expense($user, $this->expenseData(320, extra: ['area' => 'Office canteen', 'reason' => 'Lunch']));

        $this->assertSame(68_000, $this->balanceOf('Bank'));
        $this->assertSame(TransactionType::Expense, $result['transaction']->type);
        $this->assertSame(32_000, $result['transaction']->amount);
        $this->assertSame('Office canteen', $result['transaction']->area);
    }

    public function test_an_expense_without_an_amount_is_rejected_and_nothing_is_saved(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 1_000);
        $before = Transaction::query()->count();

        try {
            $this->ledger->expense($user, $this->expenseData(0));
            $this->fail('Expected a validation error.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        $this->assertSame($before, Transaction::query()->count());
    }

    public function test_an_expense_more_than_the_account_has_is_rejected_and_nothing_is_saved(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 1_000);
        $before = Transaction::query()->count();

        try {
            $this->ledger->expense($user, $this->expenseData(1_200));
            $this->fail('Expected a validation error.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        $this->assertSame($before, Transaction::query()->count());
        $this->assertSame(100_000, $this->balanceOf('Bank'));
    }

    public function test_an_expense_that_leaves_exactly_zero_is_allowed(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 500);

        $this->ledger->expense($user, $this->expenseData(500));

        $this->assertSame(0, $this->balanceOf('Bank'));
    }

    public function test_income_raises_the_balance(): void
    {
        $user = $this->walletUser();

        $result = $this->ledger->income($user, [
            'amount' => 5_500_000,
            'account_id' => $this->account('Bank')->getKey(),
            'income_source_id' => $this->source('Salami')->getKey(),
        ]);

        $this->assertSame(5_500_000, $this->balanceOf('Bank'));
        $this->assertNull($result['auto_save']);
    }

    public function test_a_transfer_moves_money_and_is_neither_income_nor_expense(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 5_000);

        $this->ledger->transfer($user, [
            'amount' => 200_000,
            'from_account_id' => $this->account('Bank')->getKey(),
            'to_account_id' => $this->account('bKash')->getKey(),
        ]);

        $this->assertSame(300_000, $this->balanceOf('Bank'));
        $this->assertSame(200_000, $this->balanceOf('bKash'));
        $this->assertSame(500_000, $this->balanceOf('Bank') + $this->balanceOf('bKash'), 'total money is unchanged');
        $this->assertSame(0, Transaction::query()->whereIn('type', [TransactionType::Income, TransactionType::Expense])->count());
    }

    public function test_a_transfer_between_the_same_account_is_rejected(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 5_000);

        try {
            $this->ledger->transfer($user, [
                'amount' => 100_000,
                'from_account_id' => $this->account('Bank')->getKey(),
                'to_account_id' => $this->account('Bank')->getKey(),
            ]);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('to_account_id', $e->errors());
        }

        $this->assertSame(500_000, $this->balanceOf('Bank'));
    }

    public function test_a_transfer_of_more_than_the_from_account_has_is_rejected(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 1_000);

        $this->expectException(ValidationException::class);

        $this->ledger->transfer($user, [
            'amount' => 200_000,
            'from_account_id' => $this->account('Bank')->getKey(),
            'to_account_id' => $this->account('bKash')->getKey(),
        ]);
    }

    public function test_editing_an_expense_recalculates_balances_including_when_the_account_changes(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 1_000);
        $this->fund($user, 'Cash', 500);
        $entry = $this->ledger->expense($user, $this->expenseData(300))['transaction'];

        $this->ledger->update($user, $entry, $this->expenseData(250, account: 'Cash', category: 'Transport'));

        $this->assertSame(100_000, $this->balanceOf('Bank'), 'old account is refunded');
        $this->assertSame(25_000, $this->balanceOf('Cash'));
        $this->assertSame(1, LedgerPosting::query()->where('transaction_id', $entry->getKey())->count());
        $this->assertSame($this->category('Transport')->getKey(), $entry->fresh()->category_id);
    }

    public function test_editing_an_expense_cannot_overdraw_the_account(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 1_000);
        $entry = $this->ledger->expense($user, $this->expenseData(300))['transaction'];

        try {
            $this->ledger->update($user, $entry, $this->expenseData(1_500));
            $this->fail('Expected a validation error.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(70_000, $this->balanceOf('Bank'));
        $this->assertSame(30_000, $entry->fresh()->amount);
    }

    public function test_only_expense_income_and_transfer_can_be_edited(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 1_000);
        $adjustment = Transaction::query()->where('type', TransactionType::BalanceAdjustment)->firstOrFail();

        $this->expectException(LogicException::class);

        $this->ledger->update($user, $adjustment, ['amount' => 100]);
    }

    public function test_deleting_an_expense_reverses_its_effect(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 1_000);
        $entry = $this->ledger->expense($user, $this->expenseData(320))['transaction'];

        $this->ledger->delete($user, $entry);

        $this->assertSame(100_000, $this->balanceOf('Bank'));
        $this->assertNull(Transaction::query()->find($entry->getKey()));
        $this->assertSame(0, LedgerPosting::query()->where('transaction_id', $entry->getKey())->count());
    }

    public function test_deleting_a_transfer_reverses_both_accounts(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 5_000);
        $entry = $this->ledger->transfer($user, [
            'amount' => 200_000,
            'from_account_id' => $this->account('Bank')->getKey(),
            'to_account_id' => $this->account('bKash')->getKey(),
        ]);

        $this->ledger->delete($user, $entry);

        $this->assertSame(500_000, $this->balanceOf('Bank'));
        $this->assertSame(0, $this->balanceOf('bKash'));
    }

    public function test_deleting_income_that_was_already_spent_is_blocked(): void
    {
        $user = $this->walletUser();
        $income = $this->ledger->income($user, [
            'amount' => 100_000,
            'account_id' => $this->account('Bank')->getKey(),
            'income_source_id' => $this->source('Salami')->getKey(),
        ])['transaction'];
        $this->ledger->expense($user, $this->expenseData(800));

        try {
            $this->ledger->delete($user, $income);
            $this->fail('Expected deletion to be blocked.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('delete', $e->errors());
        }

        $this->assertNotNull(Transaction::query()->find($income->getKey()));
        $this->assertSame(20_000, $this->balanceOf('Bank'));
    }

    public function test_the_expense_result_warns_when_a_budget_crosses_eighty_percent_and_goes_over(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 100_000);
        app(BudgetService::class)->set($user, [$this->category('Food & Groceries')->getKey() => 800_000]);

        $first = $this->ledger->expense($user, $this->expenseData(5_000));
        $second = $this->ledger->expense($user, $this->expenseData(1_800));
        $third = $this->ledger->expense($user, $this->expenseData(1_300));

        $this->assertSame(BudgetRow::OnTrack, $first['budget']->state());
        $this->assertSame(BudgetRow::Warning, $second['budget']->state());
        $this->assertSame(85, $second['budget']->percent());
        $this->assertSame(BudgetRow::Over, $third['budget']->state());
    }

    public function test_a_category_without_a_budget_gives_no_budget_status(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 1_000);

        $this->assertNull($this->ledger->expense($user, $this->expenseData(100))['budget']);
    }

    public function test_a_repeated_submission_with_the_same_key_is_applied_once(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 1_000);
        $key = 'form-'.uniqid();

        $this->ledger->expense($user, $this->expenseData(100), $key);

        try {
            $this->ledger->expense($user, $this->expenseData(100), $key);
            $this->fail('The second submission should be ignored.');
        } catch (DuplicateSubmission) {
            // expected
        }

        $this->assertSame(90_000, $this->balanceOf('Bank'));
        $this->assertSame(1, Transaction::query()->where('type', TransactionType::Expense)->count());
    }

    public function test_an_entry_belonging_to_a_foreign_account_cannot_be_created(): void
    {
        $user = $this->walletUser();
        $foreignAccountId = Account::withoutGlobalScopes()->whereKeyNot($this->account('Bank')->getKey())
            ->where('user_id', '!=', $user->getKey())->value('id');

        if ($foreignAccountId === null) {
            $other = User::factory()->create();
            app(DefaultData::class)->seed($other);
            $this->actingAs($user);
            $foreignAccountId = Account::withoutGlobalScopes()->where('user_id', $other->getKey())->value('id');
        }

        $this->expectException(ModelNotFoundException::class);

        $this->ledger->expense($user, ['amount' => 100, 'account_id' => $foreignAccountId, 'category_id' => $this->category('Rent')->getKey()]);
    }
}

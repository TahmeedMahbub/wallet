<?php

namespace Tests\Feature\Wallet;

use App\Enums\DueDirection;
use App\Enums\TransactionType;
use App\Models\Due;
use App\Models\Transaction;
use App\Services\Wallet\DueService;
use App\Services\Wallet\LedgerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WalletTestHelpers;
use Tests\TestCase;

class DueServiceTest extends TestCase
{
    use DatabaseTransactions, WalletTestHelpers;

    private DueService $dues;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dues = app(DueService::class);
    }

    public function test_lending_takes_money_out_but_is_not_an_expense(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 5_000);

        $due = $this->dues->lend($user, ['person' => 'Efty', 'amount' => 200_000, 'account_id' => $this->account('Bank')->getKey(), 'note' => 'Admission fee']);

        $this->assertSame(300_000, $this->balanceOf('Bank'));
        $this->assertSame(0, Transaction::query()->where('type', TransactionType::Expense)->count());
        $this->assertSame(200_000, $this->dues->outstanding($user, DueDirection::Lent));
        $this->assertSame(DueDirection::Lent, $due->direction);
    }

    public function test_lending_more_than_the_account_has_is_rejected(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 100);

        try {
            $this->dues->lend($user, ['person' => 'Efty', 'amount' => 500_000, 'account_id' => $this->account('Bank')->getKey()]);
            $this->fail('Expected a validation error.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(0, Due::query()->count());
        $this->assertSame(10_000, $this->balanceOf('Bank'));
    }

    public function test_borrowing_adds_money_but_is_not_income(): void
    {
        $user = $this->walletUser();

        $this->dues->borrow($user, ['person' => 'Apu', 'amount' => 1_000_000, 'account_id' => $this->account('Cash')->getKey()]);

        $this->assertSame(1_000_000, $this->balanceOf('Cash'));
        $this->assertSame(0, Transaction::query()->where('type', TransactionType::Income)->count());
        $this->assertSame(1_000_000, $this->dues->outstanding($user, DueDirection::Borrowed));
    }

    public function test_a_lend_needs_a_name_and_an_amount(): void
    {
        $user = $this->walletUser();

        $this->expectException(ValidationException::class);

        $this->dues->lend($user, ['person' => ' ', 'amount' => 100, 'account_id' => $this->account('Cash')->getKey()]);
    }

    public function test_partial_returns_are_recorded_until_settled(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 5_000);
        $due = $this->dues->lend($user, ['person' => 'Rina', 'amount' => 500_000, 'account_id' => $this->account('Bank')->getKey()]);

        $this->dues->recordPayment($user, $due, 200_000, $this->account('bKash'));

        $row = $this->dues->list($user, DueDirection::Lent)->sole();
        $this->assertSame('partly', $row['status']);
        $this->assertSame(200_000, $row['paid']);
        $this->assertSame(300_000, $row['remaining']);
        $this->assertSame(200_000, $this->balanceOf('bKash'));
        $this->assertSame(300_000, $this->dues->outstanding($user, DueDirection::Lent));

        $this->dues->recordPayment($user, $due, 300_000, $this->account('bKash'));

        $this->assertSame('settled', $this->dues->list($user, DueDirection::Lent)->sole()['status']);
        $this->assertSame(0, $this->dues->outstanding($user, DueDirection::Lent));
    }

    public function test_a_payment_larger_than_what_is_left_is_rejected(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 5_000);
        $due = $this->dues->lend($user, ['person' => 'Rina', 'amount' => 500_000, 'account_id' => $this->account('Bank')->getKey()]);
        $this->dues->recordPayment($user, $due, 200_000, $this->account('bKash'));

        try {
            $this->dues->recordPayment($user, $due, 300_001, $this->account('bKash'));
            $this->fail('Expected a validation error.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        $this->assertSame(300_000, $this->dues->remaining($due));
    }

    public function test_repaying_a_loan_takes_money_out_and_needs_enough_money(): void
    {
        $user = $this->walletUser();
        $due = $this->dues->borrow($user, ['person' => 'Apu', 'amount' => 1_000_000, 'account_id' => $this->account('Cash')->getKey()]);

        $this->dues->recordPayment($user, $due, 400_000, $this->account('Cash'));
        $this->assertSame(600_000, $this->balanceOf('Cash'));

        $this->expectException(ValidationException::class);

        $this->dues->recordPayment($user, $due, 600_000, $this->account('bKash'));
    }

    public function test_an_unpaid_due_after_its_return_date_is_late(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 5_000);
        $this->dues->lend($user, ['person' => 'Efty', 'amount' => 200_000, 'account_id' => $this->account('Bank')->getKey(), 'due_date' => now()->subDays(3)->toDateString()]);
        $this->dues->lend($user, ['person' => 'Mitu', 'amount' => 100_000, 'account_id' => $this->account('Bank')->getKey(), 'due_date' => now()->addDays(10)->toDateString()]);

        $statuses = $this->dues->list($user, DueDirection::Lent)->mapWithKeys(fn (array $row): array => [$row['due']->person => $row['status']]);

        $this->assertSame('late', $statuses['Efty']);
        $this->assertSame('open', $statuses['Mitu']);
    }

    public function test_deleting_a_lend_also_removes_it_from_dues_and_reverses_its_payments(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 5_000);
        $this->fund($user, 'bKash', 0);
        $due = $this->dues->lend($user, ['person' => 'Rina', 'amount' => 500_000, 'account_id' => $this->account('Bank')->getKey()]);
        $this->dues->recordPayment($user, $due, 200_000, $this->account('bKash'));
        $lend = Transaction::query()->where('type', TransactionType::Lend)->sole();

        app(LedgerService::class)->delete($user, $lend);

        $this->assertSame(0, Due::query()->count());
        $this->assertSame(500_000, $this->balanceOf('Bank'));
        $this->assertSame(0, $this->balanceOf('bKash'));
        $this->assertSame(0, Transaction::query()->whereIn('type', [TransactionType::Lend, TransactionType::DuePayment])->count());
    }

    public function test_deleting_one_payment_reopens_the_due(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 5_000);
        $due = $this->dues->lend($user, ['person' => 'Rina', 'amount' => 500_000, 'account_id' => $this->account('Bank')->getKey()]);
        $payment = $this->dues->recordPayment($user, $due, 500_000, $this->account('Bank'));
        $this->assertSame('settled', $this->dues->list($user, DueDirection::Lent)->sole()['status']);

        app(LedgerService::class)->delete($user, $payment);

        $this->assertSame(500_000, $this->dues->remaining($due));
        $this->assertSame(0, $this->balanceOf('Bank'), 'the lent money is out again after the payment is undone');
    }
}

<?php

namespace Tests\Feature\Pages;

use App\Enums\GoalKind;
use App\Models\SavingPlace;
use App\Models\SavingReason;
use App\Services\Wallet\BudgetService;
use App\Services\Wallet\DueService;
use App\Services\Wallet\LedgerService;
use App\Services\Wallet\SavingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\WalletTestHelpers;
use Tests\TestCase;

class PagesRenderTest extends TestCase
{
    use DatabaseTransactions, WalletTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<int, array{string}>
     */
    public static function pages(): array
    {
        return array_map(fn (string $url): array => [$url], ['/', '/transactions', '/reports', '/savings', '/dues', '/accounts', '/notifications', '/profile']);
    }

    public function test_every_page_renders_for_a_brand_new_user(): void
    {
        $this->walletUser(['name' => 'Newcomer']);

        foreach (self::pages() as [$url]) {
            $this->get($url)->assertOk();
        }

        $this->get('/')->assertSee('Start tracking this month')->assertSee('No goals yet')->assertSee('Set budgets');
        $this->get('/notifications')->assertSee("You're all caught up");
        $this->get('/transactions')->assertSee('No entries in');
    }

    public function test_every_page_renders_for_a_user_with_data(): void
    {
        $user = $this->walletUser(['name' => 'Busy User']);
        $this->fund($user, 'Bank', 100_000);
        $ledger = app(LedgerService::class);
        $savings = app(SavingsService::class);

        $ledger->income($user, ['amount' => 5_500_000, 'account_id' => $this->account('Bank')->getKey(), 'income_source_id' => $this->source('Salary')->getKey()]);
        $savings->createGoal($user, [
            'reason_id' => SavingReason::query()->where('name', 'Tour')->value('id'), 'place_id' => SavingPlace::query()->value('id'),
            'kind' => GoalKind::Monthly, 'target_amount' => 2_500_000, 'months' => 6, 'monthly_amount' => 420_000, 'auto_save' => true,
        ]);
        $ledger->income($user, ['amount' => 5_500_000, 'account_id' => $this->account('Bank')->getKey(), 'income_source_id' => $this->source('Salary')->getKey()]);
        app(BudgetService::class)->set($user, [$this->category('Food & Groceries')->getKey() => 800_000, $this->category('Shopping')->getKey() => 100_000]);
        $ledger->expense($user, ['amount' => 680_000, 'account_id' => $this->account('Bank')->getKey(), 'category_id' => $this->category('Food & Groceries')->getKey(), 'reason' => 'Groceries']);
        $ledger->expense($user, ['amount' => 120_000, 'account_id' => $this->account('Bank')->getKey(), 'category_id' => $this->category('Shopping')->getKey()]);
        $ledger->transfer($user, ['amount' => 200_000, 'from_account_id' => $this->account('Bank')->getKey(), 'to_account_id' => $this->account('bKash')->getKey()]);
        app(DueService::class)->lend($user, ['person' => 'Efty', 'amount' => 200_000, 'account_id' => $this->account('Bank')->getKey(), 'due_date' => now()->subDays(2)->toDateString(), 'note' => 'Admission fee']);

        foreach (self::pages() as [$url]) {
            $this->get($url)->assertOk();
        }

        $this->get('/')->assertSee('Left to spend')->assertSee('Food &amp; Groceries', false)->assertSee('Groceries')->assertSee('See all entries');
        $this->get('/savings')->assertSee('Tour')->assertSee('+ Add money');
        $this->get('/dues')->assertSee('Efty')->assertSee('Late');
        $this->get('/reports')->assertSee('Where your income went')->assertSee('Expenses by category');
        $this->get('/transactions')->assertSee('TODAY')->assertSee('Groceries');
        $this->get('/notifications')->assertSee('Shopping budget exceeded')->assertSee('Efty is late');
        $this->get('/accounts')->assertSee('Net worth');
        $this->get('/profile')->assertSee('Busy User');
    }

    public function test_guests_cannot_open_any_app_page(): void
    {
        foreach (self::pages() as [$url]) {
            $this->get($url)->assertRedirect('/login');
        }

        $this->get('/export/json')->assertRedirect('/login');
        $this->get('/export/csv')->assertRedirect('/login');
    }
}

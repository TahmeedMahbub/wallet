<?php

namespace App\Livewire\Pages;

use App\Enums\DueDirection;
use App\Models\Account;
use App\Services\Wallet\BalanceService;
use App\Services\Wallet\BudgetService;
use App\Services\Wallet\DueService;
use App\Services\Wallet\HistoryService;
use App\Services\Wallet\MonthService;
use App\Services\Wallet\SavingsService;
use App\Support\Months;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts::app', ['title' => 'Home', 'nav' => 'home', 'bar' => 'home'])]
class Home extends Component
{
    /**
     * Hide left-to-spend and account balances (the eye button and the Profile switch).
     */
    public function toggleHidden(): void
    {
        $user = auth()->user();
        $user->forceFill(['hide_amounts' => ! $user->hide_amounts])->save();
    }

    #[On('wallet-updated')]
    public function refresh(): void
    {
        auth()->user()->refresh();
    }

    public function render()
    {
        $user = auth()->user();
        $balances = app(BalanceService::class)->all();
        $now = Months::today($user);
        $summary = app(MonthService::class)->summary($user, $now);
        $savings = app(SavingsService::class);
        $dues = app(DueService::class);
        $accounts = Account::query()->orderBy('id')->get();

        return view('livewire.pages.home', [
            'user' => $user,
            'hidden' => (bool) $user->hide_amounts,
            'now' => $now,
            'summary' => $summary,
            'budgets' => app(BudgetService::class)->rows($user, $now),
            'savingsTotal' => $savings->total(),
            'goalCount' => $savings->goals()->count(),
            'autoSaveTotal' => $savings->nextAutoSaveTotal(),
            'toGet' => $dues->outstanding($user, DueDirection::Lent),
            'toPay' => $dues->outstanding($user, DueDirection::Borrowed),
            'accounts' => $accounts,
            'balances' => $balances,
            'walletTotal' => app(BalanceService::class)->wallet(),
            'recent' => app(HistoryService::class)->entries($user, $now, null, null, 5),
        ]);
    }
}

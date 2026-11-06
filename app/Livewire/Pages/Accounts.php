<?php

namespace App\Livewire\Pages;

use App\Enums\DueDirection;
use App\Models\Account;
use App\Services\Wallet\BalanceService;
use App\Services\Wallet\DueService;
use App\Services\Wallet\SavingsService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts::app', ['title' => 'Accounts', 'nav' => 'home', 'bar' => 'back', 'barTitle' => 'Accounts'])]
class Accounts extends Component
{
    #[On('wallet-updated')]
    public function refresh(): void {}

    public function render()
    {
        $user = auth()->user();
        $balances = app(BalanceService::class);
        $dues = app(DueService::class);
        $wallet = $balances->wallet();
        $savings = app(SavingsService::class)->total();
        $toGet = $dues->outstanding($user, DueDirection::Lent);
        $toPay = $dues->outstanding($user, DueDirection::Borrowed);

        return view('livewire.pages.accounts', [
            'accounts' => Account::query()->orderBy('id')->get(),
            'balances' => $balances->all(),
            'wallet' => $wallet,
            'savings' => $savings,
            'toGet' => $toGet,
            'toPay' => $toPay,
            'netWorth' => $wallet + $savings + $toGet - $toPay,
        ]);
    }
}

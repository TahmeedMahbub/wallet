<?php

namespace App\Livewire\Pages;

use App\Models\Account;
use App\Models\CategoryBudget;
use App\Services\Wallet\BalanceService;
use App\Services\Wallet\NotificationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts::app', ['title' => 'Profile', 'nav' => 'home', 'bar' => 'back', 'barTitle' => 'Profile'])]
class Profile extends Component
{
    public function toggleHidden(): void
    {
        $user = auth()->user();
        $user->forceFill(['hide_amounts' => ! $user->hide_amounts])->save();
    }

    public function logout()
    {
        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();

        return $this->redirect(route('login'));
    }

    #[On('wallet-updated')]
    public function refresh(): void {}

    public function render()
    {
        $user = auth()->user();

        return view('livewire.pages.profile', [
            'user' => $user,
            'accountCount' => Account::query()->count(),
            'wallet' => app(BalanceService::class)->wallet(),
            'budgetCount' => CategoryBudget::query()->count(),
            'attention' => app(NotificationService::class)->attentionCount($user),
        ]);
    }
}

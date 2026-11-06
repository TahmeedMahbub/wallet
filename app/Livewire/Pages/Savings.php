<?php

namespace App\Livewire\Pages;

use App\Services\Wallet\SavingsService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts::app', ['title' => 'Savings', 'nav' => 'savings', 'bar' => 'back', 'barTitle' => 'Savings'])]
class Savings extends Component
{
    public bool $choosing = false;

    public function chooseWithdraw(): void
    {
        $this->choosing = true;
    }

    public function closeChooser(): void
    {
        $this->choosing = false;
    }

    public function withdrawFrom(int $goalId): void
    {
        $this->choosing = false;
        $this->dispatch('open-goal-money', goalId: $goalId, mode: 'withdraw');
    }

    #[On('wallet-updated')]
    public function refresh(): void {}

    public function render()
    {
        $savings = app(SavingsService::class);
        $goals = $savings->goals();

        return view('livewire.pages.savings', [
            'goals' => $goals,
            'total' => $savings->total(),
            'autoSaveTotal' => $savings->nextAutoSaveTotal(),
            'places' => $savings->byPlace(),
            'withdrawable' => $goals->filter(fn (array $row): bool => $row['balance'] > 0)->values(),
        ]);
    }
}

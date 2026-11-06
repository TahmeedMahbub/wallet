<?php

namespace App\Livewire\Pages;

use App\Enums\DueDirection;
use App\Services\Wallet\DueService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts::app', ['title' => 'Dues', 'nav' => 'dues', 'bar' => 'back', 'barTitle' => 'Lend & borrow'])]
class Dues extends Component
{
    public string $tab = 'lent';

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'borrowed' ? 'borrowed' : 'lent';
    }

    #[On('wallet-updated')]
    public function refresh(): void {}

    public function render()
    {
        $user = auth()->user();
        $service = app(DueService::class);
        $lent = $service->list($user, DueDirection::Lent);
        $borrowed = $service->list($user, DueDirection::Borrowed);

        return view('livewire.pages.dues', [
            'lent' => $lent,
            'borrowed' => $borrowed,
            'toGet' => (int) $lent->sum('remaining'),
            'toPay' => (int) $borrowed->sum('remaining'),
            'rows' => $this->tab === 'borrowed' ? $borrowed : $lent,
        ]);
    }
}

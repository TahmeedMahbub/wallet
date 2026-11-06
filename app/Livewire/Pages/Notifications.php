<?php

namespace App\Livewire\Pages;

use App\Services\Wallet\NotificationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts::app', ['title' => 'Notifications', 'nav' => 'home', 'bar' => 'back', 'barTitle' => 'Notifications'])]
class Notifications extends Component
{
    #[On('wallet-updated')]
    public function refresh(): void {}

    public function render()
    {
        return view('livewire.pages.notifications', [
            'items' => app(NotificationService::class)->items(auth()->user()),
        ]);
    }
}

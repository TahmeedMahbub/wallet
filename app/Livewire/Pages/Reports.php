<?php

namespace App\Livewire\Pages;

use App\Services\Wallet\ReportService;
use App\Support\Months;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts::app', ['title' => 'Reports', 'nav' => 'reports', 'bar' => 'back', 'barTitle' => 'Reports'])]
class Reports extends Component
{
    public int $monthsBack = 0;

    public function previousMonth(): void
    {
        $this->monthsBack++;
    }

    public function nextMonth(): void
    {
        $this->monthsBack = max(0, $this->monthsBack - 1);
    }

    #[On('wallet-updated')]
    public function refresh(): void {}

    public function render()
    {
        $user = auth()->user();
        $month = Months::today($user)->startOfMonth()->subMonths($this->monthsBack);

        return view('livewire.pages.reports', [
            'month' => $month,
            'report' => app(ReportService::class)->month($user, $month),
        ]);
    }
}

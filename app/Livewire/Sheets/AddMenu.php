<?php

namespace App\Livewire\Sheets;

use App\Livewire\Concerns\InteractsWithSheet;
use Livewire\Attributes\On;
use Livewire\Component;

class AddMenu extends Component
{
    use InteractsWithSheet;

    #[On('open-add-menu')]
    public function openMenu(): void
    {
        $this->beginForm();
    }

    /**
     * Close the menu and open the chosen form.
     */
    public function choose(string $event, string $direction = ''): void
    {
        $this->open = false;

        $allowed = ['open-expense', 'open-income', 'open-move-money', 'open-goal-wizard', 'open-lend-borrow'];

        if (! in_array($event, $allowed, true)) {
            return;
        }

        $event === 'open-lend-borrow'
            ? $this->dispatch($event, direction: $direction)
            : $this->dispatch($event);
    }

    public function render()
    {
        return view('livewire.sheets.add-menu');
    }
}

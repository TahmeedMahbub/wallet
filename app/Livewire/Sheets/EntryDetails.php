<?php

namespace App\Livewire\Sheets;

use App\Livewire\Concerns\InteractsWithSheet;
use App\Models\Transaction;
use App\Services\Wallet\LedgerService;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class EntryDetails extends Component
{
    use InteractsWithSheet;

    #[Locked]
    public ?int $transactionId = null;

    public bool $confirmingDelete = false;

    #[On('open-entry')]
    public function openEntry(int $id): void
    {
        Transaction::query()->findOrFail($id);

        $this->beginForm();
        $this->transactionId = $id;
        $this->confirmingDelete = false;
    }

    /**
     * Open the matching edit form on top of this sheet.
     */
    public function edit(): void
    {
        $entry = Transaction::query()->findOrFail($this->transactionId);

        $event = match ($entry->type->value) {
            'expense' => 'open-expense',
            'income' => 'open-income',
            'transfer' => 'open-move-money',
            default => null,
        };

        if ($event === null) {
            return;
        }

        $this->open = false;
        $this->dispatch($event, id: $entry->getKey());
    }

    /**
     * Delete needs a second tap; there is no system pop-up.
     */
    public function delete(LedgerService $ledger): void
    {
        if (! $this->confirmingDelete) {
            $this->confirmingDelete = true;

            return;
        }

        $deleted = $this->attempt(fn () => $ledger->delete($this->user(), Transaction::query()->findOrFail($this->transactionId)));

        if ($deleted) {
            $this->finish('Entry deleted.');
        } else {
            $this->confirmingDelete = false;
        }
    }

    public function render()
    {
        $entry = $this->open && $this->transactionId
            ? Transaction::query()->with(['account', 'toAccount', 'category', 'incomeSource', 'due', 'postings', 'movements'])->find($this->transactionId)
            : null;

        return view('livewire.sheets.entry-details', ['entry' => $entry]);
    }
}

<?php

namespace App\Livewire\Sheets;

use App\Enums\DueDirection;
use App\Livewire\Concerns\InteractsWithSheet;
use App\Services\Wallet\DueService;
use App\Support\Money;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class LendBorrowForm extends Component
{
    use InteractsWithSheet;

    #[Locked]
    public string $direction = 'lent';

    public string $amount = '';

    public string $person = '';

    public ?int $accountId = null;

    public string $occurredAt = '';

    public string $dueDate = '';

    public string $note = '';

    protected array $errorMap = ['account_id' => 'accountId'];

    #[On('open-lend-borrow')]
    public function openForm(string $direction = 'lent'): void
    {
        $this->beginForm();
        $this->direction = $direction === 'borrowed' ? 'borrowed' : 'lent';
        $this->amount = '';
        $this->person = '';
        $this->dueDate = '';
        $this->note = '';
        $this->accountId = $this->accountOptions()->first()['account']->getKey() ?? null;
        $this->occurredAt = $this->nowInput();
    }

    public function save(DueService $dues): void
    {
        $data = [
            'person' => $this->person,
            'amount' => Money::parse($this->amount) ?? 0,
            'account_id' => $this->accountId,
            'due_date' => $this->dueDate,
            'note' => $this->note,
            'occurred_at' => $this->occurredAt,
        ];
        $lent = $this->direction === DueDirection::Lent->value;

        $saved = $this->attempt(function () use ($dues, $data, $lent): void {
            $lent ? $dues->lend($this->user(), $data, $this->formKey) : $dues->borrow($this->user(), $data, $this->formKey);
        });

        if ($saved) {
            $this->finish($lent ? 'Lend saved.' : 'Borrow saved.');
        }
    }

    public function render()
    {
        return view('livewire.sheets.lend-borrow-form', ['accounts' => $this->open ? $this->accountOptions() : collect()]);
    }
}

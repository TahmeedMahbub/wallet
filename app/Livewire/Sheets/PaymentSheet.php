<?php

namespace App\Livewire\Sheets;

use App\Enums\DueDirection;
use App\Livewire\Concerns\InteractsWithSheet;
use App\Models\Account;
use App\Models\Due;
use App\Services\Wallet\DueService;
use App\Support\Money;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class PaymentSheet extends Component
{
    use InteractsWithSheet;

    #[Locked]
    public ?int $dueId = null;

    public string $amount = '';

    public ?int $accountId = null;

    public string $occurredAt = '';

    #[On('open-payment')]
    public function openSheet(int $dueId): void
    {
        $due = Due::query()->findOrFail($dueId);

        $this->beginForm();
        $this->dueId = $due->getKey();
        $this->amount = Money::toGroupedInput(app(DueService::class)->remaining($due));
        $this->accountId = $this->accountOptions()->first()['account']->getKey() ?? null;
        $this->occurredAt = $this->nowInput();
    }

    public function save(DueService $dues): void
    {
        $due = Due::query()->findOrFail($this->dueId);
        $account = Account::query()->find($this->accountId) ?? new Account(['id' => 0]);
        $amount = Money::parse($this->amount) ?? 0;

        $saved = $this->attempt(fn () => $dues->recordPayment($this->user(), $due, $amount, $account, $this->occurredAt, $this->formKey));

        if ($saved) {
            $remaining = $dues->remaining($due);
            $this->finish($remaining === 0 ? 'Settled. This record stays in your history.' : 'Payment recorded. '.Money::format($remaining).' left.');
        }
    }

    public function render()
    {
        $due = $this->open && $this->dueId ? Due::query()->find($this->dueId) : null;

        return view('livewire.sheets.payment-sheet', [
            'due' => $due,
            'remaining' => $due ? app(DueService::class)->remaining($due) : 0,
            'lent' => $due?->direction === DueDirection::Lent,
            'accounts' => $this->open ? $this->accountOptions() : collect(),
        ]);
    }
}

<?php

namespace App\Livewire\Sheets;

use App\Enums\TransactionType;
use App\Livewire\Concerns\InteractsWithSheet;
use App\Models\Transaction;
use App\Services\Wallet\LedgerService;
use App\Support\Money;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class MoveMoneyForm extends Component
{
    use InteractsWithSheet;

    #[Locked]
    public ?int $transactionId = null;

    public string $amount = '';

    public ?int $fromAccountId = null;

    public ?int $toAccountId = null;

    public string $occurredAt = '';

    protected array $errorMap = ['from_account_id' => 'fromAccountId', 'to_account_id' => 'toAccountId'];

    #[On('open-move-money')]
    public function openForm(?int $id = null): void
    {
        $this->beginForm();
        $this->transactionId = null;
        $accounts = $this->accountOptions();

        if ($id !== null) {
            $entry = Transaction::query()->where('type', TransactionType::Transfer)->findOrFail($id);
            $this->transactionId = $entry->getKey();
            $this->amount = Money::toGroupedInput($entry->amount);
            $this->fromAccountId = $entry->account_id;
            $this->toAccountId = $entry->to_account_id;
            $this->occurredAt = $entry->occurred_at->setTimezone($this->user()->timezone)->format('Y-m-d\TH:i');

            return;
        }

        $this->amount = '';
        $this->fromAccountId = $accounts->first()['account']->getKey() ?? null;
        $this->toAccountId = $accounts->skip(1)->first()['account']->getKey() ?? null;
        $this->occurredAt = $this->nowInput();
    }

    public function save(LedgerService $ledger): void
    {
        $data = [
            'amount' => Money::parse($this->amount) ?? 0,
            'from_account_id' => $this->fromAccountId,
            'to_account_id' => $this->toAccountId,
            'occurred_at' => $this->occurredAt,
        ];

        $saved = $this->attempt(function () use ($ledger, $data): void {
            $this->transactionId
                ? $ledger->update($this->user(), Transaction::query()->findOrFail($this->transactionId), $data)
                : $ledger->transfer($this->user(), $data, $this->formKey);
        });

        if ($saved) {
            $this->finish($this->transactionId ? 'Entry updated.' : 'Money moved.');
        }
    }

    public function render()
    {
        return view('livewire.sheets.move-money-form', ['accounts' => $this->open ? $this->accountOptions() : collect()]);
    }
}

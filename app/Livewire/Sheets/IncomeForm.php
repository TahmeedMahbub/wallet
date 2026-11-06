<?php

namespace App\Livewire\Sheets;

use App\Enums\TransactionType;
use App\Livewire\Concerns\InteractsWithSheet;
use App\Models\IncomeSource;
use App\Models\Transaction;
use App\Services\Wallet\LedgerService;
use App\Services\Wallet\SavingsService;
use App\Support\Money;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class IncomeForm extends Component
{
    use InteractsWithSheet;

    #[Locked]
    public ?int $transactionId = null;

    public string $amount = '';

    public ?int $sourceId = null;

    public ?int $accountId = null;

    public string $occurredAt = '';

    public string $note = '';

    public bool $addingSource = false;

    public string $newSource = '';

    protected array $errorMap = ['income_source_id' => 'sourceId', 'account_id' => 'accountId'];

    #[On('open-income')]
    public function openForm(?int $id = null): void
    {
        $this->beginForm();
        $this->reset('addingSource', 'newSource');
        $this->transactionId = null;

        if ($id !== null) {
            $entry = Transaction::query()->where('type', TransactionType::Income)->findOrFail($id);
            $this->transactionId = $entry->getKey();
            $this->amount = Money::toGroupedInput($entry->amount);
            $this->sourceId = $entry->income_source_id;
            $this->accountId = $entry->account_id;
            $this->note = (string) $entry->note;
            $this->occurredAt = $entry->occurred_at->setTimezone($this->user()->timezone)->format('Y-m-d\TH:i');

            return;
        }

        $this->amount = '';
        $this->note = '';
        $this->sourceId = IncomeSource::query()->orderBy('position')->value('id');
        $this->accountId = $this->accountOptions()->first()['account']->getKey() ?? null;
        $this->occurredAt = $this->nowInput();
    }

    public function addSource(): void
    {
        $name = trim($this->newSource);

        if ($name === '') {
            $this->addError('newSource', 'Enter a source name.');

            return;
        }

        if (IncomeSource::query()->where('name', $name)->exists()) {
            $this->addError('newSource', 'You already have a source with this name.');

            return;
        }

        $source = IncomeSource::create(['name' => mb_substr($name, 0, 120), 'is_salary' => false, 'position' => (int) IncomeSource::query()->max('position') + 1]);
        $this->sourceId = $source->getKey();
        $this->reset('addingSource', 'newSource');
        $this->resetErrorBag();
    }

    public function cancelSource(): void
    {
        $this->reset('addingSource', 'newSource');
        $this->resetErrorBag('newSource');
    }

    public function save(LedgerService $ledger): void
    {
        $data = [
            'amount' => Money::parse($this->amount) ?? 0,
            'income_source_id' => $this->sourceId,
            'account_id' => $this->accountId,
            'note' => $this->note,
            'occurred_at' => $this->occurredAt,
        ];

        $saved = $this->attempt(function () use ($ledger, $data, &$result): void {
            $result = $this->transactionId
                ? $ledger->update($this->user(), Transaction::query()->findOrFail($this->transactionId), $data)
                : $ledger->income($this->user(), $data, $this->formKey);
        });

        if (! $saved) {
            return;
        }

        $message = $this->transactionId ? 'Entry updated.' : 'Income saved.';

        if (($result['auto_save'] ?? null) === 'ran') {
            $total = (int) Transaction::query()->where('parent_transaction_id', $result['transaction']->getKey())->sum('amount');
            $message .= ' '.Money::format($total).' auto-saved to your goals.';
        } elseif (($result['auto_save'] ?? null) === 'skipped') {
            $message .= ' Auto-save could not run: the account does not have enough money. Add money to your goals by hand.';
        }

        $this->finish($message);
    }

    public function render()
    {
        $sources = $this->open ? IncomeSource::query()->orderBy('position')->orderBy('id')->get() : collect();
        $isSalary = $this->open && $this->sourceId !== null && (bool) $sources->firstWhere('id', $this->sourceId)?->is_salary;

        return view('livewire.sheets.income-form', [
            'sources' => $sources,
            'accounts' => $this->open ? $this->accountOptions() : collect(),
            'autoSaveTotal' => $isSalary && ! $this->transactionId ? app(SavingsService::class)->nextAutoSaveTotal() : 0,
        ]);
    }
}

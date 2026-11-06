<?php

namespace App\Livewire\Sheets;

use App\Enums\TransactionType;
use App\Livewire\Concerns\InteractsWithSheet;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\Wallet\Data\BudgetRow;
use App\Services\Wallet\LedgerService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class ExpenseForm extends Component
{
    use InteractsWithSheet;

    #[Locked]
    public ?int $transactionId = null;

    public string $amount = '';

    public ?int $categoryId = null;

    public ?int $accountId = null;

    public string $area = '';

    public string $reason = '';

    public string $occurredAt = '';

    public bool $addingCategory = false;

    public string $newCategory = '';

    protected array $errorMap = ['category_id' => 'categoryId', 'account_id' => 'accountId'];

    #[On('open-expense')]
    public function openForm(?int $id = null): void
    {
        $this->beginForm();
        $this->reset('addingCategory', 'newCategory');
        $this->transactionId = null;

        if ($id !== null) {
            $entry = Transaction::query()->where('type', TransactionType::Expense)->findOrFail($id);
            $this->transactionId = $entry->getKey();
            $this->amount = Money::toGroupedInput($entry->amount);
            $this->categoryId = $entry->category_id;
            $this->accountId = $entry->account_id;
            $this->area = (string) $entry->area;
            $this->reason = (string) $entry->reason;
            $this->occurredAt = $entry->occurred_at->setTimezone($this->user()->timezone)->format('Y-m-d\TH:i');

            return;
        }

        $this->amount = '';
        $this->area = '';
        $this->reason = '';
        $this->categoryId = Category::query()->orderBy('position')->value('id');
        $this->accountId = $this->accountOptions()->first()['account']->getKey() ?? null;
        $this->occurredAt = $this->nowInput();
    }

    /**
     * Create the extra category from the second sheet and select it.
     */
    public function addCategory(): void
    {
        $name = trim($this->newCategory);

        if ($name === '') {
            $this->addError('newCategory', 'Enter a category name.');

            return;
        }

        if (Category::query()->where('name', $name)->exists()) {
            $this->addError('newCategory', 'You already have a category with this name.');

            return;
        }

        $category = Category::create(['name' => mb_substr($name, 0, 120), 'position' => (int) Category::query()->max('position') + 1]);
        $this->categoryId = $category->getKey();
        $this->reset('addingCategory', 'newCategory');
        $this->resetErrorBag();
    }

    public function cancelCategory(): void
    {
        $this->reset('addingCategory', 'newCategory');
        $this->resetErrorBag('newCategory');
    }

    public function save(LedgerService $ledger): void
    {
        $data = [
            'amount' => Money::parse($this->amount) ?? 0,
            'category_id' => $this->categoryId,
            'account_id' => $this->accountId,
            'area' => $this->area,
            'reason' => $this->reason,
            'occurred_at' => $this->occurredAt,
        ];

        $saved = $this->attempt(function () use ($ledger, $data, &$result): void {
            $result = $this->transactionId
                ? $ledger->update($this->user(), Transaction::query()->findOrFail($this->transactionId), $data)
                : $ledger->expense($this->user(), $data, $this->formKey);
        });

        if (! $saved) {
            return;
        }

        $budget = $result['budget'];
        $message = $this->transactionId ? 'Entry updated.' : 'Expense saved.';

        if ($budget !== null && $budget->state() === BudgetRow::Warning) {
            $message .= ' '.$budget->name.' has used '.$budget->percent().'% of its budget. '.Money::format($budget->left()).' left.';
        } elseif ($budget !== null && $budget->state() === BudgetRow::Over) {
            $message .= ' '.$budget->name.' is over its budget by '.Money::format(abs($budget->left())).'.';
        }

        $this->finish($message);
    }

    /**
     * @return Collection<int, Category>
     */
    public function categories(): Collection
    {
        return Category::query()->orderBy('position')->orderBy('id')->get();
    }

    public function render()
    {
        return view('livewire.sheets.expense-form', [
            'categories' => $this->open ? $this->categories() : collect(),
            'accounts' => $this->open ? $this->accountOptions() : collect(),
        ]);
    }
}

<?php

namespace App\Livewire\Sheets;

use App\Enums\AccountType;
use App\Livewire\Concerns\InteractsWithSheet;
use App\Models\Account;
use App\Services\Wallet\AccountService;
use App\Services\Wallet\BalanceService;
use App\Support\Money;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class AccountSheet extends Component
{
    use InteractsWithSheet;

    #[Locked]
    public ?int $accountId = null;

    public string $name = '';

    public string $type = 'bank';

    public string $balance = '';

    public bool $confirmingDelete = false;

    /**
     * Add an account, or manage an existing one when an id is given.
     */
    #[On('open-account')]
    public function openSheet(?int $id = null): void
    {
        $this->beginForm();
        $this->accountId = null;
        $this->confirmingDelete = false;
        $this->name = '';
        $this->type = AccountType::Bank->value;
        $this->balance = '';

        if ($id !== null) {
            $account = Account::query()->findOrFail($id);
            $this->accountId = $account->getKey();
            $this->name = $account->name;
            $this->type = $account->type->value;
            $this->balance = Money::toGroupedInput(app(BalanceService::class)->of($account->getKey()));
        }
    }

    public function save(AccountService $accounts): void
    {
        $type = AccountType::tryFrom($this->type) ?? AccountType::Bank;
        $balance = Money::parse($this->balance) ?? 0;

        $saved = $this->attempt(function () use ($accounts, $type, $balance, &$account): void {
            $account = $this->accountId
                ? $accounts->update($this->user(), Account::query()->findOrFail($this->accountId), $this->name, $type, $balance)
                : $accounts->create($this->user(), $this->name, $type, $balance, $this->formKey);
        });

        if (! $saved) {
            return;
        }

        $this->open = false;
        $this->dispatch('wallet-updated');
        $this->dispatch('account-created', id: $account->getKey());
        $this->dispatch('toast', message: $this->accountId ? 'Account updated.' : 'Account added.');
    }

    /**
     * Delete needs a second tap. Past entries of the account stay in the history.
     */
    public function delete(AccountService $accounts): void
    {
        if (! $this->confirmingDelete) {
            $this->confirmingDelete = true;

            return;
        }

        $deleted = $this->attempt(fn () => $accounts->delete($this->user(), Account::query()->findOrFail($this->accountId)));

        if ($deleted) {
            $this->finish('Account deleted.');
        } else {
            $this->confirmingDelete = false;
        }
    }

    public function render()
    {
        return view('livewire.sheets.account-sheet', ['types' => AccountType::cases()]);
    }
}

<?php

namespace App\Livewire\Concerns;

use App\Exceptions\DuplicateSubmission;
use App\Models\Account;
use App\Models\User;
use App\Services\Wallet\BalanceService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shared behaviour of the bottom-sheet forms: open/close state, a one-time form
 * key (so a double tap saves once), error mapping and the success toast.
 */
trait InteractsWithSheet
{
    public bool $open = false;

    public string $formKey = '';

    protected function beginForm(): void
    {
        $this->resetErrorBag();
        $this->formKey = (string) Str::uuid();
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->resetErrorBag();
    }

    protected function user(): User
    {
        return auth()->user();
    }

    /**
     * Close, refresh every screen that shows money, and confirm with a toast.
     */
    protected function finish(string $message): void
    {
        $this->open = false;
        $this->dispatch('wallet-updated');
        $this->dispatch('toast', message: $message);
    }

    /**
     * Run a money action. Returns whether it succeeded. A repeated submit is
     * ignored; a "not enough money" refusal is shown as a toast with the form
     * left open; every other problem is shown under its field.
     */
    protected function attempt(Closure $work): bool
    {
        try {
            $work();

            return true;
        } catch (DuplicateSubmission) {
            $this->open = false;

            return false;
        } catch (ValidationException $exception) {
            $this->resetErrorBag();

            foreach ($exception->errors() as $field => $messages) {
                $message = $messages[0];

                if ($field === 'amount' && str_starts_with($message, 'Not enough money')) {
                    $this->dispatch('toast', message: $message);

                    continue;
                }

                $this->addError((property_exists($this, 'errorMap') ? $this->errorMap[$field] ?? null : null) ?? $field, $message);
            }

            return false;
        }
    }

    /**
     * The user's accounts with current balances, for the account chips.
     *
     * @return Collection<int, array{account: Account, balance: int}>
     */
    protected function accountOptions(): Collection
    {
        $balances = app(BalanceService::class)->all();

        return Account::query()->orderBy('id')->get()->map(fn (Account $account): array => [
            'account' => $account,
            'balance' => (int) ($balances[$account->getKey()] ?? 0),
        ]);
    }

    /**
     * Now in the user's timezone, in the format a datetime-local field expects.
     */
    protected function nowInput(): string
    {
        return CarbonImmutable::now($this->user()->timezone)->format('Y-m-d\TH:i');
    }
}

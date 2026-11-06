<?php

namespace App\Services\Wallet;

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AccountService
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly PostingWriter $writer,
        private readonly BalanceService $balances,
    ) {}

    /**
     * Add an account. An opening balance is money the user already has, so it is
     * recorded as a balance change, never as income.
     *
     * @throws ValidationException
     */
    public function create(User $user, string $name, AccountType $type, int $openingBalance = 0, ?string $idempotencyKey = null): Account
    {
        return $this->unitOfWork->run($user, function () use ($name, $type, $openingBalance): Account {
            $name = $this->cleanName($name);
            $this->assertNameIsFree($name);

            $account = Account::create(['name' => $name, 'type' => $type]);

            if ($openingBalance > 0) {
                $this->writer->create(TransactionType::BalanceAdjustment, $openingBalance, now(), [
                    'account_id' => $account->getKey(),
                    'note' => 'Opening balance',
                ], [$account->getKey() => $openingBalance]);
            }

            return $account;
        }, $idempotencyKey);
    }

    /**
     * Rename an account, change its type and/or set its balance to what is really in it.
     * The balance change is recorded as an adjustment, not as income or expense.
     *
     * @throws ValidationException
     */
    public function update(User $user, Account $account, string $name, AccountType $type, ?int $newBalance = null): Account
    {
        return $this->unitOfWork->run($user, function () use ($account, $name, $type, $newBalance): Account {
            $account = Account::query()->findOrFail($account->getKey());
            $name = $this->cleanName($name);
            $this->assertNameIsFree($name, $account);

            $account->update(['name' => $name, 'type' => $type]);

            if ($newBalance !== null) {
                $difference = $newBalance - $this->balances->of($account->getKey());

                if ($difference !== 0) {
                    $this->writer->create(TransactionType::BalanceAdjustment, abs($difference), now(), [
                        'account_id' => $account->getKey(),
                        'note' => 'Balance set',
                    ], [$account->getKey() => $difference]);
                }
            }

            return $account->refresh();
        });
    }

    /**
     * Delete an account. Its past entries stay in the history. Blocked while it
     * still holds money (the total would silently change) and for the last account.
     *
     * @throws ValidationException
     */
    public function delete(User $user, Account $account): void
    {
        $this->unitOfWork->run($user, function () use ($account): void {
            $account = Account::query()->findOrFail($account->getKey());

            if ($this->balances->of($account->getKey()) !== 0) {
                throw ValidationException::withMessages([
                    'delete' => 'Money would disappear from totals, so delete is blocked until the balance is ৳0.',
                ]);
            }

            if (Account::query()->count() <= 1) {
                throw ValidationException::withMessages(['delete' => 'You need at least one account.']);
            }

            $account->delete();
        });
    }

    private function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Enter an account name.']);
        }

        if (mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'Keep the name under 120 characters.']);
        }

        return $name;
    }

    private function assertNameIsFree(string $name, ?Account $except = null): void
    {
        $taken = Account::query()
            ->where('name', $name)
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['name' => 'You already have an account with this name.']);
        }
    }
}

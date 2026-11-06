<?php

namespace Tests\Concerns;

use App\Models\Account;
use App\Models\Category;
use App\Models\IncomeSource;
use App\Models\User;
use App\Services\Wallet\AccountService;
use App\Services\Wallet\BalanceService;
use App\Services\Wallet\DefaultData;

/**
 * Helpers for tests of the money features. Use together with DatabaseTransactions.
 */
trait WalletTestHelpers
{
    /**
     * A signed-in user with the default categories, sources, goals lists and three accounts at ৳0.
     */
    protected function walletUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        app(DefaultData::class)->seed($user);
        $this->actingAs($user);

        return $user;
    }

    protected function account(string $name): Account
    {
        return Account::query()->where('name', $name)->firstOrFail();
    }

    protected function category(string $name): Category
    {
        return Category::query()->where('name', $name)->firstOrFail();
    }

    protected function source(string $name): IncomeSource
    {
        return IncomeSource::query()->where('name', $name)->firstOrFail();
    }

    /**
     * Put money in an account through a balance change (not income), in taka.
     */
    protected function fund(User $user, string $accountName, int $taka): void
    {
        app(AccountService::class)->update(
            $user,
            $this->account($accountName),
            $accountName,
            $this->account($accountName)->type,
            $taka * 100,
        );
    }

    protected function balanceOf(string $accountName): int
    {
        return app(BalanceService::class)->of($this->account($accountName)->getKey());
    }
}

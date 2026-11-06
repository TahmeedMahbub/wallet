<?php

namespace App\Services\Backup;

use App\Models\User;
use App\Services\Wallet\DefaultData;
use App\Services\Wallet\UnitOfWork;
use Illuminate\Support\Facades\DB;

/**
 * Removes a user's financial data (and optionally the account itself). Deletes
 * run in dependency order, only for that user, and never touch other users.
 */
class UserDataEraser
{
    /**
     * Tables holding a user's money data, children before parents.
     *
     * @var array<int, string>
     */
    private const TABLES = [
        'savings_movements', 'ledger_postings', 'transactions', 'dues', 'savings_goals', 'category_budgets',
        'saving_reasons', 'saving_places', 'categories', 'income_sources', 'accounts', 'idempotency_keys', 'data_restores',
    ];

    public function __construct(private readonly UnitOfWork $unitOfWork, private readonly DefaultData $defaults) {}

    /**
     * Delete all money data of the user inside the current transaction, soft-deleted rows included.
     */
    public function wipe(User $user): void
    {
        DB::table('transactions')->where('user_id', $user->getKey())->update(['parent_transaction_id' => null]);

        foreach (self::TABLES as $table) {
            DB::table($table)->where('user_id', $user->getKey())->delete();
        }
    }

    /**
     * "Start over": erase everything and give the user the starting lists and accounts again.
     */
    public function startOver(User $user): void
    {
        $this->unitOfWork->run($user, fn () => $this->wipe($user));
        $this->defaults->seed($user);
    }

    /**
     * Delete the account and all its data, and sign it out everywhere.
     */
    public function deleteAccount(User $user): void
    {
        $this->unitOfWork->run($user, function () use ($user): void {
            $this->wipe($user);

            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
            }

            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('users')->where('id', $user->getKey())->delete();
        });
    }
}

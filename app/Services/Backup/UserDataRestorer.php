<?php

namespace App\Services\Backup;

use App\Enums\AccountType;
use App\Enums\DueDirection;
use App\Enums\GoalKind;
use App\Enums\TransactionType;
use App\Exceptions\InvalidBackup;
use App\Models\Account;
use App\Models\Category;
use App\Models\CategoryBudget;
use App\Models\DataRestore;
use App\Models\Due;
use App\Models\IncomeSource;
use App\Models\LedgerPosting;
use App\Models\SavingPlace;
use App\Models\SavingReason;
use App\Models\SavingsGoal;
use App\Models\SavingsMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Wallet\UnitOfWork;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * Validates a backup file and replaces the user's data with it. Ids inside the
 * file are never trusted: every record is created again for the signed-in
 * user and relations are remapped, so a file can never touch another user's data.
 */
class UserDataRestorer
{
    public const MAX_BYTES = 10_485_760;

    public const MAX_TRANSACTIONS = 200_000;

    public function __construct(private readonly UnitOfWork $unitOfWork, private readonly UserDataEraser $eraser) {}

    /**
     * Check a backup file completely. Returns the data and a summary for the preview.
     *
     * @return array{data: array<string, mixed>, summary: array{exported_at: string|null, accounts: int, entries: int, goals: int, dues: int, wallet_balance: int}}
     *
     * @throws InvalidBackup
     */
    public function validate(string $json): array
    {
        if (strlen($json) > self::MAX_BYTES) {
            throw new InvalidBackup('The file is too large to restore.');
        }

        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidBackup('The file is not a valid Wallet backup.');
        }

        if (! is_array($data) || ($data['app'] ?? null) !== 'wallet') {
            throw new InvalidBackup('The file is not a Wallet backup.');
        }

        if (($data['schema'] ?? null) !== UserDataExporter::SCHEMA) {
            throw new InvalidBackup('This backup was made by a different version of Wallet.');
        }

        if (! is_string($data['checksum'] ?? null) || ! hash_equals(UserDataExporter::checksum($data), $data['checksum'])) {
            throw new InvalidBackup('The file was changed or is incomplete (totals do not match).');
        }

        foreach (['accounts', 'categories', 'income_sources', 'category_budgets', 'saving_reasons', 'saving_places', 'savings_goals', 'dues', 'transactions', 'ledger_postings', 'savings_movements'] as $table) {
            if (! is_array($data[$table] ?? null) || ! array_is_list($data[$table])) {
                throw new InvalidBackup("The backup is missing its $table list.");
            }
        }

        if (count($data['transactions']) > self::MAX_TRANSACTIONS) {
            throw new InvalidBackup('The backup has too many entries.');
        }

        $this->checkRecords($data);

        $balances = [];
        foreach ($data['ledger_postings'] as $posting) {
            $balances[$posting['account_id']] = ($balances[$posting['account_id']] ?? 0) + $posting['delta'];
        }

        $deleted = collect($data['accounts'])->filter(fn (array $account): bool => $account['deleted_at'] !== null)->pluck('id')->all();

        foreach ($balances as $accountId => $balance) {
            if ($balance < 0) {
                throw new InvalidBackup('An account in the backup would end below zero (totals do not match).');
            }
        }

        return [
            'data' => $data,
            'summary' => [
                'exported_at' => $data['exported_at'] ?? null,
                'accounts' => count($data['accounts']) - count($deleted),
                'entries' => count($data['transactions']),
                'goals' => count($data['savings_goals']),
                'dues' => count($data['dues']),
                'wallet_balance' => (int) collect($balances)->except($deleted)->sum(),
            ],
        ];
    }

    /**
     * Replace everything the user has with the contents of a validated backup.
     *
     * @param  array<string, mixed>  $data  from validate()
     */
    public function restore(User $user, array $data, int $sizeBytes = 0): void
    {
        $this->unitOfWork->run($user, function () use ($user, $data, $sizeBytes): void {
            $this->eraser->wipe($user);

            $maps = [];
            $now = now();

            $copy = function (string $key, string $model, callable $attributes) use (&$maps, $data): void {
                foreach ($data[$key] as $row) {
                    $created = $model::create($attributes($row, $maps));

                    if (isset($row['id'])) {
                        $maps[$key][$row['id']] = $created->getKey();
                    }
                }
            };

            $copy('accounts', Account::class, fn (array $r): array => ['name' => $r['name'], 'type' => AccountType::from($r['type']), 'deleted_at' => $r['deleted_at']]);
            $copy('categories', Category::class, fn (array $r): array => ['name' => $r['name'], 'position' => (int) $r['position'], 'deleted_at' => $r['deleted_at']]);
            $copy('income_sources', IncomeSource::class, fn (array $r): array => ['name' => $r['name'], 'is_salary' => (bool) $r['is_salary'], 'position' => (int) $r['position'], 'deleted_at' => $r['deleted_at']]);
            $copy('saving_reasons', SavingReason::class, fn (array $r): array => ['name' => $r['name'], 'position' => (int) $r['position']]);
            $copy('saving_places', SavingPlace::class, fn (array $r): array => ['name' => $r['name'], 'position' => (int) $r['position']]);
            $copy('category_budgets', CategoryBudget::class, fn (array $r, array $m): array => ['category_id' => $m['categories'][$r['category_id']], 'monthly_limit' => (int) $r['monthly_limit']]);
            $copy('savings_goals', SavingsGoal::class, fn (array $r, array $m): array => [
                'reason_id' => $m['saving_reasons'][$r['reason_id']], 'place_id' => $m['saving_places'][$r['place_id']],
                'kind' => GoalKind::from($r['kind']), 'target_amount' => (int) $r['target_amount'], 'months' => $r['months'],
                'monthly_amount' => (int) $r['monthly_amount'], 'auto_save' => (bool) $r['auto_save'],
            ]);
            $copy('dues', Due::class, fn (array $r): array => [
                'direction' => DueDirection::from($r['direction']), 'person' => $r['person'], 'amount' => (int) $r['amount'],
                'note' => $r['note'], 'due_date' => $r['due_date'],
            ]);

            $parents = [];
            $copy('transactions', Transaction::class, function (array $r, array $m) use (&$parents): array {
                $parents[$r['id']] = $r['parent_transaction_id'];

                return [
                    'type' => TransactionType::from($r['type']), 'amount' => (int) $r['amount'], 'occurred_at' => $r['occurred_at'],
                    'account_id' => $r['account_id'] === null ? null : $m['accounts'][$r['account_id']],
                    'to_account_id' => $r['to_account_id'] === null ? null : $m['accounts'][$r['to_account_id']],
                    'category_id' => $r['category_id'] === null ? null : $m['categories'][$r['category_id']],
                    'income_source_id' => $r['income_source_id'] === null ? null : $m['income_sources'][$r['income_source_id']],
                    'due_id' => $r['due_id'] === null ? null : $m['dues'][$r['due_id']],
                    'area' => $r['area'], 'reason' => $r['reason'], 'note' => $r['note'], 'auto_save_status' => $r['auto_save_status'],
                ];
            });

            foreach ($parents as $oldId => $oldParent) {
                if ($oldParent !== null) {
                    Transaction::query()->whereKey($maps['transactions'][$oldId])->update(['parent_transaction_id' => $maps['transactions'][$oldParent]]);
                }
            }

            foreach ($data['ledger_postings'] as $row) {
                LedgerPosting::create(['transaction_id' => $maps['transactions'][$row['transaction_id']], 'account_id' => $maps['accounts'][$row['account_id']], 'delta' => (int) $row['delta']]);
            }

            foreach ($data['savings_movements'] as $row) {
                SavingsMovement::create(['goal_id' => $maps['savings_goals'][$row['goal_id']], 'transaction_id' => $maps['transactions'][$row['transaction_id']], 'amount' => (int) $row['amount']]);
            }

            DataRestore::create(['status' => 'restored', 'checksum' => $data['checksum'], 'size_bytes' => $sizeBytes, 'entries' => count($data['transactions']), 'created_at' => $now, 'updated_at' => $now]);
        });
    }

    /**
     * Every record must have the right shape and point at records that exist in the file.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidBackup
     */
    private function checkRecords(array $data): void
    {
        $ids = fn (string $table): array => array_flip(array_column($data[$table], 'id'));
        $accounts = $ids('accounts');
        $categories = $ids('categories');
        $sources = $ids('income_sources');
        $reasons = $ids('saving_reasons');
        $places = $ids('saving_places');
        $goals = $ids('savings_goals');
        $dues = $ids('dues');
        $transactions = $ids('transactions');

        $fail = fn (string $what) => throw new InvalidBackup("The backup has a problem: $what.");
        $money = fn (mixed $value): bool => is_int($value) && $value >= 0 && $value <= Money::MAX_POISHA;
        $text = fn (mixed $value, int $max = 255): bool => $value === null || (is_string($value) && mb_strlen($value) <= $max);

        foreach ($data['accounts'] as $row) {
            (is_int($row['id'] ?? null) && is_string($row['name'] ?? null) && $text($row['name'], 120) && AccountType::tryFrom((string) ($row['type'] ?? '')) !== null) || $fail('an account is invalid');
        }

        foreach (['categories', 'income_sources', 'saving_reasons', 'saving_places'] as $table) {
            foreach ($data[$table] as $row) {
                (is_int($row['id'] ?? null) && is_string($row['name'] ?? null) && $text($row['name'], 120)) || $fail("a record in $table is invalid");
            }
        }

        foreach ($data['category_budgets'] as $row) {
            (isset($categories[$row['category_id'] ?? null]) && $money($row['monthly_limit'] ?? null)) || $fail('a budget is invalid');
        }

        foreach ($data['savings_goals'] as $row) {
            (isset($reasons[$row['reason_id'] ?? null]) && isset($places[$row['place_id'] ?? null]) && GoalKind::tryFrom((string) ($row['kind'] ?? '')) !== null
                && $money($row['target_amount'] ?? null) && $money($row['monthly_amount'] ?? null)) || $fail('a savings goal is invalid');
        }

        foreach ($data['dues'] as $row) {
            (is_int($row['id'] ?? null) && DueDirection::tryFrom((string) ($row['direction'] ?? '')) !== null && is_string($row['person'] ?? null) && $text($row['person'], 120)
                && $money($row['amount'] ?? null) && $text($row['note'] ?? null)) || $fail('a lend or borrow record is invalid');
        }

        foreach ($data['transactions'] as $row) {
            (is_int($row['id'] ?? null) && TransactionType::tryFrom((string) ($row['type'] ?? '')) !== null && $money($row['amount'] ?? null)
                && is_string($row['occurred_at'] ?? null) && strtotime($row['occurred_at']) !== false
                && ($row['account_id'] === null || isset($accounts[$row['account_id']]))
                && ($row['to_account_id'] === null || isset($accounts[$row['to_account_id']]))
                && ($row['category_id'] === null || isset($categories[$row['category_id']]))
                && ($row['income_source_id'] === null || isset($sources[$row['income_source_id']]))
                && ($row['due_id'] === null || isset($dues[$row['due_id']]))
                && ($row['parent_transaction_id'] === null || isset($transactions[$row['parent_transaction_id']]))
                && $text($row['area'] ?? null) && $text($row['reason'] ?? null) && $text($row['note'] ?? null)) || $fail('an entry is invalid');
        }

        foreach ($data['ledger_postings'] as $row) {
            (isset($transactions[$row['transaction_id'] ?? null]) && isset($accounts[$row['account_id'] ?? null]) && is_int($row['delta'] ?? null) && abs($row['delta']) <= Money::MAX_POISHA) || $fail('a balance record is invalid');
        }

        foreach ($data['savings_movements'] as $row) {
            (isset($goals[$row['goal_id'] ?? null]) && isset($transactions[$row['transaction_id'] ?? null]) && is_int($row['amount'] ?? null) && abs($row['amount']) <= Money::MAX_POISHA) || $fail('a savings record is invalid');
        }
    }

    /**
     * Keep a short record that a restore was refused, without any financial values.
     */
    public function logFailure(User $user, string $message, int $sizeBytes): void
    {
        DB::table('data_restores')->insert([
            'user_id' => $user->getKey(),
            'status' => 'failed',
            'failure' => mb_substr($message, 0, 255),
            'size_bytes' => $sizeBytes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

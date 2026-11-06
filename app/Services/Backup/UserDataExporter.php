<?php

namespace App\Services\Backup;

use App\Models\Account;
use App\Models\Category;
use App\Models\CategoryBudget;
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
use RuntimeException;
use ZipArchive;

/**
 * Builds a user's own data as JSON (restorable) or CSV (readable). Only the
 * signed-in user's rows are read, through the ownership scope.
 */
class UserDataExporter
{
    public const SCHEMA = 1;

    public function __construct(private readonly UnitOfWork $unitOfWork) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $user): array
    {
        return $this->unitOfWork->run($user, function () use ($user): array {
            $data = [
                'app' => 'wallet',
                'schema' => self::SCHEMA,
                'exported_at' => now()->utc()->toIso8601String(),
                'user' => ['name' => $user->name, 'timezone' => $user->timezone],
                'accounts' => Account::withTrashed()->orderBy('id')->get(['id', 'name', 'type', 'deleted_at'])->toArray(),
                'categories' => Category::withTrashed()->orderBy('id')->get(['id', 'name', 'position', 'deleted_at'])->toArray(),
                'income_sources' => IncomeSource::withTrashed()->orderBy('id')->get(['id', 'name', 'is_salary', 'position', 'deleted_at'])->toArray(),
                'category_budgets' => CategoryBudget::query()->orderBy('id')->get(['category_id', 'monthly_limit'])->toArray(),
                'saving_reasons' => SavingReason::query()->orderBy('id')->get(['id', 'name', 'position'])->toArray(),
                'saving_places' => SavingPlace::query()->orderBy('id')->get(['id', 'name', 'position'])->toArray(),
                'savings_goals' => SavingsGoal::query()->orderBy('id')->get(['id', 'reason_id', 'place_id', 'kind', 'target_amount', 'months', 'monthly_amount', 'auto_save'])->toArray(),
                'dues' => Due::query()->orderBy('id')->get(['id', 'direction', 'person', 'amount', 'note', 'due_date'])->toArray(),
                'transactions' => Transaction::query()->orderBy('id')->get(['id', 'type', 'amount', 'occurred_at', 'account_id', 'to_account_id', 'category_id', 'income_source_id', 'due_id', 'parent_transaction_id', 'area', 'reason', 'note', 'auto_save_status'])->toArray(),
                'ledger_postings' => LedgerPosting::query()->orderBy('id')->get(['transaction_id', 'account_id', 'delta'])->toArray(),
                'savings_movements' => SavingsMovement::query()->orderBy('id')->get(['goal_id', 'transaction_id', 'amount'])->toArray(),
            ];

            $data = json_decode(json_encode($data, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            $data['checksum'] = self::checksum($data);

            return $data;
        });
    }

    public function json(User $user): string
    {
        return json_encode($this->toArray($user), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * A zip of readable CSV files. Returns the path of a temporary file to stream and delete.
     */
    public function csvZip(User $user): string
    {
        $data = $this->toArray($user);
        $path = tempnam(sys_get_temp_dir(), 'wallet-csv-');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Could not create the export file.');
        }

        $accounts = collect($data['accounts'])->pluck('name', 'id');
        $categories = collect($data['categories'])->pluck('name', 'id');
        $sources = collect($data['income_sources'])->pluck('name', 'id');
        $dues = collect($data['dues'])->keyBy('id');
        $postingSum = collect($data['ledger_postings'])->groupBy('transaction_id')->map(fn ($rows) => $rows->sum('delta'));

        $zip->addFromString('transactions.csv', $this->csv(
            ['Date (UTC)', 'Type', 'Amount (BDT)', 'Account', 'To account', 'Category', 'Income source', 'Person', 'Area', 'Reason', 'Note'],
            collect($data['transactions'])->map(fn (array $t): array => [
                $t['occurred_at'], $t['type'], number_format($t['amount'] / 100, 2, '.', ''),
                $accounts[$t['account_id']] ?? '', $accounts[$t['to_account_id']] ?? '', $categories[$t['category_id']] ?? '',
                $sources[$t['income_source_id']] ?? '', $dues[$t['due_id']]['person'] ?? '', $t['area'], $t['reason'], $t['note'],
            ])->all(),
        ));
        $zip->addFromString('accounts.csv', $this->csv(['Name', 'Type', 'Balance (BDT)', 'Deleted'], collect($data['accounts'])->map(fn (array $a): array => [
            $a['name'], $a['type'],
            number_format(collect($data['ledger_postings'])->where('account_id', $a['id'])->sum('delta') / 100, 2, '.', ''),
            $a['deleted_at'] ? 'yes' : '',
        ])->all()));
        $zip->addFromString('category_budgets.csv', $this->csv(['Category', 'Monthly limit (BDT)'], collect($data['category_budgets'])->map(fn (array $b): array => [
            $categories[$b['category_id']] ?? '', number_format($b['monthly_limit'] / 100, 2, '.', ''),
        ])->all()));
        $zip->addFromString('savings_goals.csv', $this->csv(['Reason', 'Kind', 'Target (BDT)', 'Months', 'Monthly (BDT)', 'Auto-save'], collect($data['savings_goals'])->map(fn (array $g): array => [
            collect($data['saving_reasons'])->firstWhere('id', $g['reason_id'])['name'] ?? '', $g['kind'], number_format($g['target_amount'] / 100, 2, '.', ''),
            $g['months'], number_format($g['monthly_amount'] / 100, 2, '.', ''), $g['auto_save'] ? 'yes' : 'no',
        ])->all()));
        $zip->addFromString('dues.csv', $this->csv(['Direction', 'Person', 'Amount (BDT)', 'Return by', 'Note'], collect($data['dues'])->map(fn (array $d): array => [
            $d['direction'], $d['person'], number_format($d['amount'] / 100, 2, '.', ''), $d['due_date'], $d['note'],
        ])->all()));
        $zip->close();

        unset($postingSum);

        return $path;
    }

    /**
     * Checksum of the data without its own checksum field, so tampering or truncation is noticed.
     *
     * @param  array<string, mixed>  $data
     */
    public static function checksum(array $data): string
    {
        unset($data['checksum']);

        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function csv(array $header, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $header);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($cell) => $this->safeCell($cell), $row));
        }

        rewind($handle);
        $out = (string) stream_get_contents($handle);
        fclose($handle);

        return $out;
    }

    /**
     * Neutralise spreadsheet formula injection: cells starting with = + - @ are prefixed with a quote.
     */
    private function safeCell(mixed $cell): mixed
    {
        if (is_string($cell) && $cell !== '' && in_array($cell[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$cell;
        }

        return $cell;
    }
}

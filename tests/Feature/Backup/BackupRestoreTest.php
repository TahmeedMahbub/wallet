<?php

namespace Tests\Feature\Backup;

use App\Enums\DueDirection;
use App\Enums\GoalKind;
use App\Exceptions\InvalidBackup;
use App\Livewire\Sheets\BackupSheet;
use App\Models\Account;
use App\Models\DataRestore;
use App\Models\Due;
use App\Models\SavingPlace;
use App\Models\SavingReason;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Backup\UserDataEraser;
use App\Services\Backup\UserDataExporter;
use App\Services\Backup\UserDataRestorer;
use App\Services\Wallet\BalanceService;
use App\Services\Wallet\BudgetService;
use App\Services\Wallet\DueService;
use App\Services\Wallet\LedgerService;
use App\Services\Wallet\SavingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\WalletTestHelpers;
use Tests\TestCase;
use ZipArchive;

class BackupRestoreTest extends TestCase
{
    use DatabaseTransactions, WalletTestHelpers;

    /**
     * A user with every kind of entry.
     */
    private function userWithEverything(array $attributes = []): User
    {
        $user = $this->walletUser($attributes);
        $ledger = app(LedgerService::class);
        $savings = app(SavingsService::class);
        $bank = $this->account('Bank')->getKey();

        $goal = $savings->createGoal($user, [
            'reason_id' => SavingReason::query()->where('name', 'Tour')->value('id'), 'place_id' => SavingPlace::query()->value('id'),
            'kind' => GoalKind::Monthly, 'target_amount' => 2_500_000, 'months' => 6, 'monthly_amount' => 420_000, 'auto_save' => true,
        ]);
        $ledger->income($user, ['amount' => 5_500_000, 'account_id' => $bank, 'income_source_id' => $this->source('Salary')->getKey(), 'note' => 'October']);
        $ledger->expense($user, ['amount' => 32_000, 'account_id' => $bank, 'category_id' => $this->category('Food & Groceries')->getKey(), 'reason' => 'Lunch, "team"', 'area' => 'Canteen']);
        $ledger->transfer($user, ['amount' => 200_000, 'from_account_id' => $bank, 'to_account_id' => $this->account('bKash')->getKey()]);
        $savings->deposit($user, $goal, $this->account('bKash'), 50_000);
        $due = app(DueService::class)->lend($user, ['person' => 'Rina (cousin)', 'amount' => 500_000, 'account_id' => $bank, 'due_date' => '2026-12-01', 'note' => 'Fee']);
        app(DueService::class)->recordPayment($user, $due, 100_000, $this->account('Cash'));
        app(BudgetService::class)->set($user, [$this->category('Food & Groceries')->getKey() => 800_000]);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(User $user): array
    {
        $this->actingAs($user);
        $balances = app(BalanceService::class)->all();

        return [
            'balances' => Account::query()->withTrashed()->orderBy('name')->get()->mapWithKeys(fn ($a) => [$a->name => (int) ($balances[$a->id] ?? 0)])->all(),
            'wallet' => app(BalanceService::class)->wallet(),
            'entries' => Transaction::query()->count(),
            'goals' => app(SavingsService::class)->total(),
            'owed' => app(DueService::class)->outstanding($user, DueDirection::Lent),
            'budgets' => app(BudgetService::class)->totalLimits(),
            'reasons' => Transaction::query()->orderBy('id')->pluck('reason')->filter()->values()->all(),
        ];
    }

    public function test_an_export_restores_to_exactly_the_same_state(): void
    {
        $user = $this->userWithEverything();
        $before = $this->snapshot($user);
        $json = app(UserDataExporter::class)->json($user);

        app(UserDataEraser::class)->startOver($user);
        $this->assertSame(0, $this->snapshot($user)['entries']);

        $validated = app(UserDataRestorer::class)->validate($json);
        app(UserDataRestorer::class)->restore($user, $validated['data'], strlen($json));

        $this->assertEquals($before, $this->snapshot($user));
        $this->assertSame(1, DataRestore::query()->where('status', 'restored')->count());
    }

    public function test_the_preview_summary_matches_the_backup(): void
    {
        $user = $this->userWithEverything();
        $json = app(UserDataExporter::class)->json($user);

        $summary = app(UserDataRestorer::class)->validate($json)['summary'];

        $this->assertSame(3, $summary['accounts']);
        $this->assertSame(Transaction::query()->count(), $summary['entries']);
        $this->assertSame(1, $summary['goals']);
        $this->assertSame(1, $summary['dues']);
        $this->assertSame(app(BalanceService::class)->wallet(), $summary['wallet_balance']);
    }

    public function test_the_export_contains_only_the_signed_in_users_data(): void
    {
        $alice = $this->userWithEverything(['name' => 'Alice']);
        $this->walletUser(['name' => 'Bob']);
        $bobJson = app(UserDataExporter::class)->json(auth()->user());

        $this->assertStringNotContainsString('Rina (cousin)', $bobJson);
        $this->assertStringNotContainsString('Lunch', $bobJson);
        $this->assertStringContainsString('Bob', $bobJson);
        $this->assertStringNotContainsString($alice->email, $bobJson);
        $this->assertStringNotContainsString('password', $bobJson);
    }

    /**
     * @return array<string, array{callable(array<string, mixed>): array<string, mixed>, string}>
     */
    public static function brokenBackups(): array
    {
        return [
            'changed amount' => [function (array $d) {
                $d['transactions'][0]['amount'] += 1;

                return $d;
            }, 'changed or is incomplete'],
            'removed list' => [function (array $d) {
                unset($d['dues']);

                return $d;
            }, 'changed or is incomplete'],
            'wrong app' => [function (array $d) {
                $d['app'] = 'other';

                return $d;
            }, 'not a Wallet backup'],
            'newer schema' => [function (array $d) {
                $d['schema'] = 99;

                return $d;
            }, 'different version'],
        ];
    }

    #[DataProvider('brokenBackups')]
    public function test_a_tampered_or_wrong_file_is_refused(callable $break, string $message): void
    {
        $user = $this->userWithEverything();
        $data = app(UserDataExporter::class)->toArray($user);
        $data = $break($data);

        $this->expectException(InvalidBackup::class);
        $this->expectExceptionMessageMatches('/'.preg_quote($message, '/').'/');

        app(UserDataRestorer::class)->validate(json_encode($data));
    }

    public function test_a_file_that_is_not_json_or_is_too_large_is_refused(): void
    {
        $restorer = app(UserDataRestorer::class);

        foreach (['not json at all', '[]', '"text"', str_repeat('a', UserDataRestorer::MAX_BYTES + 1)] as $bad) {
            try {
                $restorer->validate($bad);
                $this->fail('Should have been refused.');
            } catch (InvalidBackup) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * Re-seal a modified backup with a correct checksum, as a careful attacker would.
     *
     * @param  array<string, mixed>  $data
     */
    private function reseal(array $data): string
    {
        $data['checksum'] = UserDataExporter::checksum($data);

        return json_encode($data);
    }

    public function test_a_resealed_file_with_broken_references_or_negative_balances_is_still_refused(): void
    {
        $user = $this->userWithEverything();
        $good = app(UserDataExporter::class)->toArray($user);

        $dangling = $good;
        $dangling['ledger_postings'][0]['account_id'] = 999_999_999;
        $negative = $good;
        $negative['ledger_postings'][] = ['transaction_id' => $good['transactions'][0]['id'], 'account_id' => $good['accounts'][0]['id'], 'delta' => -9_000_000_000];
        $badType = $good;
        $badType['transactions'][0]['type'] = 'steal';
        $badAmount = $good;
        $badAmount['transactions'][0]['amount'] = -5;

        foreach ([$dangling, $negative, $badType, $badAmount] as $broken) {
            try {
                app(UserDataRestorer::class)->validate($this->reseal($broken));
                $this->fail('A broken backup was accepted.');
            } catch (InvalidBackup) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_ids_and_user_ids_inside_a_file_never_reach_another_users_data(): void
    {
        $alice = $this->userWithEverything(['name' => 'Alice']);
        $aliceBefore = $this->snapshot($alice);
        $aliceIds = Transaction::query()->pluck('id')->all();

        $bob = $this->walletUser(['name' => 'Bob']);
        $data = app(UserDataExporter::class)->toArray($alice);
        $data['user_id'] = $alice->getKey();
        foreach ($data['transactions'] as $i => $t) {
            $data['transactions'][$i]['user_id'] = $alice->getKey();
        }
        $json = $this->reseal($data);

        app(UserDataRestorer::class)->restore($bob, app(UserDataRestorer::class)->validate($json)['data']);

        $this->actingAs($bob);
        $this->assertSame(Transaction::query()->count(), count($aliceIds));
        $this->assertSame([], array_intersect(Transaction::query()->pluck('id')->all(), $aliceIds), 'restored rows got fresh ids');
        $this->assertSame($bob->getKey(), Transaction::query()->first()->user_id);
        $this->assertEquals($aliceBefore, $this->snapshot($alice), "Alice's data is untouched");
    }

    public function test_restoring_replaces_current_data_and_does_not_touch_other_users(): void
    {
        $other = $this->userWithEverything(['name' => 'Other']);
        $otherBefore = $this->snapshot($other);
        $user = $this->userWithEverything(['name' => 'Me']);
        $json = app(UserDataExporter::class)->json($user);
        app(LedgerService::class)->expense($user, ['amount' => 100, 'account_id' => $this->account('Bank')->getKey(), 'category_id' => $this->category('Rent')->getKey(), 'reason' => 'new junk']);

        app(UserDataRestorer::class)->restore($user, app(UserDataRestorer::class)->validate($json)['data']);

        $this->assertNotContains('new junk', $this->snapshot($user)['reasons']);
        $this->assertEquals($otherBefore, $this->snapshot($other));
    }

    public function test_the_csv_export_is_a_zip_of_readable_files_and_neutralises_formulas(): void
    {
        $user = $this->walletUser();
        app(LedgerService::class)->income($user, ['amount' => 100_000, 'account_id' => $this->account('Bank')->getKey(), 'income_source_id' => $this->source('Salami')->getKey(), 'note' => '=HYPERLINK("http://evil")']);

        $path = app(UserDataExporter::class)->csvZip($user);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $transactions = $zip->getFromName('transactions.csv');
        $zip->close();
        @unlink($path);

        $this->assertContains('transactions.csv', $names);
        $this->assertContains('accounts.csv', $names);
        $this->assertStringContainsString("'=HYPERLINK", $transactions);
        $this->assertStringNotContainsString(',=HYPERLINK', $transactions);
        $this->assertStringContainsString('1000.00', $transactions);
    }

    public function test_the_export_routes_download_for_the_signed_in_user_only(): void
    {
        $user = $this->userWithEverything();

        $json = $this->get('/export/json');
        $json->assertOk()->assertHeader('content-type', 'application/json')->assertDownload();
        $this->assertStringContainsString('Rina (cousin)', $json->streamedContent());
        $this->get('/export/csv')->assertOk()->assertDownload();

        auth()->logout();
        $this->get('/export/json')->assertRedirect('/login');
        $this->assertNotNull($user);
    }

    public function test_uploading_a_valid_backup_shows_a_preview_and_restore_needs_the_word_and_password(): void
    {
        $user = $this->userWithEverything(['password' => Hash::make('secret-password')]);
        $json = app(UserDataExporter::class)->json($user);
        $entries = Transaction::query()->count();
        app(UserDataEraser::class)->startOver($user);

        $sheet = Livewire::test(BackupSheet::class)->dispatch('open-backup')
            ->set('file', UploadedFile::fake()->createWithContent('wallet.json', $json))
            ->assertHasNoErrors()
            ->assertSee('RESTORE THIS BACKUP?')->assertSee((string) $entries)
            ->call('restore')->assertHasErrors('confirmText')
            ->set('confirmText', 'RESTORE')->set('password', 'wrong')->call('restore')->assertHasErrors('password');

        $this->assertSame(0, Transaction::query()->count(), 'nothing restored yet');

        $sheet->set('password', 'secret-password')->call('restore')->assertDispatched('toast', message: 'Backup restored.');

        $this->assertSame($entries, Transaction::query()->count());
    }

    public function test_uploading_a_broken_backup_is_refused_and_nothing_changes(): void
    {
        $user = $this->userWithEverything();
        $data = app(UserDataExporter::class)->toArray($user);
        $data['transactions'][0]['amount'] += 5;
        $before = Transaction::query()->count();

        Livewire::test(BackupSheet::class)->dispatch('open-backup')
            ->set('file', UploadedFile::fake()->createWithContent('wallet.json', json_encode($data)))
            ->assertHasErrors('file')->assertSet('preview', null)->assertDispatched('toast', message: 'Backup file is not valid.');

        $this->assertSame($before, Transaction::query()->count());
        $this->assertSame(1, DataRestore::query()->where('status', 'failed')->count());
    }

    public function test_erasing_everything_needs_two_taps_and_returns_to_day_one(): void
    {
        $user = $this->userWithEverything();

        $sheet = Livewire::test(BackupSheet::class)->dispatch('open-backup')->call('erase')->assertSet('confirmingErase', true);
        $this->assertGreaterThan(0, Transaction::query()->count());

        $sheet->call('erase')->assertDispatched('toast');

        $this->assertSame(0, Transaction::query()->count());
        $this->assertSame(0, Due::query()->count());
        $this->assertSame(3, Account::query()->count(), 'starting accounts are back');
        $this->assertSame(0, app(BalanceService::class)->wallet());
        $this->assertNotNull(User::query()->find($user->getKey()));
    }

    public function test_erasing_one_users_data_leaves_another_users_data_alone(): void
    {
        $other = $this->userWithEverything(['name' => 'Other']);
        $otherBefore = $this->snapshot($other);
        $me = $this->userWithEverything(['name' => 'Me']);

        app(UserDataEraser::class)->startOver($me);

        $this->assertEquals($otherBefore, $this->snapshot($other));
    }

    public function test_deleting_the_account_removes_the_user_and_all_their_data_after_a_password_check(): void
    {
        $other = $this->userWithEverything(['name' => 'Other']);
        $otherBefore = $this->snapshot($other);
        $me = $this->userWithEverything(['name' => 'Me', 'password' => Hash::make('secret-password')]);
        $id = $me->getKey();

        $sheet = Livewire::test(BackupSheet::class)->dispatch('open-backup')
            ->call('deleteAccount')->assertSet('confirmingDelete', true)
            ->set('password', 'nope')->call('deleteAccount')->assertHasErrors('password');
        $this->assertNotNull(User::query()->find($id));

        $sheet->set('password', 'secret-password')->call('deleteAccount')->assertRedirect(route('login'));

        $this->assertNull(User::query()->find($id));
        foreach (['accounts', 'transactions', 'ledger_postings', 'dues', 'savings_goals', 'categories', 'category_budgets'] as $table) {
            $this->assertSame(0, DB::table($table)->where('user_id', $id)->count(), "$table rows were removed");
        }
        $this->assertEquals($otherBefore, $this->snapshot($other));
    }
}

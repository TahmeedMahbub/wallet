<?php

namespace Tests\Feature\Production;

use App\Console\Commands\BackupDatabase;
use App\Models\User;
use App\Services\Wallet\LedgerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WalletTestHelpers;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use DatabaseTransactions, WalletTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_every_web_response_carries_the_security_headers(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
    }

    public function test_pages_of_a_signed_in_user_are_never_cached(): void
    {
        $this->walletUser();

        $cache = $this->get('/')->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cache);
        $this->assertStringContainsString('private', $cache);
    }

    public function test_hsts_is_sent_over_https(): void
    {
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
    }

    public function test_the_database_backup_command_never_puts_the_password_in_the_command(): void
    {
        $command = BackupDatabase::dumpCommand([
            'host' => 'db.internal', 'port' => '3306', 'username' => 'wallet_app', 'password' => 'super-secret-password', 'database' => 'wallet',
        ], '/tmp/out.sql');

        $this->assertSame('wallet', end($command));
        $this->assertContains('--user=wallet_app', $command);
        $this->assertContains('--single-transaction', $command);
        $this->assertStringNotContainsString('super-secret-password', implode(' ', $command));
        $this->assertSame([], array_filter($command, fn ($part) => str_starts_with($part, '--password')));
    }

    public function test_old_submission_keys_are_pruned(): void
    {
        $user = $this->walletUser();
        DB::table('idempotency_keys')->insert([
            ['user_id' => $user->getKey(), 'key' => 'old-key', 'created_at' => now()->subDays(5)],
            ['user_id' => $user->getKey(), 'key' => 'new-key', 'created_at' => now()],
        ]);

        Artisan::call('wallet:prune-keys');

        $this->assertSame(['new-key'], DB::table('idempotency_keys')->where('user_id', $user->getKey())->pluck('key')->all());
    }

    public function test_the_maintenance_jobs_are_scheduled(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        foreach (['wallet:backup-database', 'wallet:prune-keys', 'auth:clear-resets', 'queue:prune-failed'] as $command) {
            $this->assertStringContainsString($command, $output);
        }
    }

    public function test_the_application_is_configured_for_safe_cookies_and_utc_storage(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertSame('mariadb', config('database.default'));
    }

    public function test_the_home_page_uses_a_bounded_number_of_queries(): void
    {
        $user = $this->walletUser();
        $this->fund($user, 'Bank', 100_000);
        $ledger = app(LedgerService::class);
        foreach (range(1, 12) as $i) {
            $ledger->expense($user, ['amount' => 1_000 * $i, 'account_id' => $this->account('Bank')->getKey(), 'category_id' => $this->category('Rent')->getKey()]);
        }
        $ledger->income($user, ['amount' => 5_000_000, 'account_id' => $this->account('Bank')->getKey(), 'income_source_id' => $this->source('Salary')->getKey()]);

        DB::enableQueryLog();
        $this->get('/')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(70, $queries, "Home ran $queries queries");
    }

    public function test_other_users_data_never_leaks_into_the_home_page_html(): void
    {
        $alice = $this->walletUser(['name' => 'Alice Example']);
        $this->fund($alice, 'Bank', 7_777);
        app(LedgerService::class)->expense($alice, [
            'amount' => 12_345, 'account_id' => $this->account('Bank')->getKey(), 'category_id' => $this->category('Rent')->getKey(), 'reason' => 'alice-secret-reason',
        ]);

        $this->walletUser(['name' => 'Bob Example']);
        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('alice-secret-reason', $html);
        $this->assertStringNotContainsString('Alice Example', $html);
        $this->assertStringNotContainsString('7,777', $html);
        $this->assertNotNull(User::query()->where('name', 'Alice Example')->first());
    }
}

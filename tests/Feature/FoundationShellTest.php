<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class FoundationShellTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_shell_renders_the_wallet_name_and_mobile_viewport(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Sahara']));

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Home · Wallet</title>', false)
            ->assertSee('width=device-width, initial-scale=1, viewport-fit=cover', false)
            ->assertSee('Sahara')
            ->assertDontSee('Clario');
    }

    public function test_the_bottom_navigation_marks_the_active_tab(): void
    {
        $html = Blade::render('<x-bottom-nav active="reports" />');

        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertStringContainsString('Home', $html);
        $this->assertStringContainsString('Reports', $html);
        $this->assertStringContainsString('Savings', $html);
        $this->assertStringContainsString('Dues', $html);
        $this->assertStringContainsString('aria-label="Add"', $html);
    }

    public function test_the_top_app_bar_supports_home_page_and_back_types(): void
    {
        $home = Blade::render('<x-top-app-bar type="home" name="Sahara" />');
        $page = Blade::render('<x-top-app-bar type="page" title="Transactions" />');
        $back = Blade::render('<x-top-app-bar type="back" title="Accounts" />');

        $this->assertStringContainsString('Sahara', $home);
        $this->assertStringContainsString('Notifications', $home);
        $this->assertStringContainsString('Transactions', $page);
        $this->assertStringContainsString('Accounts', $back);
    }

    public function test_the_foundation_preview_is_not_available_outside_the_local_environment(): void
    {
        $this->assertTrue(app()->environment('testing'));

        $this->get('/foundation')->assertNotFound();
    }

    public function test_tests_refuse_traits_that_could_wipe_the_shared_database(): void
    {
        $unsafe = new class
        {
            use RefreshDatabase;
        };

        $this->expectException(\LogicException::class);

        TestCase::assertNoDestructiveDatabaseTraits($unsafe::class);
    }

    public function test_transaction_based_tests_are_allowed(): void
    {
        TestCase::assertNoDestructiveDatabaseTraits(static::class);

        $this->addToAssertionCount(1);
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_user_can_log_in_with_their_username_in_any_letter_case(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => strtoupper($user->username), 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_password_with_a_username_fails_like_an_unknown_username(): void
    {
        $user = User::factory()->create();

        $this->from('/login')->post('/login', ['email' => $user->username, 'password' => 'nope'])->assertSessionHasErrors('email');
        $this->from('/login')->post('/login', ['email' => 'nobody_'.uniqid(), 'password' => 'nope'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_the_login_screen_is_shown_to_guests(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('Forgot password?')
            ->assertSee('Create an account');
    }

    public function test_a_verified_user_can_log_in_and_sees_their_own_name_on_home(): void
    {
        $user = User::factory()->create(['name' => 'Rina Akter']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->get('/')->assertOk()->assertSee('Rina Akter');
    }

    public function test_the_email_is_matched_regardless_of_case(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => strtoupper($user->email), 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_password_and_an_unknown_email_get_the_same_answer(): void
    {
        $user = User::factory()->create();

        $wrongPassword = $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'not-the-password']);
        $unknownEmail = $this->from('/login')->post('/login', ['email' => 'nobody-'.uniqid().'@example.com', 'password' => 'not-the-password']);

        $this->assertGuest();
        $wrongPassword->assertSessionHasErrors(['email' => __('auth.failed')]);
        $unknownEmail->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->followingRedirects()->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'not-the-password'])
            ->assertSee('Email, username or password is not correct. Please try again.');
    }

    public function test_login_is_blocked_after_five_failed_attempts(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->assertGuest();

        $this->followingRedirects()->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSee('Too many login attempts');

        $this->assertGuest();
    }

    public function test_guests_are_sent_to_the_login_screen(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_an_unverified_user_cannot_reach_home(): void
    {
        $this->actingAs(User::factory()->unverified()->create());

        $this->get('/')->assertRedirect(route('verification.notice'));
    }

    public function test_a_user_can_log_out(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/logout')->assertRedirect();

        $this->assertGuest();
        $this->get('/')->assertRedirect('/login');
    }

    public function test_a_signed_in_user_is_sent_away_from_the_login_screen(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/login')->assertRedirect('/');
    }
}

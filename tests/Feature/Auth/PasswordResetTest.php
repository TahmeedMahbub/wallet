<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_forgot_password_screen_is_shown(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee('Send reset link');
    }

    public function test_a_reset_link_is_sent_to_a_registered_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_the_answer_is_the_same_for_an_unknown_email_and_nothing_is_sent(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);
        $unknown = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody-'.uniqid().'@example.com']);

        $known->assertSessionHas('status', Password::RESET_LINK_SENT)->assertSessionHasNoErrors();
        $unknown->assertSessionHas('status', Password::RESET_LINK_SENT)->assertSessionHasNoErrors();
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
        Notification::assertCount(1);
    }

    public function test_the_confirmation_screen_does_not_confirm_the_account_exists(): void
    {
        $response = $this->followingRedirects()->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'nobody-'.uniqid().'@example.com']);

        $response->assertSee('Link on its way')->assertSee('If')->assertSee('has a Wallet account');
    }

    public function test_the_password_can_be_reset_with_a_valid_link_and_the_link_works_once(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->get('/reset-password/'.$token.'?email='.urlencode($user->email))
            ->assertOk()
            ->assertSee('Save password');

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertRedirect('/login');

        $this->assertTrue(password_verify('brand-new-password', $user->fresh()->password));

        $this->get('/reset-password/'.$token.'?email='.urlencode($user->email))
            ->assertOk()
            ->assertSee('This reset link has expired or was already used.');
    }

    public function test_an_invalid_link_shows_the_expired_screen(): void
    {
        $user = User::factory()->create();

        $this->get('/reset-password/not-a-real-token?email='.urlencode($user->email))
            ->assertOk()
            ->assertSee('This reset link has expired or was already used.')
            ->assertSee('Send a new link');
    }

    public function test_a_weak_new_password_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors(['password' => 'Use at least 8 characters.']);

        $this->assertTrue(password_verify('password', $user->fresh()->password));
    }
}

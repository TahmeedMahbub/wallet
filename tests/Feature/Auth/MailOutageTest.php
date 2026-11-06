<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Password;
use RuntimeException;
use Tests\TestCase;

class MailOutageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->mock(Dispatcher::class, fn ($mock) => $mock->shouldReceive('send')->andThrow(new RuntimeException('smtp is down')));
    }

    public function test_registration_still_succeeds_when_the_mail_server_is_down(): void
    {
        $email = 'outage-'.uniqid().'@example.com';

        $this->post('/register', [
            'name' => 'Outage', 'username' => 'outage_'.substr(uniqid(), -6), 'email' => $email, 'password' => 'a-long-password', 'password_confirmation' => 'a-long-password', 'terms' => '1',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertNotNull(User::query()->where('email', $email)->first());
        $this->get('/')->assertRedirect(route('verification.notice'));
    }

    public function test_resending_the_verification_email_during_an_outage_does_not_crash(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->from('/email/verify')->post('/email/verification-notification')->assertSessionHas('status', 'verification-link-sent');
    }

    public function test_the_reset_answer_is_the_same_during_an_outage_for_known_and_unknown_emails(): void
    {
        $user = User::factory()->create();

        $known = $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);
        $unknown = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody-'.uniqid().'@example.com']);

        $known->assertSessionHas('status', Password::RESET_LINK_SENT)->assertSessionHasNoErrors();
        $unknown->assertSessionHas('status', Password::RESET_LINK_SENT)->assertSessionHasNoErrors();
    }
}

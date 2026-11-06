<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, mixed>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sahara',
            'email' => 'New.User+'.uniqid().'@Example.com',
            'password' => 'a-long-password',
            'password_confirmation' => 'a-long-password',
            'terms' => '1',
        ], $overrides);
    }

    public function test_the_registration_screen_is_shown_to_guests(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Create your Wallet')
            ->assertSee('Terms')
            ->assertSee('Privacy Policy');
    }

    public function test_a_user_can_register_and_is_asked_to_verify_their_email(): void
    {
        Notification::fake();
        $input = $this->validInput();

        $this->post('/register', $input)->assertRedirect('/');

        $user = User::where('email', strtolower($input['email']))->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('Asia/Dhaka', $user->timezone);
        $this->assertNotSame('a-long-password', $user->password);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->get('/')->assertRedirect(route('verification.notice'));
    }

    public function test_the_terms_must_be_accepted(): void
    {
        $this->post('/register', $this->validInput(['terms' => null]))
            ->assertSessionHasErrors(['terms' => 'Please accept the Terms and Privacy Policy to continue.']);

        $this->assertGuest();
    }

    public function test_an_email_that_is_already_registered_is_rejected_case_insensitively(): void
    {
        $existing = User::factory()->create(['email' => 'taken-'.uniqid().'@example.com']);

        $this->post('/register', $this->validInput(['email' => strtoupper($existing->email)]))
            ->assertSessionHasErrors(['email' => 'This email already has a Wallet account.']);
    }

    public function test_the_password_needs_eight_characters_and_a_matching_confirmation(): void
    {
        $this->post('/register', $this->validInput(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertSessionHasErrors(['password' => 'Use at least 8 characters.']);

        $this->post('/register', $this->validInput(['password_confirmation' => 'something-else']))
            ->assertSessionHasErrors(['password' => 'Passwords do not match.']);

        $this->assertGuest();
    }

    public function test_the_name_is_required(): void
    {
        $this->post('/register', $this->validInput(['name' => '']))->assertSessionHasErrors('name');
    }

    public function test_a_signed_in_user_is_sent_away_from_the_registration_screen(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/register')->assertRedirect('/');
    }

    public function test_the_legal_pages_are_public(): void
    {
        $this->get('/terms')->assertOk()->assertSee('Terms of Service');
        $this->get('/privacy')->assertOk()->assertSee('Privacy Policy');
    }
}

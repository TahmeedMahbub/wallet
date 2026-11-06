<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Throwable;

#[Fillable(['name', 'username', 'email', 'password', 'timezone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Send the verification email. A mail-server outage must not break
     * registration: it is logged and the user can use "Resend email".
     */
    public function sendEmailVerificationNotification(): void
    {
        try {
            $this->notify(new VerifyEmail);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Send the password-reset email. Failures are logged, never shown, so the
     * answer stays identical whether or not the address has an account.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        try {
            $this->notify(new ResetPassword($token));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'hide_amounts' => 'boolean',
        ];
    }
}

<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $input['email'] = Str::lower(trim((string) ($input['email'] ?? '')));

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ], [
            'email.unique' => 'This email already has a Wallet account.',
            'password.min' => 'Use at least 8 characters.',
            'password.confirmed' => 'Passwords do not match.',
            'terms.accepted' => 'Please accept the Terms and Privacy Policy to continue.',
        ])->validate();

        return User::create([
            'name' => trim($input['name']),
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }
}

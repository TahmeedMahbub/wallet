<?php

namespace App\Livewire\Sheets;

use App\Livewire\Concerns\InteractsWithSheet;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class ProfileSheet extends Component
{
    use InteractsWithSheet;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    #[On('open-profile')]
    public function openSheet(): void
    {
        $this->beginForm();
        $this->name = $this->user()->name;
        $this->email = $this->user()->email;
        $this->password = '';
    }

    public function save(): void
    {
        $user = $this->user();
        $this->email = Str::lower(trim($this->email));

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user->getKey())],
        ], [
            'name.required' => 'Enter your name.',
            'email.unique' => 'This email already has a Wallet account.',
        ]);

        $emailChanged = $this->email !== $user->email;

        if ($emailChanged && ! Hash::check($this->password, $user->password)) {
            $this->addError('password', 'Enter your password to change your email.');

            return;
        }

        $user->forceFill(['name' => trim($this->name)]);

        if ($emailChanged) {
            $user->forceFill(['email' => $this->email, 'email_verified_at' => null])->save();
            $user->sendEmailVerificationNotification();
            $this->open = false;
            $this->redirect(route('verification.notice'));

            return;
        }

        $user->save();
        $this->finish('Profile updated.');
    }

    public function render()
    {
        return view('livewire.sheets.profile-sheet', ['emailChanged' => $this->open && Str::lower(trim($this->email)) !== $this->user()->email]);
    }
}

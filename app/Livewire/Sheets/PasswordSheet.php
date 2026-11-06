<?php

namespace App\Livewire\Sheets;

use App\Livewire\Concerns\InteractsWithSheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\On;
use Livewire\Component;

class PasswordSheet extends Component
{
    use InteractsWithSheet;

    public string $current = '';

    public string $password = '';

    public string $password_confirmation = '';

    #[On('open-password')]
    public function openSheet(): void
    {
        $this->beginForm();
        $this->reset('current', 'password', 'password_confirmation');
    }

    public function save(): void
    {
        $user = $this->user();

        $this->validate([
            'current' => ['required'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ], [
            'current.required' => 'Enter your current password.',
            'password.min' => 'Use at least 8 characters.',
            'password.confirmed' => 'Passwords do not match.',
        ]);

        if (! Hash::check($this->current, $user->password)) {
            $this->addError('current', 'That password is not correct.');

            return;
        }

        $user->forceFill(['password' => Hash::make($this->password)])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->where('id', '!=', session()->getId())->delete();
        }

        $this->reset('current', 'password', 'password_confirmation');
        $this->finish('Password changed. Other devices were signed out.');
    }

    public function render()
    {
        return view('livewire.sheets.password-sheet');
    }
}

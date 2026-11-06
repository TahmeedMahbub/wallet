<?php

namespace App\Livewire\Sheets;

use App\Exceptions\InvalidBackup;
use App\Livewire\Concerns\InteractsWithSheet;
use App\Services\Backup\UserDataEraser;
use App\Services\Backup\UserDataRestorer;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class BackupSheet extends Component
{
    use InteractsWithSheet;
    use WithFileUploads;

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    /** @var array<string, mixed>|null */
    public ?array $preview = null;

    public string $confirmText = '';

    public string $password = '';

    public bool $confirmingErase = false;

    public bool $confirmingDelete = false;

    #[On('open-backup')]
    public function openSheet(): void
    {
        $this->beginForm();
        $this->reset('file', 'preview', 'confirmText', 'password', 'confirmingErase', 'confirmingDelete');
    }

    /**
     * Read and fully check an uploaded backup; show a preview, or why it cannot be used.
     */
    public function updatedFile(UserDataRestorer $restorer): void
    {
        $this->resetErrorBag();
        $this->preview = null;

        $this->validateOnly('file', ['file' => ['required', 'file', 'max:10240']], ['file.max' => 'The file is too large to restore.']);

        $content = (string) $this->file->get();

        try {
            $this->preview = $restorer->validate($content)['summary'];
        } catch (InvalidBackup $exception) {
            $restorer->logFailure($this->user(), $exception->getMessage(), strlen($content));
            $this->addError('file', $exception->getMessage());
            $this->dispatch('toast', message: 'Backup file is not valid.');
            $this->reset('file');
        }
    }

    public function cancelRestore(): void
    {
        $this->reset('file', 'preview', 'confirmText', 'password');
        $this->resetErrorBag();
    }

    /**
     * Replace the user's data with the backup. Needs the typed word and the password.
     */
    public function restore(UserDataRestorer $restorer): void
    {
        if ($this->file === null || $this->preview === null) {
            return;
        }

        $this->resetErrorBag();

        if (trim($this->confirmText) !== 'RESTORE') {
            $this->addError('confirmText', 'Type RESTORE to confirm.');

            return;
        }

        if (! Hash::check($this->password, $this->user()->password)) {
            $this->addError('password', 'That password is not correct.');

            return;
        }

        $content = (string) $this->file->get();

        try {
            $validated = $restorer->validate($content);
        } catch (InvalidBackup $exception) {
            $this->addError('file', $exception->getMessage());

            return;
        }

        $restorer->restore($this->user(), $validated['data'], strlen($content));
        $this->file?->delete();
        $this->finish('Backup restored.');
    }

    public function erase(UserDataEraser $eraser): void
    {
        if (! $this->confirmingErase) {
            $this->confirmingErase = true;

            return;
        }

        $eraser->startOver($this->user());
        $this->finish('All your data was erased. You are back at day one.');
    }

    public function deleteAccount(UserDataEraser $eraser)
    {
        if (! $this->confirmingDelete) {
            $this->confirmingDelete = true;

            return null;
        }

        if (! Hash::check($this->password, $this->user()->password)) {
            $this->addError('password', 'Enter your password to delete your account.');

            return null;
        }

        $user = $this->user();
        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();
        $eraser->deleteAccount($user);

        return $this->redirect(route('login'), navigate: false);
    }

    public function render()
    {
        return view('livewire.sheets.backup-sheet');
    }
}

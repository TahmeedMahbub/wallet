<div>
    @if ($open)
        <x-sheet title="Change password">
            <form wire:submit="save" class="flex flex-col gap-16" novalidate>
                <x-field label="CURRENT PASSWORD" name="current" type="password" placeholder="Your current password" wire:model="current" autocomplete="current-password" />
                <x-field label="NEW PASSWORD" name="password" type="password" placeholder="At least 8 characters" wire:model="password" autocomplete="new-password" />
                <x-field label="CONFIRM NEW PASSWORD" name="password_confirmation" type="password" placeholder="Type it again" wire:model="password_confirmation" autocomplete="new-password" />

                <x-button type="submit" wire:loading.attr="disabled" wire:target="save">Save password</x-button>
            </form>
        </x-sheet>
    @endif
</div>

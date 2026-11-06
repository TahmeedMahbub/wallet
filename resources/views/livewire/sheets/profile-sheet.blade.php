<div>
    @if ($open)
        <x-sheet title="Edit profile">
            <form wire:submit="save" class="flex flex-col gap-16" novalidate>
                <x-field label="YOUR NAME" name="name" placeholder="Name shown on Home" wire:model="name" autocomplete="name" />
                <x-field label="EMAIL" name="email" type="email" placeholder="you@example.com" wire:model.live.debounce.400ms="email" autocomplete="email" />

                @if ($emailChanged)
                    <x-info variant="warn">Changing your email means you must verify the new address before you can keep using Wallet.</x-info>
                    <x-field label="YOUR PASSWORD" name="password" type="password" placeholder="Password" wire:model="password" autocomplete="current-password" />
                @endif

                <x-button type="submit" wire:loading.attr="disabled" wire:target="save">Save changes</x-button>
            </form>
        </x-sheet>
    @endif
</div>

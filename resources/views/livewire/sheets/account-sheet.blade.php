<div>
    @if ($open)
        <x-sheet :title="$accountId ? 'Manage account' : 'Add account'" :level="2">
            <form wire:submit="save" class="flex flex-col gap-16" novalidate>
                <x-field label="ACCOUNT NAME" name="name" placeholder="e.g. EBL, bKash, Cash at home" wire:model="name" :autofocus="! $accountId" />

                <x-chip-group label="ACCOUNT TYPE">
                    @foreach ($types as $accountType)
                        <x-chip :selected="$type === $accountType->value" wire:click="$set('type', '{{ $accountType->value }}')">{{ $accountType->label() }}</x-chip>
                    @endforeach
                </x-chip-group>

                <x-field :label="$accountId ? 'CURRENT BALANCE (৳)' : 'OPENING BALANCE (৳)'" name="balance" size="amount" :money="true" placeholder="0" wire:model="balance" :autofocus="(bool) $accountId" />

                <p class="text-12 leading-17 text-grey-700">
                    {{ $accountId ? 'Use this to match your real balance. The change is not counted as income or expense.' : 'Opening balance is money you already have, so it is not counted as income.' }}
                </p>

                <x-button type="submit" wire:loading.attr="disabled" wire:target="save">{{ $accountId ? 'Save changes' : 'Add account' }}</x-button>

                @if ($accountId)
                    <div class="h-px w-full bg-grey-100"></div>

                    @if ($errors->has('delete'))
                        <x-info variant="bad">{{ $errors->first('delete') }}</x-info>
                    @endif

                    <x-button variant="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">{{ $confirmingDelete ? 'Tap again to delete' : 'Delete account' }}</x-button>
                    <p class="text-12 leading-17 text-grey-700">Past entries of this account stay in your history.</p>
                @endif
            </form>
        </x-sheet>
    @endif
</div>

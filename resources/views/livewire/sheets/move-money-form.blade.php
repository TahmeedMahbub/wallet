<div>
    @if ($open)
        <x-sheet :title="$transactionId ? 'Edit transfer' : 'Move money'">
            <form wire:submit="save" class="flex flex-col gap-16" novalidate>
                <x-field label="AMOUNT (৳)" name="amount" size="amount" :money="true" placeholder="0" wire:model="amount" :autofocus="true" />

                <x-chip-group label="FROM" :error="$errors->first('fromAccountId')">
                    @foreach ($accounts as $option)
                        <x-chip :selected="$fromAccountId === $option['account']->id" wire:click="$set('fromAccountId', {{ $option['account']->id }})">{{ $option['account']->name }} · {{ \App\Support\Money::format($option['balance']) }}</x-chip>
                    @endforeach
                    <x-chip :add="true" wire:click="$dispatch('open-account')">+ Add new</x-chip>
                </x-chip-group>

                <x-chip-group label="TO" :error="$errors->first('toAccountId')">
                    @foreach ($accounts as $option)
                        <x-chip :selected="$toAccountId === $option['account']->id" wire:click="$set('toAccountId', {{ $option['account']->id }})">{{ $option['account']->name }} · {{ \App\Support\Money::format($option['balance']) }}</x-chip>
                    @endforeach
                    <x-chip :add="true" wire:click="$dispatch('open-account')">+ Add new</x-chip>
                </x-chip-group>

                <x-datetime-field name="occurredAt" />

                <p class="text-12 leading-17 text-grey-700">A transfer is not an expense. Your total stays the same.</p>

                <x-button type="submit" wire:loading.attr="disabled" wire:target="save">{{ $transactionId ? 'Save changes' : 'Move money' }}</x-button>
            </form>
        </x-sheet>
    @endif
</div>

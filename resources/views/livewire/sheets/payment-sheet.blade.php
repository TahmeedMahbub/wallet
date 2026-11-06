<div>
    @if ($open && $due)
        <x-sheet title="Record payment">
            <form wire:submit="save" class="flex flex-col gap-16" novalidate>
                <x-info>
                    {{ $lent ? $due->person.' owes you '.\App\Support\Money::format($remaining).'. Enter how much was paid now.' : 'You owe '.$due->person.' '.\App\Support\Money::format($remaining).'. Enter how much you are paying now.' }}
                </x-info>

                <x-field label="AMOUNT (৳)" name="amount" size="amount" :money="true" placeholder="0" wire:model="amount" :autofocus="true" />

                <x-chip-group :label="$lent ? 'RECEIVED IN ACCOUNT' : 'PAY FROM ACCOUNT'" :error="$errors->first('accountId')">
                    @foreach ($accounts as $option)
                        <x-chip :selected="$accountId === $option['account']->id" wire:click="$set('accountId', {{ $option['account']->id }})">{{ $option['account']->name }} · {{ \App\Support\Money::format($option['balance']) }}</x-chip>
                    @endforeach
                    <x-chip :add="true" wire:click="$dispatch('open-account')">+ Add new</x-chip>
                </x-chip-group>

                <x-datetime-field name="occurredAt" />

                <x-button type="submit" wire:loading.attr="disabled" wire:target="save">Save payment</x-button>
            </form>
        </x-sheet>
    @endif
</div>

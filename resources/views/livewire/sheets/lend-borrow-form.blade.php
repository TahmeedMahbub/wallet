<div>
    @if ($open)
        @php $lent = $direction === 'lent'; @endphp

        <x-sheet :title="$lent ? 'Lend money' : 'Borrow money'">
            <form wire:submit="save" class="flex flex-col gap-16" novalidate>
                <x-field label="AMOUNT (৳)" name="amount" size="amount" :money="true" placeholder="0" wire:model="amount" :autofocus="true" />
                <x-field :label="$lent ? 'LEND TO' : 'BORROW FROM'" name="person" placeholder="Name of the person" wire:model="person" />

                <x-chip-group :label="$lent ? 'GIVE FROM ACCOUNT' : 'RECEIVED IN ACCOUNT'" :error="$errors->first('accountId')">
                    @foreach ($accounts as $option)
                        <x-chip :selected="$accountId === $option['account']->id" wire:click="$set('accountId', {{ $option['account']->id }})">{{ $option['account']->name }} · {{ \App\Support\Money::format($option['balance']) }}</x-chip>
                    @endforeach
                    <x-chip :add="true" wire:click="$dispatch('open-account')">+ Add new</x-chip>
                </x-chip-group>

                <x-datetime-field name="occurredAt" />
                <x-datetime-field label="RETURN BY (OPTIONAL)" name="dueDate" kind="date" placeholder="Pick a date" />
                <x-field label="NOTE (OPTIONAL)" name="note" placeholder="e.g. Admission fee" wire:model="note" />

                <p class="text-12 leading-17 text-grey-700">
                    {{ $lent ? 'Lending is not an expense. It shows as money you will get back.' : 'Borrowing is not income. It shows as money you need to pay.' }}
                </p>

                <x-button type="submit" wire:loading.attr="disabled" wire:target="save">{{ $lent ? 'Save lend' : 'Save borrow' }}</x-button>
            </form>
        </x-sheet>
    @endif
</div>

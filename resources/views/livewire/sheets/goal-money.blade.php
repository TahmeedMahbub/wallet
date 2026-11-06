<div>
    @if ($open && $goal)
        @php $withdraw = $mode === 'withdraw'; @endphp

        <x-sheet :title="$withdraw ? 'Withdraw from goal' : 'Add money to goal'">
            <form wire:submit="save" class="flex flex-col gap-16" novalidate>
                <div class="rounded-12 bg-grey-50 px-12 py-10 text-13 leading-18 text-grey-800">
                    @if ($withdraw)
                        {{ $goal->reason->name }}: {{ \App\Support\Money::format($balance) }} available to withdraw.
                    @else
                        {{ $goal->reason->name }}: {{ \App\Support\Money::format($balance) }} saved of {{ \App\Support\Money::format((int) $goal->target_amount) }}.
                    @endif
                </div>

                <x-field label="AMOUNT (৳)" name="amount" size="amount" :money="true" placeholder="0" wire:model="amount" :autofocus="true" />

                <x-chip-group :label="$withdraw ? 'SEND TO ACCOUNT' : 'TAKE FROM ACCOUNT'" :error="$errors->first('accountId')">
                    @foreach ($accounts as $option)
                        <x-chip :selected="$accountId === $option['account']->id" wire:click="$set('accountId', {{ $option['account']->id }})">{{ $option['account']->name }} · {{ \App\Support\Money::format($option['balance']) }}</x-chip>
                    @endforeach
                    <x-chip :add="true" wire:click="$dispatch('open-account')">+ Add new</x-chip>
                </x-chip-group>

                <x-datetime-field name="occurredAt" />

                @if ($withdraw)
                    <p class="text-12 leading-17 text-grey-700">Withdrawing is not an expense. It is only an expense when you spend it.</p>
                @endif

                <x-button type="submit" wire:loading.attr="disabled" wire:target="save">{{ $withdraw ? 'Withdraw' : 'Add to goal' }}</x-button>
            </form>
        </x-sheet>
    @endif
</div>

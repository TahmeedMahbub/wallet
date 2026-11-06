<div>
    @if ($open)
        <x-sheet :title="$transactionId ? 'Edit income' : 'Add income'">
            <form wire:submit="save" class="flex flex-col gap-16" novalidate>
                <x-field label="AMOUNT (৳)" name="amount" size="amount" :money="true" placeholder="0" wire:model="amount" :autofocus="true" />

                <x-chip-group label="SOURCE" :error="$errors->first('sourceId')">
                    @foreach ($sources as $source)
                        <x-chip :selected="$sourceId === $source->id" wire:click="$set('sourceId', {{ $source->id }})">{{ $source->name }}</x-chip>
                    @endforeach
                    <x-chip :add="true" wire:click="$set('addingSource', true)">+ Add new</x-chip>
                </x-chip-group>

                <x-chip-group label="RECEIVED IN" :error="$errors->first('accountId')">
                    @foreach ($accounts as $option)
                        <x-chip :selected="$accountId === $option['account']->id" wire:click="$set('accountId', {{ $option['account']->id }})">{{ $option['account']->name }} · {{ \App\Support\Money::format($option['balance']) }}</x-chip>
                    @endforeach
                    <x-chip :add="true" wire:click="$dispatch('open-account')">+ Add new</x-chip>
                </x-chip-group>

                <x-datetime-field name="occurredAt" />
                <x-field label="NOTE (OPTIONAL)" name="note" placeholder="e.g. Eid salami from Nana" wire:model="note" />

                @if ($autoSaveTotal > 0)
                    <x-info>Salary: {{ \App\Support\Money::format($autoSaveTotal) }} will move to your savings goals automatically.</x-info>
                @endif

                <x-button type="submit" wire:loading.attr="disabled" wire:target="save">{{ $transactionId ? 'Save changes' : 'Save income' }}</x-button>
            </form>
        </x-sheet>

        @if ($addingSource)
            <x-sheet title="New income source" close="cancelSource" :level="2">
                <form wire:submit="addSource" class="flex flex-col gap-16" novalidate>
                    <x-field label="SOURCE NAME" name="newSource" placeholder="e.g. Freelance" wire:model="newSource" :autofocus="true" />
                    <div class="flex items-center gap-10">
                        <x-button variant="secondary" wire:click="cancelSource" class="flex-1">Cancel</x-button>
                        <x-button type="submit" class="flex-1">Add source</x-button>
                    </div>
                </form>
            </x-sheet>
        @endif
    @endif
</div>

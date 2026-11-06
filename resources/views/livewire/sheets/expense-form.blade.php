<div>
    @if ($open)
        <x-sheet :title="$transactionId ? 'Edit expense' : 'Add expense'">
            <form wire:submit="save" class="flex flex-col gap-16" novalidate>
                <x-field label="AMOUNT (৳)" name="amount" size="amount" :money="true" placeholder="0" wire:model="amount" :autofocus="true" />

                <x-chip-group label="CATEGORY" :error="$errors->first('categoryId')">
                    @foreach ($categories as $category)
                        <x-chip :selected="$categoryId === $category->id" wire:click="$set('categoryId', {{ $category->id }})">{{ $category->name }}</x-chip>
                    @endforeach
                    <x-chip :add="true" wire:click="$set('addingCategory', true)">+ Add new</x-chip>
                </x-chip-group>

                <x-chip-group label="PAID FROM" :error="$errors->first('accountId')">
                    @foreach ($accounts as $option)
                        <x-chip :selected="$accountId === $option['account']->id" wire:click="$set('accountId', {{ $option['account']->id }})">{{ $option['account']->name }} · {{ \App\Support\Money::format($option['balance']) }}</x-chip>
                    @endforeach
                    <x-chip :add="true" wire:click="$dispatch('open-account')">+ Add new</x-chip>
                </x-chip-group>

                <x-field label="EXPENSE AREA (WHERE)" name="area" placeholder="e.g. Office canteen, Bashundhara" wire:model="area" />
                <x-field label="REASON" name="reason" placeholder="e.g. Lunch with team" wire:model="reason" />
                <x-datetime-field name="occurredAt" />

                <x-button type="submit" wire:loading.attr="disabled" wire:target="save">{{ $transactionId ? 'Save changes' : 'Save expense' }}</x-button>
            </form>
        </x-sheet>

        @if ($addingCategory)
            <x-sheet title="New expense category" close="cancelCategory" :level="2">
                <form wire:submit="addCategory" class="flex flex-col gap-16" novalidate>
                    <x-field label="CATEGORY NAME" name="newCategory" placeholder="e.g. Stationery" wire:model="newCategory" :autofocus="true" />
                    <div class="flex items-center gap-10">
                        <x-button variant="secondary" wire:click="cancelCategory" class="flex-1">Cancel</x-button>
                        <x-button type="submit" class="flex-1">Add category</x-button>
                    </div>
                </form>
            </x-sheet>
        @endif
    @endif
</div>

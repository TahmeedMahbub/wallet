<div>
    @if ($open && $entry)
        @php
            $row = \App\Support\TransactionPresenter::present($entry, auth()->user());
            $signed = \App\Support\TransactionPresenter::signedAmount($entry);
            $isSalary = $entry->type === \App\Enums\TransactionType::Income && $entry->incomeSource?->is_salary;
            $note = match (true) {
                $isSalary => 'Deleting income from salary does not remove its auto-save entry. Deleting reverses its effect on your balances.',
                $entry->type === \App\Enums\TransactionType::Lend, $entry->type === \App\Enums\TransactionType::Borrow => 'Deleting also removes it from Dues, together with any payments recorded for it.',
                default => 'Deleting reverses its effect on your balances.',
            };
        @endphp

        <x-sheet title="Entry details">
            <div class="flex flex-col gap-4 rounded-14 bg-grey-50 p-14">
                <p class="text-12 leading-17 text-grey-700">{{ $row['label'] }}</p>
                <p class="text-24 font-bold leading-34 text-grey-900">{{ $entry->type === \App\Enums\TransactionType::Transfer ? $row['amount'] : \App\Support\Money::format($signed) }}</p>
                <p class="text-14 font-semibold leading-20 text-grey-900">{{ $row['title'] }}</p>
                <p class="text-12 leading-17 text-grey-700">{{ $row['meta'] }}</p>
                <p class="text-12 leading-17 text-grey-700">
                    {{ $entry->type === \App\Enums\TransactionType::Transfer ? 'From: '.$row['account'].' · To: '.$entry->toAccount?->name : 'Account: '.$row['account'] }}
                </p>
            </div>

            @if ($errors->has('delete'))
                <x-info variant="bad">{{ $errors->first('delete') }}</x-info>
            @endif

            <p class="text-12 leading-17 text-grey-700">{{ $note }}</p>

            @if ($entry->type->isEditable())
                <x-button variant="secondary" wire:click="edit">Edit entry</x-button>
            @else
                <p class="text-12 leading-17 text-grey-700">This kind of entry can only be deleted, not edited.</p>
            @endif

            <x-button variant="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">{{ $confirmingDelete ? 'Tap again to delete' : 'Delete entry' }}</x-button>
        </x-sheet>
    @endif
</div>

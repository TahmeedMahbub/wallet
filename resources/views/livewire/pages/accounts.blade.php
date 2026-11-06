@php use App\Support\Money; @endphp

<div class="flex flex-col gap-16" wire:key="accounts">
    <div class="flex flex-col gap-4 rounded-24 bg-blue-800 p-20">
        <p class="text-13 leading-18 text-blue-100">Net worth</p>
        <p class="whitespace-nowrap text-32 font-bold leading-45 text-grey-0">{{ Money::format($netWorth) }}</p>
        <p class="text-12 leading-17 text-blue-100">Wallet {{ Money::format($wallet) }} + Savings {{ Money::format($savings) }} + To get {{ Money::format($toGet) }} − To pay {{ Money::format($toPay) }}</p>
    </div>

    <x-card>
        <div class="flex items-center justify-between whitespace-nowrap font-semibold text-grey-900">
            <p class="text-16 leading-22">Wallet (for spending)</p>
            <p class="text-14 leading-20">{{ Money::format($wallet) }}</p>
        </div>
        <p class="text-12 leading-17 text-grey-700">Tap an account to rename it, update its balance or delete it.</p>

        @foreach ($accounts as $account)
            <button type="button" wire:click="$dispatch('open-account', { id: {{ $account->id }} })" class="flex min-h-48 w-full items-center gap-12 text-left" wire:key="account-{{ $account->id }}">
                <span class="flex size-40 shrink-0 items-center justify-center rounded-12 bg-blue-50 text-12 font-bold leading-17 text-blue-700">{{ $account->initials() }}</span>
                <span class="flex min-w-px flex-1 flex-col gap-2">
                    <span class="truncate text-14 font-semibold leading-20 text-grey-900">{{ $account->name }}</span>
                    <span class="text-12 leading-17 text-grey-700">{{ $account->type->label() }}</span>
                </span>
                <span class="whitespace-nowrap text-14 font-semibold leading-20 text-grey-900">{{ Money::format((int) ($balances[$account->id] ?? 0)) }}</span>
                <x-icon name="chevron-right" class="size-18 text-grey-700" />
            </button>
        @endforeach

        <div class="flex items-center gap-10">
            <x-button variant="secondary" wire:click="$dispatch('open-account')" class="min-w-px flex-1">+ Add account</x-button>
            <x-button variant="secondary" wire:click="$dispatch('open-move-money')" class="min-w-px flex-1">Move money</x-button>
        </div>
    </x-card>
</div>

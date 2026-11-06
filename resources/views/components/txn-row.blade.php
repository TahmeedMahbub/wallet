@props(['entry', 'compact' => false, 'hideBalance' => false])

@php
    $row = \App\Support\TransactionPresenter::present($entry, auth()->user(), $compact);
@endphp

{{-- Figma "Txn Row": icon, title and meta on the left; amount and the account balance after it on the right. --}}
<button type="button" wire:click="$dispatch('open-entry', { id: {{ $entry->id }} })" {{ $attributes->class('flex w-full items-center gap-12 py-6 text-left') }}>
    <span class="flex size-40 shrink-0 items-center justify-center rounded-full {{ $row['icon_bg'] }} {{ $row['icon_ink'] }}"><x-icon :name="$row['icon']" class="size-18" /></span>
    <span class="flex min-w-px flex-1 flex-col gap-2">
        <span class="w-full truncate text-14 font-semibold leading-20 text-grey-900">{{ $row['title'] }}</span>
        <span class="w-full truncate text-12 leading-17 text-grey-700">{{ $row['meta'] }}</span>
    </span>
    <span class="flex shrink-0 flex-col items-end gap-2 whitespace-nowrap">
        <span class="text-14 font-semibold leading-20 {{ $row['amount_class'] }}">{{ $row['amount'] }}</span>
        @if ($row['balance'])
            <span class="text-12 leading-17 text-grey-700">{{ $hideBalance ? $row['account'].' · '.\App\Support\Money::format(0, hidden: true) : $row['balance'] }}</span>
        @endif
    </span>
</button>

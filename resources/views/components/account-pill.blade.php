@props(['account', 'balance', 'hidden' => false])

{{-- Figma "Account Pill": avatar with initials, account name and balance. --}}
<span {{ $attributes->class('flex h-44 shrink-0 items-center gap-8 rounded-full border border-solid bg-grey-0 pl-6 pr-14') }}>
    <span class="flex size-32 shrink-0 items-center justify-center rounded-full bg-blue-50 text-11 font-bold leading-15 text-blue-700">{{ $account->initials() }}</span>
    <span class="flex flex-col whitespace-nowrap">
        <span class="text-11 font-medium leading-14 text-grey-700">{{ $account->name }}</span>
        <span class="text-13 font-semibold leading-18 text-grey-900">{{ \App\Support\Money::format($balance, hidden: $hidden) }}</span>
    </span>
</span>

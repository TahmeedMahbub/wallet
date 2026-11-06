@props(['tab', 'current' => false])

<li class="flex min-w-px flex-1">
    <a
        href="{{ $tab['href'] }}" wire:navigate
        @if ($current) aria-current="page" @endif
        class="flex min-w-px flex-1 flex-col items-center gap-4 py-8"
    >
        <span @class([
            'flex h-30 w-56 items-center justify-center overflow-clip rounded-full',
            'bg-blue-50' => $current,
        ])>
            <x-icon :name="$tab['icon']" :class="$current ? ($tab['activeIconClass'] ?? 'text-blue-700') : 'text-grey-700'" />
        </span>
        <span @class([
            'whitespace-nowrap text-11 leading-14',
            'font-semibold text-blue-700' => $current,
            'font-medium text-grey-700' => ! $current,
        ])>{{ $tab['label'] }}</span>
    </a>
</li>

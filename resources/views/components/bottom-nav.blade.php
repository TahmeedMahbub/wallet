@props(['active' => null])

@php
    /*
     * Figma: the active Home icon keeps the grey stroke while the other three
     * active icons turn blue. Reproduced as designed; flagged for the client.
     */
    $leftTabs = [
        'home' => ['label' => 'Home', 'icon' => 'home', 'href' => route('home'), 'activeIconClass' => 'text-grey-700'],
        'reports' => ['label' => 'Reports', 'icon' => 'reports', 'href' => route('reports')],
    ];
    $rightTabs = [
        'savings' => ['label' => 'Savings', 'icon' => 'savings', 'href' => route('savings')],
        'dues' => ['label' => 'Dues', 'icon' => 'dues', 'href' => route('dues')],
    ];
@endphp

{{-- Figma "Bottom Nav": 92px on a 390x844 frame (71px of content plus 21px of device inset). --}}
<nav
    aria-label="Main"
    {{ $attributes->class('sticky bottom-0 z-30 border-t border-solid bg-grey-0 pb-[max(21px,env(safe-area-inset-bottom))]') }}
>
    <ul class="flex items-start px-8 pt-6">
        @foreach ($leftTabs as $key => $tab)
            <x-bottom-nav-item :tab="$tab" :current="$active === $key" />
        @endforeach

        <li class="flex h-58 w-72 shrink-0 items-center justify-center">
            <x-fab-add />
        </li>

        @foreach ($rightTabs as $key => $tab)
            <x-bottom-nav-item :tab="$tab" :current="$active === $key" />
        @endforeach
    </ul>
</nav>

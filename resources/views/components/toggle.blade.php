@props(['on' => false, 'label' => 'Toggle'])

{{-- Figma "Toggle": 44x26 switch. --}}
<button
    type="button"
    role="switch"
    aria-checked="{{ $on ? 'true' : 'false' }}"
    aria-label="{{ $label }}"
    {{ $attributes->class(['relative h-26 w-44 shrink-0 rounded-full transition-colors', 'bg-blue-600' => $on, 'bg-grey-300' => ! $on]) }}
>
    <span @class(['absolute top-3 size-20 rounded-full bg-grey-0 transition-all', 'left-[21px]' => $on, 'left-3' => ! $on])></span>
</button>

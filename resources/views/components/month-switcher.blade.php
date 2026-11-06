@props(['label', 'canNext' => false])

{{-- Figma "Month switcher": previous / month name / next (dimmed on the current month). --}}
<div {{ $attributes->class('flex w-full items-center justify-between overflow-clip rounded-14 border border-solid bg-grey-0 p-2') }}>
    <button type="button" wire:click="previousMonth" aria-label="Previous month" class="flex size-44 shrink-0 items-center justify-center text-grey-700">
        <x-icon name="chevron-left" class="size-20" />
    </button>
    <p class="whitespace-nowrap text-14 font-semibold leading-20 text-grey-900">{{ $label }}</p>
    <button type="button" wire:click="nextMonth" @disabled(! $canNext) aria-label="Next month" @class(['flex size-44 shrink-0 items-center justify-center text-grey-700', 'opacity-30' => ! $canNext])>
        <x-icon name="chevron-right" class="size-20" />
    </button>
</div>

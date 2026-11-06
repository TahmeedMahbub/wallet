@props(['title', 'close' => 'close', 'level' => 1])

{{-- Figma bottom sheet: dimmed scrim, 24px top corners, handle, header with a 44px close button. --}}
<div
    x-data="{
        init() { document.documentElement.classList.add('overflow-hidden'); },
        destroy() { if (! document.querySelector('[data-sheet]:not([data-sheet-gone])')) { document.documentElement.classList.remove('overflow-hidden'); } },
    }"
    x-on:keydown.escape.window="$wire.{{ $close }}()"
    data-sheet
    @class(['fixed inset-0 flex items-end justify-center', 'z-50' => $level === 1, 'z-[60]' => $level === 2])
    role="dialog"
    aria-modal="true"
    aria-label="{{ $title }}"
>
    <div class="absolute inset-0 bg-grey-900/45" wire:click="{{ $close }}"></div>

    <div class="relative flex max-h-[calc(100dvh-56px)] w-full max-w-480 flex-col gap-16 overflow-y-auto overscroll-contain rounded-t-24 bg-grey-0 px-20 pb-[max(28px,env(safe-area-inset-bottom))] pt-8">
        <div class="flex shrink-0 items-center justify-center"><div class="h-4 w-36 rounded-full bg-grey-300"></div></div>

        <div class="flex shrink-0 items-center justify-between">
            <h2 class="whitespace-nowrap text-18 font-semibold text-grey-900">{{ $title }}</h2>
            <button type="button" wire:click="{{ $close }}" aria-label="Close" class="flex size-44 items-center justify-center text-grey-900">
                <x-icon name="x" class="size-20" />
            </button>
        </div>

        {{ $slot }}
    </div>
</div>

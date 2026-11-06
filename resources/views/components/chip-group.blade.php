@props(['label' => '', 'error' => null])

<div class="flex flex-col gap-6">
    @if ($label !== '')
        <p class="text-12 font-semibold leading-17 tracking-label text-grey-700">{{ $label }}</p>
    @endif
    <div {{ $attributes->class('flex flex-wrap content-start items-start gap-8') }}>{{ $slot }}</div>
    @if ($error)
        <p class="text-12 font-medium leading-16 text-red-700">{{ $error }}</p>
    @endif
</div>

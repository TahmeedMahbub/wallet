@props(['title', 'body' => null])

{{-- Figma "Empty State": dashed card that always says what to do next. --}}
<div {{ $attributes->class('flex flex-col items-center gap-8 rounded-20 border-[1.5px] border-dashed bg-grey-0 p-20 text-center') }}>
    <p class="w-full text-15 font-semibold leading-21 text-grey-900">{{ $title }}</p>
    @if ($body)
        <p class="w-full text-12 leading-17 text-grey-700">{{ $body }}</p>
    @endif
    {{ $slot }}
</div>

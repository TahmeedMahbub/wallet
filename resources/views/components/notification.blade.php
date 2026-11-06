@props(['level' => 'warn', 'title', 'body'])

@php
    [$tint, $icon, $ink] = match ($level) {
        'care' => ['bg-yellow-50', 'alert', 'text-orange-900'],
        'info' => ['bg-blue-50', 'info', 'text-blue-700'],
        'good' => ['bg-green-50', 'check', 'text-green-700'],
        default => ['bg-red-50', 'alert', 'text-red-700'],
    };
@endphp

{{-- Figma "Notification": Warn = over/late, Care = close to a limit, Info = reminder, Good = done/reached. --}}
<div {{ $attributes->class('flex items-start gap-12 rounded-16 border border-solid bg-grey-0 p-14') }}>
    <span class="flex size-36 shrink-0 items-center justify-center rounded-full {{ $tint }} {{ $ink }}"><x-icon :name="$icon" class="size-18" /></span>
    <span class="flex min-w-px flex-1 flex-col gap-2">
        <span class="text-14 font-semibold leading-20 text-grey-900">{{ $title }}</span>
        <span class="text-13 leading-18 text-grey-700">{{ $body }}</span>
    </span>
</div>

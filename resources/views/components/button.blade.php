@props(['variant' => 'primary', 'type' => 'button', 'href' => null])

@php
    $classes = match ($variant) {
        'secondary' => 'h-48 border border-solid border-grey-300 bg-grey-0 text-14 leading-20 text-blue-700',
        'danger' => 'h-48 border border-solid border-red-500 bg-grey-0 text-14 leading-20 text-red-700',
        default => 'h-52 bg-blue-600 text-15 leading-21 text-grey-0',
    };
    $classes = 'flex w-full items-center justify-center rounded-14 font-semibold disabled:cursor-not-allowed disabled:opacity-60 '.$classes;
@endphp

{{-- Figma "Button": Primary 52px, Secondary and Danger 48px. --}}
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif

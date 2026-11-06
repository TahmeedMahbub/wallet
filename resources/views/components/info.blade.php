@props(['variant' => 'info'])

@php
    $classes = match ($variant) {
        'warn' => 'bg-yellow-50 text-orange-900',
        'bad' => 'bg-red-50 text-red-700',
        default => 'bg-blue-50 text-blue-900',
    };
@endphp

<div @if ($variant === 'bad') role="alert" @endif {{ $attributes->class('rounded-12 px-12 py-10 text-13 leading-18 '.$classes) }}>{{ $slot }}</div>

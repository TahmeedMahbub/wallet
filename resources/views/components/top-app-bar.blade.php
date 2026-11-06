@props(['type' => 'home', 'title' => '', 'name' => 'Sahara', 'back' => '#', 'alerts' => 0])

@php
    $hour = now(auth()->user()?->timezone ?? config('app.timezone'))->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
@endphp

@if ($type === 'home')
    <header {{ $attributes->class('flex items-center justify-between bg-grey-50 px-20 py-12') }}>
        <a href="{{ route('profile') }}" wire:navigate class="flex items-center gap-12">
            <span class="flex size-40 shrink-0 items-center justify-center rounded-full bg-blue-100 text-17 font-semibold text-blue-700">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($name, 0, 1)) }}</span>
            <span class="flex flex-col gap-2 whitespace-nowrap">
                <span class="text-12 font-medium text-grey-700">{{ $greeting }}</span>
                <span class="text-17 font-semibold text-grey-900">{{ $name }}</span>
            </span>
        </a>
        <a href="{{ route('notifications') }}" wire:navigate aria-label="Notifications" class="relative flex size-40 shrink-0 items-center justify-center rounded-full border border-solid bg-grey-0 text-grey-700">
            <x-icon name="bell" />
            @if ($alerts > 0)
                <span class="absolute right-8 top-8 size-8 rounded-full border-2 border-solid border-grey-0 bg-red-500" aria-label="Needs attention"></span>
            @endif
        </a>
    </header>
@elseif ($type === 'page')
    <header {{ $attributes->class('flex items-center justify-between bg-grey-50 p-12') }}>
        <a href="{{ $back }}" wire:navigate aria-label="Back" class="flex size-40 shrink-0 items-center justify-center rounded-full text-grey-700">
            <x-icon name="chevron-left" />
        </a>
        <h1 class="whitespace-nowrap text-17 font-semibold text-grey-900">{{ $title }}</h1>
        <button type="button" aria-label="More" class="flex size-40 shrink-0 items-center justify-center rounded-full text-grey-700">
            <x-icon name="more" />
        </button>
    </header>
@else
    <header {{ $attributes->class('flex items-center gap-4 bg-grey-50 pb-8 pl-12 pr-20 pt-16') }}>
        <a href="{{ $back }}" wire:navigate aria-label="Back" class="flex size-44 shrink-0 items-center justify-center text-grey-900">
            <x-icon name="back" />
        </a>
        <h1 class="whitespace-nowrap text-20 font-semibold text-grey-900">{{ $title }}</h1>
    </header>
@endif

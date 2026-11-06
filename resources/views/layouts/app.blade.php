@props(['title' => null, 'nav' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f8f9fa">
    <title>{{ $title ? $title.' · Wallet' : 'Wallet' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-dvh font-sans text-14 leading-20 text-grey-900">
    {{-- Mobile only: one 390px-designed column, fluid from 320px, centred and capped on wider screens. --}}
    <div class="mx-auto flex min-h-dvh w-full max-w-480 flex-col bg-grey-50">
        {{ $topBar ?? '' }}

        <main class="flex flex-1 flex-col gap-16 px-20 pb-24 pt-8">
            {{ $slot }}
        </main>

        @if ($nav)
            <x-bottom-nav :active="$nav === true ? null : $nav" />
        @endif
    </div>

    @livewireScripts
</body>
</html>

@props(['title' => null, 'nav' => null, 'bar' => null, 'barTitle' => '', 'back' => null])

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
        @if ($bar)
            <x-top-app-bar :type="$bar" :title="$barTitle" :name="auth()->user()?->name ?? ''" :back="$back ?? route('home')" :alerts="$bar === 'home' && auth()->check() ? app(\App\Services\Wallet\NotificationService::class)->attentionCount(auth()->user()) : 0" />
        @else
            {{ $topBar ?? '' }}
        @endif

        <main class="flex flex-1 flex-col gap-16 px-20 pb-24 pt-8">
            {{ $slot }}
        </main>

        @if ($nav)
            <x-bottom-nav :active="$nav === true ? null : $nav" />
        @endif
    </div>

    {{-- Money sheets are available from every app screen (the + button, edit, delete…). --}}
    @auth
        @if ($nav)
            <livewire:sheets.add-menu />
            <livewire:sheets.expense-form />
            <livewire:sheets.income-form />
            <livewire:sheets.move-money-form />
            <livewire:sheets.lend-borrow-form />
            <livewire:sheets.entry-details />
            <livewire:sheets.account-sheet />
            <livewire:sheets.budget-sheet />
            <livewire:sheets.goal-wizard />
            <livewire:sheets.goal-money />
            <livewire:sheets.payment-sheet />
            <livewire:sheets.backup-sheet />
            <livewire:sheets.profile-sheet />
            <livewire:sheets.password-sheet />

            <div
                x-data="{ message: '', open: false, timer: null }"
                x-on:toast.window="message = $event.detail.message ?? $event.detail[0]?.message ?? ''; open = true; clearTimeout(timer); timer = setTimeout(() => open = false, 3500)"
            >
                <div
                    x-show="open"
                    x-cloak
                    x-transition.opacity
                    role="status"
                    class="fixed bottom-[calc(92px+12px)] left-1/2 z-[70] w-[calc(100%-32px)] max-w-448 -translate-x-1/2 rounded-14 bg-grey-900 px-16 py-12 text-13 leading-18 text-grey-0 shadow-toast"
                    x-text="message"
                ></div>
            </div>
        @endif
    @endauth

    @livewireScripts
</body>
</html>

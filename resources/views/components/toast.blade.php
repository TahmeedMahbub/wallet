{{-- Figma "Toast": dark confirmation that hides itself after about 3 seconds. --}}
<div
    x-data="{ open: true }"
    x-init="setTimeout(() => open = false, 3000)"
    x-show="open"
    x-transition.opacity
    role="status"
    {{ $attributes->class('fixed bottom-24 left-1/2 z-40 w-[calc(100%-32px)] max-w-448 -translate-x-1/2 rounded-14 bg-grey-900 px-16 py-12 text-13 leading-18 text-grey-0 shadow-toast') }}
>{{ $slot }}</div>

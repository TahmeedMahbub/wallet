@props(['title', 'logo' => 64, 'paddingTop' => 24])

<div class="flex flex-col items-center gap-8 pb-8 text-center" style="padding-top: {{ $paddingTop }}px">
    @isset($icon)
        {{ $icon }}
    @else
        <x-logo variant="color" :size="$logo" />
    @endisset
    <h1 class="text-20 font-semibold leading-28 text-grey-900">{{ $title }}</h1>
    <p class="max-w-310 text-13 leading-18 text-grey-700">{{ $slot }}</p>
</div>

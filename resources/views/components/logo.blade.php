@props(['variant' => 'color', 'size' => 200])

<span
    role="img"
    aria-label="Wallet"
    style="width: {{ $size }}px; height: {{ $size }}px"
    {{ $attributes->class(['logo-mark', 'logo-mark-'.$variant]) }}
></span>

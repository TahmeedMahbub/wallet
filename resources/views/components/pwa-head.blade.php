{{-- Installable-app tags: theme and status bar colour (Figma grey/50), manifest, icons, iOS splash screens. --}}
@php
    $splashScreens = [
        [430, 932, 3, '1290x2796'],
        [428, 926, 3, '1284x2778'],
        [414, 896, 3, '1242x2688'],
        [414, 896, 2, '828x1792'],
        [393, 852, 3, '1179x2556'],
        [390, 844, 3, '1170x2532'],
        [375, 812, 3, '1125x2436'],
        [375, 667, 2, '750x1334'],
    ];
@endphp
<meta name="theme-color" content="#f8f9fa">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Wallet">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="format-detection" content="telephone=no">
@foreach ($splashScreens as [$width, $height, $ratio, $file])
    <link rel="apple-touch-startup-image" href="/splash/splash-{{ $file }}.png" media="(device-width: {{ $width }}px) and (device-height: {{ $height }}px) and (-webkit-device-pixel-ratio: {{ $ratio }}) and (orientation: portrait)">
@endforeach

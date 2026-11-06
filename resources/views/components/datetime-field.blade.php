@props(['label' => 'DATE & TIME', 'name' => 'occurredAt', 'kind' => 'datetime', 'placeholder' => ''])

@php
    $isDate = $kind === 'date';
@endphp

{{-- Figma "Field" (Filled) showing a friendly date; the real picker is a transparent native input on top. --}}
<div class="flex flex-col gap-6" x-data="{ get text() { return {{ $isDate ? 'window.walletDateOnly' : 'window.walletDateLabel' }}($wire.{{ $name }}); } }">
    <label for="{{ $name }}" class="text-12 font-semibold leading-17 tracking-label text-grey-700">{{ $label }}</label>

    <div class="relative flex h-48 items-center rounded-12 border border-solid bg-grey-0 px-14 text-15 leading-21 focus-within:border-2 focus-within:border-blue-600">
        <span x-show="text" x-text="text" class="text-grey-900"></span>
        <span x-show="! text" class="text-grey-500">{{ $placeholder }}</span>
        <input id="{{ $name }}" type="{{ $isDate ? 'date' : 'datetime-local' }}" wire:model="{{ $name }}" class="absolute inset-0 size-full cursor-pointer opacity-0">
    </div>

    @error($name)
        <p class="text-12 font-medium leading-16 text-red-700">{{ $message }}</p>
    @enderror
</div>

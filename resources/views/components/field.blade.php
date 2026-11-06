@props([
    'label',
    'name',
    'type' => 'text',
    'placeholder' => '',
    'autocomplete' => null,
    'autofocus' => false,
    'invalid' => false,
    'value' => null,
])

@php
    $error = $errors->first($name);
    $hasError = $error !== '' || $invalid;
    $isPassword = $type === 'password';
    $inputValue = $isPassword ? null : ($value ?? old($name));
@endphp

{{-- Figma "Field" (Regular): label, 48px box, optional helper line in the Error state. --}}
<div class="flex flex-col gap-6" @if ($isPassword) x-data="{ show: false }" @endif>
    <label for="{{ $name }}" class="text-12 font-semibold leading-17 tracking-label text-grey-700">{{ $label }}</label>

    <div @class([
        'flex h-48 items-center justify-between gap-8 rounded-12 border border-solid bg-grey-0 px-14',
        'border-red-700' => $hasError,
        'border-grey-300 focus-within:border-2 focus-within:border-blue-600' => ! $hasError,
    ])>
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            @if ($isPassword) x-bind:type="show ? 'text' : 'password'" @endif
            type="{{ $type }}"
            @if ($inputValue !== null) value="{{ $inputValue }}" @endif
            placeholder="{{ $placeholder }}"
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($autofocus) autofocus @endif
            @if ($hasError) aria-invalid="true" @endif
            @if ($error !== '') aria-describedby="{{ $name }}-error" @endif
            {{ $attributes->class('min-w-0 flex-1 bg-transparent text-15 leading-21 text-grey-900 outline-none placeholder:text-grey-500') }}
        >

        @if ($isPassword)
            <button type="button" x-on:click="show = ! show" x-bind:aria-label="show ? 'Hide password' : 'Show password'" aria-label="Show password" class="flex size-24 shrink-0 items-center justify-center text-grey-700">
                <x-icon name="eye-off" class="size-20" x-show="! show" />
                <x-icon name="eye" class="size-20" x-show="show" x-cloak />
            </button>
        @endif
    </div>

    @if ($error !== '')
        <p id="{{ $name }}-error" class="text-12 font-medium leading-16 text-red-700">{{ $error }}</p>
    @endif
</div>

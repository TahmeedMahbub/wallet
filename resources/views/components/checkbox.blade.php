@props(['name', 'checked' => false])

@php
    $isChecked = old($name, $checked) ? true : false;
@endphp

<div class="flex flex-col gap-6">
    <label class="relative flex items-start gap-10 text-13 leading-20 text-grey-700">
        <input type="checkbox" name="{{ $name }}" value="1" @checked($isChecked) class="peer sr-only">
        <span class="flex size-20 shrink-0 items-center justify-center rounded-6 border-[1.5px] border-solid border-grey-500 bg-grey-0 peer-checked:border-blue-600 peer-checked:bg-blue-600 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-blue-600"></span>
        <x-icon name="check" class="pointer-events-none absolute left-3 top-3 size-14 text-grey-0 opacity-0 peer-checked:opacity-100" />
        <span class="min-w-0 flex-1">{{ $slot }}</span>
    </label>

    @error($name)
        <p class="text-12 font-medium leading-16 text-red-700">{{ $message }}</p>
    @enderror
</div>

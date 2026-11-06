@props(['selected' => false, 'add' => false])

{{-- Figma "Chip": Default, Selected, and the dashed "+ Add new" chip every picker ends with. --}}
<button
    type="button"
    @if ($selected) aria-pressed="true" @endif
    {{ $attributes->class([
        'flex items-center rounded-full border px-14 py-9 text-13 leading-18',
        'border-dashed border-blue-600 bg-grey-0 font-semibold text-blue-700' => $add,
        'border-solid border-blue-600 bg-blue-50 font-semibold text-blue-900' => ! $add && $selected,
        'border-solid border-grey-300 bg-grey-0 font-medium text-grey-900' => ! $add && ! $selected,
    ]) }}
>{{ $slot }}</button>

<button
    type="button"
    aria-label="Add"
    x-data
    x-on:click="Livewire.dispatch('open-add-menu')"
    {{ $attributes->class('flex size-56 shrink-0 items-center justify-center rounded-full bg-blue-600 text-grey-0 shadow-fab') }}
>
    <x-icon name="fab-plus" class="size-26" />
</button>

@php use App\Support\Money; @endphp

<div class="flex flex-col gap-14" wire:key="transactions">
    <x-month-switcher :label="$month->format('F Y')" :can-next="$monthsBack > 0" />

    <div class="flex items-start gap-10 whitespace-nowrap">
        <div class="flex min-w-px flex-1 flex-col gap-2 rounded-14 bg-green-50 p-12 text-green-700">
            <p class="text-12 font-medium leading-16">Income</p>
            <p class="text-16 font-bold leading-22">{{ Money::format($summary->income, withSign: true) }}</p>
        </div>
        <div class="flex min-w-px flex-1 flex-col gap-2 rounded-14 bg-red-50 p-12 text-red-700">
            <p class="text-12 font-medium leading-16">Expenses</p>
            <p class="text-16 font-bold leading-22">{{ $summary->spent > 0 ? Money::format(-$summary->spent) : Money::SYMBOL.'0' }}</p>
        </div>
    </div>

    <div class="-mx-20 flex gap-8 overflow-x-auto px-20 [scrollbar-width:none]">
        @foreach ($filters as $key => [$label])
            <x-chip class="shrink-0 whitespace-nowrap" :selected="$activeFilter === $key" wire:click="setFilter('{{ $key }}')">{{ $label }}</x-chip>
        @endforeach
    </div>

    @if ($categories->isNotEmpty())
        <div class="-mx-20 flex gap-8 overflow-x-auto px-20 [scrollbar-width:none]">
            <x-chip class="shrink-0 whitespace-nowrap" :selected="$categoryId === null" wire:click="$set('categoryId', null)">All categories</x-chip>
            @foreach ($categories as $category)
                <x-chip class="shrink-0 whitespace-nowrap" :selected="$categoryId === $category->id" wire:click="$set('categoryId', {{ $category->id }})">{{ $category->name }}</x-chip>
            @endforeach
        </div>
    @endif

    @forelse ($days as $day)
        <div class="flex flex-col gap-4 rounded-20 border border-solid bg-grey-0 px-16 py-12" wire:key="day-{{ $day['label'] }}-{{ $loop->index }}">
            <div class="flex items-center justify-between whitespace-nowrap text-12 leading-17 text-grey-700">
                <p class="font-semibold tracking-label">{{ $day['label'] }}</p>
                <p>{{ $day['spent'] > 0 ? 'Spent '.Money::format($day['spent']) : '' }}</p>
            </div>
            @foreach ($day['entries'] as $entry)
                <x-txn-row :entry="$entry" :compact="true" wire:key="entry-{{ $entry->id }}" />
            @endforeach
        </div>
    @empty
        @if ($filtered)
            <x-empty-state title="Nothing matches this filter" body="Try another type or category." />
        @else
            <x-empty-state :title="'No entries in '.$month->format('F Y')" body="Entries you add will show here. Tap + to add one." />
        @endif
    @endforelse
</div>

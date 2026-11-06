<div class="flex flex-col gap-12" wire:key="notifications">
    @forelse ($items as $item)
        <a href="{{ route($item['route']) }}" wire:navigate wire:key="alert-{{ $loop->index }}">
            <x-notification :level="$item['level']" :title="$item['title']" :body="$item['body']" />
        </a>
    @empty
        <x-empty-state title="You're all caught up" body="Budget alerts, due dates and salary reminders will show here." />
    @endforelse

    @if ($items->isNotEmpty())
        <p class="w-full text-center text-12 leading-17 text-grey-700">Alerts are worked out from your entries.</p>
    @endif
</div>

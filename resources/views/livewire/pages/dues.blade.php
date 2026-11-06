@php use App\Support\Money; @endphp

<div class="flex flex-col gap-16" wire:key="dues">
    <div class="flex items-start gap-12 whitespace-nowrap">
        <div class="flex min-w-px flex-1 flex-col gap-4 rounded-18 bg-green-50 p-14 text-green-700">
            <p class="text-13 font-medium leading-18">You'll get back</p>
            <p class="text-20 font-bold leading-28">{{ Money::format($toGet) }}</p>
        </div>
        <div class="flex min-w-px flex-1 flex-col gap-4 rounded-18 bg-red-50 p-14 text-red-700">
            <p class="text-13 font-medium leading-18">You need to pay</p>
            <p class="text-20 font-bold leading-28">{{ Money::format($toPay) }}</p>
        </div>
    </div>

    <div class="flex items-center gap-10">
        <x-button variant="secondary" wire:click="$dispatch('open-lend-borrow', { direction: 'lent' })" class="min-w-px flex-1">+ Lend money</x-button>
        <x-button variant="secondary" wire:click="$dispatch('open-lend-borrow', { direction: 'borrowed' })" class="min-w-px flex-1">+ Borrow money</x-button>
    </div>

    <div role="tablist" class="flex items-start rounded-12 bg-grey-100 p-4">
        @foreach (['lent' => 'Lent ('.$lent->count().')', 'borrowed' => 'Borrowed ('.$borrowed->count().')'] as $key => $label)
            <button
                type="button"
                role="tab"
                aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                wire:click="setTab('{{ $key }}')"
                @class([
                    'flex h-40 min-w-px flex-1 items-center justify-center rounded-10 text-14 font-semibold leading-20',
                    'bg-grey-0 text-blue-700 shadow-tab' => $tab === $key,
                    'text-grey-700' => $tab !== $key,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>

    @forelse ($rows as $row)
        @php
            $due = $row['due'];
            $isLent = $due->direction === \App\Enums\DueDirection::Lent;
            $status = $row['status'];
            $percent = $due->amount > 0 ? min(100, (int) floor($row['paid'] * 100 / $due->amount)) : 0;
            $badge = match ($status) {
                'late' => ['Late', 'bg-red-50 text-red-700'],
                'settled' => ['Settled', 'bg-green-50 text-green-700'],
                'partly' => ['Partly paid', 'bg-yellow-50 text-orange-900'],
                default => null,
            };
            $meta = ($isLent ? 'Lent' : 'Borrowed').' · '.match (true) {
                $status === 'late' => 'was due '.$due->due_date?->format('M j').' · late',
                $due->due_date !== null => ($isLent ? 'return by ' : 'return by ').$due->due_date->format('M j'),
                default => $due->created_at->format('M j'),
            };
        @endphp

        <x-card class="gap-10" wire:key="due-{{ $due->id }}">
            <div class="flex items-start justify-between gap-8">
                <div class="flex min-w-px flex-1 flex-col gap-2">
                    <p class="truncate text-15 font-semibold leading-21 text-grey-900">{{ $due->person }}</p>
                    <p class="text-12 leading-17 {{ $status === 'late' ? 'text-red-700' : 'text-grey-700' }}">{{ $meta }}</p>
                    @if ($due->note)
                        <p class="text-13 leading-18 text-grey-800">Note: {{ $due->note }}</p>
                    @endif
                </div>
                @if ($badge)
                    <span class="shrink-0 rounded-full px-8 py-4 text-11 font-semibold leading-14 {{ $badge[1] }}">{{ $badge[0] }}</span>
                @endif
            </div>

            <div class="flex h-6 w-full items-start overflow-clip rounded-full bg-grey-100">
                <div class="h-6 shrink-0 rounded-full {{ $status === 'settled' ? 'bg-green-700' : 'bg-blue-600' }}" style="width: {{ $percent }}%"></div>
            </div>

            <div class="flex items-center justify-between whitespace-nowrap text-13 leading-18">
                <p class="flex gap-4"><span class="font-semibold text-grey-900">{{ Money::format($row['paid']) }}</span> <span class="text-grey-700">{{ $isLent ? 'returned' : 'repaid' }} of {{ Money::format((int) $due->amount) }}</span></p>
                @if ($status !== 'settled')
                    <button type="button" wire:click="$dispatch('open-payment', { dueId: {{ $due->id }} })" class="font-semibold text-blue-700">{{ $isLent ? 'Record return' : 'Repay' }}</button>
                @endif
            </div>
        </x-card>
    @empty
        <x-empty-state :title="$tab === 'lent' ? 'Nothing lent yet' : 'Nothing borrowed yet'" body="Tap + Lend money or + Borrow money to keep track of money between you and other people." />
    @endforelse
</div>

@props(['row'])

@php
    use App\Services\Wallet\Data\BudgetRow;

    $state = $row->state();
    $bar = match ($state) {
        BudgetRow::Over => 'bg-red-500',
        BudgetRow::Warning => 'bg-yellow-600',
        default => 'bg-blue-600',
    };
    $ink = match ($state) {
        BudgetRow::Over => 'text-red-700',
        BudgetRow::Warning => 'text-orange-900',
        default => 'text-grey-700',
    };
    $money = fn (int $poisha): string => \App\Support\Money::format($poisha);
@endphp

{{-- Figma "Budget Row": blue under 80%, yellow at 80-100%, red when over. --}}
<div {{ $attributes->class('flex w-full flex-col items-start gap-6') }}>
    <div class="flex w-full items-center justify-between font-semibold">
        <span class="text-14 leading-20 text-grey-900">{{ $row->name }}</span>
        <span class="text-12 leading-17 {{ $ink }}">{{ $row->percent() }}% used</span>
    </div>
    <div class="flex h-6 w-full items-start overflow-clip rounded-full bg-grey-100">
        <div class="h-6 shrink-0 rounded-full {{ $bar }}" style="width: {{ $row->barPercent() }}%"></div>
    </div>
    <p class="text-12 leading-17 text-grey-700">{{ $money($row->spent) }} of {{ $money($row->limit) }} · {{ $state === BudgetRow::Over ? $money(abs($row->left())).' over' : $money($row->left()).' left' }}</p>
</div>

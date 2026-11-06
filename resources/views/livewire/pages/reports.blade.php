@php
    use App\Support\Money;

    $monthName = $month->format('F Y');
    $income = $report['income'];
    $pct = fn (int $part): float => $income > 0 ? round($part * 100 / $income, 1) : 0.0;
    $parts = [
        ['Saved', max(0, $report['saved']), 'bg-green-700'],
        ['Spent', max(0, $report['spent']), 'bg-blue-600'],
        ['Not used yet', max(0, $report['not_used']), 'bg-grey-300'],
    ];
    $rate = $report['savings_rate'];
@endphp

<div class="flex flex-col gap-16" wire:key="reports">
    <x-month-switcher :label="$monthName" :can-next="$monthsBack > 0" />

    <x-card>
        <p class="text-16 font-semibold leading-22 text-grey-900">Where your income went</p>

        @if ($income <= 0)
            <p class="text-12 leading-17 text-grey-700">Add this month's income to see how much you saved, spent and kept.</p>
        @else
            <div class="flex h-14 w-full items-start gap-2 overflow-clip rounded-full">
                @foreach ($parts as [$label, $amount, $color])
                    @if ($amount > 0)
                        <div class="h-14 shrink-0 {{ $color }}" style="width: {{ round($amount * 100 / max(1, array_sum(array_column($parts, 1))), 2) }}%"></div>
                    @endif
                @endforeach
            </div>

            @foreach ($parts as [$label, $amount, $color])
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-8"><span class="size-10 rounded-3 {{ $color }}"></span><span class="text-13 leading-18 text-grey-900">{{ $label }}</span></div>
                    <span class="whitespace-nowrap text-13 font-semibold leading-18 text-grey-900">{{ Money::format($amount) }} · {{ number_format($pct($amount), 1) }}%</span>
                </div>
            @endforeach

            @if ($rate !== null)
                @if ($rate >= 20)
                    <div class="rounded-12 bg-green-50 px-12 py-10 text-13 leading-18 text-green-700">Savings rate {{ number_format($rate, 1) }}% of {{ Money::format($income) }} income. 20% or more is a healthy target.</div>
                @else
                    <div class="rounded-12 bg-yellow-50 px-12 py-10 text-13 leading-18 text-orange-900">Savings rate {{ number_format($rate, 1) }}% of {{ Money::format($income) }} income. Aim for 20% or more.</div>
                @endif
            @endif
        @endif
    </x-card>

    <x-card>
        <div class="flex items-center justify-between font-semibold text-grey-900">
            <p class="text-16 leading-22">Expenses by category</p>
            <p class="text-14 leading-20">{{ Money::format($report['spent']) }}</p>
        </div>

        @forelse ($report['expenses'] as $row)
            <div class="flex w-full flex-col gap-6">
                <div class="flex items-start justify-between gap-8">
                    <p class="shrink-0 text-14 font-medium leading-20 text-grey-900">{{ $row['name'] }}</p>
                    <p class="min-w-px text-right text-13 leading-18 text-grey-700">{{ number_format($row['percent'], 1) }}% · {{ Money::format($row['total']) }}@if ($row['limit'] !== null) of {{ Money::format($row['limit']) }} limit @endif</p>
                </div>
                <div class="flex h-8 w-full items-start overflow-clip rounded-full bg-grey-100">
                    <div class="h-8 shrink-0 rounded-full {{ $row['over'] ? 'bg-red-700' : 'bg-blue-600' }}" style="width: {{ min(100, $row['percent']) }}%"></div>
                </div>
            </div>
        @empty
            <p class="text-12 leading-17 text-grey-700">No expenses in {{ $monthName }}.</p>
        @endforelse
    </x-card>

    <x-card>
        <p class="text-16 font-semibold leading-22 text-grey-900">Income by source</p>

        @forelse ($report['sources'] as $row)
            <div class="flex items-center justify-between whitespace-nowrap text-14 leading-20">
                <p class="font-medium text-grey-900">{{ $row['name'] }}</p>
                <p class="font-semibold text-green-700">{{ Money::format($row['total']) }} · {{ number_format($row['percent'], 1) }}%</p>
            </div>
        @empty
            <p class="text-12 leading-17 text-grey-700">No income in {{ $monthName }}.</p>
        @endforelse
    </x-card>
</div>

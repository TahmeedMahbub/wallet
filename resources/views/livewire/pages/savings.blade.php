@php use App\Support\Money; @endphp

<div class="flex flex-col gap-16" wire:key="savings">
    <x-button variant="secondary" wire:click="$dispatch('open-goal-wizard')">+ New goal</x-button>

    @if ($goals->isEmpty())
        <x-empty-state title="No goals yet" body="Create a goal for something you are saving for: pick a reason, an amount and where you will keep the money." />
    @else
        <div class="flex flex-col gap-14 rounded-24 bg-blue-800 p-20">
            <div class="flex flex-col gap-2 whitespace-nowrap">
                <p class="text-13 leading-18 text-blue-100">Total savings · {{ $goals->count() }} {{ $goals->count() === 1 ? 'goal' : 'goals' }}</p>
                <p class="text-34 font-bold leading-48 text-grey-0">{{ Money::format($total) }}</p>
            </div>

            @if ($autoSaveTotal > 0)
                <div class="flex items-center gap-10 rounded-14 bg-grey-0/10 px-12 py-10">
                    <x-icon name="repeat" class="size-18 text-grey-0" />
                    <p class="min-w-px flex-1 text-13 leading-18 text-grey-0">{{ Money::format($autoSaveTotal) }} moves to your goals automatically when you add your salary. No daily entry needed.</p>
                </div>
            @endif

            @if ($withdrawable->isNotEmpty())
                <button type="button" wire:click="chooseWithdraw" class="flex h-44 w-full items-center justify-center rounded-12 bg-grey-0 text-14 font-semibold leading-20 text-blue-800">Withdraw to wallet</button>
            @endif
        </div>

        @foreach ($goals as $row)
            @php
                $goal = $row['goal'];
                $kindStyle = match ($goal->kind->value) {
                    'yearly' => 'bg-yellow-50 text-orange-900',
                    'lifetime' => 'bg-green-50 text-green-700',
                    default => 'bg-blue-50 text-blue-900',
                };
                $when = match ($goal->kind->value) {
                    'yearly' => 'every year',
                    'lifetime' => 'no deadline',
                    default => 'in '.$goal->months.' '.($goal->months === 1 ? 'month' : 'months'),
                };
            @endphp

            <x-card class="gap-10" wire:key="goal-{{ $goal->id }}">
                <div class="flex items-start justify-between gap-8">
                    <div class="flex min-w-px flex-1 flex-col gap-4">
                        <p class="text-15 font-semibold leading-21 text-grey-900">{{ $goal->reason->name }}</p>
                        <p class="text-12 leading-17 text-grey-700">{{ Money::format((int) $goal->monthly_amount) }}/month ({{ $goal->auto_save ? 'auto' : 'manual' }}) · {{ $goal->place->name }} · {{ $when }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-8 py-4 text-11 font-semibold leading-14 {{ $kindStyle }}">{{ $goal->kind->label() }}</span>
                </div>

                <div class="flex h-8 w-full items-start overflow-clip rounded-full bg-grey-100">
                    <div class="h-8 shrink-0 rounded-full {{ $row['reached'] ? 'bg-green-700' : 'bg-blue-600' }}" style="width: {{ $row['percent'] }}%"></div>
                </div>

                <div class="flex items-center justify-between whitespace-nowrap text-13 leading-18">
                    <p class="flex gap-4"><span class="font-semibold text-grey-900">{{ Money::format($row['balance']) }}</span> <span class="text-grey-700">of {{ Money::format((int) $goal->target_amount) }} · {{ $row['percent'] }}%</span></p>
                    @if ($row['reached'])
                        <p class="font-semibold text-green-700">Reached ✓</p>
                    @else
                        <button type="button" wire:click="$dispatch('open-goal-money', { goalId: {{ $goal->id }}, mode: 'add' })" class="font-semibold text-blue-700">+ Add money</button>
                    @endif
                </div>
            </x-card>
        @endforeach

        @if ($places->isNotEmpty())
            <x-card>
                <p class="text-16 font-semibold leading-22 text-grey-900">Where your savings are kept</p>
                @foreach ($places as $row)
                    <div class="flex flex-col gap-6">
                        <div class="flex items-center justify-between whitespace-nowrap text-14 leading-20 text-grey-900">
                            <p class="font-medium">{{ $row['place']->name }}</p>
                            <p class="font-semibold">{{ Money::format($row['total']) }} · {{ $total > 0 ? number_format($row['total'] * 100 / $total, 1) : '0.0' }}%</p>
                        </div>
                        <div class="flex h-6 w-full items-start overflow-clip rounded-full bg-grey-100">
                            <div class="h-6 shrink-0 rounded-full bg-green-700" style="width: {{ $total > 0 ? round($row['total'] * 100 / $total, 2) : 0 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </x-card>
        @endif
    @endif

    @if ($choosing)
        <x-sheet title="Withdraw to wallet" close="closeChooser">
            <p class="text-12 leading-17 text-grey-700">Only goals that hold money are listed.</p>
            <div class="flex flex-col gap-10">
                @foreach ($withdrawable as $row)
                    <button type="button" wire:click="withdrawFrom({{ $row['goal']->id }})" class="flex items-center justify-between rounded-14 border border-solid bg-grey-0 px-14 py-12 text-left">
                        <span class="text-14 font-semibold leading-20 text-grey-900">{{ $row['goal']->reason->name }}</span>
                        <span class="text-14 font-semibold leading-20 text-grey-700">{{ Money::format($row['balance']) }}</span>
                    </button>
                @endforeach
            </div>
        </x-sheet>
    @endif
</div>

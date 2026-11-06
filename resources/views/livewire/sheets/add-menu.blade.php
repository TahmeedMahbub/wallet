<div>
    @if ($open)
        <x-sheet title="Add">
            @php
                $options = [
                    ['Expense', 'Money spent', '−', 'bg-red-50 text-red-700', 'open-expense', ''],
                    ['Income', 'Salary, bonus, salami', '+', 'bg-green-50 text-green-700', 'open-income', ''],
                    ['Transfer', 'Between accounts', '⇄', 'bg-blue-50 text-blue-700', 'open-move-money', ''],
                    ['Save', 'New savings goal', '◎', 'bg-blue-50 text-blue-700', 'open-goal-wizard', ''],
                    ['Lend', 'Give to someone', '↗', 'bg-yellow-50 text-orange-900', 'open-lend-borrow', 'lent'],
                    ['Borrow', 'Take a loan', '↙', 'bg-yellow-50 text-orange-900', 'open-lend-borrow', 'borrowed'],
                ];
            @endphp

            <div class="flex flex-wrap content-start items-start gap-10">
                @foreach ($options as [$label, $hint, $glyph, $tint, $event, $direction])
                    <button type="button" wire:click="choose('{{ $event }}', '{{ $direction }}')" class="flex w-[calc((100%-20px)/3)] min-w-100 flex-col items-center gap-8 rounded-16 border border-solid bg-grey-0 px-6 py-14">
                        <span class="flex size-40 items-center justify-center rounded-full text-18 font-bold leading-25 {{ $tint }}">{{ $glyph }}</span>
                        <span class="whitespace-nowrap text-13 font-semibold leading-18 text-grey-900">{{ $label }}</span>
                        <span class="text-center text-11 leading-15 text-grey-700">{{ $hint }}</span>
                    </button>
                @endforeach
            </div>
        </x-sheet>
    @endif
</div>

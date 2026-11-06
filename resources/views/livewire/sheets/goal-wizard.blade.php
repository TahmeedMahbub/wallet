<div>
    @if ($open)
        @php
            $reasonName = $reasons->firstWhere('id', $reasonId)?->name ?? '';
            $placeName = $places->firstWhere('id', $placeId)?->name ?? '';
            $kindEnum = \App\Enums\GoalKind::tryFrom($kind) ?? \App\Enums\GoalKind::Monthly;
            $monthlyText = \App\Support\Money::format(\App\Support\Money::parse($monthly) ?? 0);
            $targetText = \App\Support\Money::format(\App\Support\Money::parse($target) ?? 0);
        @endphp

        <x-sheet title="New savings goal">
            <div class="flex shrink-0 items-center gap-6" aria-label="Step {{ $step }} of 3">
                @foreach ([1, 2, 3] as $number)
                    <div @class(['h-4 min-w-px flex-1 rounded-full', 'bg-blue-600' => $number <= $step, 'bg-grey-300' => $number > $step])></div>
                @endforeach
            </div>

            @if ($step === 1)
                <p class="text-15 font-semibold leading-21 text-grey-900">What are you saving for?</p>

                <x-chip-group label="" :error="$errors->first('reasonId')">
                    @foreach ($reasons as $reason)
                        <x-chip :selected="$reasonId === $reason->id" wire:click="$set('reasonId', {{ $reason->id }})">{{ $reason->name }}</x-chip>
                    @endforeach
                    <x-chip :add="true" wire:click="$set('adding', 'reason')">+ Add new</x-chip>
                </x-chip-group>

                <x-button wire:click="next">Next</x-button>
            @elseif ($step === 2)
                <p class="text-15 font-semibold leading-21 text-grey-900">{{ $reasonName }}: how much and how long?</p>

                <x-field label="TARGET AMOUNT (৳)" name="target" size="amount" :money="true" placeholder="0" wire:model.live.debounce.400ms="target" />

                <x-chip-group label="DURATION">
                    @foreach ($kinds as $goalKind)
                        <x-chip :selected="$kind === $goalKind->value" wire:click="$set('kind', '{{ $goalKind->value }}')">{{ $goalKind->label() }}</x-chip>
                    @endforeach
                </x-chip-group>

                <p class="text-12 leading-17 text-grey-700">
                    {{ match ($kindEnum) {
                        \App\Enums\GoalKind::Monthly => 'Short goal with an end date, e.g. a tour in 6 months.',
                        \App\Enums\GoalKind::Yearly => 'Repeats every year, like Qurbani or Zakat.',
                        \App\Enums\GoalKind::Lifetime => 'No deadline, like Hajj. Pick how much you can save each month.',
                    } }}
                </p>

                @if ($kindEnum->hasMonths())
                    <x-field label="IN HOW MANY MONTHS?" name="months" inputmode="numeric" placeholder="6" wire:model.live.debounce.400ms="months" />
                @endif

                <x-field label="SAVE EVERY MONTH (৳)" name="monthly" :money="true" placeholder="0" wire:model.live.debounce.400ms="monthly" />

                @if ($suggestion > 0)
                    <p class="text-12 leading-17 text-grey-700">Suggested: {{ \App\Support\Money::format($suggestion) }}/month to reach {{ $targetText }} in {{ (int) $months }} months.</p>
                @endif

                <div class="flex items-center gap-10">
                    <x-button variant="secondary" wire:click="back" class="flex-1">Back</x-button>
                    <x-button wire:click="next" class="flex-1">Next</x-button>
                </div>
            @else
                <p class="text-15 font-semibold leading-21 text-grey-900">Where will you keep this money?</p>

                <x-chip-group label="" :error="$errors->first('placeId')">
                    @foreach ($places as $place)
                        <x-chip :selected="$placeId === $place->id" wire:click="$set('placeId', {{ $place->id }})">{{ $place->name }}</x-chip>
                    @endforeach
                    <x-chip :add="true" wire:click="$set('adding', 'place')">+ Add new</x-chip>
                </x-chip-group>

                <div class="flex items-center gap-12 rounded-14 border border-solid bg-grey-0 p-14">
                    <div class="flex min-w-px flex-1 flex-col gap-2">
                        <p class="text-14 font-semibold leading-20 text-grey-900">Auto-deduct from salary</p>
                        <p class="text-12 leading-17 text-grey-700">Moves {{ $monthlyText }} to this goal every time you add your salary.</p>
                    </div>
                    <x-toggle :on="$autoSave" label="Auto-deduct from salary" wire:click="$toggle('autoSave')" />
                </div>

                <div class="rounded-12 bg-grey-50 px-12 py-10 text-13 leading-18 text-grey-800">
                    {{ $reasonName }} · {{ $kindEnum->label() }} · target {{ $targetText }} · {{ $monthlyText }}/month · kept in {{ $placeName }} · {{ $autoSave ? 'auto-moved on salary day' : 'added manually' }}
                </div>

                @if ($errors->any())
                    <x-info variant="bad">{{ $errors->first() }}</x-info>
                @endif

                <div class="flex items-center gap-10">
                    <x-button variant="secondary" wire:click="back" class="flex-1">Back</x-button>
                    <x-button wire:click="create" wire:loading.attr="disabled" wire:target="create" class="flex-1">Create goal</x-button>
                </div>
            @endif
        </x-sheet>

        @if ($adding !== '')
            <x-sheet :title="$adding === 'place' ? 'New place' : 'New reason'" close="cancelAdd" :level="2">
                <form wire:submit="addNew" class="flex flex-col gap-16" novalidate>
                    <x-field :label="$adding === 'place' ? 'PLACE NAME' : 'REASON NAME'" name="newName" :placeholder="$adding === 'place' ? 'e.g. Savings account' : 'e.g. Wedding'" wire:model="newName" :autofocus="true" />
                    <div class="flex items-center gap-10">
                        <x-button variant="secondary" wire:click="cancelAdd" class="flex-1">Cancel</x-button>
                        <x-button type="submit" class="flex-1">Add</x-button>
                    </div>
                </form>
            </x-sheet>
        @endif
    @endif
</div>

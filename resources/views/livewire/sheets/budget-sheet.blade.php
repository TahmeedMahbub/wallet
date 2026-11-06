<div>
    @if ($open)
        <x-sheet title="Category budgets">
            <form wire:submit="save" class="flex flex-col gap-16" novalidate>
                <p class="text-12 leading-17 text-grey-700">Monthly limit for each category. Leave empty for no limit.</p>

                @foreach ($categories as $category)
                    <div class="flex items-center justify-between gap-12" x-data="moneyField()">
                        <label for="limit-{{ $category->id }}" class="flex min-w-px flex-col gap-2">
                            <span class="truncate text-14 font-semibold leading-20 text-grey-900">{{ $category->name }}</span>
                            <span class="text-12 leading-17 text-grey-700">Spent {{ \App\Support\Money::format((int) ($spent[$category->id] ?? 0)) }} this month</span>
                        </label>
                        <div class="flex h-48 w-132 shrink-0 items-center rounded-12 border border-solid bg-grey-0 px-14 focus-within:border-2 focus-within:border-blue-600">
                            <input
                                id="limit-{{ $category->id }}"
                                type="text"
                                inputmode="decimal"
                                placeholder="No limit"
                                wire:model.live.debounce.400ms="limits.{{ $category->id }}"
                                x-on:input="format($el)"
                                class="w-full min-w-0 bg-transparent text-15 leading-21 text-grey-900 outline-none placeholder:text-grey-500"
                            >
                        </div>
                    </div>
                @endforeach

                @if ($spendingBudget > 0 && $total > $spendingBudget)
                    <x-info variant="warn">Limits add up to {{ \App\Support\Money::format($total) }}. Your spending budget this month is {{ \App\Support\Money::format($spendingBudget) }}. That is more than you can spend.</x-info>
                @endif

                <x-button type="submit" wire:loading.attr="disabled" wire:target="save">Save budgets</x-button>
            </form>
        </x-sheet>
    @endif
</div>

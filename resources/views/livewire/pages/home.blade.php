@php
    use App\Support\Money;

    $money = fn (int $poisha, bool $sign = false): string => Money::format($poisha, withSign: $sign, hidden: false);
    $monthLabel = $now->format('F Y');
    $daysLeft = $summary->daysLeft();
    $daysText = $daysLeft === 1 ? '1 day left' : $daysLeft.' days left';
    $over = $summary->isOverBudget();
    $nudge = $summary->hasIncome() && ($over || $summary->isPaceWarning());
@endphp

<div class="flex flex-col gap-16" wire:key="home">
    {{-- Hero --}}
    @if (! $summary->hasIncome())
        <div class="flex flex-col gap-14 rounded-24 bg-blue-800 p-20">
            <p class="text-13 font-medium leading-18 text-blue-100">{{ $monthLabel }} · {{ $daysText }}</p>
            <p class="text-20 font-semibold leading-28 text-grey-0">Start tracking this month</p>
            <p class="text-13 leading-18 text-blue-100">1. Set what's in your accounts right now. 2. Add this month's income to see how much you can spend.</p>
            <div class="flex items-center gap-10">
                <button type="button" wire:click="$dispatch('open-account', { id: {{ $accounts->first()?->id ?? 'null' }} })" class="flex h-44 min-w-px flex-1 items-center justify-center rounded-12 border border-solid border-grey-0 text-14 font-semibold leading-20 text-grey-0">Set balances</button>
                <button type="button" wire:click="$dispatch('open-income')" class="flex h-44 min-w-px flex-1 items-center justify-center rounded-12 bg-grey-0 text-14 font-semibold leading-20 text-blue-800">Add income</button>
            </div>
        </div>
    @else
        <div class="flex flex-col gap-14 rounded-24 bg-blue-800 p-20">
            <div class="flex items-center justify-between">
                <p class="text-13 font-medium leading-18 text-blue-100">{{ $monthLabel }} · {{ $daysText }}</p>
                <span class="rounded-full bg-grey-0/20 px-10 py-4 text-12 font-semibold leading-17 text-grey-0">Spending budget</span>
            </div>
            <div class="flex flex-col gap-2">
                <p class="text-13 leading-18 text-blue-100">Left to spend</p>
                <div class="flex items-center gap-8">
                    <p class="whitespace-nowrap text-36 font-bold leading-50 text-grey-0">{{ Money::format($summary->left(), hidden: $hidden) }}</p>
                    <button type="button" wire:click="toggleHidden" aria-label="{{ $hidden ? 'Show amounts' : 'Hide amounts' }}" class="flex size-44 shrink-0 items-center justify-center rounded-full bg-grey-0/20 text-grey-0">
                        <x-icon :name="$hidden ? 'eye' : 'eye-off'" class="size-20" />
                    </button>
                </div>
                <p class="text-13 leading-18 text-blue-100">about {{ Money::format($summary->perDay(), hidden: $hidden) }}/day</p>
            </div>
            <div class="relative flex h-10 w-full items-start rounded-full bg-grey-0/25">
                <div class="h-10 rounded-full bg-grey-0" style="width: {{ $summary->shownPercent() }}%"></div>
                <div class="absolute -top-4 h-18 w-2 rounded-2 bg-today-marker" style="left: {{ $summary->elapsedPercent() }}%"></div>
            </div>
            <div class="flex items-center justify-between text-12 leading-17 text-blue-100">
                <p>{{ $money($summary->spent) }} spent of {{ $money($summary->spendingBudget()) }}</p>
                <p>{{ $summary->shownPercent() }}% used</p>
            </div>
        </div>
    @endif

    {{-- Category budgets --}}
    <x-card>
        <div class="flex items-center justify-between font-semibold">
            <p class="text-16 leading-22 text-grey-900">Category budgets</p>
            @if ($budgets->isNotEmpty())
                <button type="button" wire:click="$dispatch('open-budgets')" class="text-13 leading-18 text-blue-700">Edit</button>
            @endif
        </div>

        @if ($budgets->isEmpty())
            <p class="text-12 leading-17 text-grey-700">Set a monthly limit for categories like Food. You'll be warned at 80% and when you go over.</p>
            <x-button variant="secondary" wire:click="$dispatch('open-budgets')">Set budgets</x-button>
        @else
            @foreach ($budgets->take(3) as $row)
                <x-budget-row :row="$row" />
            @endforeach
        @endif
    </x-card>

    {{-- Savings & Dues --}}
    <div class="flex items-stretch gap-12">
        <a href="{{ route('savings') }}" wire:navigate class="flex min-w-px flex-1 flex-col gap-6 rounded-18 bg-blue-50 p-14">
            <span class="text-13 font-semibold leading-18 text-blue-700">Savings</span>
            <span class="text-18 font-bold leading-25 text-grey-900">{{ $money($savingsTotal) }}</span>
            <span class="text-12 leading-17 text-grey-700">
                @if ($goalCount === 0) No goals yet @elseif ($autoSaveTotal > 0) {{ $money($autoSaveTotal) }} auto-saves with next salary @else {{ $goalCount }} {{ $goalCount === 1 ? 'goal' : 'goals' }} @endif
            </span>
        </a>
        <a href="{{ route('dues') }}" wire:navigate class="flex min-w-px flex-1 flex-col gap-6 rounded-18 border border-solid bg-grey-0 p-14 font-semibold">
            <span class="text-13 leading-18 text-grey-700">Lend &amp; borrow</span>
            <span class="text-14 leading-20 text-green-700">Get {{ $money($toGet) }}</span>
            <span class="text-14 leading-20 text-red-700">Owe {{ $money($toPay) }}</span>
        </a>
    </div>

    {{-- Accounts strip --}}
    <div class="flex flex-col gap-8">
        <div class="flex items-center justify-between whitespace-nowrap">
            <p class="flex gap-4 text-14 leading-20"><span class="font-semibold text-grey-900">Accounts</span> <span class="font-medium text-grey-700">· {{ Money::format($walletTotal, hidden: $hidden) }}</span></p>
            <a href="{{ route('accounts') }}" wire:navigate class="text-13 font-semibold leading-18 text-blue-700">Manage</a>
        </div>
        <div class="-mx-20 flex gap-8 overflow-x-auto px-20 pb-2 [scrollbar-width:none]">
            @foreach ($accounts as $account)
                <a href="{{ route('accounts') }}" wire:navigate class="shrink-0">
                    <x-account-pill :account="$account" :balance="(int) ($balances[$account->id] ?? 0)" :hidden="$hidden" />
                </a>
            @endforeach
        </div>
    </div>

    {{-- Recent activity --}}
    <x-card>
        <div class="flex items-center justify-between whitespace-nowrap">
            <div class="flex flex-col gap-2">
                <p class="text-16 font-semibold leading-22 text-grey-900">Recent activity</p>
                <p class="text-12 leading-17 text-grey-700">Wallet balance {{ Money::format($walletTotal, hidden: $hidden) }}</p>
            </div>
            <a href="{{ route('reports') }}" wire:navigate class="text-13 font-semibold leading-18 text-blue-700">Spending report</a>
        </div>

        @forelse ($recent as $entry)
            <x-txn-row :entry="$entry" :hide-balance="$hidden" wire:key="recent-{{ $entry->id }}" />
        @empty
            <p class="text-12 leading-17 text-grey-700">No entries yet. Tap + to add your first income or expense.</p>
        @endforelse

        @if ($recent->isNotEmpty())
            <a href="{{ route('transactions') }}" wire:navigate class="flex items-center justify-center text-13 font-semibold leading-18 text-blue-700">See all entries</a>
        @endif
    </x-card>

    {{-- Pace / over-budget nudge --}}
    @if ($nudge)
        <div
            x-data="{ key: 'wallet-nudge-{{ $now->format('Y-m-d') }}-{{ $over ? 'over' : 'pace' }}', open: false, init() { try { this.open = ! sessionStorage.getItem(this.key); } catch (e) { this.open = true; } } }"
            x-show="open"
            x-cloak
            class="fixed bottom-[calc(92px+12px)] left-1/2 z-20 flex w-[calc(100%-32px)] max-w-448 -translate-x-1/2 items-start gap-10 rounded-16 border border-solid bg-grey-0 p-13 shadow-toast"
        >
            <span class="flex size-32 shrink-0 items-center justify-center rounded-full {{ $over ? 'bg-red-50 text-red-700' : 'bg-yellow-50 text-orange-900' }}"><x-icon name="alert" class="size-18" /></span>
            <div class="flex min-w-px flex-1 flex-col gap-4">
                <p class="text-13 leading-18 text-grey-900">
                    @if ($over)
                        You are {{ $money(abs($summary->left())) }} over budget this month. This now eats into your savings.
                    @else
                        You have used {{ $summary->usedPercent() }}% of your budget, but only {{ $summary->elapsedPercent() }}% of the month has passed. Slow down a little.
                    @endif
                </p>
                <a href="{{ route('transactions') }}" wire:navigate class="text-13 font-semibold leading-18 text-blue-700">See where it went</a>
            </div>
            <button type="button" aria-label="Dismiss" x-on:click="open = false; try { sessionStorage.setItem(key, '1') } catch (e) {}" class="flex size-36 shrink-0 items-center justify-center text-grey-700"><x-icon name="x" class="size-18" /></button>
        </div>
    @endif
</div>

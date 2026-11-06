@php use App\Support\Money; @endphp

<div class="flex flex-col gap-16" wire:key="profile">
    <x-card class="h-100 flex-row items-center gap-14">
        <span class="flex size-56 shrink-0 items-center justify-center rounded-full bg-blue-100 text-22 font-bold leading-31 text-blue-700">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->name, 0, 1)) }}</span>
        <span class="flex min-w-px flex-col gap-2">
            <span class="truncate text-17 font-semibold leading-24 text-grey-900">{{ $user->name }}</span>
            <span class="truncate text-12 leading-17 text-grey-700">{{ $user->email }}</span>
        </span>
    </x-card>

    <div class="flex flex-col rounded-20 border border-solid bg-grey-0 px-16 py-4">
        @php
            $rows = [
                ['Edit profile', 'Name and email', "wire:click=\"\$dispatch('open-profile')\""],
                ['Change password', 'Keep your account safe', "wire:click=\"\$dispatch('open-password')\""],
                ['Accounts', $accountCount.' '.($accountCount === 1 ? 'account' : 'accounts').' · '.Money::format($wallet), 'href='.route('accounts')],
                ['Category budgets', $budgetCount === 0 ? 'No budgets set' : $budgetCount.' '.($budgetCount === 1 ? 'budget' : 'budgets').' set', "wire:click=\"\$dispatch('open-budgets')\""],
                ['Notifications', $attention === 0 ? 'All caught up' : $attention.' need attention', 'href='.route('notifications')],
                ['Backup & export', 'Export, restore or erase your data', "wire:click=\"\$dispatch('open-backup')\""],
            ];
        @endphp

        @foreach ($rows as [$title, $sub, $action])
            @if (str_starts_with($action, 'href='))
                <a href="{{ substr($action, 5) }}" wire:navigate class="flex min-h-56 items-center gap-8 border-b border-solid border-grey-100">
            @else
                <button type="button" {!! $action !!} class="flex min-h-56 w-full items-center gap-8 border-b border-solid border-grey-100 text-left">
            @endif
                <span class="flex min-w-px flex-1 flex-col gap-2 whitespace-nowrap">
                    <span class="text-14 font-semibold leading-20 text-grey-900">{{ $title }}</span>
                    <span class="truncate text-12 leading-17 text-grey-700">{{ $sub }}</span>
                </span>
                <x-icon name="chevron-right" class="size-18 text-grey-700" />
            @if (str_starts_with($action, 'href='))
                </a>
            @else
                </button>
            @endif
        @endforeach

        <div class="flex min-h-56 items-center gap-8">
            <span class="flex min-w-px flex-1 flex-col gap-2">
                <span class="text-14 font-semibold leading-20 text-grey-900">Hide amounts on Home</span>
                <span class="text-12 leading-17 text-grey-700">Hides left to spend and account balances</span>
            </span>
            <x-toggle :on="(bool) $user->hide_amounts" label="Hide amounts on Home" wire:click="toggleHidden" />
        </div>
    </div>

    <div class="flex flex-col rounded-20 border border-solid bg-grey-0 px-16 py-4">
        <a href="{{ route('terms') }}" class="flex min-h-56 items-center gap-8 border-b border-solid border-grey-100">
            <span class="min-w-px flex-1 text-14 font-semibold leading-20 text-grey-900">Terms of Service</span>
            <x-icon name="chevron-right" class="size-18 text-grey-700" />
        </a>
        <a href="{{ route('privacy') }}" class="flex min-h-56 items-center gap-8">
            <span class="min-w-px flex-1 text-14 font-semibold leading-20 text-grey-900">Privacy Policy</span>
            <x-icon name="chevron-right" class="size-18 text-grey-700" />
        </a>
    </div>

    <x-button variant="secondary" wire:click="logout">Log out</x-button>
</div>

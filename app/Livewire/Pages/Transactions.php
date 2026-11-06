<?php

namespace App\Livewire\Pages;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Services\Wallet\HistoryService;
use App\Services\Wallet\MonthService;
use App\Support\Months;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::app', ['title' => 'Transactions', 'nav' => 'home', 'bar' => 'back', 'barTitle' => 'Transactions'])]
class Transactions extends Component
{
    /** Months before the current one (0 = this month). */
    public int $monthsBack = 0;

    #[Url(as: 'type')]
    public string $filter = 'all';

    public ?int $categoryId = null;

    private const FILTERS = [
        'all' => ['All', null],
        'expenses' => ['Expenses', [TransactionType::Expense]],
        'income' => ['Income', [TransactionType::Income]],
        'savings' => ['Savings', [TransactionType::SavingDeposit, TransactionType::SavingWithdrawal]],
        'people' => ['Lend & borrow', [TransactionType::Lend, TransactionType::Borrow, TransactionType::DuePayment]],
        'moves' => ['Transfers & balance', [TransactionType::Transfer, TransactionType::BalanceAdjustment]],
    ];

    public function previousMonth(): void
    {
        $this->monthsBack++;
    }

    public function nextMonth(): void
    {
        $this->monthsBack = max(0, $this->monthsBack - 1);
    }

    public function setFilter(string $filter): void
    {
        $this->filter = array_key_exists($filter, self::FILTERS) ? $filter : 'all';
        $this->categoryId = null;
    }

    #[On('wallet-updated')]
    public function refresh(): void {}

    public function render()
    {
        $user = auth()->user();
        $month = Months::today($user)->startOfMonth()->subMonths($this->monthsBack);
        $filter = array_key_exists($this->filter, self::FILTERS) ? $this->filter : 'all';
        $entries = app(HistoryService::class)->entries($user, $month, self::FILTERS[$filter][1], $filter === 'expenses' ? $this->categoryId : null);
        $summary = app(MonthService::class)->summary($user, $month);
        $today = Months::today($user);

        $days = $entries->groupBy(fn ($entry) => $entry->occurred_at->setTimezone($user->timezone)->toDateString())->map(function ($rows, $date) use ($user, $today): array {
            $day = CarbonImmutable::parse($date, $user->timezone);
            $label = match (true) {
                $day->isSameDay($today) => 'TODAY',
                $day->isSameDay($today->subDay()) => 'YESTERDAY',
                default => mb_strtoupper($day->format('D, M j')),
            };

            return ['label' => $label, 'spent' => (int) $rows->where('type', TransactionType::Expense)->sum('amount'), 'entries' => $rows];
        })->values();

        return view('livewire.pages.transactions', [
            'month' => $month,
            'filters' => self::FILTERS,
            'activeFilter' => $filter,
            'categories' => $filter === 'expenses' ? Category::query()->orderBy('position')->orderBy('id')->get() : collect(),
            'summary' => $summary,
            'days' => $days,
            'filtered' => $filter !== 'all',
        ]);
    }
}

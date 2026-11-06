<?php

namespace App\Livewire\Sheets;

use App\Livewire\Concerns\InteractsWithSheet;
use App\Models\Account;
use App\Models\SavingsGoal;
use App\Services\Wallet\SavingsService;
use App\Support\Money;
use App\Support\Months;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class GoalMoney extends Component
{
    use InteractsWithSheet;

    #[Locked]
    public ?int $goalId = null;

    #[Locked]
    public string $mode = 'add';

    public string $amount = '';

    public ?int $accountId = null;

    public string $occurredAt = '';

    #[On('open-goal-money')]
    public function openSheet(int $goalId, string $mode = 'add'): void
    {
        SavingsGoal::query()->findOrFail($goalId);

        $this->beginForm();
        $this->goalId = $goalId;
        $this->mode = $mode === 'withdraw' ? 'withdraw' : 'add';
        $this->amount = '';
        $this->accountId = $this->accountOptions()->first()['account']->getKey() ?? null;
        $this->occurredAt = $this->nowInput();
    }

    public function save(SavingsService $savings): void
    {
        $goal = SavingsGoal::query()->findOrFail($this->goalId);
        $account = Account::query()->find($this->accountId) ?? new Account(['id' => 0]);
        $amount = Money::parse($this->amount) ?? 0;
        $at = Months::fromInput($this->user(), $this->occurredAt);

        $saved = $this->attempt(function () use ($savings, $goal, $account, $amount, $at): void {
            $this->mode === 'withdraw'
                ? $savings->withdraw($this->user(), $goal, $account, $amount, $at, $this->formKey)
                : $savings->deposit($this->user(), $goal, $account, $amount, $at, $this->formKey);
        });

        if ($saved) {
            $this->finish($this->mode === 'withdraw' ? 'Money moved back to your account.' : 'Money added to your goal.');
        }
    }

    public function render()
    {
        $goal = $this->open && $this->goalId ? SavingsGoal::query()->with('reason')->find($this->goalId) : null;
        $balance = $goal ? app(SavingsService::class)->goalBalance($goal->getKey()) : 0;

        return view('livewire.sheets.goal-money', [
            'goal' => $goal,
            'balance' => $balance,
            'accounts' => $this->open ? $this->accountOptions() : collect(),
        ]);
    }
}

<?php

namespace App\Livewire\Sheets;

use App\Enums\GoalKind;
use App\Livewire\Concerns\InteractsWithSheet;
use App\Models\SavingPlace;
use App\Models\SavingReason;
use App\Services\Wallet\SavingsService;
use App\Support\Money;
use Livewire\Attributes\On;
use Livewire\Component;

class GoalWizard extends Component
{
    use InteractsWithSheet;

    public int $step = 1;

    public ?int $reasonId = null;

    public string $kind = 'monthly';

    public string $target = '';

    public string $months = '';

    public string $monthly = '';

    public bool $monthlyEdited = false;

    public ?int $placeId = null;

    public bool $autoSave = true;

    public string $newName = '';

    public string $adding = '';

    protected array $errorMap = ['target_amount' => 'target', 'monthly_amount' => 'monthly', 'reason_id' => 'reasonId', 'place_id' => 'placeId'];

    #[On('open-goal-wizard')]
    public function openSheet(): void
    {
        $this->beginForm();
        $this->step = 1;
        $this->reasonId = SavingReason::query()->orderBy('position')->value('id');
        $this->placeId = SavingPlace::query()->orderBy('position')->value('id');
        $this->kind = GoalKind::Monthly->value;
        $this->target = '';
        $this->months = '6';
        $this->monthly = '';
        $this->monthlyEdited = false;
        $this->autoSave = true;
        $this->reset('newName', 'adding');
    }

    public function updatedMonthly(): void
    {
        $this->monthlyEdited = true;
    }

    /**
     * Keep the suggested monthly amount in step with target and months until the user types their own.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['target', 'months', 'kind'], true) && ! $this->monthlyEdited) {
            $this->monthly = $this->suggestion() > 0 ? Money::toGroupedInput($this->suggestion()) : '';
        }
    }

    public function suggestion(): int
    {
        if (! (GoalKind::tryFrom($this->kind)?->hasMonths() ?? false)) {
            return 0;
        }

        return SavingsService::suggestMonthly(Money::parse($this->target) ?? 0, (int) $this->months);
    }

    public function next(): void
    {
        $this->resetErrorBag();

        if ($this->step === 2) {
            if ((Money::parse($this->target) ?? 0) <= 0) {
                $this->addError('target', 'Enter a target amount to continue.');

                return;
            }

            $kind = GoalKind::tryFrom($this->kind) ?? GoalKind::Monthly;

            if ($kind->hasMonths() && (int) $this->months <= 0) {
                $this->addError('months', 'Enter how many months.');

                return;
            }

            if ((Money::parse($this->monthly) ?? 0) <= 0) {
                $this->addError('monthly', 'Enter a monthly amount.');

                return;
            }
        }

        $this->step = min(3, $this->step + 1);
    }

    public function back(): void
    {
        $this->resetErrorBag();
        $this->step = max(1, $this->step - 1);
    }

    /**
     * Add a new reason (step 1) or place (step 3) and select it.
     */
    public function addNew(): void
    {
        $name = trim($this->newName);
        $model = $this->adding === 'place' ? SavingPlace::class : SavingReason::class;

        if ($name === '') {
            $this->addError('newName', 'Enter a name.');

            return;
        }

        if ($model::query()->where('name', $name)->exists()) {
            $this->addError('newName', 'You already have one with this name.');

            return;
        }

        $created = $model::create(['name' => mb_substr($name, 0, 120), 'position' => (int) $model::query()->max('position') + 1]);
        $this->adding === 'place' ? $this->placeId = $created->getKey() : $this->reasonId = $created->getKey();
        $this->reset('newName', 'adding');
        $this->resetErrorBag();
    }

    public function cancelAdd(): void
    {
        $this->reset('newName', 'adding');
        $this->resetErrorBag('newName');
    }

    public function create(SavingsService $savings): void
    {
        $kind = GoalKind::tryFrom($this->kind) ?? GoalKind::Monthly;

        $saved = $this->attempt(fn () => $savings->createGoal($this->user(), [
            'reason_id' => $this->reasonId,
            'place_id' => $this->placeId,
            'kind' => $kind,
            'target_amount' => Money::parse($this->target) ?? 0,
            'months' => $kind->hasMonths() ? (int) $this->months : null,
            'monthly_amount' => Money::parse($this->monthly) ?? 0,
            'auto_save' => $this->autoSave,
        ], $this->formKey));

        if ($saved) {
            $this->finish('Goal created.');
        }
    }

    public function render()
    {
        return view('livewire.sheets.goal-wizard', [
            'reasons' => $this->open ? SavingReason::query()->orderBy('position')->orderBy('id')->get() : collect(),
            'places' => $this->open ? SavingPlace::query()->orderBy('position')->orderBy('id')->get() : collect(),
            'kinds' => GoalKind::cases(),
            'suggestion' => $this->open ? $this->suggestion() : 0,
        ]);
    }
}

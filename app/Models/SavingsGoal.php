<?php

namespace App\Models;

use App\Enums\GoalKind;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavingsGoal extends Model
{
    use BelongsToUser;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => GoalKind::class, 'auto_save' => 'boolean'];
    }

    /**
     * @return BelongsTo<SavingReason, $this>
     */
    public function reason(): BelongsTo
    {
        return $this->belongsTo(SavingReason::class);
    }

    /**
     * @return BelongsTo<SavingPlace, $this>
     */
    public function place(): BelongsTo
    {
        return $this->belongsTo(SavingPlace::class);
    }

    /**
     * @return HasMany<SavingsMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(SavingsMovement::class, 'goal_id');
    }
}

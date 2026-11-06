<?php

namespace App\Models\Concerns;

use App\Models\Scopes\UserScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use LogicException;

/**
 * Marks a model as personal financial data owned by exactly one user.
 *
 * Every query is scoped to the signed-in user, user_id is filled in on create,
 * and the owner can never be changed. Background jobs, which have no signed-in
 * user, must use forUser() to say whose data they are working on.
 */
trait BelongsToUser
{
    /**
     * Boot the trait: add the global scope and the ownership guards.
     */
    protected static function bootBelongsToUser(): void
    {
        static::addGlobalScope(new UserScope);

        static::creating(function (self $model): void {
            if ($model->user_id === null) {
                $userId = Auth::id();

                if ($userId === null) {
                    throw new LogicException('Cannot create '.static::class.' without a signed-in user.');
                }

                $model->user_id = $userId;
            }
        });

        static::updating(function (self $model): void {
            if ($model->isDirty('user_id')) {
                throw new LogicException('The owner of '.static::class.' cannot be changed.');
            }
        });
    }

    /**
     * The user who owns this record.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Query one user's records explicitly, without relying on the signed-in user.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->withoutGlobalScope(UserScope::class)->where($this->qualifyColumn('user_id'), $user->getKey());
    }
}

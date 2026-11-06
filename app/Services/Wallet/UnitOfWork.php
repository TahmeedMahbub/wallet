<?php

namespace App\Services\Wallet;

use App\Exceptions\DuplicateSubmission;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Runs money work for one user as a single all-or-nothing unit.
 *
 * - everything happens inside a database transaction;
 * - the user's own row is locked, so one user's writes never run at the same
 *   time (no double spending from parallel requests); other users are unaffected;
 * - the user is made the "current user", so the ownership scope applies even
 *   in queued jobs and commands;
 * - an optional idempotency key makes a repeated submission harmless.
 */
class UnitOfWork
{
    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     *
     * @throws DuplicateSubmission
     */
    public function run(User $user, Closure $work, ?string $idempotencyKey = null): mixed
    {
        $previous = Auth::user();
        Auth::setUser($user);

        try {
            return DB::transaction(function () use ($user, $work, $idempotencyKey) {
                DB::table('users')->where('id', $user->getKey())->lockForUpdate()->first();

                if ($idempotencyKey !== null) {
                    $inserted = DB::table('idempotency_keys')->insertOrIgnore([
                        'user_id' => $user->getKey(),
                        'key' => substr($idempotencyKey, 0, 64),
                        'created_at' => now(),
                    ]);

                    if ($inserted === 0) {
                        throw new DuplicateSubmission;
                    }
                }

                return $work();
            });
        } finally {
            if ($previous !== null) {
                Auth::setUser($previous);
            } else {
                Auth::forgetUser();
            }
        }
    }
}

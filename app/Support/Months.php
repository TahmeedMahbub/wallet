<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Calendar months are the user's own: they start and end at midnight in the
 * user's timezone, while timestamps are stored in UTC.
 */
final class Months
{
    /**
     * First moment of the month containing $moment, in the user's timezone.
     */
    public static function start(User $user, CarbonInterface $moment): CarbonImmutable
    {
        return CarbonImmutable::instance($moment)->setTimezone($user->timezone)->startOfMonth();
    }

    /**
     * UTC bounds [start, end) of the user's month containing $moment.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function utcRange(User $user, CarbonInterface $moment): array
    {
        $start = self::start($user, $moment);

        return [$start->utc(), $start->addMonth()->utc()];
    }

    /**
     * Today, as the user sees it.
     */
    public static function today(User $user): CarbonImmutable
    {
        return CarbonImmutable::now($user->timezone);
    }

    /**
     * Read a form date-time typed in the user's timezone as a UTC moment.
     */
    public static function fromInput(User $user, string|CarbonInterface|null $input): CarbonImmutable
    {
        if ($input === null || $input === '') {
            return CarbonImmutable::now('UTC');
        }

        if ($input instanceof CarbonInterface) {
            return CarbonImmutable::instance($input)->utc();
        }

        return CarbonImmutable::parse($input, $user->timezone)->utc();
    }
}

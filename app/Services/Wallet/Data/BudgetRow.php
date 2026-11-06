<?php

namespace App\Services\Wallet\Data;

/**
 * One category budget for a month. State follows the Figma rule: blue under
 * 80% used, yellow from 80% up to the limit, red once the limit is passed.
 */
final readonly class BudgetRow
{
    public const OnTrack = 'on_track';

    public const Warning = 'warning';

    public const Over = 'over';

    public function __construct(
        public int $categoryId,
        public string $name,
        public int $limit,
        public int $spent,
    ) {}

    public function state(): string
    {
        if ($this->limit <= 0) {
            return self::OnTrack;
        }

        if ($this->spent > $this->limit) {
            return self::Over;
        }

        return $this->spent * 100 >= $this->limit * 80 ? self::Warning : self::OnTrack;
    }

    /**
     * Whole-number percent used (not capped), as shown in "85% used".
     */
    public function percent(): int
    {
        return $this->limit > 0 ? (int) round($this->spent * 100 / $this->limit) : 0;
    }

    /**
     * Percent for the bar width, never more than 100.
     */
    public function barPercent(): int
    {
        return min(100, $this->percent());
    }

    /**
     * Money left (negative when over).
     */
    public function left(): int
    {
        return $this->limit - $this->spent;
    }
}

<?php

namespace App\Enums;

enum GoalKind: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Lifetime = 'lifetime';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Lifetime goals have no deadline, so they have no months field.
     */
    public function hasMonths(): bool
    {
        return $this !== self::Lifetime;
    }
}

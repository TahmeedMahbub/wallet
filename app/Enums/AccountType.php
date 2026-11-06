<?php

namespace App\Enums;

enum AccountType: string
{
    case Bank = 'bank';
    case MobileWallet = 'mobile_wallet';
    case Cash = 'cash';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Bank => 'Bank',
            self::MobileWallet => 'Mobile wallet',
            self::Cash => 'Cash',
            self::Card => 'Card',
        };
    }
}

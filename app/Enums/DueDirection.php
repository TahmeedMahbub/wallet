<?php

namespace App\Enums;

enum DueDirection: string
{
    case Lent = 'lent';
    case Borrowed = 'borrowed';
}

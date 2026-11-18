<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Where the money of a cash entry came from (CASH-1, CASH-6).
 */
enum FundSource: string
{
    use HasValues;

    case OutletCash = 'outlet_cash';
    case Personal = 'personal';
    case Business = 'business';

    /**
     * Human readable label (Bahasa Indonesia).
     */
    public function label(): string
    {
        return match ($this) {
            self::OutletCash => 'Kas outlet',
            self::Personal => 'Uang pribadi',
            self::Business => 'Dana usaha',
        };
    }
}

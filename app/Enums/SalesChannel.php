<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum SalesChannel: string
{
    use HasValues;

    case Offline = 'offline';
    case Gofood = 'gofood';
    case Grabfood = 'grabfood';
    case Shopeefood = 'shopeefood';

    /**
     * Human readable label (Bahasa Indonesia).
     */
    public function label(): string
    {
        return match ($this) {
            self::Offline => 'Offline',
            self::Gofood => 'GoFood',
            self::Grabfood => 'GrabFood',
            self::Shopeefood => 'ShopeeFood',
        };
    }
}

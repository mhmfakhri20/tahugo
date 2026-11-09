<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum LocationType: string
{
    use HasValues;

    case Warehouse = 'warehouse';
    case Outlet = 'outlet';

    /**
     * Human readable label (Bahasa Indonesia).
     */
    public function label(): string
    {
        return match ($this) {
            self::Warehouse => 'Gudang',
            self::Outlet => 'Outlet',
        };
    }
}

<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ProductType: string
{
    use HasValues;

    case Single = 'single';
    case Paket = 'paket';

    /**
     * Human readable label (Bahasa Indonesia).
     */
    public function label(): string
    {
        return match ($this) {
            self::Single => 'Satuan',
            self::Paket => 'Paket',
        };
    }
}

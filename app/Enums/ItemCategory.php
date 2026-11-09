<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ItemCategory: string
{
    use HasValues;

    case Tahu = 'tahu';
    case Bumbu = 'bumbu';
    case Packaging = 'packaging';
    case Lainnya = 'lainnya';

    /**
     * Human readable label (Bahasa Indonesia).
     */
    public function label(): string
    {
        return match ($this) {
            self::Tahu => 'Tahu',
            self::Bumbu => 'Bumbu',
            self::Packaging => 'Kemasan',
            self::Lainnya => 'Lainnya',
        };
    }
}

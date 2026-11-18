<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum SaleStatus: string
{
    use HasValues;

    case Completed = 'completed';
    case Void = 'void';

    /**
     * Human readable label (Bahasa Indonesia).
     */
    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Selesai',
            self::Void => 'Dibatalkan',
        };
    }

    /**
     * Variant for <x-status-badge>.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::Void => 'danger',
        };
    }
}

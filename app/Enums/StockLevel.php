<?php

namespace App\Enums;

use App\Support\Qty;

/**
 * Stock status shown as a badge (INV-1). Always rendered with text, never colour only.
 */
enum StockLevel: string
{
    case Safe = 'safe';
    case Low = 'low';
    case Out = 'out';

    /**
     * Habis when nothing is left; Stok menipis when below the minimum of that location; otherwise Aman.
     */
    public static function for(int|float|string|null $qty, int|float|string|null $min = null): self
    {
        if (Qty::cmp($qty, 0) <= 0) {
            return self::Out;
        }

        if ($min !== null && Qty::cmp($qty, $min) < 0) {
            return self::Low;
        }

        return self::Safe;
    }

    public function label(): string
    {
        return match ($this) {
            self::Safe => 'Aman',
            self::Low => 'Stok menipis',
            self::Out => 'Habis',
        };
    }

    /**
     * Variant for <x-status-badge>.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Safe => 'success',
            self::Low => 'warning',
            self::Out => 'danger',
        };
    }

    public function needsAttention(): bool
    {
        return $this !== self::Safe;
    }
}

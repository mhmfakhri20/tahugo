<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ShiftStatus: string
{
    use HasValues;

    case Open = 'open';
    case Closed = 'closed';

    /**
     * Human readable label (Bahasa Indonesia).
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Sedang buka',
            self::Closed => 'Sudah ditutup',
        };
    }

    /**
     * Variant for <x-status-badge>.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::Closed => 'muted',
        };
    }
}

<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum AttendanceType: string
{
    use HasValues;

    case ClockIn = 'clock_in';
    case ClockOut = 'clock_out';

    /**
     * Human readable label (Bahasa Indonesia).
     */
    public function label(): string
    {
        return match ($this) {
            self::ClockIn => 'Absen masuk',
            self::ClockOut => 'Absen pulang',
        };
    }
}

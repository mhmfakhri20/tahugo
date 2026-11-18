<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum CashDirection: string
{
    use HasValues;

    case In = 'in';
    case Out = 'out';

    /**
     * Human readable label (Bahasa Indonesia).
     */
    public function label(): string
    {
        return match ($this) {
            self::In => 'Pemasukan',
            self::Out => 'Pengeluaran',
        };
    }
}

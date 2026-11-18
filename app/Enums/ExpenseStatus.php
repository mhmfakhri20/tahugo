<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ExpenseStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Human readable label (Bahasa Indonesia).
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu verifikasi',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }

    /**
     * Variant for <x-status-badge> (design system: Menunggu=muted).
     */
    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'muted',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}

<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum RequestStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Approved = 'approved';
    case Shipped = 'shipped';
    case Received = 'received';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /**
     * Human readable label (Bahasa Indonesia).
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu persetujuan',
            self::Approved => 'Disetujui',
            self::Shipped => 'Dikirim',
            self::Received => 'Diterima',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
        };
    }
}

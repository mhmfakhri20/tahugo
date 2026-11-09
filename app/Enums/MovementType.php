<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum MovementType: string
{
    use HasValues;

    case PurchaseIn = 'purchase_in';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case SaleOut = 'sale_out';
    case SaleVoidIn = 'sale_void_in';
    case Adjustment = 'adjustment';
    case Waste = 'waste';

    /**
     * Human readable label (Bahasa Indonesia).
     */
    public function label(): string
    {
        return match ($this) {
            self::PurchaseIn => 'Pembelian masuk',
            self::TransferOut => 'Kirim ke outlet',
            self::TransferIn => 'Terima dari gudang',
            self::SaleOut => 'Terjual',
            self::SaleVoidIn => 'Batal jual (kembali)',
            self::Adjustment => 'Penyesuaian',
            self::Waste => 'Rusak/terbuang',
        };
    }
}

<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\InventoryException;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Qty;

/**
 * Waste (INV-9) and owner adjustments (§6.6). Both require a reason.
 */
class StockAdjustmentService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Damaged / spoiled goods. Never makes stock negative (BR-2).
     */
    public function waste(Location $location, Item $item, int|float|string $qty, string $reason, User $user): StockMovement
    {
        if (! Qty::isPositive($qty)) {
            throw new InventoryException('Jumlah rusak/terbuang harus lebih dari 0.');
        }

        $this->ensureReason($reason);

        return $this->stock->move($location, $item, MovementType::Waste, Qty::neg($qty), $user, note: $reason);
    }

    /**
     * Owner correction, signed qty. May go below zero (BR-2 exception).
     */
    public function adjust(Location $location, Item $item, int|float|string $signedQty, string $reason, User $user): StockMovement
    {
        if (! $user->isOwner()) {
            throw new InventoryException('Penyesuaian stok hanya boleh dilakukan pemilik.');
        }

        $this->ensureReason($reason);

        return $this->stock->move($location, $item, MovementType::Adjustment, $signedQty, $user, note: $reason, allowNegative: true);
    }

    private function ensureReason(string $reason): void
    {
        if (blank($reason)) {
            throw new InventoryException('Alasan wajib diisi.');
        }
    }
}

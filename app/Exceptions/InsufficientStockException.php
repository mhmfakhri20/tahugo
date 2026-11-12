<?php

namespace App\Exceptions;

use App\Models\Item;
use App\Models\Location;

/**
 * Thrown by StockService when a movement would make a balance negative (BR-2).
 */
class InsufficientStockException extends InventoryException
{
    public function __construct(
        public readonly Item $item,
        public readonly Location $location,
        public readonly string $available,
        public readonly string $requested,
    ) {
        parent::__construct(sprintf(
            'Stok %s di %s tidak cukup (tersedia %s %s, dibutuhkan %s %s).',
            $item->name,
            $location->name,
            qty($available),
            $item->unit,
            qty($requested),
            $item->unit,
        ));
    }
}

<?php

namespace App\Models;

use App\Support\Qty;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $stock_request_id
 * @property int $item_id
 * @property string $qty_requested
 * @property string|null $qty_approved
 * @property string|null $qty_shipped
 * @property string|null $qty_received
 * @property-read Item $item
 */
#[Fillable(['stock_request_id', 'item_id', 'qty_requested', 'qty_approved', 'qty_shipped', 'qty_received'])]
class StockRequestItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty_requested' => 'decimal:3',
            'qty_approved' => 'decimal:3',
            'qty_shipped' => 'decimal:3',
            'qty_received' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<StockRequest, $this>
     */
    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Shipped less than approved (INV-6).
     */
    public function isShortShipped(): bool
    {
        return $this->qty_shipped !== null && $this->qty_approved !== null
            && Qty::cmp($this->qty_shipped, $this->qty_approved) < 0;
    }

    /**
     * Received differs from shipped (INV-7).
     */
    public function hasReceiveDiscrepancy(): bool
    {
        return $this->qty_received !== null && $this->qty_shipped !== null
            && Qty::cmp($this->qty_received, $this->qty_shipped) !== 0;
    }

    /**
     * qty_received − qty_shipped (negative = less arrived than shipped).
     */
    public function receiveDifference(): string
    {
        return Qty::sub($this->qty_received, $this->qty_shipped);
    }
}

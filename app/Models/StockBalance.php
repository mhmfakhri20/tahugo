<?php

namespace App\Models;

use App\Enums\StockLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cached stock balance per (location, item). Only StockService may write it;
 * it can always be rebuilt from stock_movements (BR-3).
 *
 * @property int $id
 * @property int $location_id
 * @property int $item_id
 * @property string $qty
 * @property-read Location $location
 * @property-read Item $item
 */
#[Fillable(['location_id', 'item_id', 'qty'])]
class StockBalance extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function level(int|float|string|null $min): StockLevel
    {
        return StockLevel::for($this->qty, $min);
    }
}

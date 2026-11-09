<?php

namespace App\Models;

use Database\Factories\ItemMinStockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Minimum stock of an item at one location (MD-2).
 *
 * @property int $id
 * @property int $item_id
 * @property int $location_id
 * @property string $min_qty
 * @property-read Item $item
 * @property-read Location $location
 */
#[Fillable(['item_id', 'location_id', 'min_qty'])]
class ItemMinStock extends Model
{
    /** @use HasFactory<ItemMinStockFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_qty' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}

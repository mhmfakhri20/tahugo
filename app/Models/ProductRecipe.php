<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One BOM line: quantity of an item used per 1 unit of product (MD-4).
 *
 * @property int $id
 * @property int $product_id
 * @property int $item_id
 * @property string $qty
 * @property-read Item $item
 */
#[Fillable(['product_id', 'item_id', 'qty'])]
class ProductRecipe extends Model
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
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}

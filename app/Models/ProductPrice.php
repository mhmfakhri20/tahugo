<?php

namespace App\Models;

use App\Enums\SalesChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Selling price of a product on one sales channel (MD-3), in rupiah.
 *
 * @property int $id
 * @property int $product_id
 * @property SalesChannel $channel
 * @property int $price
 */
#[Fillable(['product_id', 'channel', 'price'])]
class ProductPrice extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => SalesChannel::class,
            'price' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

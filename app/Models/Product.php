<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Enums\SalesChannel;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Product sold at outlets (MD-3) with its recipe/BOM (MD-4).
 *
 * @property int $id
 * @property string $name
 * @property ProductType $type
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'type', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ProductPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /**
     * @return HasMany<ProductRecipe, $this>
     */
    public function recipes(): HasMany
    {
        return $this->hasMany(ProductRecipe::class);
    }

    /**
     * Price (rupiah) of this product on the given channel, or null when not set.
     */
    public function priceFor(SalesChannel $channel): ?int
    {
        return $this->prices->firstWhere('channel', $channel)?->price;
    }

    /**
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}

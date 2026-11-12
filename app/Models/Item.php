<?php

namespace App\Models;

use App\Enums\ItemCategory;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Raw material / bahan baku (MD-2).
 *
 * @property int $id
 * @property string $name
 * @property string $unit
 * @property ItemCategory $category
 * @property int $last_purchase_price
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'unit', 'category', 'last_purchase_price', 'is_active'])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ItemCategory::class,
            'last_purchase_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Minimum stock per location.
     *
     * @return HasMany<ItemMinStock, $this>
     */
    public function minStocks(): HasMany
    {
        return $this->hasMany(ItemMinStock::class);
    }

    /**
     * Stock balances of this item per location.
     *
     * @return HasMany<StockBalance, $this>
     */
    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    /**
     * @param  Builder<Item>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}

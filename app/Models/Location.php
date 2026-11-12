<?php

namespace App\Models;

use App\Enums\LocationType;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property LocationType $type
 * @property string|null $address
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int $attendance_radius_m
 * @property string|null $open_time
 * @property string|null $close_time
 * @property int|null $monthly_target
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'code', 'name', 'type', 'address', 'latitude', 'longitude',
    'attendance_radius_m', 'open_time', 'close_time', 'monthly_target', 'is_active',
])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LocationType::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'attendance_radius_m' => 'integer',
            'monthly_target' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Users assigned to this location.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Minimum stock settings for items at this location.
     *
     * @return HasMany<ItemMinStock, $this>
     */
    public function minStocks(): HasMany
    {
        return $this->hasMany(ItemMinStock::class);
    }

    public function isOutlet(): bool
    {
        return $this->type === LocationType::Outlet;
    }

    public function isWarehouse(): bool
    {
        return $this->type === LocationType::Warehouse;
    }

    /**
     * Stock balances held at this location.
     *
     * @return HasMany<StockBalance, $this>
     */
    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    /**
     * @param  Builder<Location>  $query
     */
    #[Scope]
    protected function outlets(Builder $query): void
    {
        $query->where('type', LocationType::Outlet);
    }

    /**
     * @param  Builder<Location>  $query
     */
    #[Scope]
    protected function warehouses(Builder $query): void
    {
        $query->where('type', LocationType::Warehouse);
    }

    /**
     * @param  Builder<Location>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}

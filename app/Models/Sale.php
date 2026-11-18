<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * A POS transaction (POS-1..8). Prices are snapshots in `sale_items`; stock moved via BOM (BR-1).
 *
 * @property int $id
 * @property string $number
 * @property int $location_id
 * @property int $shift_id
 * @property int $user_id
 * @property SalesChannel $channel
 * @property PaymentMethod|null $payment_method
 * @property string|null $platform_order_id
 * @property int $subtotal
 * @property int $total
 * @property int|null $cash_received
 * @property int|null $change
 * @property SaleStatus $status
 * @property string|null $void_reason
 * @property int|null $voided_by
 * @property Carbon|null $voided_at
 * @property Carbon $created_at
 * @property-read Location $location
 * @property-read Shift $shift
 * @property-read User $user
 * @property-read User|null $voider
 */
#[Fillable([
    'number', 'location_id', 'shift_id', 'user_id', 'channel', 'payment_method', 'platform_order_id',
    'subtotal', 'total', 'cash_received', 'change', 'status', 'void_reason', 'voided_by', 'voided_at',
])]
class Sale extends Model
{
    protected function casts(): array
    {
        return [
            'channel' => SalesChannel::class,
            'payment_method' => PaymentMethod::class,
            'status' => SaleStatus::class,
            'subtotal' => 'integer',
            'total' => 'integer',
            'cash_received' => 'integer',
            'change' => 'integer',
            'voided_at' => 'datetime',
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
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Ledger rows created by this sale (`sale_out`) and its void (`sale_void_in`).
     *
     * @return MorphMany<StockMovement, $this>
     */
    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function isVoid(): bool
    {
        return $this->status === SaleStatus::Void;
    }

    /**
     * "Offline · Tunai", "GoFood", ...
     */
    public function channelLabel(): string
    {
        return $this->channel->label().($this->payment_method ? ' · '.$this->payment_method->label() : '');
    }
}

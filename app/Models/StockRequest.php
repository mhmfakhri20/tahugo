<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Warehouse → outlet stock request (INV-4..INV-8).
 *
 * @property int $id
 * @property string $number
 * @property int $from_location_id
 * @property int $to_location_id
 * @property int $requested_by
 * @property RequestStatus $status
 * @property string|null $note
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int|null $rejected_by
 * @property Carbon|null $rejected_at
 * @property string|null $reject_reason
 * @property int|null $shipped_by
 * @property Carbon|null $shipped_at
 * @property string|null $ship_note
 * @property int|null $received_by
 * @property Carbon|null $received_at
 * @property string|null $receive_note
 * @property int|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property Carbon $created_at
 * @property-read Location $fromLocation
 * @property-read Location $toLocation
 * @property-read User $requester
 */
#[Fillable([
    'number', 'from_location_id', 'to_location_id', 'requested_by', 'status', 'note',
    'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'reject_reason',
    'shipped_by', 'shipped_at', 'ship_note', 'received_by', 'received_at', 'receive_note',
    'cancelled_by', 'cancelled_at',
])]
class StockRequest extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'shipped_at' => 'datetime',
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<StockRequestItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockRequestItem::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function shipper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * True when any received quantity differs from the shipped one (INV-7).
     * Requires the `items` relation.
     */
    public function hasDiscrepancy(): bool
    {
        return $this->items->contains(fn (StockRequestItem $line) => $line->hasReceiveDiscrepancy());
    }

    /**
     * Requests visible to the user: outlet staff only see their own outlet (BR-7).
     *
     * @param  Builder<StockRequest>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->isOutletStaff()) {
            $query->where('to_location_id', $user->location_id);
        }
    }

    /**
     * @param  Builder<StockRequest>  $query
     */
    #[Scope]
    protected function inStatus(Builder $query, RequestStatus $status): void
    {
        $query->where('status', $status);
    }
}

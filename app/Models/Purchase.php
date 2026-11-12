<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Raw material purchase by the warehouse (INV-3).
 *
 * @property int $id
 * @property int $location_id
 * @property string $supplier
 * @property Carbon $purchase_date
 * @property int $total
 * @property string|null $receipt_photo_path
 * @property int $user_id
 * @property Carbon $created_at
 * @property-read Location $location
 * @property-read User $user
 */
#[Fillable(['location_id', 'supplier', 'purchase_date', 'total', 'receipt_photo_path', 'user_id'])]
class Purchase extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'total' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<PurchaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * Display number, e.g. "PB-000012".
     */
    public function number(): string
    {
        return 'PB-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}

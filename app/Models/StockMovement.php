<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Stock ledger row (PRD §6.6). Immutable: positive qty = in, negative = out.
 *
 * @property int $id
 * @property int $location_id
 * @property int $item_id
 * @property MovementType $type
 * @property string $qty
 * @property string $balance_after
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $note
 * @property int $user_id
 * @property Carbon $created_at
 * @property-read Location $location
 * @property-read Item $item
 * @property-read User $user
 */
#[Fillable(['location_id', 'item_id', 'type', 'qty', 'balance_after', 'reference_type', 'reference_id', 'note', 'user_id'])]
class StockMovement extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'qty' => 'decimal:3',
            'balance_after' => 'decimal:3',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Source document (purchase, stock request, ...).
     *
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}

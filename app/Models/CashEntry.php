<?php

namespace App\Models;

use App\Enums\CashDirection;
use App\Enums\ExpenseStatus;
use App\Enums\FundSource;
use Database\Factories\CashEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A cash movement (CASH-1..7): outlet expense (shift), other income (shift),
 * business expense (no shift) or a warehouse purchase (source = purchase).
 *
 * @property int $id
 * @property int|null $location_id
 * @property int|null $shift_id
 * @property int $user_id
 * @property CashDirection $direction
 * @property int|null $category_id
 * @property int $amount
 * @property string $description
 * @property FundSource|null $fund_source
 * @property string|null $receipt_photo_path
 * @property ExpenseStatus $status
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property string|null $reject_reason
 * @property string|null $source_type
 * @property int|null $source_id
 * @property Carbon $created_at
 * @property-read Location|null $location
 * @property-read Shift|null $shift
 * @property-read User $user
 * @property-read ExpenseCategory|null $category
 * @property-read User|null $verifier
 */
#[Fillable([
    'location_id', 'shift_id', 'user_id', 'direction', 'category_id', 'amount', 'description',
    'fund_source', 'receipt_photo_path', 'status', 'verified_by', 'verified_at', 'reject_reason',
    'source_type', 'source_id',
])]
class CashEntry extends Model
{
    /** @use HasFactory<CashEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'direction' => CashDirection::class,
            'fund_source' => FundSource::class,
            'status' => ExpenseStatus::class,
            'amount' => 'integer',
            'verified_at' => 'datetime',
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
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * The record that created this entry automatically (e.g. a purchase, CASH-7).
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Display number "EXP-000123" (not stored, like PB-000001 for purchases).
     */
    public function number(): string
    {
        return ($this->direction === CashDirection::In ? 'INC-' : 'EXP-').str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function isOutletExpense(): bool
    {
        return $this->direction === CashDirection::Out && $this->shift_id !== null;
    }

    public function isPending(): bool
    {
        return $this->status === ExpenseStatus::Pending;
    }

    /**
     * Badge label: a rejected outlet expense must be accounted for by the staff (CASH-5).
     *
     * @return array{label: string, variant: string}
     */
    public function statusBadge(): array
    {
        return match ($this->status) {
            ExpenseStatus::Pending => ['label' => 'Menunggu', 'variant' => 'muted'],
            ExpenseStatus::Approved => ['label' => 'Disetujui', 'variant' => 'success'],
            ExpenseStatus::Rejected => ['label' => 'Ditolak', 'variant' => 'danger'],
        };
    }
}

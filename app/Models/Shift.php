<?php

namespace App\Models;

use App\Enums\ShiftStatus;
use App\Support\WorkTime;
use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An outlet shift (SHF-1..5). One outlet has at most one open shift.
 *
 * @property int $id
 * @property int $location_id
 * @property int $opened_by
 * @property Carbon $opened_at
 * @property int $opening_cash
 * @property int|null $closed_by
 * @property Carbon|null $closed_at
 * @property int|null $counted_cash
 * @property int|null $expected_cash
 * @property int|null $variance
 * @property string|null $closing_photo_path
 * @property ShiftStatus $status
 * @property-read Location $location
 * @property-read User $opener
 * @property-read User|null $closer
 */
#[Fillable([
    'location_id', 'opened_by', 'opened_at', 'opening_cash', 'closed_by', 'closed_at',
    'counted_cash', 'expected_cash', 'variance', 'closing_photo_path', 'status',
])]
class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'integer',
            'counted_cash' => 'integer',
            'expected_cash' => 'integer',
            'variance' => 'integer',
            'status' => ShiftStatus::class,
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
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function isOpen(): bool
    {
        return $this->status === ShiftStatus::Open;
    }

    /**
     * Minutes the shift opened after the outlet's standard opening time (null = no standard / on time).
     */
    public function openedLateMinutes(): ?int
    {
        return WorkTime::lateMinutes($this->location->open_time, $this->opened_at);
    }

    /**
     * Minutes the shift closed before the outlet's standard closing time.
     */
    public function closedEarlyMinutes(): ?int
    {
        return $this->closed_at ? WorkTime::earlyMinutes($this->location->close_time, $this->closed_at) : null;
    }

    /**
     * @param  Builder<Shift>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', ShiftStatus::Open);
    }
}

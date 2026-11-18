<?php

namespace App\Models;

use App\Enums\AttendanceType;
use App\Support\Geo;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A clock-in / clock-out with live selfie + GPS (ATT-1..5). `server_time` is always the server clock.
 *
 * @property int $id
 * @property int $user_id
 * @property int $location_id
 * @property int|null $shift_id
 * @property AttendanceType $type
 * @property Carbon $server_time
 * @property string|null $photo_path null once pruned (NFR-5)
 * @property string $latitude
 * @property string $longitude
 * @property string $accuracy_m
 * @property string $distance_m
 * @property array{late?: bool, late_minutes?: int, early_leave?: bool, early_minutes?: int}|null $flags
 * @property string|null $user_agent
 * @property-read User $user
 * @property-read Location $location
 * @property-read Shift|null $shift
 */
#[Fillable([
    'user_id', 'location_id', 'shift_id', 'type', 'server_time', 'photo_path',
    'latitude', 'longitude', 'accuracy_m', 'distance_m', 'flags', 'user_agent',
])]
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => AttendanceType::class,
            'server_time' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy_m' => 'decimal:1',
            'distance_m' => 'decimal:1',
            'flags' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function isLate(): bool
    {
        return (bool) ($this->flags['late'] ?? false);
    }

    public function isEarlyLeave(): bool
    {
        return (bool) ($this->flags['early_leave'] ?? false);
    }

    /**
     * Badge text + variant for the status of this record (status never by color only).
     *
     * @return array{label: string, variant: string}
     */
    public function statusBadge(): array
    {
        return match (true) {
            $this->isLate() => ['label' => 'Terlambat '.($this->flags['late_minutes'] ?? 0).'m', 'variant' => 'warning'],
            $this->isEarlyLeave() => ['label' => 'Pulang awal '.($this->flags['early_minutes'] ?? 0).'m', 'variant' => 'warning'],
            default => ['label' => 'Tepat waktu', 'variant' => 'success'],
        };
    }

    public function mapsUrl(): string
    {
        return Geo::mapsUrl($this->latitude, $this->longitude);
    }
}

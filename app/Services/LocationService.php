<?php

namespace App\Services;

use App\Enums\LocationType;
use App\Models\Location;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Outlets & warehouses (MD-1). Locations are never deleted, only deactivated.
 */
class LocationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function save(?Location $location, array $data): Location
    {
        $location ??= new Location;

        $type = $data['type'] instanceof LocationType ? $data['type'] : LocationType::from($data['type']);
        $isActive = (bool) ($data['is_active'] ?? true);

        if ($location->exists && ($type !== LocationType::Outlet || ! $isActive)) {
            $this->ensureNoActiveStaff($location, $isActive ? 'type' : 'is_active');
        }

        $isOutlet = $type === LocationType::Outlet;

        $location->fill([
            'code' => Str::upper(trim($data['code'])),
            'name' => trim($data['name']),
            'type' => $type,
            'address' => filled($data['address'] ?? null) ? trim($data['address']) : null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'attendance_radius_m' => (int) ($data['attendance_radius_m'] ?? 100),
            'open_time' => $isOutlet ? ($data['open_time'] ?? null) : null,
            'close_time' => $isOutlet ? ($data['close_time'] ?? null) : null,
            'monthly_target' => $isOutlet && filled($data['monthly_target'] ?? null) ? (int) $data['monthly_target'] : null,
            'is_active' => $isActive,
        ])->save();

        return $location;
    }

    public function setActive(Location $location, bool $active): void
    {
        if (! $active) {
            $this->ensureNoActiveStaff($location, 'is_active');
        }

        $location->update(['is_active' => $active]);
    }

    private function ensureNoActiveStaff(Location $location, string $field): void
    {
        $staff = $location->users()->active()->count();

        if ($staff > 0) {
            throw ValidationException::withMessages([
                $field => "Outlet ini masih punya {$staff} penjaga aktif. Pindahkan atau nonaktifkan penjaganya dulu.",
            ]);
        }
    }
}

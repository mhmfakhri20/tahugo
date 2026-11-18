<?php

namespace Database\Factories;

use App\Enums\ShiftStatus;
use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => Location::factory()->outlet(),
            'opened_by' => User::factory(),
            'opened_at' => now(),
            'opening_cash' => 100_000,
            'status' => ShiftStatus::Open,
        ];
    }

    /**
     * A shift of $outlet opened by $user.
     */
    public function atOutlet(Location $outlet, ?User $user = null): static
    {
        return $this->state(fn () => [
            'location_id' => $outlet->id,
            'opened_by' => $user?->id ?? User::factory()->outletStaff($outlet),
        ]);
    }

    public function closed(int $counted = 100_000, ?string $photo = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShiftStatus::Closed,
            'closed_by' => $attributes['opened_by'],
            'closed_at' => now(),
            'counted_cash' => $counted,
            'expected_cash' => $attributes['opening_cash'],
            'variance' => $counted - $attributes['opening_cash'],
            'closing_photo_path' => $photo,
        ]);
    }
}

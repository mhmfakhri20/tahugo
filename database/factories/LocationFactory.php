<?php

namespace Database\Factories;

use App\Enums\LocationType;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('????')),
            'name' => 'Tahu Go '.fake()->streetName(),
            'type' => LocationType::Outlet,
            'address' => fake()->address(),
            'latitude' => fake()->latitude(-7.6, -7.5),
            'longitude' => fake()->longitude(110.8, 110.9),
            'attendance_radius_m' => 100,
            'open_time' => '10:00',
            'close_time' => '21:00',
            'is_active' => true,
        ];
    }

    public function outlet(): static
    {
        return $this->state(fn () => ['type' => LocationType::Outlet]);
    }

    public function warehouse(): static
    {
        return $this->state(fn () => [
            'name' => 'Gudang '.fake()->city(),
            'type' => LocationType::Warehouse,
            'open_time' => null,
            'close_time' => null,
        ]);
    }

    /**
     * Fixed coordinates & attendance radius (ATT-2).
     */
    public function withCoordinates(?float $lat, ?float $lng, int $radius = 100): static
    {
        return $this->state(fn () => ['latitude' => $lat, 'longitude' => $lng, 'attendance_radius_m' => $radius]);
    }

    /**
     * Standard opening/closing time "HH:MM" (null = no rule) (ATT-5).
     */
    public function withHours(?string $open, ?string $close): static
    {
        return $this->state(fn () => ['open_time' => $open, 'close_time' => $close]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

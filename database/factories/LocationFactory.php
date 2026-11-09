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

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

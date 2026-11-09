<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => Str::lower(fake()->unique()->bothify('user_####??')),
            'phone' => null,
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Owner,
            'location_id' => null,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => ['role' => Role::Owner, 'location_id' => null]);
    }

    /**
     * Outlet staff bound to the given outlet (a new outlet is created when omitted).
     */
    public function outletStaff(?Location $outlet = null): static
    {
        return $this->state(fn () => [
            'role' => Role::OutletStaff,
            'location_id' => $outlet->id ?? Location::factory()->outlet(),
        ]);
    }

    public function warehouseAdmin(): static
    {
        return $this->state(fn () => ['role' => Role::WarehouseAdmin, 'location_id' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory()->outletStaff();

        return [
            'user_id' => $user,
            'location_id' => fn (array $attributes) => User::find($attributes['user_id'])->location_id,
            'type' => AttendanceType::ClockIn,
            'server_time' => now(),
            'photo_path' => null,
            'latitude' => -7.5600000,
            'longitude' => 110.8200000,
            'accuracy_m' => 10,
            'distance_m' => 5,
            'flags' => null,
            'user_agent' => 'Pest',
        ];
    }

    /**
     * Attendance of $user at their own outlet.
     */
    public function by(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id, 'location_id' => $user->location_id]);
    }

    public function clockOut(): static
    {
        return $this->state(fn () => ['type' => AttendanceType::ClockOut]);
    }
}

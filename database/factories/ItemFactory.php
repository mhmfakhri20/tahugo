<?php

namespace Database\Factories;

use App\Enums\ItemCategory;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Bahan '.fake()->unique()->words(2, true),
            'unit' => fake()->randomElement(['pcs', 'kg', 'gram', 'liter']),
            'category' => fake()->randomElement(ItemCategory::cases()),
            'last_purchase_price' => fake()->numberBetween(0, 50_000),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

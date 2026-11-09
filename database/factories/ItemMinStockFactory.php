<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemMinStock;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemMinStock>
 */
class ItemMinStockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'location_id' => Location::factory(),
            'min_qty' => fake()->numberBetween(1, 100),
        ];
    }
}

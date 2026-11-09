<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Enums\SalesChannel;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Produk '.fake()->unique()->words(2, true),
            'type' => ProductType::Single,
            'is_active' => true,
        ];
    }

    public function paket(): static
    {
        return $this->state(fn () => ['type' => ProductType::Paket]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * Give the product the same price on every sales channel.
     */
    public function withPrices(int $price = 10_000): static
    {
        return $this->afterCreating(function (Product $product) use ($price): void {
            foreach (SalesChannel::cases() as $channel) {
                $product->prices()->create(['channel' => $channel, 'price' => $price]);
            }
        });
    }
}

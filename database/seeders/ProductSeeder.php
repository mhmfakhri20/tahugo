<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Enums\SalesChannel;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Placeholder products (PRD §12). Prices are seeded as Rp0 and recipes left empty
 * until the owner fills in the real data ([TBD] §15-1).
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['name' => 'Tahu Go Reguler', 'type' => ProductType::Single],
            ['name' => 'Paket isi 5', 'type' => ProductType::Paket],
            ['name' => 'Paket isi 10', 'type' => ProductType::Paket],
        ];

        foreach ($products as $data) {
            $product = Product::firstOrCreate(['name' => $data['name']], $data + ['is_active' => true]);

            foreach (SalesChannel::cases() as $channel) {
                $product->prices()->firstOrCreate(['channel' => $channel], ['price' => 0]);
            }
        }
    }
}

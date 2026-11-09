<?php

namespace Database\Seeders;

use App\Enums\ItemCategory;
use App\Models\Item;
use Illuminate\Database\Seeder;

/**
 * Placeholder raw materials (PRD §12). Prices, bumbu variants and minimum stock are [TBD].
 */
class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Tahu mentah', 'unit' => 'pcs', 'category' => ItemCategory::Tahu],
            ['name' => 'Bumbu tabur', 'unit' => 'gram', 'category' => ItemCategory::Bumbu],
            ['name' => 'Saus', 'unit' => 'sachet', 'category' => ItemCategory::Bumbu],
            ['name' => 'Minyak goreng', 'unit' => 'liter', 'category' => ItemCategory::Lainnya],
            ['name' => 'Kemasan kecil', 'unit' => 'pcs', 'category' => ItemCategory::Packaging],
            ['name' => 'Kemasan besar', 'unit' => 'pcs', 'category' => ItemCategory::Packaging],
            ['name' => 'Plastik', 'unit' => 'pcs', 'category' => ItemCategory::Packaging],
        ];

        foreach ($items as $item) {
            Item::firstOrCreate(['name' => $item['name']], $item + ['last_purchase_price' => 0, 'is_active' => true]);
        }
    }
}

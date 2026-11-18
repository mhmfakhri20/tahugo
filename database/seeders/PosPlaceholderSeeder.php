<?php

namespace Database\Seeders;

use App\Enums\SalesChannel;
use App\Models\Item;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Development-only PLACEHOLDER prices & recipes so the cashier can be tested (F4).
 * Real prices and recipes are [TBD] (PRD §15-1). Only fills prices still at Rp0 and empty recipes,
 * so anything the owner already set is never overwritten. Not run in production.
 */
class PosPlaceholderSeeder extends Seeder
{
    /**
     * product => [prices per channel, recipe item => qty per 1 unit]
     *
     * @var array<string, array{prices: array<string, int>, recipe: array<string, int|float>}>
     */
    private const PLACEHOLDER = [
        'Tahu Go Reguler' => [
            'prices' => ['offline' => 2000, 'gofood' => 2500, 'grabfood' => 2500, 'shopeefood' => 2500],
            'recipe' => ['Tahu mentah' => 1, 'Bumbu tabur' => 5, 'Plastik' => 1],
        ],
        'Paket isi 5' => [
            'prices' => ['offline' => 10000, 'gofood' => 12500, 'grabfood' => 12500, 'shopeefood' => 12000],
            'recipe' => ['Tahu mentah' => 5, 'Bumbu tabur' => 25, 'Saus' => 1, 'Kemasan kecil' => 1],
        ],
        'Paket isi 10' => [
            'prices' => ['offline' => 18000, 'gofood' => 22000, 'grabfood' => 22000, 'shopeefood' => 21500],
            'recipe' => ['Tahu mentah' => 10, 'Bumbu tabur' => 50, 'Saus' => 2, 'Kemasan besar' => 1],
        ],
    ];

    public function run(): void
    {
        $items = Item::query()->get()->keyBy('name');

        foreach (self::PLACEHOLDER as $name => $data) {
            $product = Product::query()->where('name', $name)->first();

            if ($product === null) {
                continue;
            }

            foreach (SalesChannel::cases() as $channel) {
                $price = $product->prices()->firstOrCreate(['channel' => $channel], ['price' => 0]);

                if ($price->price === 0) {
                    $price->update(['price' => $data['prices'][$channel->value]]);
                }
            }

            if ($product->recipes()->doesntExist()) {
                foreach ($data['recipe'] as $itemName => $qty) {
                    if ($item = $items->get($itemName)) {
                        $product->recipes()->create(['item_id' => $item->id, 'qty' => $qty]);
                    }
                }
            }
        }
    }
}

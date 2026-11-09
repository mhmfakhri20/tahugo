<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Support\Facades\DB;

/**
 * Raw materials & their minimum stock per location (MD-2).
 */
class ItemService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int|string, mixed>  $minStocks  location_id => min qty (empty = no minimum)
     */
    public function save(?Item $item, array $data, array $minStocks = []): Item
    {
        $item ??= new Item;

        return DB::transaction(function () use ($item, $data, $minStocks): Item {
            $item->fill([
                'name' => trim($data['name']),
                'unit' => trim($data['unit']),
                'category' => $data['category'],
                'last_purchase_price' => (int) ($data['last_purchase_price'] ?? 0),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ])->save();

            foreach ($minStocks as $locationId => $qty) {
                if (blank($qty)) {
                    $item->minStocks()->where('location_id', $locationId)->delete();

                    continue;
                }

                $item->minStocks()->updateOrCreate(
                    ['location_id' => (int) $locationId],
                    ['min_qty' => $qty],
                );
            }

            return $item;
        });
    }

    public function setActive(Item $item, bool $active): void
    {
        $item->update(['is_active' => $active]);
    }
}

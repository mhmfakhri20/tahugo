<?php

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Models\Item;
use App\Models\ItemMinStock;
use App\Models\Location;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Database\Seeder;

/**
 * Development-only opening balances & minimum stock (PLACEHOLDER, real figures are [TBD]).
 * Opening balances go through StockService as owner `adjustment`s so the ledger stays consistent (BR-3).
 */
class StockSeeder extends Seeder
{
    /**
     * item name => [warehouse qty, outlet qty, warehouse min, outlet min]
     *
     * @var array<string, array{0: int, 1: int, 2: int, 3: int}>
     */
    private const PLACEHOLDER = [
        'Tahu mentah' => [2000, 150, 500, 100],
        'Bumbu tabur' => [5000, 400, 1000, 250],
        'Saus' => [600, 30, 100, 40],
        'Minyak goreng' => [40, 4, 10, 2],
        'Kemasan kecil' => [1000, 80, 200, 50],
        'Kemasan besar' => [800, 20, 200, 30],
        'Plastik' => [1500, 120, 300, 50],
    ];

    public function run(StockService $stock): void
    {
        $owner = User::query()->where('username', 'owner')->first();

        if ($owner === null) {
            return;
        }

        $items = Item::query()->whereIn('name', array_keys(self::PLACEHOLDER))->get()->keyBy('name');

        foreach (Location::query()->active()->get() as $location) {
            $index = $location->isWarehouse() ? 0 : 1;

            foreach ($items as $name => $item) {
                ItemMinStock::firstOrCreate(
                    ['item_id' => $item->id, 'location_id' => $location->id],
                    ['min_qty' => self::PLACEHOLDER[$name][$index + 2]],
                );
            }

            // Idempotent: skip locations that already have a ledger.
            if (StockMovement::query()->where('location_id', $location->id)->exists()) {
                continue;
            }

            $stock->moveMany(
                $items->map(fn (Item $item, string $name) => [
                    'location' => $location,
                    'item' => $item,
                    'qty' => self::PLACEHOLDER[$name][$index],
                    'note' => 'Saldo awal (placeholder)',
                ])->values()->all(),
                MovementType::Adjustment,
                $owner,
            );
        }
    }
}

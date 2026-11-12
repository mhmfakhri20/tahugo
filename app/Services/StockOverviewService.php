<?php

namespace App\Services;

use App\Enums\ItemCategory;
use App\Enums\StockLevel;
use App\Models\Item;
use App\Models\ItemMinStock;
use App\Models\Location;
use App\Models\StockBalance;
use App\Support\Qty;
use Illuminate\Support\Collection;

/**
 * Read side of the inventory (INV-1, INV-2): balances with minimum & stock level per location.
 */
class StockOverviewService
{
    /**
     * One row per (location, active item).
     *
     * @param  Collection<int, Location>  $locations
     * @return Collection<int, array{location: Location, item: Item, qty: string, min: string|null, level: StockLevel, tracked: bool}>
     */
    public function rows(Collection $locations, ?string $search = null, ?ItemCategory $category = null): Collection
    {
        $locationIds = $locations->map(fn (Location $location) => $location->id)->all();

        $items = Item::query()
            ->active()
            ->when(filled($search), fn ($query) => $query->where('name', 'like', '%'.trim($search).'%'))
            ->when($category, fn ($query) => $query->where('category', $category))
            ->orderBy('name')
            ->get();

        $balances = StockBalance::query()
            ->whereIn('location_id', $locationIds)
            ->get(['location_id', 'item_id', 'qty'])
            ->keyBy(fn ($row) => $row->location_id.':'.$row->item_id);

        $mins = ItemMinStock::query()
            ->whereIn('location_id', $locationIds)
            ->get(['location_id', 'item_id', 'min_qty'])
            ->keyBy(fn ($row) => $row->location_id.':'.$row->item_id);

        return $locations->flatMap(fn (Location $location) => $items->map(function (Item $item) use ($location, $balances, $mins): array {
            $key = $location->id.':'.$item->id;
            $balance = $balances->get($key);
            $min = $mins->get($key)?->min_qty;
            $qty = Qty::of($balance?->qty);

            return [
                'location' => $location,
                'item' => $item,
                'qty' => $qty,
                'min' => $min !== null ? Qty::of($min) : null,
                'level' => StockLevel::for($qty, $min),
                // Item is stocked or watched at this location (otherwise "Habis" is meaningless).
                'tracked' => $balance !== null || $min !== null,
            ];
        }))->values();
    }

    /**
     * Rows that need attention (Stok menipis / Habis), most critical first.
     *
     * @param  Collection<int, Location>  $locations
     * @return Collection<int, array{location: Location, item: Item, qty: string, min: string|null, level: StockLevel, tracked: bool}>
     */
    public function attention(Collection $locations): Collection
    {
        return $this->rows($locations)
            ->filter(fn (array $row) => $row['tracked'] && $row['level']->needsAttention())
            ->sortBy(fn (array $row) => [$row['level'] === StockLevel::Out ? 0 : 1, $row['location']->name, $row['item']->name])
            ->values();
    }
}

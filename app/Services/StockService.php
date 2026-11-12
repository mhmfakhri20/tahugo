<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InventoryException;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Qty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The ONLY entry point for stock changes (PRD §6.6). Every change writes a ledger row in
 * `stock_movements` and updates the cached `stock_balances` in the same DB transaction,
 * with the balance row locked (`lockForUpdate`, NFR-6).
 */
class StockService
{
    /**
     * Record one movement. $qty is signed: positive = in, negative = out.
     *
     * @throws InsufficientStockException when the balance would go below zero (BR-2)
     */
    public function move(
        Location $location,
        Item $item,
        MovementType $type,
        int|float|string $qty,
        User $user,
        ?Model $reference = null,
        ?string $note = null,
        bool $allowNegative = false,
    ): StockMovement {
        return $this->moveMany([[
            'location' => $location,
            'item' => $item,
            'qty' => $qty,
            'note' => $note,
        ]], $type, $user, $reference, $allowNegative)->first();
    }

    /**
     * Record several movements of the same type atomically. All lines are checked before
     * anything is written; balances are locked in a fixed order to avoid deadlocks.
     *
     * @param  list<array{location: Location, item: Item, qty: int|float|string, note?: string|null}>  $lines
     * @return Collection<int, StockMovement>
     *
     * @throws InsufficientStockException
     */
    public function moveMany(
        array $lines,
        MovementType $type,
        User $user,
        ?Model $reference = null,
        bool $allowNegative = false,
    ): Collection {
        // BR-2: only an owner adjustment may push a balance below zero.
        if ($allowNegative && $type !== MovementType::Adjustment) {
            throw new InventoryException('Stok minus hanya boleh lewat penyesuaian pemilik.');
        }

        foreach ($lines as $line) {
            if (Qty::isZero($line['qty'])) {
                throw new InventoryException("Jumlah {$line['item']->name} tidak boleh nol.");
            }
        }

        usort($lines, fn (array $a, array $b) => [$a['location']->id, $a['item']->id] <=> [$b['location']->id, $b['item']->id]);

        return DB::transaction(function () use ($lines, $type, $user, $reference, $allowNegative): Collection {
            // 1. Lock every balance first, then validate, then write.
            $balances = [];

            foreach ($lines as $index => $line) {
                $key = $line['location']->id.':'.$line['item']->id;
                $balances[$key] ??= $this->lockBalance($line['location'], $line['item']);

                $newQty = Qty::add($balances[$key]->qty, $line['qty']);

                if (! $allowNegative && Qty::isNegative($newQty)) {
                    throw new InsufficientStockException(
                        $line['item'],
                        $line['location'],
                        Qty::of($balances[$key]->qty),
                        Qty::neg($line['qty']),
                    );
                }

                $balances[$key]->qty = $newQty;
                $lines[$index]['balance_after'] = $newQty;
            }

            foreach ($balances as $balance) {
                $balance->save();
            }

            return collect($lines)->map(fn (array $line): StockMovement => StockMovement::create([
                'location_id' => $line['location']->id,
                'item_id' => $line['item']->id,
                'type' => $type,
                'qty' => Qty::of($line['qty']),
                'balance_after' => $line['balance_after'],
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'note' => filled($line['note'] ?? null) ? trim($line['note']) : null,
                'user_id' => $user->id,
            ]));
        });
    }

    /**
     * Current balance (no lock) — for display and pre-validation only.
     */
    public function balance(Location|int $location, Item|int $item): string
    {
        return Qty::of(StockBalance::query()
            ->where('location_id', $location instanceof Location ? $location->id : $location)
            ->where('item_id', $item instanceof Item ? $item->id : $item)
            ->value('qty'));
    }

    /**
     * Balances of a location keyed by item_id.
     *
     * @return array<int, string>
     */
    public function balancesFor(Location|int $location): array
    {
        return StockBalance::query()
            ->where('location_id', $location instanceof Location ? $location->id : $location)
            ->pluck('qty', 'item_id')
            ->map(fn ($qty) => Qty::of($qty))
            ->all();
    }

    /**
     * BR-3: every (location, item) whose cached balance differs from the ledger sum.
     *
     * @return Collection<int, array{location_id: int, item_id: int, balance: string, ledger: string}>
     */
    public function verify(): Collection
    {
        $ledger = StockMovement::query()
            ->select('location_id', 'item_id')
            ->selectRaw('SUM(qty) as total')
            ->groupBy('location_id', 'item_id')
            ->get()
            ->keyBy(fn ($row) => $row->location_id.':'.$row->item_id);

        $balances = StockBalance::query()
            ->get(['location_id', 'item_id', 'qty'])
            ->keyBy(fn ($row) => $row->location_id.':'.$row->item_id);

        return $ledger->keys()->merge($balances->keys())->unique()->values()
            ->map(function (string $key) use ($ledger, $balances): array {
                [$locationId, $itemId] = array_map('intval', explode(':', $key));

                return [
                    'location_id' => $locationId,
                    'item_id' => $itemId,
                    'balance' => Qty::of($balances->get($key)?->qty),
                    'ledger' => Qty::of($ledger->get($key)?->total),
                ];
            })
            ->filter(fn (array $row) => Qty::cmp($row['balance'], $row['ledger']) !== 0)
            ->values();
    }

    /**
     * Get (or create) the balance row and lock it for the rest of the transaction.
     */
    private function lockBalance(Location $location, Item $item): StockBalance
    {
        StockBalance::query()->insertOrIgnore([
            'location_id' => $location->id,
            'item_id' => $item->id,
            'qty' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return StockBalance::query()
            ->where('location_id', $location->id)
            ->where('item_id', $item->id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}

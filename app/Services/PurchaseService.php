<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\InventoryException;
use App\Models\Item;
use App\Models\Location;
use App\Models\Purchase;
use App\Models\User;
use App\Support\Qty;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Raw material purchases by the warehouse (INV-3).
 */
class PurchaseService
{
    public const RECEIPT_DISK = 'local';

    public function __construct(private readonly StockService $stock) {}

    /**
     * Record a purchase: header + lines, `purchase_in` to the warehouse, and update
     * `items.last_purchase_price`.
     *
     * TODO(F5, CASH-7): also record the purchase as a business expense in `cash_entries`.
     *
     * @param  array{supplier: string, purchase_date: string}  $data
     * @param  list<array{item_id: int|string, qty: int|float|string, unit_price: int|string}>  $lines
     */
    public function record(Location $warehouse, array $data, array $lines, ?UploadedFile $receipt, User $user): Purchase
    {
        if (! $warehouse->isWarehouse()) {
            throw new InventoryException('Pembelian hanya dicatat ke gudang.');
        }

        if ($lines === []) {
            throw new InventoryException('Isi minimal satu bahan yang dibeli.');
        }

        $items = Item::query()->whereIn('id', array_column($lines, 'item_id'))->get()->keyBy('id');
        $path = $receipt?->store('purchases', self::RECEIPT_DISK);

        try {
            return DB::transaction(function () use ($warehouse, $data, $lines, $items, $path, $user): Purchase {
                $purchase = Purchase::create([
                    'location_id' => $warehouse->id,
                    'supplier' => trim($data['supplier']),
                    'purchase_date' => $data['purchase_date'],
                    'total' => 0,
                    'receipt_photo_path' => $path,
                    'user_id' => $user->id,
                ]);

                $total = 0;
                $movements = [];

                foreach ($lines as $line) {
                    $item = $items->get((int) $line['item_id']) ?? throw new InventoryException('Bahan tidak ditemukan.');
                    $unitPrice = (int) $line['unit_price'];
                    $lineTotal = Qty::money($line['qty'], $unitPrice);

                    $purchase->items()->create([
                        'item_id' => $item->id,
                        'qty' => Qty::of($line['qty']),
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                    ]);

                    $item->update(['last_purchase_price' => $unitPrice]);

                    $total += $lineTotal;
                    $movements[] = ['location' => $warehouse, 'item' => $item, 'qty' => $line['qty']];
                }

                $purchase->update(['total' => $total]);

                $this->stock->moveMany($movements, MovementType::PurchaseIn, $user, $purchase);

                return $purchase->load('items.item');
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk(self::RECEIPT_DISK)->delete($path);
            }

            throw $e;
        }
    }
}

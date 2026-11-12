<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\RequestStatus;
use App\Exceptions\InventoryException;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockRequest;
use App\Models\User;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;

/**
 * Stock request flow warehouse → outlet (INV-4..INV-8, BR-8):
 * pending → approved → shipped → received, or rejected / cancelled.
 * Every status change records who and when.
 */
class StockRequestService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * The warehouse that supplies outlets (single warehouse for now, see ASSUMPTIONS F2).
     */
    public function defaultWarehouse(): Location
    {
        return Location::query()->warehouses()->active()->orderBy('id')->first()
            ?? throw new InventoryException('Belum ada gudang aktif. Tambahkan gudang di Pengaturan.');
    }

    /**
     * INV-4 / INV-5: create a request. The owner may create it already approved.
     *
     * @param  array<int|string, int|float|string>  $quantities  item_id => qty requested
     */
    public function create(Location $outlet, array $quantities, ?string $note, User $user, bool $preApproved = false): StockRequest
    {
        if (! $outlet->isOutlet() || ! $outlet->is_active) {
            throw new InventoryException('Permintaan hanya bisa dibuat untuk outlet yang aktif.');
        }

        $quantities = array_filter($quantities, fn ($qty) => Qty::isPositive($qty));

        if ($quantities === []) {
            throw new InventoryException('Isi minimal satu bahan dengan jumlah lebih dari 0.');
        }

        $warehouse = $this->defaultWarehouse();

        return DB::transaction(function () use ($outlet, $quantities, $note, $user, $preApproved, $warehouse): StockRequest {
            $now = now();

            $request = StockRequest::create([
                'number' => $this->nextNumber(),
                'from_location_id' => $warehouse->id,
                'to_location_id' => $outlet->id,
                'requested_by' => $user->id,
                'status' => $preApproved ? RequestStatus::Approved : RequestStatus::Pending,
                'note' => filled($note) ? trim($note) : null,
                'approved_by' => $preApproved ? $user->id : null,
                'approved_at' => $preApproved ? $now : null,
            ]);

            foreach ($quantities as $itemId => $qty) {
                $request->items()->create([
                    'item_id' => (int) $itemId,
                    'qty_requested' => Qty::of($qty),
                    'qty_approved' => $preApproved ? Qty::of($qty) : null,
                ]);
            }

            return $request->load('items');
        });
    }

    /**
     * INV-5: owner approves, optionally changing quantities (0 = item dropped).
     *
     * @param  array<int|string, int|float|string>  $approved  stock_request_item_id => qty approved
     */
    public function approve(StockRequest $request, array $approved, User $user): StockRequest
    {
        return DB::transaction(function () use ($request, $approved, $user): StockRequest {
            $request = $this->lockInStatus($request, RequestStatus::Pending, 'disetujui');

            $total = '0';

            foreach ($request->items as $line) {
                $qty = Qty::of($approved[$line->id] ?? $line->qty_requested);

                if (Qty::isNegative($qty)) {
                    throw new InventoryException("Jumlah disetujui {$line->item->name} tidak boleh minus.");
                }

                $line->update(['qty_approved' => $qty]);
                $total = Qty::add($total, $qty);
            }

            if (! Qty::isPositive($total)) {
                throw new InventoryException('Setujui minimal satu bahan dengan jumlah lebih dari 0, atau tolak permintaan ini.');
            }

            $request->update([
                'status' => RequestStatus::Approved,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            return $request;
        });
    }

    public function reject(StockRequest $request, string $reason, User $user): StockRequest
    {
        if (blank($reason)) {
            throw new InventoryException('Alasan penolakan wajib diisi.');
        }

        return DB::transaction(function () use ($request, $reason, $user): StockRequest {
            $request = $this->lockInStatus($request, RequestStatus::Pending, 'ditolak');

            $request->update([
                'status' => RequestStatus::Rejected,
                'rejected_by' => $user->id,
                'rejected_at' => now(),
                'reject_reason' => trim($reason),
            ]);

            return $request;
        });
    }

    public function cancel(StockRequest $request, User $user): StockRequest
    {
        return DB::transaction(function () use ($request, $user): StockRequest {
            $request = $this->lockInStatus($request, [RequestStatus::Pending, RequestStatus::Approved], 'dibatalkan');

            $request->update([
                'status' => RequestStatus::Cancelled,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
            ]);

            return $request;
        });
    }

    /**
     * INV-6: warehouse ships. qty_shipped ≤ approved and ≤ warehouse stock (BR-8);
     * shipping less than approved requires a note.
     *
     * @param  array<int|string, int|float|string>  $shipped  stock_request_item_id => qty shipped
     */
    public function ship(StockRequest $request, array $shipped, ?string $note, User $user): StockRequest
    {
        return DB::transaction(function () use ($request, $shipped, $note, $user): StockRequest {
            $request = $this->lockInStatus($request, RequestStatus::Approved, 'dikirim');

            $movements = [];
            $short = false;
            $total = '0';

            foreach ($request->items as $line) {
                $qty = Qty::of($shipped[$line->id] ?? $line->qty_approved);

                if (Qty::isNegative($qty)) {
                    throw new InventoryException("Jumlah kirim {$line->item->name} tidak boleh minus.");
                }

                if (Qty::cmp($qty, $line->qty_approved) > 0) {
                    throw new InventoryException("Jumlah kirim {$line->item->name} melebihi jumlah yang disetujui ({$this->display($line->qty_approved, $line->item)}).");
                }

                $short = $short || Qty::cmp($qty, $line->qty_approved) < 0;
                $total = Qty::add($total, $qty);

                $line->update(['qty_shipped' => $qty]);

                if (Qty::isPositive($qty)) {
                    $movements[] = ['location' => $request->fromLocation, 'item' => $line->item, 'qty' => Qty::neg($qty)];
                }
            }

            if (! Qty::isPositive($total)) {
                throw new InventoryException('Kirim minimal satu bahan dengan jumlah lebih dari 0.');
            }

            if ($short && blank($note)) {
                throw new InventoryException('Jumlah kirim kurang dari yang disetujui. Tulis catatan alasannya.');
            }

            // Throws InsufficientStockException when the warehouse has less than qty_shipped (BR-8).
            $this->stock->moveMany($movements, MovementType::TransferOut, $user, $request);

            $request->update([
                'status' => RequestStatus::Shipped,
                'shipped_by' => $user->id,
                'shipped_at' => now(),
                'ship_note' => filled($note) ? trim($note) : null,
            ]);

            return $request;
        });
    }

    /**
     * INV-7: outlet confirms what actually arrived. A difference with qty_shipped is kept
     * and flagged in reports; a note is required when there is a difference.
     *
     * @param  array<int|string, int|float|string>  $received  stock_request_item_id => qty received
     */
    public function receive(StockRequest $request, array $received, ?string $note, User $user): StockRequest
    {
        return DB::transaction(function () use ($request, $received, $note, $user): StockRequest {
            $request = $this->lockInStatus($request, RequestStatus::Shipped, 'diterima');

            $movements = [];
            $different = false;

            foreach ($request->items as $line) {
                $qty = Qty::of($received[$line->id] ?? $line->qty_shipped);

                if (Qty::isNegative($qty)) {
                    throw new InventoryException("Jumlah terima {$line->item->name} tidak boleh minus.");
                }

                $different = $different || Qty::cmp($qty, $line->qty_shipped) !== 0;

                $line->update(['qty_received' => $qty]);

                if (Qty::isPositive($qty)) {
                    $movements[] = ['location' => $request->toLocation, 'item' => $line->item, 'qty' => $qty];
                }
            }

            if ($different && blank($note)) {
                throw new InventoryException('Jumlah yang diterima berbeda dengan yang dikirim. Tulis catatan singkat.');
            }

            if ($movements !== []) {
                $this->stock->moveMany($movements, MovementType::TransferIn, $user, $request);
            }

            $request->update([
                'status' => RequestStatus::Received,
                'received_by' => $user->id,
                'received_at' => now(),
                'receive_note' => filled($note) ? trim($note) : null,
            ]);

            return $request;
        });
    }

    /**
     * REQ-DDMMYY-XXX, sequence per day. Called inside a transaction; the UNIQUE index on
     * `number` is the final guard against duplicates.
     */
    private function nextNumber(): string
    {
        $prefix = 'REQ-'.now()->format('dmy').'-';

        $last = StockRequest::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('number');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Re-read the request with a row lock and make sure it is still in an allowed status.
     *
     * @param  RequestStatus|list<RequestStatus>  $allowed
     */
    private function lockInStatus(StockRequest $request, RequestStatus|array $allowed, string $action): StockRequest
    {
        $fresh = StockRequest::query()->lockForUpdate()->findOrFail($request->id);
        $allowed = is_array($allowed) ? $allowed : [$allowed];

        if (! in_array($fresh->status, $allowed, true)) {
            throw new InventoryException("Permintaan {$fresh->number} berstatus \"{$fresh->status->label()}\", jadi tidak bisa {$action}.");
        }

        return $fresh->load(['items.item', 'fromLocation', 'toLocation']);
    }

    private function display(string $qty, Item $item): string
    {
        return qty($qty).' '.$item->unit;
    }
}

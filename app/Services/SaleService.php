<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Enums\SaleStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\SaleException;
use App\Models\Item;
use App\Models\Location;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Qty;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * POS sales (POS-1..8, BR-1, BR-2, BR-4, BR-6).
 *
 * Prices and totals are ALWAYS recomputed here from `product_prices`; the browser only sends
 * product ids and quantities. Stock goes out through StockService (`sale_out`) via the recipe (BOM).
 */
class SaleService
{
    public const MAX_QTY = 999;

    public function __construct(private readonly StockService $stock) {}

    /**
     * Record a sale at the user's own outlet, in its open shift, in ONE DB transaction (POS-5).
     *
     * @param  list<array{product_id: int|string, qty: int|string}>  $lines  extra keys (prices...) are ignored
     *
     * @throws SaleException
     * @throws InsufficientStockException (rolled back, message names the item)
     */
    public function checkout(
        User $user,
        SalesChannel $channel,
        array $lines,
        ?PaymentMethod $method = null,
        ?int $cashReceived = null,
        ?string $platformOrderId = null,
    ): Sale {
        Gate::forUser($user)->authorize('create', Sale::class);

        $outlet = $user->location;
        $quantities = $this->quantities($lines);
        $products = $this->products(array_keys($quantities));

        // Pricing (server side) + BOM need (Σ qty_product × qty_recipe per item).
        $priced = [];
        $need = [];
        $total = 0;

        foreach ($quantities as $productId => $qty) {
            $product = $products->get($productId) ?? throw new SaleException('Produk tidak ditemukan atau sudah tidak dijual.');
            $price = $product->priceFor($channel);

            if ($price === null || $price <= 0) {
                throw new SaleException("Harga {$product->name} untuk {$channel->label()} belum diatur. Hubungi pemilik.");
            }

            if ($product->recipes->isEmpty()) {
                throw new SaleException("Resep {$product->name} belum diatur. Hubungi pemilik.");
            }

            $lineTotal = $price * $qty;
            $priced[] = ['product_id' => $product->id, 'qty' => $qty, 'unit_price' => $price, 'line_total' => $lineTotal];
            $total += $lineTotal;

            foreach ($product->recipes as $recipe) {
                $need[$recipe->item_id] = [
                    'item' => $recipe->item,
                    'qty' => Qty::add($need[$recipe->item_id]['qty'] ?? 0, Qty::mul($recipe->qty, $qty)),
                ];
            }
        }

        // Payment (POS-3/4): platform orders have no payment method or cash.
        if ($channel === SalesChannel::Offline) {
            $method ??= PaymentMethod::Cash;
            $platformOrderId = null;

            if ($method === PaymentMethod::Cash) {
                if ($cashReceived === null || $cashReceived < $total) {
                    throw new SaleException('Uang diterima kurang dari total '.rupiah($total).'. Periksa lagi uang dari pelanggan.');
                }
            } else {
                $cashReceived = null;
            }
        } else {
            $method = null;
            $cashReceived = null;
            $platformOrderId = filled($platformOrderId) ? mb_substr(trim($platformOrderId), 0, 50) : null;
        }

        return DB::transaction(function () use ($user, $outlet, $channel, $method, $cashReceived, $platformOrderId, $priced, $need, $total): Sale {
            // Serialize per outlet: the sequence number and the "shift still open" check (BR-4).
            Location::query()->whereKey($outlet->id)->lockForUpdate()->first();

            $shift = Shift::query()->open()->where('location_id', $outlet->id)->lockForUpdate()->first()
                ?? throw new SaleException('Shift belum dibuka. Buka shift dulu sebelum berjualan.');

            $sale = Sale::create([
                'number' => $this->nextNumber($outlet),
                'location_id' => $outlet->id,
                'shift_id' => $shift->id,
                'user_id' => $user->id,
                'channel' => $channel,
                'payment_method' => $method,
                'platform_order_id' => $platformOrderId,
                'subtotal' => $total,
                'total' => $total,
                'cash_received' => $cashReceived,
                'change' => $cashReceived !== null ? $cashReceived - $total : null,
                'status' => SaleStatus::Completed,
            ]);

            $sale->items()->createMany($priced);

            // BR-1 / BR-2: one `sale_out` row per item; not enough stock → exception → full rollback.
            $this->stock->moveMany(
                collect($need)->map(fn (array $row) => [
                    'location' => $outlet,
                    'item' => $row['item'],
                    'qty' => Qty::neg($row['qty']),
                ])->values()->all(),
                MovementType::SaleOut,
                $user,
                $sale,
            );

            return $sale->load('items.product');
        });
    }

    /**
     * Void a sale of a still-open shift (POS-8, BR-6): reverse EXACTLY its own `sale_out` rows.
     */
    public function void(Sale $sale, User $user, string $reason): Sale
    {
        Gate::forUser($user)->authorize('view', $sale);

        $reason = trim($reason);

        if (mb_strlen($reason) < 3) {
            throw new SaleException('Tulis alasan pembatalan (minimal 3 huruf).');
        }

        return DB::transaction(function () use ($sale, $user, $reason): Sale {
            $locked = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $shift = Shift::query()->whereKey($locked->shift_id)->lockForUpdate()->firstOrFail();

            if ($locked->isVoid()) {
                throw new SaleException("Transaksi {$locked->number} sudah dibatalkan.");
            }

            if (! $shift->isOpen()) {
                throw new SaleException('Transaksi di shift yang sudah ditutup tidak bisa dibatalkan.');
            }

            Gate::forUser($user)->authorize('void', $locked->setRelation('shift', $shift));

            $out = StockMovement::query()
                ->with(['item', 'location'])
                ->where('reference_type', $locked->getMorphClass())
                ->where('reference_id', $locked->id)
                ->where('type', MovementType::SaleOut)
                ->get();

            if ($out->isNotEmpty()) {
                $this->stock->moveMany(
                    $out->map(fn (StockMovement $movement) => [
                        'location' => $movement->location,
                        'item' => $movement->item,
                        'qty' => Qty::neg($movement->qty),
                    ])->values()->all(),
                    MovementType::SaleVoidIn,
                    $user,
                    $locked,
                );
            }

            $locked->update([
                'status' => SaleStatus::Void,
                'void_reason' => mb_substr($reason, 0, 255),
                'voided_by' => $user->id,
                'voided_at' => now(),
            ]);

            audit(AuditAction::SaleVoid, $locked,
                ['status' => SaleStatus::Completed, 'number' => $locked->number, 'total' => $locked->total],
                ['status' => SaleStatus::Void, 'void_reason' => $locked->void_reason],
                $user,
            );

            return $locked;
        });
    }

    /**
     * Sales summary of a shift for the closing screen (SHF-3/4).
     *
     * @return array{
     *     count: int, void_count: int, total: int,
     *     per_channel: array<string, int>, cash: int, qris: int,
     *     usage: Collection<int, array{item: Item, qty: string}>,
     * }
     */
    public function shiftSummary(Shift $shift): array
    {
        $sales = Sale::query()->where('shift_id', $shift->id)->get();
        $completed = $sales->where('status', SaleStatus::Completed);
        $offline = $completed->where('channel', SalesChannel::Offline);

        $perChannel = [];
        foreach (SalesChannel::cases() as $channel) {
            $perChannel[$channel->value] = (int) $completed->where('channel', $channel)->sum('total');
        }

        $usage = StockMovement::query()
            ->with('item')
            ->where('reference_type', (new Sale)->getMorphClass())
            ->whereIn('reference_id', $sales->pluck('id'))
            ->whereIn('type', [MovementType::SaleOut, MovementType::SaleVoidIn])
            ->get()
            ->groupBy('item_id')
            ->map(fn (Collection $rows) => [
                'item' => $rows->first()->item,
                'qty' => Qty::neg($rows->reduce(fn (string $sum, StockMovement $m) => Qty::add($sum, $m->qty), '0')),
            ])
            ->reject(fn (array $row) => Qty::isZero($row['qty']))
            ->sortBy(fn (array $row) => $row['item']->name)
            ->values();

        return [
            'count' => $completed->count(),
            'void_count' => $sales->count() - $completed->count(),
            'total' => (int) $completed->sum('total'),
            'per_channel' => $perChannel,
            'cash' => (int) $offline->where('payment_method', PaymentMethod::Cash)->sum('total'),
            'qris' => (int) $offline->where('payment_method', PaymentMethod::Qris)->sum('total'),
            'usage' => $usage,
        ];
    }

    /**
     * Σ total of completed offline cash sales of a shift (input of expected_cash, §6.3).
     */
    public function cashSalesTotal(Shift $shift): int
    {
        return (int) Sale::query()
            ->where('shift_id', $shift->id)
            ->where('status', SaleStatus::Completed)
            ->where('channel', SalesChannel::Offline)
            ->where('payment_method', PaymentMethod::Cash)
            ->sum('total');
    }

    /**
     * Products for the cashier with what blocks them, and how many units the outlet stock allows.
     * `status` is one of: available, no_recipe, no_stock.
     *
     * @return Collection<int, array{product: Product, prices: array<string, int>, status: string, max: int|null}>
     */
    public function catalog(Location $outlet): Collection
    {
        $balances = $this->stock->balancesFor($outlet);

        return Product::query()->active()->with(['prices', 'recipes.item'])->orderBy('type')->orderBy('name')->get()
            ->map(function (Product $product) use ($balances): array {
                $max = null;

                foreach ($product->recipes as $recipe) {
                    if (Qty::isPositive($recipe->qty)) {
                        $units = (int) floor((float) bcdiv(Qty::of($balances[$recipe->item_id] ?? 0), Qty::of($recipe->qty), 3));
                        $max = $max === null ? $units : min($max, $units);
                    }
                }

                return [
                    'product' => $product,
                    'prices' => collect(SalesChannel::cases())
                        ->mapWithKeys(fn (SalesChannel $c) => [$c->value => (int) ($product->priceFor($c) ?? 0)])
                        ->all(),
                    'status' => match (true) {
                        $product->recipes->isEmpty() => 'no_recipe',
                        ($max ?? 0) < 1 => 'no_stock',
                        default => 'available',
                    },
                    'max' => $max,
                ];
            })
            ->values();
    }

    /**
     * Merge duplicate lines, keep only product_id + qty (anything else from the client is ignored).
     *
     * @return array<int, int> product_id => qty
     */
    private function quantities(array $lines): array
    {
        $quantities = [];

        foreach ($lines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $qty = $line['qty'] ?? 0;

            if ($productId <= 0 || ! is_numeric($qty) || (int) $qty != $qty || (int) $qty < 1) {
                throw new SaleException('Jumlah produk harus bilangan bulat minimal 1.');
            }

            $quantities[$productId] = ($quantities[$productId] ?? 0) + (int) $qty;
        }

        if ($quantities === []) {
            throw new SaleException('Keranjang masih kosong. Pilih produk dulu.');
        }

        foreach ($quantities as $qty) {
            if ($qty > self::MAX_QTY) {
                throw new SaleException('Jumlah satu produk maksimal '.self::MAX_QTY.'.');
            }
        }

        return $quantities;
    }

    /**
     * @param  list<int>  $ids
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    private function products(array $ids): \Illuminate\Database\Eloquent\Collection
    {
        return Product::query()->active()->with(['prices', 'recipes.item'])->whereIn('id', $ids)->get()->keyBy('id');
    }

    /**
     * {KODE}-{YYYYMMDD}-{0001}, per outlet per day (POS-7). Called inside the outlet lock;
     * the UNIQUE index on `number` is the final guard.
     */
    private function nextNumber(Location $outlet): string
    {
        $prefix = $outlet->code.'-'.now()->format('Ymd').'-';

        $last = Sale::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('number');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}

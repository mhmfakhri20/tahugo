<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Exceptions\InsufficientStockException;
use App\Models\CashEntry;
use App\Models\ExpenseCategory;
use App\Models\Item;
use App\Models\Location;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\CashService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Services\ShiftService;
use App\Services\StockAdjustmentService;
use App\Services\StockRequestService;
use App\Services\StockService;
use App\Support\Qty;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * DEMO data (PRD §12): 14 days of realistic activity for 2 outlets, for the dashboard & reports.
 * Not part of DatabaseSeeder and never runs in production:
 *
 *     php artisan db:seed --class=DemoSeeder
 *
 * Everything goes through the existing services (attendance, shifts, sales, cash, stock requests,
 * purchases) with a simulated clock, so the stock ledger and cash stay consistent (BR-3).
 * All names, prices and amounts are PLACEHOLDERS.
 */
class DemoSeeder extends Seeder
{
    public const DAYS = 14;

    /** Outlet stock levels to restock up to (placeholder). */
    private const OUTLET_TARGET = [
        'Tahu mentah' => 600, 'Bumbu tabur' => 3000, 'Saus' => 80, 'Minyak goreng' => 6,
        'Kemasan kecil' => 80, 'Kemasan besar' => 60, 'Plastik' => 150,
    ];

    /** Restock when an outlet falls below this share of the target. */
    private const RESTOCK_RATIO = 0.35;

    private const EXPENSES = [
        'Bahan dadakan' => ['Beli cabai rawit (demo)', 'Beli bawang goreng (demo)', 'Beli tisu (demo)'],
        'Gas/LPG' => ['Isi ulang LPG 3 kg (demo)'],
        'Kebersihan' => ['Sabun cuci piring (demo)', 'Kantong sampah (demo)'],
        'Parkir/retribusi' => ['Retribusi pasar (demo)'],
        'Transport' => ['Ongkos ojek ambil bahan (demo)'],
    ];

    private User $owner;

    private User $admin;

    /** @var Collection<int, Product> */
    private Collection $products;

    /** @var Collection<string, Item> */
    private Collection $items;

    private Location $warehouse;

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSeeder tidak boleh dijalankan di production.');

            return;
        }

        if (! User::query()->where('username', 'owner')->exists()) {
            $this->call(DatabaseSeeder::class);
        }

        if (Sale::query()->exists()) {
            $this->command?->warn('Sudah ada data penjualan. Jalankan `php artisan migrate:fresh --seed` dulu bila ingin data demo baru.');

            return;
        }

        mt_srand(2026);
        $realNow = CarbonImmutable::now();

        try {
            $this->prepare();
            $this->simulate($realNow);
            $this->verifyExpenses($realNow);
        } finally {
            Date::setTestNow();
        }

        $this->command?->info('Data demo '.self::DAYS.' hari selesai dibuat.');
    }

    private function prepare(): void
    {
        $this->owner = User::query()->where('username', 'owner')->firstOrFail();
        $this->admin = User::query()->where('username', 'gudang')->firstOrFail();
        $this->warehouse = app(StockRequestService::class)->defaultWarehouse();
        $this->items = Item::query()->get()->keyBy('name');

        $this->products = Product::query()->active()->with(['prices', 'recipes'])->get()
            ->filter(fn (Product $p) => $p->recipes->isNotEmpty() && ($p->priceFor(SalesChannel::Offline) ?? 0) > 0)
            ->values();

        if ($this->products->isEmpty()) {
            throw new \RuntimeException('Tidak ada produk dengan harga & resep. Jalankan DatabaseSeeder (PosPlaceholderSeeder) dulu.');
        }

        // Placeholder coordinates / hours / target ONLY where still empty ([TBD] §15-2, §15-9).
        $placeholder = [
            'FJI' => ['latitude' => -7.5560000, 'longitude' => 110.8310000, 'monthly_target' => 15_000_000],
            'BKK' => ['latitude' => -7.5980000, 'longitude' => 110.9520000, 'monthly_target' => null],
        ];

        foreach (Location::query()->outlets()->active()->get() as $outlet) {
            $data = $placeholder[$outlet->code] ?? ['latitude' => -7.57, 'longitude' => 110.85, 'monthly_target' => null];
            $outlet->update(array_filter([
                'latitude' => $outlet->latitude ?? $data['latitude'],
                'longitude' => $outlet->longitude ?? $data['longitude'],
                'open_time' => $outlet->open_time ?? '08:00',
                'close_time' => $outlet->close_time ?? '21:00',
                'monthly_target' => $outlet->monthly_target ?? $data['monthly_target'],
            ], fn ($value) => $value !== null));
        }
    }

    private function simulate(CarbonImmutable $realNow): void
    {
        $outlets = Location::query()->outlets()->active()->orderBy('id')->get()
            ->map(fn (Location $outlet) => [
                'outlet' => $outlet->fresh(),
                'staff' => User::query()->active()->where('location_id', $outlet->id)->orderBy('id')->first(),
            ])
            ->filter(fn (array $row) => $row['staff'] !== null)
            ->values();

        for ($daysAgo = self::DAYS; $daysAgo >= 0; $daysAgo--) {
            $day = $realNow->startOfDay()->subDays($daysAgo);
            $isToday = $daysAgo === 0;

            if ($isToday && $realNow->hour < 9) {
                break;
            }

            foreach ($outlets as $row) {
                $this->simulateDay($row['outlet'], $row['staff'], $day, $isToday ? $realNow : null);
            }
        }
    }

    private function simulateDay(Location $outlet, User $staff, CarbonImmutable $day, ?CarbonImmutable $until): void
    {
        $attendance = app(AttendanceService::class);
        $shifts = app(ShiftService::class);
        $cash = app(CashService::class);

        $clockIn = $day->setTime(7, 45)->addMinutes(mt_rand(0, 25));
        $this->at($clockIn);
        $attendance->clockIn($staff, $this->photo(), (float) $outlet->latitude, (float) $outlet->longitude, mt_rand(5, 25));

        $this->restockIfLow($outlet, $staff, $clockIn->addMinutes(2));

        $this->at($clockIn->addMinutes(8));
        $shift = $shifts->open($staff, 100_000);

        // Sales spread over the day.
        $count = mt_rand(15, 35);
        $times = collect(range(1, $count))
            ->map(fn () => $day->setTime(9, 0)->addMinutes(mt_rand(0, 11 * 60 + 30)))
            ->sort()
            ->values();

        foreach ($times as $time) {
            if ($until && $time->gt($until)) {
                break;
            }

            $this->at($time);
            $sale = $this->sell($outlet, $staff, $time);

            if ($sale && mt_rand(1, 100) <= 3) {
                $this->at($time->addMinutes(2));
                app(SaleService::class)->void($sale, $staff, 'Salah input produk (demo)');
            }
        }

        // Expenses & other income.
        $categories = ExpenseCategory::query()->pluck('id', 'name');

        foreach (range(1, mt_rand(0, 3)) as $i) {
            $time = $day->setTime(10, 0)->addMinutes(mt_rand(0, 9 * 60));

            if ($until && $time->gt($until)) {
                continue;
            }

            $name = array_rand(self::EXPENSES);
            $this->at($time);
            $cash->recordExpense($staff, [
                'category_id' => $categories->get($name) ?? $categories->first(),
                'amount' => mt_rand(1, 12) * 5_000,
                'description' => self::EXPENSES[$name][array_rand(self::EXPENSES[$name])],
                'fund_source' => mt_rand(1, 100) <= 80 ? 'outlet_cash' : 'personal',
            ], $this->photo());
        }

        if (mt_rand(1, 7) === 1 && ! $until) {
            $this->at($day->setTime(13, 15));
            $cash->recordIncome($staff, 50_000, 'Tambahan uang kembalian dari pemilik (demo)');
        }

        if (mt_rand(1, 5) === 1 && ! $until) {
            $tahu = $this->items->get('Tahu mentah');
            $this->at($day->setTime(20, 40));

            if ($tahu && Qty::cmp(app(StockService::class)->balance($outlet, $tahu), 5) >= 0) {
                app(StockAdjustmentService::class)->waste($outlet, $tahu, mt_rand(2, 5), 'Tahu basi (demo)', $staff);
            }
        }

        if ($until) {
            return; // today: shift stays open
        }

        // Close: counted cash = expected ± small difference.
        $close = $day->setTime(21, 0)->addMinutes(mt_rand(0, 25));
        $this->at($close);
        $expected = $shifts->expectedCash($shift->fresh());
        $difference = [0, 0, 0, 0, -2_000, -5_000, 1_000, -10_000][mt_rand(0, 7)];
        $shifts->close($shift->fresh(), $staff, max(0, $expected + $difference), $this->photo());

        $this->at($close->addMinutes(4));
        $attendance->clockOut($staff, $this->photo(), (float) $outlet->latitude, (float) $outlet->longitude, mt_rand(5, 25));
    }

    private function sell(Location $outlet, User $staff, CarbonImmutable $time, bool $retry = true): ?Sale
    {
        $roll = mt_rand(1, 100);
        $channel = match (true) {
            $roll <= 60 => SalesChannel::Offline,
            $roll <= 75 => SalesChannel::Gofood,
            $roll <= 90 => SalesChannel::Grabfood,
            default => SalesChannel::Shopeefood,
        };

        $lines = $this->products->shuffle()->take(mt_rand(1, 2))
            ->map(fn (Product $p) => ['product_id' => $p->id, 'qty' => mt_rand(1, 3), 'price' => $p->priceFor($channel) ?? 0])
            ->filter(fn (array $line) => $line['price'] > 0)
            ->values();

        if ($lines->isEmpty()) {
            return null;
        }

        $total = $lines->sum(fn (array $line) => $line['price'] * $line['qty']);
        $method = $channel === SalesChannel::Offline ? (mt_rand(1, 100) <= 75 ? PaymentMethod::Cash : PaymentMethod::Qris) : null;
        $cash = $method === PaymentMethod::Cash ? (mt_rand(0, 1) ? $total : (int) ceil($total / 10_000) * 10_000) : null;

        try {
            return app(SaleService::class)->checkout(
                $staff,
                $channel,
                $lines->map(fn (array $line) => ['product_id' => $line['product_id'], 'qty' => $line['qty']])->all(),
                $method,
                $cash,
                $channel === SalesChannel::Offline ? null : strtoupper(substr($channel->value, 0, 2)).'-'.mt_rand(100000, 999999),
            );
        } catch (InsufficientStockException) {
            if (! $retry) {
                return null;
            }

            $this->restockIfLow($outlet, $staff, $time, force: true);
            $this->at($time->addMinute());

            return $this->sell($outlet, $staff, $time->addMinute(), retry: false);
        }
    }

    /**
     * Request → approve → (purchase if needed) → ship → receive, through the services (INV-4..8).
     */
    private function restockIfLow(Location $outlet, User $staff, CarbonImmutable $time, bool $force = false): void
    {
        $stock = app(StockService::class);
        $requests = app(StockRequestService::class);
        $balances = $stock->balancesFor($outlet);

        $needs = collect(self::OUTLET_TARGET)
            ->filter(fn ($target, $name) => $this->items->has($name))
            ->mapWithKeys(fn ($target, $name) => [$this->items[$name]->id => [
                'target' => $target,
                'balance' => (float) ($balances[$this->items[$name]->id] ?? 0),
            ]]);

        $low = $needs->contains(fn (array $row) => $row['balance'] < $row['target'] * self::RESTOCK_RATIO);

        if (! $low && ! $force) {
            return;
        }

        $quantities = $needs
            ->map(fn (array $row) => (int) max(0, $row['target'] - $row['balance']))
            ->filter(fn (int $qty) => $qty > 0)
            ->all();

        if ($quantities === []) {
            return;
        }

        $this->at($time);
        $request = $requests->create($outlet, $quantities, 'Stok menipis (demo)', $staff);

        $this->at($time->addMinutes(1));
        $request = $requests->approve($request, [], $this->owner);

        $this->at($time->addMinutes(2));
        $this->purchaseIfNeeded($quantities, $time->addMinutes(2));

        $this->at($time->addMinutes(3));
        $request = $requests->ship($request->fresh('items'), [], null, $this->admin);

        $this->at($time->addMinutes(4));
        $received = [];
        $note = null;

        if (mt_rand(1, 100) <= 15) {
            $line = $request->items->first();
            $received[$line->id] = max(0, (float) $line->qty_shipped - 1);
            $note = 'Satu kurang saat diterima (demo)';
        }

        $requests->receive($request->fresh('items'), $received, $note, $staff);
    }

    /**
     * @param  array<int, int>  $quantities  item_id => qty needed from the warehouse
     */
    private function purchaseIfNeeded(array $quantities, CarbonImmutable $time): void
    {
        $balances = app(StockService::class)->balancesFor($this->warehouse);
        $lines = [];

        foreach ($quantities as $itemId => $qty) {
            $missing = $qty - (float) ($balances[$itemId] ?? 0);

            if ($missing > 0) {
                $item = $this->items->firstWhere('id', $itemId);
                $lines[] = [
                    'item_id' => $itemId,
                    'qty' => (int) ceil($missing * 3),
                    'unit_price' => $item->last_purchase_price ?: $this->placeholderPrice($item->name),
                ];
            }
        }

        if ($lines === []) {
            return;
        }

        $this->at($time);
        app(PurchaseService::class)->record(
            $this->warehouse,
            ['supplier' => 'Pemasok langganan (demo)', 'purchase_date' => $time->toDateString()],
            $lines,
            null,
            $this->admin,
        );
    }

    /**
     * The owner verifies most expenses older than yesterday: ~85% approved, the rest rejected.
     */
    private function verifyExpenses(CarbonImmutable $realNow): void
    {
        $cash = app(CashService::class);

        CashEntry::query()
            ->where('status', 'pending')
            ->whereNotNull('shift_id')
            ->where('created_at', '<', $realNow->subDay()->startOfDay())
            ->orderBy('id')
            ->get()
            ->each(function (CashEntry $entry) use ($cash, $realNow) {
                $this->at(min($entry->created_at->addHours(14), $realNow));

                mt_rand(1, 100) <= 85
                    ? $cash->approve($entry, $this->owner)
                    : $cash->reject($entry, $this->owner, 'Nota tidak sesuai nominal (demo)');
            });
    }

    private function placeholderPrice(string $name): int
    {
        return match ($name) {
            'Tahu mentah' => 450,
            'Bumbu tabur' => 60,
            'Saus' => 900,
            'Minyak goreng' => 18_000,
            'Kemasan kecil' => 300,
            'Kemasan besar' => 500,
            'Plastik' => 100,
            default => 1_000,
        };
    }

    private function at(\DateTimeInterface $time): void
    {
        Date::setTestNow($time);
    }

    /**
     * A tiny valid PNG (no GD needed) standing in for camera photos.
     */
    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'demo.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
        );
    }
}

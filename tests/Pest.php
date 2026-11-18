<?php

use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\ProductType;
use App\Enums\SalesChannel;
use App\Models\Attendance;
use App\Models\Item;
use App\Models\Location;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\SaleService;
use App\Services\StockService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Inventory test fixture: 1 warehouse, outlets A & B, one user per role, two items.
 *
 * @return object{warehouse: Location, outletA: Location, outletB: Location, owner: User, admin: User, staffA: User, staffB: User, tahu: Item, saus: Item}
 */
function inventoryWorld(): object
{
    $warehouse = Location::factory()->warehouse()->create(['code' => 'GDG', 'name' => 'Gudang Solo']);
    $outletA = Location::factory()->outlet()->create(['code' => 'FJI', 'name' => 'Outlet A']);
    $outletB = Location::factory()->outlet()->create(['code' => 'BKK', 'name' => 'Outlet B']);

    return (object) [
        'warehouse' => $warehouse,
        'outletA' => $outletA,
        'outletB' => $outletB,
        'owner' => User::factory()->owner()->create(),
        'admin' => User::factory()->warehouseAdmin()->create(),
        'staffA' => User::factory()->outletStaff($outletA)->create(),
        'staffB' => User::factory()->outletStaff($outletB)->create(),
        'tahu' => Item::factory()->create(['name' => 'Tahu mentah', 'unit' => 'pcs']),
        'saus' => Item::factory()->create(['name' => 'Saus', 'unit' => 'sachet']),
    ];
}

/**
 * Current cached balance as a normalized "0.000" string.
 */
function stockOf(Location $location, Item $item): string
{
    return app(StockService::class)->balance($location, $item);
}

/**
 * Attendance test fixture (F3): outlets A & B with coordinates (radius 100 m) and hours 08:00–16:00.
 *
 * @return object{outletA: Location, outletB: Location, owner: User, staffA: User, staffA2: User, staffB: User}
 */
function attendanceWorld(): object
{
    $outletA = Location::factory()->outlet()->withCoordinates(-7.5600000, 110.8200000)->withHours('08:00', '16:00')
        ->create(['code' => 'FJI', 'name' => 'Outlet A']);
    $outletB = Location::factory()->outlet()->withCoordinates(-7.6000000, 110.9500000)->withHours('08:00', '16:00')
        ->create(['code' => 'BKK', 'name' => 'Outlet B']);

    return (object) [
        'outletA' => $outletA,
        'outletB' => $outletB,
        'owner' => User::factory()->owner()->create(),
        'staffA' => User::factory()->outletStaff($outletA)->create(['name' => 'Siti']),
        'staffA2' => User::factory()->outletStaff($outletA)->create(['name' => 'Andi']),
        'staffB' => User::factory()->outletStaff($outletB)->create(['name' => 'Rina']),
    ];
}

/**
 * Freeze the server clock at a WIB wall-clock time today (e.g. "08:07").
 */
function atWib(string $time, ?string $date = '2026-11-18'): void
{
    test()->travelTo(CarbonImmutable::parse("{$date} {$time}", 'Asia/Jakarta'));
}

function selfie(): UploadedFile
{
    return UploadedFile::fake()->image('foto.png', 640, 480);
}

/**
 * Clock in through the service at the given position (defaults to the outlet's own coordinates).
 */
function clockIn(User $user, ?float $lat = null, ?float $lng = null, float $accuracy = 10): Attendance
{
    return app(AttendanceService::class)->clockIn(
        $user, selfie(), $lat ?? (float) $user->location->latitude, $lng ?? (float) $user->location->longitude, $accuracy,
    );
}

/**
 * POS test fixture (F4): outlets A & B with an open shift at A (opening cash Rp100.000),
 * "Paket isi 10" (BOM 10 tahu + 1 bumbu + 1 kemasan) and "Tahu satuan" (1 tahu) with prices per channel,
 * and outlet stock tahu 100 / bumbu 20 / kemasan 20 at both outlets.
 *
 * @return object{outletA: Location, outletB: Location, owner: User, staffA: User, staffA2: User, staffB: User, shiftA: Shift, tahu: Item, bumbu: Item, kemasan: Item, paket: Product, satuan: Product}
 */
function posWorld(): object
{
    atWib('09:00');

    $outletA = Location::factory()->outlet()->create(['code' => 'FJI', 'name' => 'Outlet A']);
    $outletB = Location::factory()->outlet()->create(['code' => 'BKK', 'name' => 'Outlet B']);
    $owner = User::factory()->owner()->create();
    $staffA = User::factory()->outletStaff($outletA)->create();

    $tahu = Item::factory()->create(['name' => 'Tahu mentah', 'unit' => 'pcs']);
    $bumbu = Item::factory()->create(['name' => 'Bumbu tabur', 'unit' => 'pcs']);
    $kemasan = Item::factory()->create(['name' => 'Kemasan besar', 'unit' => 'pcs']);

    $paket = makeProduct('Paket isi 10', ['offline' => 18_000, 'gofood' => 22_000, 'grabfood' => 21_000, 'shopeefood' => 20_000],
        [[$tahu, 10], [$bumbu, 1], [$kemasan, 1]], ProductType::Paket);
    $satuan = makeProduct('Tahu satuan', ['offline' => 2_000, 'gofood' => 2_500, 'grabfood' => 2_500, 'shopeefood' => 2_500],
        [[$tahu, 1]]);

    foreach ([$outletA, $outletB] as $outlet) {
        app(StockService::class)->moveMany([
            ['location' => $outlet, 'item' => $tahu, 'qty' => 100],
            ['location' => $outlet, 'item' => $bumbu, 'qty' => 20],
            ['location' => $outlet, 'item' => $kemasan, 'qty' => 20],
        ], MovementType::Adjustment, $owner);
    }

    return (object) [
        'outletA' => $outletA,
        'outletB' => $outletB,
        'owner' => $owner,
        'staffA' => $staffA,
        'staffA2' => User::factory()->outletStaff($outletA)->create(),
        'staffB' => User::factory()->outletStaff($outletB)->create(),
        'shiftA' => Shift::factory()->atOutlet($outletA, $staffA)->create(['opening_cash' => 100_000]),
        'tahu' => $tahu,
        'bumbu' => $bumbu,
        'kemasan' => $kemasan,
        'paket' => $paket,
        'satuan' => $satuan,
    ];
}

/**
 * @param  array<string, int>  $prices  channel => price
 * @param  list<array{0: Item, 1: int|float}>  $recipe
 */
function makeProduct(string $name, array $prices, array $recipe, ProductType $type = ProductType::Single): Product
{
    $product = Product::factory()->create(['name' => $name, 'type' => $type]);

    foreach ($prices as $channel => $price) {
        $product->prices()->create(['channel' => $channel, 'price' => $price]);
    }

    foreach ($recipe as [$item, $qty]) {
        $product->recipes()->create(['item_id' => $item->id, 'qty' => $qty]);
    }

    return $product;
}

/**
 * Sell through the service. $lines: [[Product, qty], ...]
 */
function sell(User $user, array $lines, SalesChannel $channel = SalesChannel::Offline, ?PaymentMethod $method = PaymentMethod::Cash, ?int $cash = 1_000_000): Sale
{
    return app(SaleService::class)->checkout(
        $user,
        $channel,
        array_map(fn (array $line) => ['product_id' => $line[0]->id, 'qty' => $line[1]], $lines),
        $method,
        $cash,
    );
}

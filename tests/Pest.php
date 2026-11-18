<?php

use App\Models\Attendance;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use App\Services\AttendanceService;
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

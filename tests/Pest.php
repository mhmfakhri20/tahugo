<?php

use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

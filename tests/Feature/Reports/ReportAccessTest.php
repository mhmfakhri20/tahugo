<?php

use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->w = posWorld();
    $this->w->admin = User::factory()->warehouseAdmin()->create();
    Location::factory()->warehouse()->create(['code' => 'GDG']);
});

$financial = ['owner.reports.sales', 'owner.reports.cash', 'owner.reports.performance', 'owner.reports.daily'];

test('the owner can open every report, with and without data', function () use ($financial) {
    $w = $this->w;

    foreach ([...$financial, 'owner.reports.inventory', 'owner.dashboard'] as $route) {
        $this->actingAs($w->owner)->get(route($route))->assertOk();
    }

    sell($w->staffA, [[$w->paket, 1]]);

    foreach ([...$financial, 'owner.reports.inventory', 'owner.dashboard'] as $route) {
        $this->actingAs($w->owner)->get(route($route))->assertOk();
    }

    $this->actingAs($w->owner)->get(route('reports.daily', $w->shiftA))->assertOk()->assertSee('Laporan Harian Outlet');
});

test('outlet staff get 403 on financial reports', function () use ($financial) {
    foreach ([...$financial, 'owner.reports.inventory'] as $route) {
        $this->actingAs($this->w->staffA)->get(route($route))->assertForbidden();
    }
});

test('warehouse admin gets 403 on financial reports and can open the inventory report', function () use ($financial) {
    foreach ($financial as $route) {
        $this->actingAs($this->w->admin)->get(route($route))->assertForbidden();
    }

    $this->actingAs($this->w->admin)->get(route('warehouse.reports'))->assertOk()->assertSee('Laporan persediaan');
    $this->actingAs($this->w->admin)->get(route('reports.daily', $this->w->shiftA))->assertForbidden();
});

test('staff see the daily report of their own outlet only (BR-7)', function () {
    $w = $this->w;
    sell($w->staffA, [[$w->paket, 1]]);
    $shiftB = Shift::factory()->atOutlet($w->outletB, $w->staffB)->create();

    $this->actingAs($w->staffA)->get(route('reports.daily', $w->shiftA))
        ->assertOk()
        ->assertSee('Outlet A')
        ->assertSee('Paket isi 10');
    $this->actingAs($w->staffA2)->get(route('reports.daily', $w->shiftA))->assertOk();

    $this->actingAs($w->staffA)->get(route('reports.daily', $shiftB))->assertForbidden();
    $this->actingAs($w->staffB)->get(route('reports.daily', $w->shiftA))->assertForbidden();
});

test('the daily report shows the cash summary of a closed shift', function () {
    $w = $this->w;
    sell($w->staffA, [[$w->paket, 1]]);  // 18.000 cash
    atWib('21:00');
    app(ShiftService::class)->close($w->shiftA, $w->staffA, 115_000, selfie());

    $this->actingAs($w->owner)->get(route('reports.daily', $w->shiftA))
        ->assertOk()
        ->assertSee('Rp118.000')   // expected
        ->assertSee('Rp115.000')   // counted
        ->assertSee('−Rp3.000')    // variance
        ->assertSee('Cetak laporan');
});

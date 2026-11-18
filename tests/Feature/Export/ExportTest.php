<?php

use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use App\Services\CashService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

beforeEach(function () {
    $this->w = cashWorld();
    Shift::factory()->atOutlet($this->w->outletB, $this->w->staffB)->create();
});

/**
 * Load the downloaded .xlsx.
 */
function spreadsheetOf($response): Spreadsheet
{
    return IOFactory::load($response->baseResponse->getFile()->getPathname());
}

test('the sales export follows the filter, uses a clear filename and keeps rupiah numeric', function () {
    $w = $this->w;
    sell($w->staffA, [[$w->paket, 1]]);     // A: 18.000
    sell($w->staffB, [[$w->satuan, 1]]);    // B: 2.000

    $response = $this->actingAs($w->owner)->get(route('owner.reports.sales.export', [
        'from' => '2026-11-18', 'to' => '2026-11-18', 'outlet' => $w->outletA->id,
    ]));

    $response->assertOk()->assertDownload('laporan-penjualan_FJI_2026-11-18_2026-11-18.xlsx');

    $book = spreadsheetOf($response);
    $days = $book->getSheetByName('Per hari');

    expect($book->getSheetNames())->toContain('Per hari', 'Per produk', 'Per kanal', 'Per metode bayar', 'Per cabang')
        ->and($days->getCell('A1')->getValue())->toBe('Tanggal')
        ->and($days->getCell('A2')->getValue())->toBe('2026-11-18')
        ->and($days->getCell('C2')->getValue())->toBe(18_000)   // numeric, outlet B excluded
        ->and($days->getStyle('C2')->getNumberFormat()->getFormatCode())->toContain('#,##0')
        ->and($book->getSheetByName('Per produk')->getCell('A2')->getValue())->toBe('Paket isi 10');
});

test('cash, inventory, performance and attendance exports download', function () {
    $w = $this->w;
    sell($w->staffA, [[$w->paket, 1]]);
    app(CashService::class)->approve(spend($w->staffA, 5_000), $w->owner);
    clockIn(User::factory()->outletStaff(
        Location::factory()->outlet()->withCoordinates(-7.5, 110.8)->create(['code' => 'XYZ'])
    )->create());

    $q = ['from' => '2026-11-18', 'to' => '2026-11-18'];

    $cash = $this->actingAs($w->owner)->get(route('owner.reports.cash.export', $q))->assertOk();
    expect(spreadsheetOf($cash)->getSheetByName('Laba-rugi')->getCell('B2')->getValue())->toBe(18_000);

    $this->actingAs($w->owner)->get(route('owner.reports.inventory.export', $q))->assertOk()
        ->assertDownload('laporan-persediaan_semua_2026-11-18_2026-11-18.xlsx');
    $this->actingAs($w->owner)->get(route('owner.reports.performance.export', $q))->assertOk();

    $attendance = $this->actingAs($w->owner)->get(route('owner.attendance.export', $q))->assertOk();
    expect(spreadsheetOf($attendance)->getActiveSheet()->getHighestRow())->toBe(2);
});

test('PDF exports: daily outlet report and profit & loss', function () {
    $w = $this->w;
    sell($w->staffA, [[$w->paket, 1]]);

    $this->actingAs($w->owner)->get(route('reports.daily.pdf', $w->shiftA))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('laporan-harian_FJI_2026-11-18.pdf');

    $this->actingAs($w->staffA)->get(route('reports.daily.pdf', $w->shiftA))->assertOk();
    $this->actingAs($w->staffB)->get(route('reports.daily.pdf', $w->shiftA))->assertForbidden();

    $this->actingAs($w->owner)->get(route('owner.reports.cash.pdf', ['from' => '2026-11-18', 'to' => '2026-11-18']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('staff and warehouse admin cannot export financial reports', function () {
    $w = $this->w;

    foreach (['owner.reports.sales.export', 'owner.reports.cash.export', 'owner.reports.cash.pdf', 'owner.reports.performance.export', 'owner.attendance.export', 'owner.reports.inventory.export'] as $route) {
        $this->actingAs($w->staffA)->get(route($route))->assertForbidden();
        $this->actingAs($w->admin)->get(route($route))->assertForbidden();
    }

    // The warehouse admin may export the inventory report from their own area.
    $this->actingAs($w->admin)->get(route('warehouse.reports.export'))->assertOk();
    $this->actingAs($w->staffA)->get(route('warehouse.reports.export'))->assertForbidden();
});

test('invalid filters are rejected', function () {
    $this->actingAs($this->w->owner)
        ->get(route('owner.reports.sales.export', ['from' => '18-11-2026']))
        ->assertSessionHasErrors('from');
});

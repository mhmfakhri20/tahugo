<?php

use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Models\Shift;
use App\Services\ReportService;
use App\Services\SaleService;
use App\Support\Period;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = posWorld(); // 2026-11-18 09:00 WIB, shift open at outlet A
    $this->reports = app(ReportService::class);
});

test('revenue, transaction count and channel/payment composition are correct; void is excluded', function () {
    $w = $this->w;

    sell($w->staffA, [[$w->paket, 1]]);                                                      // offline cash 18.000
    sell($w->staffA, [[$w->satuan, 2]], SalesChannel::Offline, PaymentMethod::Qris);        // offline QRIS 4.000
    sell($w->staffA, [[$w->paket, 1]], SalesChannel::Gofood);                               // GoFood 22.000
    $void = sell($w->staffA, [[$w->paket, 2]], SalesChannel::Grabfood);                     // void
    app(SaleService::class)->void($void, $w->staffA, 'Salah');

    $day = Period::day(now());
    $totals = $this->reports->salesTotals($day);
    $channels = $this->reports->salesByChannel($day)->keyBy(fn ($r) => $r['channel']->value);
    $methods = $this->reports->salesByPaymentMethod($day)->keyBy(fn ($r) => $r['method']->value);

    expect($totals['revenue'])->toBe(44_000)
        ->and($totals['count'])->toBe(3)
        ->and($totals['pcs'])->toBe(4)
        ->and($channels['offline']['revenue'])->toBe(22_000)
        ->and($channels['gofood']['revenue'])->toBe(22_000)
        ->and($channels['grabfood']['revenue'])->toBe(0)
        ->and($channels['offline']['share'])->toBe(50.0)
        ->and($methods['cash']['revenue'])->toBe(18_000)
        ->and($methods['qris']['revenue'])->toBe(4_000);

    $products = $this->reports->salesByProduct($day)->keyBy('product');
    expect($products['Paket isi 10']['qty'])->toBe(2)
        ->and($products['Paket isi 10']['revenue'])->toBe(40_000)
        ->and($products['Tahu satuan']['qty'])->toBe(2);
});

test('day boundaries follow Asia/Jakarta: 23:30 WIB belongs to that day', function () {
    $w = $this->w;

    atWib('23:30');
    sell($w->staffA, [[$w->satuan, 1]]);  // 2026-11-18 23:30 WIB = 16:30 UTC
    atWib('00:15', '2026-11-19');
    sell($w->staffA, [[$w->paket, 1]]);   // next day

    $days = $this->reports->salesByDay(new Period('2026-11-18', '2026-11-19'));

    expect($days['2026-11-18']['revenue'])->toBe(2_000)
        ->and($days['2026-11-18']['count'])->toBe(1)
        ->and($days['2026-11-19']['revenue'])->toBe(18_000)
        ->and($this->reports->salesTotals(Period::day('2026-11-18'))['revenue'])->toBe(2_000);
});

test('the outlet filter only counts that outlet', function () {
    $w = $this->w;
    Shift::factory()->atOutlet($w->outletB, $w->staffB)->create();

    sell($w->staffA, [[$w->paket, 1]]);
    sell($w->staffB, [[$w->satuan, 1]]);

    $day = Period::day(now());

    expect($this->reports->salesTotals($day, $w->outletA->id)['revenue'])->toBe(18_000)
        ->and($this->reports->salesTotals($day, $w->outletB->id)['revenue'])->toBe(2_000)
        ->and($this->reports->salesByOutlet($day)->firstWhere('outlet.id', $w->outletA->id)['share'])->toBe(90.0);
});

test('the sales report page renders numbers and an empty state', function () {
    $w = $this->w;

    Livewire::actingAs($w->owner)
        ->test('pages::reports.sales')
        ->assertSee('Belum ada penjualan');

    sell($w->staffA, [[$w->paket, 1]]);

    Livewire::actingAs($w->owner)
        ->test('pages::reports.sales')
        ->assertSee('Rp18.000')
        ->assertSee('Paket isi 10')
        ->assertSee('Outlet A');
});

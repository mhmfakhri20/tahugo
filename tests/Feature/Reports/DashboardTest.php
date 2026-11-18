<?php

use App\Models\Shift;
use App\Services\ReportService;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = posWorld();
});

test('dashboard metrics compare today with yesterday (REP-1)', function () {
    $w = $this->w;

    atWib('10:00', '2026-11-17');
    sell($w->staffA, [[$w->paket, 1]]);       // yesterday 18.000
    atWib('10:00', '2026-11-18');
    sell($w->staffA, [[$w->paket, 1], [$w->satuan, 9]]); // today 36.000

    $m = app(ReportService::class)->dashboard();

    expect($m['revenue'])->toBe(36_000)
        ->and($m['revenue_change'])->toBe(100.0)
        ->and($m['count'])->toBe(1)
        ->and($m['count_diff'])->toBe(0)
        ->and($m['trend'])->toHaveCount(7)
        ->and($m['trend']->last()['current'])->toBe(36_000)
        ->and($m['week_revenue'])->toBe(54_000);

    Livewire::actingAs($w->owner)
        ->test('pages::owner.dashboard')
        ->assertSee('Rp36.000')
        ->assertSee('100,0% dari kemarin')
        ->assertSee('Stok menipis')
        ->assertSee('Performa penjualan 7 hari');
});

test('the dashboard outlet filter narrows the numbers', function () {
    $w = $this->w;
    Shift::factory()->atOutlet($w->outletB, $w->staffB)->create();

    sell($w->staffA, [[$w->paket, 1]]);
    sell($w->staffB, [[$w->satuan, 1]]);

    expect(app(ReportService::class)->dashboard($w->outletB->id)['revenue'])->toBe(2_000);

    Livewire::actingAs($w->owner)
        ->test('pages::owner.dashboard')
        ->assertSee('Rp20.000')
        ->set('outlet', (string) $w->outletB->id)
        ->assertSee('Rp2.000');
});

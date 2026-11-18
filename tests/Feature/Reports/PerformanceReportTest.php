<?php

use App\Models\Shift;
use App\Services\ReportService;
use App\Support\Period;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = posWorld();
    Shift::factory()->atOutlet($this->w->outletB, $this->w->staffB)->create();
});

test('monthly target progress is correct and outlets without a target do not error (REP-6)', function () {
    $w = $this->w;
    $w->outletA->update(['monthly_target' => 72_000]);
    $w->outletB->update(['monthly_target' => null]);

    atWib('09:00', '2026-11-02');
    sell($w->staffA, [[$w->paket, 1]]);   // 18.000 earlier this month
    atWib('10:00', '2026-11-18');
    sell($w->staffA, [[$w->paket, 1]]);   // 18.000 → 36.000 = 50%
    sell($w->staffB, [[$w->satuan, 1]]);

    $rows = app(ReportService::class)->outletPerformance(new Period('2026-11-12', '2026-11-18'))->keyBy(fn ($r) => $r['outlet']->id);

    expect($rows[$w->outletA->id]['month_revenue'])->toBe(36_000)
        ->and($rows[$w->outletA->id]['progress'])->toBe(50.0)
        ->and($rows[$w->outletB->id]['target'])->toBeNull()
        ->and($rows[$w->outletB->id]['progress'])->toBeNull();

    Livewire::actingAs($w->owner)
        ->test('pages::reports.performance', ['from' => '2026-11-12', 'to' => '2026-11-18'])
        ->assertSee('Target belum diatur')
        ->assertSee('50,0% tercapai');
});

test('growth compares with the previous period of the same length', function () {
    $w = $this->w;

    atWib('10:00', '2026-11-10');
    sell($w->staffA, [[$w->paket, 1]]);            // previous period: 18.000
    atWib('10:00', '2026-11-18');
    sell($w->staffA, [[$w->paket, 1], [$w->satuan, 9]]); // current: 36.000

    $row = app(ReportService::class)->outletPerformance(new Period('2026-11-12', '2026-11-18'))->firstWhere('outlet.id', $w->outletA->id);

    expect($row['previous'])->toBe(18_000)
        ->and($row['revenue'])->toBe(36_000)
        ->and($row['growth'])->toBe(100.0);

    $outletB = app(ReportService::class)->outletPerformance(new Period('2026-11-12', '2026-11-18'))->firstWhere('outlet.id', $w->outletB->id);
    expect($outletB['growth'])->toBeNull();
});

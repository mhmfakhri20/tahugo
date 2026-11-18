<?php

use App\Models\Sale;
use App\Models\Shift;
use App\Services\SaleService;

beforeEach(function () {
    $this->w = posWorld();
});

test('sale numbers are {CODE}-{YYYYMMDD}-{seq}, sequential per outlet per day and unique (POS-7)', function () {
    $w = $this->w;
    Shift::factory()->atOutlet($w->outletB, $w->staffB)->create();

    $a1 = sell($w->staffA, [[$w->satuan, 1]]);
    $a2 = sell($w->staffA2, [[$w->satuan, 1]]);
    $b1 = sell($w->staffB, [[$w->satuan, 1]]);
    $a3 = sell($w->staffA, [[$w->satuan, 1]]);

    expect($a1->number)->toBe('FJI-20261118-0001')
        ->and($a2->number)->toBe('FJI-20261118-0002')
        ->and($a3->number)->toBe('FJI-20261118-0003')
        ->and($b1->number)->toBe('BKK-20261118-0001');

    // A void does not free its number.
    app(SaleService::class)->void($a3, $w->staffA, 'Salah');
    expect(sell($w->staffA, [[$w->satuan, 1]])->number)->toBe('FJI-20261118-0004');

    // The day is the WIB date: 23.59 is still today, 00.01 starts a new sequence.
    atWib('23:59');
    expect(sell($w->staffA, [[$w->satuan, 1]])->number)->toBe('FJI-20261118-0005');

    atWib('00:01', '2026-11-19');
    expect(sell($w->staffA, [[$w->satuan, 1]])->number)->toBe('FJI-20261119-0001');

    expect(Sale::pluck('number')->duplicates())->toBeEmpty();
});

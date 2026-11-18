<?php

use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Services\SaleService;
use App\Services\ShiftService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = posWorld();
    Storage::fake('local');
});

test('expectedCash = opening cash + completed offline cash sales only (§6.3)', function () {
    $w = $this->w;

    sell($w->staffA, [[$w->paket, 1]]);                                                   // +18.000 cash
    sell($w->staffA2, [[$w->satuan, 2]]);                                                 // +4.000 cash
    sell($w->staffA, [[$w->paket, 1]], SalesChannel::Offline, PaymentMethod::Qris);      // QRIS: not counted
    sell($w->staffA, [[$w->paket, 1]], SalesChannel::Gofood);                            // platform: not counted
    $voided = sell($w->staffA, [[$w->paket, 1]]);                                         // void: not counted
    app(SaleService::class)->void($voided, $w->staffA, 'Batal');

    expect(app(ShiftService::class)->expectedCash($w->shiftA->fresh()))->toBe(100_000 + 18_000 + 4_000);

    $summary = app(SaleService::class)->shiftSummary($w->shiftA);

    expect($summary['count'])->toBe(4)
        ->and($summary['void_count'])->toBe(1)
        ->and($summary['cash'])->toBe(22_000)
        ->and($summary['qris'])->toBe(18_000)
        ->and($summary['per_channel']['gofood'])->toBe(22_000)
        ->and($summary['per_channel']['offline'])->toBe(40_000)
        ->and($summary['total'])->toBe(62_000)
        // 3 completed packages × 10 + 2 satuan = 32 tahu (the voided package is returned)
        ->and($summary['usage']->firstWhere('item.id', $w->tahu->id)['qty'])->toBe('32.000');
});

test('closing the shift stores expected cash including sales and shows the summary', function () {
    $w = $this->w;
    sell($w->staffA, [[$w->paket, 2]]); // 36.000 cash

    atWib('16:00');

    Livewire::actingAs($w->staffA)
        ->test('pages::outlet.shift-close')
        ->assertSee('Penjualan shift ini')
        ->assertSee('Rp36.000')
        ->assertSee('Tahu mentah')
        ->set('countedCash', '130000')
        ->assertSee('Rp6.000') // 130.000 − 136.000
        ->set('photo', selfie())
        ->call('close')
        ->assertHasNoErrors();

    $shift = $w->shiftA->fresh();

    expect($shift->expected_cash)->toBe(136_000)
        ->and($shift->counted_cash)->toBe(130_000)
        ->and($shift->variance)->toBe(-6_000);
});

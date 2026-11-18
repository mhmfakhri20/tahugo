<?php

use App\Enums\MovementType;
use App\Enums\SaleStatus;
use App\Exceptions\SaleException;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Services\SaleService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = posWorld();
});

test('void returns the stock exactly and marks the sale void (BR-1, POS-8)', function () {
    $w = $this->w;
    $sale = sell($w->staffA, [[$w->paket, 2]]);

    expect(stockOf($w->outletA, $w->tahu))->toBe('80.000');

    atWib('09:30');

    Livewire::actingAs($w->staffA2)
        ->test('pages::outlet.sales')
        ->assertSee($sale->number)
        ->call('confirmVoid', $sale->id)
        ->set('voidReason', 'Salah input produk')
        ->call('void')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    $sale->refresh();

    expect($sale->status)->toBe(SaleStatus::Void)
        ->and($sale->void_reason)->toBe('Salah input produk')
        ->and($sale->voided_by)->toBe($w->staffA2->id)
        ->and($sale->voided_at->format('H:i'))->toBe('09:30')
        ->and(stockOf($w->outletA, $w->tahu))->toBe('100.000')
        ->and(stockOf($w->outletA, $w->bumbu))->toBe('20.000')
        ->and(stockOf($w->outletA, $w->kemasan))->toBe('20.000');

    $out = $sale->stockMovements()->where('type', MovementType::SaleOut)->get()->keyBy('item_id');
    $in = $sale->stockMovements()->where('type', MovementType::SaleVoidIn)->get()->keyBy('item_id');

    expect($in->keys()->sort()->values()->all())->toBe($out->keys()->sort()->values()->all());
    foreach ($out as $itemId => $movement) {
        expect($in[$itemId]->qty)->toBe(ltrim($movement->qty, '-'));
    }
});

test('void reverses the original ledger even if the recipe changed afterwards', function () {
    $w = $this->w;
    $sale = sell($w->staffA, [[$w->paket, 1]]);

    $w->paket->recipes()->where('item_id', $w->tahu->id)->update(['qty' => 3]);

    app(SaleService::class)->void($sale, $w->staffA, 'Pelanggan batal');

    expect(stockOf($w->outletA, $w->tahu))->toBe('100.000');
});

test('a reason is required and a sale can be voided only once', function () {
    $w = $this->w;
    $sale = sell($w->staffA, [[$w->satuan, 1]]);

    Livewire::actingAs($w->staffA)
        ->test('pages::outlet.sales')
        ->call('confirmVoid', $sale->id)
        ->set('voidReason', '')
        ->call('void')
        ->assertHasErrors(['voidReason' => 'required']);

    app(SaleService::class)->void($sale, $w->staffA, 'Salah kanal');

    expect(fn () => app(SaleService::class)->void($sale->fresh(), $w->staffA, 'Lagi'))
        ->toThrow(SaleException::class, 'sudah dibatalkan');

    expect(StockMovement::where('type', MovementType::SaleVoidIn)->count())->toBe(1);
});

test('sales of a closed shift cannot be voided by staff (BR-6)', function () {
    $w = $this->w;
    $sale = sell($w->staffA, [[$w->satuan, 1]]);

    $w->shiftA->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $w->staffA->id]);

    expect(fn () => app(SaleService::class)->void($sale->fresh(), $w->staffA, 'Terlambat'))
        ->toThrow(SaleException::class, 'shift yang sudah ditutup');

    expect($w->staffA->can('void', $sale->fresh()))->toBeFalse()
        ->and($sale->fresh()->status)->toBe(SaleStatus::Completed)
        ->and(stockOf($w->outletA, $w->tahu))->toBe('99.000');
});

test('staff of another outlet cannot see or void the sale (BR-7)', function () {
    $w = $this->w;
    $sale = sell($w->staffA, [[$w->satuan, 1]]);

    // Outlet B has its own open shift so B can reach its history page.
    Shift::factory()->atOutlet($w->outletB, $w->staffB)->create();

    expect(fn () => app(SaleService::class)->void($sale, $w->staffB, 'Coba-coba'))
        ->toThrow(AuthorizationException::class);

    Livewire::actingAs($w->staffB)
        ->test('pages::outlet.sales')
        ->assertDontSee($sale->number)
        ->call('confirmVoid', $sale->id)
        ->assertForbidden();

    expect($w->staffB->can('view', $sale))->toBeFalse()
        ->and($sale->fresh()->status)->toBe(SaleStatus::Completed);
});

test('the history only lists sales of the current shift', function () {
    $w = $this->w;
    $old = Sale::query()->create([
        'number' => 'FJI-20261117-0001', 'location_id' => $w->outletA->id,
        'shift_id' => Shift::factory()->atOutlet($w->outletA, $w->staffA)->closed()->create()->id,
        'user_id' => $w->staffA->id, 'channel' => 'offline', 'payment_method' => 'cash',
        'subtotal' => 2_000, 'total' => 2_000, 'cash_received' => 2_000, 'change' => 0,
    ]);
    $current = sell($w->staffA, [[$w->satuan, 1]]);

    Livewire::actingAs($w->staffA)
        ->test('pages::outlet.sales')
        ->assertSee($current->number)
        ->assertDontSee($old->number);
});

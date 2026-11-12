<?php

use App\Enums\MovementType;
use App\Enums\StockLevel;
use App\Services\StockRequestService;
use App\Services\StockService;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = inventoryWorld();
    $w = $this->w;

    app(StockService::class)->move($w->warehouse, $w->tahu, MovementType::PurchaseIn, 40, $w->admin);
    $w->tahu->minStocks()->create(['location_id' => $w->warehouse->id, 'min_qty' => 100]);
    $w->tahu->minStocks()->create(['location_id' => $w->outletA->id, 'min_qty' => 20]);

    $this->pending = app(StockRequestService::class)->create($w->outletA, [$w->tahu->id => 30], null, $w->staffA);
    $this->approved = app(StockRequestService::class)->create($w->outletB, [$w->tahu->id => 10], null, $w->owner, preApproved: true);
});

test('owner pages render', function () {
    $w = $this->w;
    $this->actingAs($w->owner);

    $this->get(route('owner.stock'))->assertOk()->assertSee('Monitor stok lintas lokasi');
    $this->get(route('owner.verification'))->assertOk()->assertSee($this->pending->number);
    $this->get(route('owner.requests.create'))->assertOk();
    $this->get(route('owner.requests.show', $this->pending))->assertOk()->assertSee('Setujui permintaan');
    $this->get(route('owner.stock.card', [$w->warehouse, $w->tahu]))->assertOk()->assertSee('Pembelian masuk');
});

test('warehouse pages render', function () {
    $w = $this->w;
    $this->actingAs($w->admin);

    $this->get(route('warehouse.stock'))->assertOk()->assertSee('Stok menipis');
    $this->get(route('warehouse.stock', ['locationId' => $w->outletA->id]))->assertOk();
    $this->get(route('warehouse.purchases'))->assertOk();
    $this->get(route('warehouse.purchases.create'))->assertOk();
    $this->get(route('warehouse.shipments'))->assertOk()->assertSee($this->approved->number)->assertSee('Terkunci');
    $this->get(route('warehouse.requests.show', $this->approved))->assertOk()->assertSee('Catat pengiriman');
    $this->get(route('warehouse.stock.card', [$w->warehouse, $w->tahu]))->assertOk();
});

test('outlet pages render', function () {
    $w = $this->w;
    $this->actingAs($w->staffA);

    $this->get(route('outlet.stock'))->assertOk()->assertSee('Tahu mentah');
    $this->get(route('outlet.stock', ['tab' => 'requests']))->assertOk()->assertSee($this->pending->number);
    $this->get(route('outlet.requests.create'))->assertOk()->assertSee('Minta stok ke gudang');
    $this->get(route('outlet.requests.show', $this->pending))->assertOk()->assertSee('Menunggu persetujuan pemilik');
    $this->get(route('outlet.stock.card', $w->tahu))->assertOk();
});

test('owner dashboard shows low stock and pending requests (INV-2)', function () {
    $this->actingAs($this->w->owner)
        ->get(route('owner.dashboard'))
        ->assertOk()
        ->assertSee('Stok menipis')
        ->assertSee('Gudang Solo')
        ->assertSee('Permintaan menunggu')
        ->assertSee($this->pending->number);
});

test('warehouse home shows requests ready to ship', function () {
    $this->actingAs($this->w->admin)
        ->get(route('warehouse.home'))
        ->assertOk()
        ->assertSee('Permintaan siap dikirim')
        ->assertSee($this->approved->number)
        ->assertDontSee($this->pending->number);
});

test('stock level badges: Habis, Stok menipis, Aman', function () {
    expect(StockLevel::for('0', '10')->label())->toBe('Habis')
        ->and(StockLevel::for('9.999', '10')->label())->toBe('Stok menipis')
        ->and(StockLevel::for('10', '10')->label())->toBe('Aman')
        ->and(StockLevel::for('3', null)->label())->toBe('Aman');
});

test('owner adjusts stock from the stock page with a required reason', function () {
    $w = $this->w;

    Livewire::actingAs($w->owner)
        ->test('pages::owner.stock')
        ->call('openAdjust', $w->outletA->id, $w->tahu->id)
        ->set('adjustDirection', 'in')
        ->set('adjustQty', '25')
        ->call('adjust')
        ->assertHasErrors('adjustReason')
        ->set('adjustReason', 'Hitung fisik')
        ->call('adjust')
        ->assertHasNoErrors();

    expect(stockOf($w->outletA, $w->tahu))->toBe('25.000');
});

test('outlet staff record waste from their stock page', function () {
    $w = $this->w;
    app(StockService::class)->move($w->outletA, $w->tahu, MovementType::TransferIn, 10, $w->staffA);

    Livewire::actingAs($w->staffA)
        ->test('pages::outlet.stock')
        ->call('openWaste', $w->tahu->id)
        ->set('wasteQty', '3')
        ->set('wasteReason', 'Basi')
        ->call('recordWaste')
        ->assertHasNoErrors();

    expect(stockOf($w->outletA, $w->tahu))->toBe('7.000');
});

<?php

use App\Enums\MovementType;
use App\Enums\RequestStatus;
use App\Services\StockRequestService;
use App\Services\StockService;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = inventoryWorld();
    app(StockService::class)->move($this->w->warehouse, $this->w->tahu, MovementType::PurchaseIn, 500, $this->w->admin);

    $this->requestB = app(StockRequestService::class)->create($this->w->outletB, [$this->w->tahu->id => 10], null, $this->w->staffB);
});

test('outlet staff A gets 403 for a request of outlet B (BR-7)', function () {
    $this->actingAs($this->w->staffA)
        ->get(route('outlet.requests.show', $this->requestB))
        ->assertForbidden();

    Livewire::actingAs($this->w->staffA)
        ->test('pages::stock-requests.show', ['stockRequest' => $this->requestB])
        ->assertForbidden();
});

test('outlet staff B can open their own request', function () {
    $this->actingAs($this->w->staffB)
        ->get(route('outlet.requests.show', $this->requestB))
        ->assertOk()
        ->assertSee($this->requestB->number);
});

test('outlet staff A gets 403 for stock of outlet B', function () {
    $w = $this->w;

    // Area pemilik/gudang (dengan parameter lokasi) tertutup untuk penjaga.
    $this->actingAs($w->staffA)->get(route('owner.stock.card', [$w->outletB, $w->tahu]))->assertForbidden();
    $this->actingAs($w->staffA)->get(route('warehouse.stock.card', [$w->outletB, $w->tahu]))->assertForbidden();
    $this->actingAs($w->staffA)->get(route('warehouse.stock', ['locationId' => $w->outletB->id]))->assertForbidden();

    // Kartu stok penjaga selalu memakai outletnya sendiri.
    Livewire::actingAs($w->staffA)
        ->test('pages::stock.card', ['item' => $w->tahu, 'location' => $w->outletB])
        ->assertSet('locationId', $w->outletA->id);

    expect($w->staffA->can('viewStock', $w->outletB))->toBeFalse()
        ->and($w->staffA->can('viewStock', $w->outletA))->toBeTrue()
        ->and($w->staffA->can('recordWaste', $w->outletB))->toBeFalse();
});

test('outlet staff list only shows their own requests', function () {
    app(StockRequestService::class)->create($this->w->outletA, [$this->w->tahu->id => 5], null, $this->w->staffA);

    Livewire::actingAs($this->w->staffA)
        ->test('pages::outlet.stock', ['tab' => 'requests'])
        ->set('tab', 'requests')
        ->assertDontSee($this->requestB->number);
});

test('outlet staff and warehouse admin cannot approve', function () {
    $w = $this->w;

    // Penjaga outlet B (pemilik permintaan) tetap tidak boleh menyetujui.
    Livewire::actingAs($w->staffB)
        ->test('pages::stock-requests.show', ['stockRequest' => $this->requestB])
        ->call('approve')
        ->assertForbidden();

    Livewire::actingAs($w->admin)
        ->test('pages::stock-requests.show', ['stockRequest' => $this->requestB])
        ->call('approve')
        ->assertForbidden();

    expect($this->requestB->fresh()->status)->toBe(RequestStatus::Pending);
});

test('only the warehouse admin ships and only the destination outlet receives', function () {
    $w = $this->w;
    $service = app(StockRequestService::class);
    $service->approve($this->requestB, [], $w->owner);

    Livewire::actingAs($w->owner)
        ->test('pages::stock-requests.show', ['stockRequest' => $this->requestB])
        ->call('ship')
        ->assertForbidden();

    $service->ship($this->requestB->fresh(), [], null, $w->admin);

    Livewire::actingAs($w->admin)
        ->test('pages::stock-requests.show', ['stockRequest' => $this->requestB])
        ->call('receive')
        ->assertForbidden();

    expect($this->requestB->fresh()->status)->toBe(RequestStatus::Shipped);
});

test('outlet staff cannot adjust stock nor open the owner stock page', function () {
    $w = $this->w;

    expect($w->staffA->can('adjustStock', $w->outletA))->toBeFalse()
        ->and($w->admin->can('adjustStock', $w->warehouse))->toBeFalse()
        ->and($w->owner->can('adjustStock', $w->outletA))->toBeTrue();

    $this->actingAs($w->staffA)->get(route('owner.stock'))->assertForbidden();
});

test('warehouse admin can record waste only at a warehouse', function () {
    $w = $this->w;

    expect($w->admin->can('recordWaste', $w->warehouse))->toBeTrue()
        ->and($w->admin->can('recordWaste', $w->outletA))->toBeFalse();

    Livewire::actingAs($w->admin)
        ->test('pages::warehouse.stock', ['locationId' => $w->outletA->id])
        ->call('openWaste', $w->tahu->id)
        ->assertForbidden();
});

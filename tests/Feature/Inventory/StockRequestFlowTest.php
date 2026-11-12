<?php

use App\Enums\MovementType;
use App\Enums\RequestStatus;
use App\Exceptions\InventoryException;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Services\StockRequestService;
use App\Services\StockService;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = inventoryWorld();
    $this->service = app(StockRequestService::class);

    app(StockService::class)->moveMany([
        ['location' => $this->w->warehouse, 'item' => $this->w->tahu, 'qty' => 1000],
        ['location' => $this->w->warehouse, 'item' => $this->w->saus, 'qty' => 50],
    ], MovementType::PurchaseIn, $this->w->admin);
});

test('full flow pending → approved → shipped → received keeps warehouse and outlet balances right (BR-8)', function () {
    $w = $this->w;

    // 1. Penjaga outlet A mengajukan permintaan.
    Livewire::actingAs($w->staffA)
        ->test('pages::stock-requests.create')
        ->set('lines', [
            ['item_id' => $w->tahu->id, 'qty' => '200'],
            ['item_id' => $w->saus->id, 'qty' => '20'],
        ])
        ->set('note', 'Stok menipis')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $request = StockRequest::with('items')->sole();
    $tahuLine = $request->items->firstWhere('item_id', $w->tahu->id);
    $sausLine = $request->items->firstWhere('item_id', $w->saus->id);

    expect($request->status)->toBe(RequestStatus::Pending)
        ->and($request->to_location_id)->toBe($w->outletA->id)
        ->and($request->from_location_id)->toBe($w->warehouse->id)
        ->and($request->requested_by)->toBe($w->staffA->id);

    // 2. Pemilik menyetujui dengan mengubah qty tahu.
    Livewire::actingAs($w->owner)
        ->test('pages::stock-requests.show', ['stockRequest' => $request])
        ->set("qtys.{$tahuLine->id}", '150')
        ->call('approve')
        ->assertHasNoErrors();

    $request->refresh();
    expect($request->status)->toBe(RequestStatus::Approved)
        ->and($request->approved_by)->toBe($w->owner->id)
        ->and($request->approved_at)->not->toBeNull()
        ->and($tahuLine->fresh()->qty_approved)->toBe('150.000');

    // 3. Gudang mengirim kurang dari disetujui (catatan wajib).
    Livewire::actingAs($w->admin)
        ->test('pages::stock-requests.show', ['stockRequest' => $request])
        ->set("qtys.{$tahuLine->id}", '140')
        ->set("qtys.{$sausLine->id}", '20')
        ->call('ship')
        ->assertHasErrors('inventory')
        ->set('actionNote', 'Sebagian tahu rusak')
        ->call('ship')
        ->assertHasNoErrors();

    $request->refresh();
    expect($request->status)->toBe(RequestStatus::Shipped)
        ->and($request->shipped_by)->toBe($w->admin->id)
        ->and($request->ship_note)->toBe('Sebagian tahu rusak')
        ->and(stockOf($w->warehouse, $w->tahu))->toBe('860.000')
        ->and(stockOf($w->warehouse, $w->saus))->toBe('30.000')
        ->and(stockOf($w->outletA, $w->tahu))->toBe('0.000');

    // 4. Penjaga menerima 138 pcs tahu (2 pcs kurang).
    Livewire::actingAs($w->staffA)
        ->test('pages::stock-requests.show', ['stockRequest' => $request])
        ->set("qtys.{$tahuLine->id}", '138')
        ->set('actionNote', '2 pcs hancur di jalan')
        ->call('receive')
        ->assertHasNoErrors();

    $request->refresh()->load('items');
    expect($request->status)->toBe(RequestStatus::Received)
        ->and($request->received_by)->toBe($w->staffA->id)
        ->and($request->received_at)->not->toBeNull()
        ->and(stockOf($w->outletA, $w->tahu))->toBe('138.000')
        ->and(stockOf($w->outletA, $w->saus))->toBe('20.000')
        ->and(stockOf($w->warehouse, $w->tahu))->toBe('860.000')
        ->and($request->hasDiscrepancy())->toBeTrue()
        ->and($request->items->firstWhere('item_id', $w->tahu->id)->receiveDifference())->toBe('-2.000')
        ->and($request->items->firstWhere('item_id', $w->saus->id)->hasReceiveDiscrepancy())->toBeFalse();

    expect(StockMovement::where('type', MovementType::TransferOut)->sum('qty'))->toEqual(-160)
        ->and(StockMovement::where('type', MovementType::TransferIn)->sum('qty'))->toEqual(158)
        ->and(app(StockService::class)->verify())->toBeEmpty();
});

test('request numbers follow REQ-DDMMYY-XXX per day', function () {
    $w = $this->w;
    Carbon::setTestNow('2026-11-12 09:00:00');

    $first = $this->service->create($w->outletA, [$w->tahu->id => 10], null, $w->staffA);
    $second = $this->service->create($w->outletB, [$w->tahu->id => 10], null, $w->staffB);

    Carbon::setTestNow('2026-11-13 08:00:00');
    $nextDay = $this->service->create($w->outletA, [$w->tahu->id => 10], null, $w->staffA);

    expect($first->number)->toBe('REQ-121126-001')
        ->and($second->number)->toBe('REQ-121126-002')
        ->and($nextDay->number)->toBe('REQ-131126-001');

    Carbon::setTestNow();
});

test('the owner can create a request that is approved right away', function () {
    $w = $this->w;

    Livewire::actingAs($w->owner)
        ->test('pages::stock-requests.create')
        ->set('outletId', $w->outletB->id)
        ->set('lines', [['item_id' => $w->tahu->id, 'qty' => '75']])
        ->call('save')
        ->assertHasNoErrors();

    $request = StockRequest::with('items')->sole();

    expect($request->status)->toBe(RequestStatus::Approved)
        ->and($request->to_location_id)->toBe($w->outletB->id)
        ->and($request->approved_by)->toBe($w->owner->id)
        ->and($request->items->first()->qty_approved)->toBe('75.000');
});

test('outlet staff always request for their own outlet', function () {
    $w = $this->w;

    Livewire::actingAs($w->staffA)
        ->test('pages::stock-requests.create')
        ->set('outletId', $w->outletB->id)
        ->set('lines', [['item_id' => $w->tahu->id, 'qty' => '5']])
        ->call('save');

    expect(StockRequest::sole()->to_location_id)->toBe($w->outletA->id);
});

test('rejecting needs a reason and records who and when', function () {
    $w = $this->w;
    $request = $this->service->create($w->outletA, [$w->tahu->id => 10], null, $w->staffA);

    Livewire::actingAs($w->owner)
        ->test('pages::stock-requests.show', ['stockRequest' => $request])
        ->call('confirmReject')
        ->call('reject')
        ->assertHasErrors('rejectReason')
        ->set('rejectReason', 'Stok outlet masih cukup')
        ->call('reject')
        ->assertHasNoErrors();

    $request->refresh();
    expect($request->status)->toBe(RequestStatus::Rejected)
        ->and($request->rejected_by)->toBe($w->owner->id)
        ->and($request->rejected_at)->not->toBeNull()
        ->and($request->reject_reason)->toBe('Stok outlet masih cukup');
});

test('illegal status transitions are refused', function () {
    $w = $this->w;
    $request = $this->service->create($w->outletA, [$w->tahu->id => 10], null, $w->staffA);

    expect(fn () => $this->service->ship($request, [], null, $w->admin))->toThrow(InventoryException::class)
        ->and(fn () => $this->service->receive($request, [], null, $w->staffA))->toThrow(InventoryException::class);

    $this->service->reject($request, 'Tidak perlu', $w->owner);

    expect(fn () => $this->service->approve($request, [], $w->owner))->toThrow(InventoryException::class)
        ->and(fn () => $this->service->cancel($request, $w->owner))->toThrow(InventoryException::class);
});

test('the requester can cancel a pending request', function () {
    $w = $this->w;
    $request = $this->service->create($w->outletA, [$w->tahu->id => 10], null, $w->staffA);

    Livewire::actingAs($w->staffA)
        ->test('pages::stock-requests.show', ['stockRequest' => $request])
        ->call('cancel')
        ->assertHasNoErrors();

    expect($request->fresh()->status)->toBe(RequestStatus::Cancelled)
        ->and($request->fresh()->cancelled_by)->toBe($w->staffA->id);
});

<?php

use App\Enums\MovementType;
use App\Enums\RequestStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InventoryException;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Services\StockRequestService;
use App\Services\StockService;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = inventoryWorld();
    $this->service = app(StockRequestService::class);

    app(StockService::class)->move($this->w->warehouse, $this->w->tahu, MovementType::PurchaseIn, 100, $this->w->admin);

    // Disetujui 150 padahal gudang hanya punya 100.
    $this->request = $this->service->create($this->w->outletA, [$this->w->tahu->id => 150], null, $this->w->owner, preApproved: true);
    $this->line = $this->request->items->first();
});

test('shipping more than the warehouse has is rejected and nothing changes (BR-2, BR-8)', function () {
    $w = $this->w;
    $movementsBefore = StockMovement::count();

    Livewire::actingAs($w->admin)
        ->test('pages::stock-requests.show', ['stockRequest' => $this->request])
        ->set("qtys.{$this->line->id}", '150')
        ->call('ship')
        ->assertHasErrors(['inventory' => 'Stok Tahu mentah di Gudang Solo tidak cukup (tersedia 100 pcs, dibutuhkan 150 pcs).']);

    expect($this->request->fresh()->status)->toBe(RequestStatus::Approved)
        ->and($this->line->fresh()->qty_shipped)->toBeNull()
        ->and(stockOf($w->warehouse, $w->tahu))->toBe('100.000')
        ->and(StockMovement::count())->toBe($movementsBefore);
});

test('shipping exactly the available stock (with a note) is allowed and leaves zero', function () {
    $w = $this->w;

    $this->service->ship($this->request, [$this->line->id => 100], 'Stok gudang hanya 100', $w->admin);

    expect(stockOf($w->warehouse, $w->tahu))->toBe('0.000')
        ->and($this->request->fresh()->status)->toBe(RequestStatus::Shipped);
});

test('shipping more than approved is rejected', function () {
    expect(fn () => $this->service->ship($this->request, [$this->line->id => 151], null, $this->w->admin))
        ->toThrow(InventoryException::class);
});

test('balances never go negative after many attempts', function () {
    $w = $this->w;

    foreach ([120, 101, 100.001] as $qty) {
        try {
            $this->service->ship($this->request, [$this->line->id => $qty], 'coba', $w->admin);
        } catch (InsufficientStockException) {
            // expected
        }
    }

    expect(StockBalance::where('qty', '<', 0)->count())->toBe(0)
        ->and(stockOf($w->warehouse, $w->tahu))->toBe('100.000');
});

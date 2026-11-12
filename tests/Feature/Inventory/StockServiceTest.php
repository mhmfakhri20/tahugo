<?php

use App\Enums\MovementType;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InventoryException;
use App\Models\StockMovement;
use App\Services\StockAdjustmentService;
use App\Services\StockService;

beforeEach(function () {
    $this->w = inventoryWorld();
    $this->stock = app(StockService::class);
});

test('a movement writes the ledger and updates the cached balance in one go', function () {
    $w = $this->w;

    $this->stock->move($w->warehouse, $w->tahu, MovementType::PurchaseIn, '100', $w->admin);
    $movement = $this->stock->move($w->warehouse, $w->tahu, MovementType::TransferOut, '-30.5', $w->admin, note: 'Kirim');

    expect(stockOf($w->warehouse, $w->tahu))->toBe('69.500')
        ->and($movement->qty)->toBe('-30.500')
        ->and($movement->balance_after)->toBe('69.500')
        ->and($movement->note)->toBe('Kirim')
        ->and(StockMovement::count())->toBe(2);
});

test('a movement that would make stock negative is rejected and changes nothing (BR-2)', function () {
    $w = $this->w;
    $this->stock->move($w->outletA, $w->tahu, MovementType::TransferIn, 10, $w->staffA);

    expect(fn () => $this->stock->move($w->outletA, $w->tahu, MovementType::SaleOut, -11, $w->staffA))
        ->toThrow(InsufficientStockException::class, 'Stok Tahu mentah di Outlet A tidak cukup (tersedia 10 pcs, dibutuhkan 11 pcs).');

    expect(stockOf($w->outletA, $w->tahu))->toBe('10.000')
        ->and(StockMovement::count())->toBe(1);
});

test('moveMany is all-or-nothing', function () {
    $w = $this->w;
    $this->stock->move($w->warehouse, $w->tahu, MovementType::PurchaseIn, 50, $w->admin);

    expect(fn () => $this->stock->moveMany([
        ['location' => $w->warehouse, 'item' => $w->tahu, 'qty' => -20],
        ['location' => $w->warehouse, 'item' => $w->saus, 'qty' => -1],
    ], MovementType::TransferOut, $w->admin))->toThrow(InsufficientStockException::class);

    expect(stockOf($w->warehouse, $w->tahu))->toBe('50.000')
        ->and(StockMovement::count())->toBe(1);
});

test('only adjustments may go negative', function () {
    $w = $this->w;

    expect(fn () => $this->stock->move($w->outletA, $w->tahu, MovementType::Waste, -1, $w->owner, allowNegative: true))
        ->toThrow(InventoryException::class);

    $movement = app(StockAdjustmentService::class)->adjust($w->outletA, $w->tahu, '-3', 'Selisih hitung fisik', $w->owner);

    expect($movement->type)->toBe(MovementType::Adjustment)
        ->and(stockOf($w->outletA, $w->tahu))->toBe('-3.000');
});

test('adjustment is owner only and needs a reason', function () {
    $w = $this->w;
    $service = app(StockAdjustmentService::class);

    expect(fn () => $service->adjust($w->warehouse, $w->tahu, 5, 'Koreksi', $w->admin))->toThrow(InventoryException::class)
        ->and(fn () => $service->adjust($w->warehouse, $w->tahu, 5, '  ', $w->owner))->toThrow(InventoryException::class, 'Alasan wajib diisi.');
});

test('waste reduces stock, needs a reason and cannot go negative', function () {
    $w = $this->w;
    $service = app(StockAdjustmentService::class);
    $this->stock->move($w->outletA, $w->tahu, MovementType::TransferIn, 10, $w->staffA);

    $service->waste($w->outletA, $w->tahu, 4, 'Tahu basi', $w->staffA);

    expect(stockOf($w->outletA, $w->tahu))->toBe('6.000')
        ->and(StockMovement::latest('id')->first()->type)->toBe(MovementType::Waste)
        ->and(fn () => $service->waste($w->outletA, $w->tahu, 1, '', $w->staffA))->toThrow(InventoryException::class)
        ->and(fn () => $service->waste($w->outletA, $w->tahu, 7, 'Jatuh', $w->staffA))->toThrow(InsufficientStockException::class);
});

<?php

use App\Enums\MovementType;
use App\Exceptions\InventoryException;
use App\Models\StockBalance;
use App\Services\PurchaseService;
use App\Services\StockAdjustmentService;
use App\Services\StockRequestService;
use App\Services\StockService;

test('stock:verify passes after a random series of transactions (BR-3)', function () {
    $w = inventoryWorld();
    $items = [$w->tahu, $w->saus];
    $outlets = [[$w->outletA, $w->staffA], [$w->outletB, $w->staffB]];

    $purchases = app(PurchaseService::class);
    $requests = app(StockRequestService::class);
    $adjustments = app(StockAdjustmentService::class);

    mt_srand(2026);

    for ($round = 0; $round < 25; $round++) {
        try {
            match (mt_rand(1, 4)) {
                1 => $purchases->record($w->warehouse, ['supplier' => 'Supplier', 'purchase_date' => now()->toDateString()], [
                    ['item_id' => $items[mt_rand(0, 1)]->id, 'qty' => mt_rand(1, 300) / 4, 'unit_price' => mt_rand(100, 900)],
                ], null, $w->admin),
                2 => (function () use ($requests, $outlets, $items, $w) {
                    [$outlet, $staff] = $outlets[mt_rand(0, 1)];
                    $request = $requests->create($outlet, [$items[mt_rand(0, 1)]->id => mt_rand(1, 80)], null, $staff);
                    $requests->approve($request, [], $w->owner);
                    $request = $request->fresh('items');
                    $line = $request->items->first();
                    $requests->ship($request, [$line->id => $line->qty_approved], null, $w->admin);
                    $received = max(0, (float) $line->qty_approved - mt_rand(0, 2));
                    $requests->receive($request->fresh(), [$line->id => $received], 'Hitung ulang', $staff);
                })(),
                3 => $adjustments->waste($outlets[mt_rand(0, 1)][0], $items[mt_rand(0, 1)], mt_rand(1, 5), 'Rusak', $w->staffA),
                4 => $adjustments->adjust($outlets[mt_rand(0, 1)][0], $items[mt_rand(0, 1)], mt_rand(-5, 5) ?: 1, 'Koreksi', $w->owner),
            };
        } catch (InventoryException) {
            // Rejected operations (e.g. not enough stock) must not break consistency either.
        }
    }

    expect(app(StockService::class)->verify())->toBeEmpty();

    $this->artisan('stock:verify')
        ->expectsOutput('Semua saldo cocok dengan ledger.')
        ->assertSuccessful();
});

test('stock:verify fails when a balance was changed outside the ledger', function () {
    $w = inventoryWorld();
    app(StockService::class)->move($w->warehouse, $w->tahu, MovementType::PurchaseIn, 10, $w->admin);

    StockBalance::query()->where('item_id', $w->tahu->id)->update(['qty' => 12]);

    $this->artisan('stock:verify')
        ->expectsOutputToContain('tidak cocok')
        ->assertFailed();
});

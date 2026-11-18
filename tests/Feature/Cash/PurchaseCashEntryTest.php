<?php

use App\Enums\CashDirection;
use App\Enums\ExpenseStatus;
use App\Enums\FundSource;
use App\Exceptions\InventoryException;
use App\Models\CashEntry;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Services\PurchaseService;

beforeEach(function () {
    $this->w = cashWorld();
});

function purchase(object $w, array $lines): Purchase
{
    return app(PurchaseService::class)->record(
        $w->warehouse,
        ['supplier' => 'Pak Darto', 'purchase_date' => now()->toDateString()],
        $lines,
        null,
        $w->admin,
    );
}

test('a warehouse purchase creates an approved business cash entry in the same transaction (CASH-7)', function () {
    $w = $this->w;

    $purchase = purchase($w, [['item_id' => $w->tahu->id, 'qty' => 100, 'unit_price' => 450]]);

    $entry = CashEntry::sole();

    expect($entry->direction)->toBe(CashDirection::Out)
        ->and($entry->fund_source)->toBe(FundSource::Business)
        ->and($entry->status)->toBe(ExpenseStatus::Approved)
        ->and($entry->amount)->toBe(45_000)
        ->and($entry->location_id)->toBe($w->warehouse->id)
        ->and($entry->shift_id)->toBeNull()
        ->and($entry->source_type)->toBe($purchase->getMorphClass())
        ->and($entry->source_id)->toBe($purchase->id)
        ->and($entry->description)->toContain($purchase->number())
        ->and($purchase->cashEntry->is($entry))->toBeTrue();
});

test('if the cash entry fails the whole purchase is rolled back', function () {
    $w = $this->w;
    $movementsBefore = StockMovement::count();

    CashEntry::creating(fn () => throw new RuntimeException('disk penuh'));

    expect(fn () => purchase($w, [['item_id' => $w->tahu->id, 'qty' => 100, 'unit_price' => 450]]))
        ->toThrow(RuntimeException::class);

    expect(Purchase::count())->toBe(0)
        ->and(CashEntry::count())->toBe(0)
        ->and(StockMovement::count())->toBe($movementsBefore)
        ->and(stockOf($w->warehouse, $w->tahu))->toBe('0.000');
});

test('if a purchase line fails no cash entry is created', function () {
    $w = $this->w;

    expect(fn () => purchase($w, [['item_id' => $w->tahu->id, 'qty' => 0, 'unit_price' => 450]]))
        ->toThrow(InventoryException::class);

    expect(Purchase::count())->toBe(0)->and(CashEntry::count())->toBe(0);
});

test('cash:backfill-purchases creates the missing entries once', function () {
    $w = $this->w;

    $purchase = purchase($w, [['item_id' => $w->tahu->id, 'qty' => 10, 'unit_price' => 500]]);
    $purchase->cashEntry()->delete(); // simulate a purchase recorded before F5

    $this->artisan('cash:backfill-purchases')->expectsOutputToContain('1 pembelian')->assertSuccessful();
    $this->artisan('cash:backfill-purchases')->expectsOutputToContain('0 pembelian')->assertSuccessful();

    expect(CashEntry::count())->toBe(1)
        ->and(CashEntry::sole()->amount)->toBe(5_000)
        ->and(CashEntry::sole()->created_at->equalTo($purchase->created_at))->toBeTrue();
});

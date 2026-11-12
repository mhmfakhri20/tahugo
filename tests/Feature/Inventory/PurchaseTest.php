<?php

use App\Enums\MovementType;
use App\Models\Purchase;
use App\Models\StockMovement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * A real 1×1 PNG (no GD needed) so the `image` rule passes.
 */
function fakeReceipt(): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'nota.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
    );
}

beforeEach(function () {
    $this->w = inventoryWorld();
    Storage::fake('local');
});

test('a purchase adds stock to the warehouse with a correct ledger and updates the last price', function () {
    $w = $this->w;

    Livewire::actingAs($w->admin)
        ->test('pages::warehouse.purchase-create')
        ->set('supplier', 'Pabrik Tahu Pak Darto')
        ->set('purchaseDate', now()->toDateString())
        ->set('lines', [
            ['item_id' => $w->tahu->id, 'qty' => '500', 'unit_price' => '450'],
            ['item_id' => $w->saus->id, 'qty' => '12.5', 'unit_price' => '1000'],
        ])
        ->set('receipt', fakeReceipt())
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $purchase = Purchase::with('items')->sole();

    expect($purchase->total)->toBe(500 * 450 + 12_500)
        ->and($purchase->location_id)->toBe($w->warehouse->id)
        ->and($purchase->items)->toHaveCount(2)
        ->and(stockOf($w->warehouse, $w->tahu))->toBe('500.000')
        ->and(stockOf($w->warehouse, $w->saus))->toBe('12.500')
        ->and($w->tahu->fresh()->last_purchase_price)->toBe(450);

    $movement = StockMovement::where('item_id', $w->tahu->id)->sole();
    expect($movement->type)->toBe(MovementType::PurchaseIn)
        ->and($movement->qty)->toBe('500.000')
        ->and($movement->balance_after)->toBe('500.000')
        ->and($movement->location_id)->toBe($w->warehouse->id)
        ->and($movement->reference_type)->toBe($purchase->getMorphClass())
        ->and($movement->reference_id)->toBe($purchase->id);

    Storage::disk('local')->assertExists($purchase->receipt_photo_path);
});

test('the receipt photo is served only to owner and warehouse admin', function () {
    $w = $this->w;

    Livewire::actingAs($w->admin)
        ->test('pages::warehouse.purchase-create')
        ->set('supplier', 'Toko Bumbu')
        ->set('lines', [['item_id' => $w->saus->id, 'qty' => '10', 'unit_price' => '900']])
        ->set('receipt', fakeReceipt())
        ->call('save');

    $purchase = Purchase::sole();

    $this->actingAs($w->admin)->get(route('purchases.receipt', $purchase))->assertOk();
    $this->actingAs($w->owner)->get(route('purchases.receipt', $purchase))->assertOk();
    $this->actingAs($w->staffA)->get(route('purchases.receipt', $purchase))->assertForbidden();
});

test('purchase validation: lines, quantity and file type are checked', function () {
    $w = $this->w;

    Livewire::actingAs($w->admin)
        ->test('pages::warehouse.purchase-create')
        ->set('supplier', '')
        ->set('lines', [['item_id' => $w->tahu->id, 'qty' => '0', 'unit_price' => '-1']])
        ->set('receipt', UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf'))
        ->call('save')
        ->assertHasErrors(['supplier', 'lines.0.qty', 'lines.0.unit_price', 'receipt']);

    expect(Purchase::count())->toBe(0)->and(StockMovement::count())->toBe(0);
});

test('only the warehouse admin can record purchases', function () {
    $w = $this->w;

    Livewire::actingAs($w->owner)->test('pages::warehouse.purchase-create')->assertForbidden();
    $this->actingAs($w->staffA)->get(route('warehouse.purchases.create'))->assertForbidden();
});

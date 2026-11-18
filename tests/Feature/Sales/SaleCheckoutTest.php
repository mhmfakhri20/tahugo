<?php

use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Enums\SaleStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\SaleException;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = posWorld();
});

test('selling 1 package reduces outlet stock exactly by its BOM (BR-1)', function () {
    $w = $this->w;
    $ledgerBefore = StockMovement::count();

    Livewire::actingAs($w->staffA)
        ->test('pages::outlet.cashier')
        ->call('checkout', 'offline', [['product_id' => $w->paket->id, 'qty' => 1]], 'cash', 20_000)
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    $sale = Sale::with('items')->sole();

    expect($sale->status)->toBe(SaleStatus::Completed)
        ->and($sale->shift_id)->toBe($w->shiftA->id)
        ->and($sale->location_id)->toBe($w->outletA->id)
        ->and($sale->total)->toBe(18_000)
        ->and($sale->cash_received)->toBe(20_000)
        ->and($sale->change)->toBe(2_000)
        ->and($sale->items->sole()->unit_price)->toBe(18_000)
        ->and(stockOf($w->outletA, $w->tahu))->toBe('90.000')
        ->and(stockOf($w->outletA, $w->bumbu))->toBe('19.000')
        ->and(stockOf($w->outletA, $w->kemasan))->toBe('19.000')
        // outlet B untouched
        ->and(stockOf($w->outletB, $w->tahu))->toBe('100.000');

    $movements = StockMovement::where('reference_id', $sale->id)->where('reference_type', $sale->getMorphClass())->get();

    expect(StockMovement::count())->toBe($ledgerBefore + 3)
        ->and($movements)->toHaveCount(3)
        ->and($movements->every(fn ($m) => $m->type === MovementType::SaleOut))->toBeTrue()
        ->and($movements->firstWhere('item_id', $w->tahu->id)->qty)->toBe('-10.000');
});

test('BOM usage is summed per item across lines', function () {
    $w = $this->w;

    sell($w->staffA, [[$w->paket, 2], [$w->satuan, 3]]);

    // 2×10 + 3×1 tahu, one ledger row per item.
    expect(stockOf($w->outletA, $w->tahu))->toBe('77.000')
        ->and(StockMovement::where('type', MovementType::SaleOut)->where('item_id', $w->tahu->id)->count())->toBe(1);
});

test('prices sent by the client are ignored; totals use the channel price in the DB', function () {
    $w = $this->w;

    Livewire::actingAs($w->staffA)
        ->test('pages::outlet.cashier')
        ->call('checkout', 'offline', [
            ['product_id' => $w->paket->id, 'qty' => 2, 'unit_price' => 1, 'line_total' => 2, 'price' => 1],
        ], 'cash', 36_000)
        ->assertHasNoErrors();

    $sale = Sale::with('items')->sole();

    expect($sale->total)->toBe(36_000)
        ->and($sale->items->sole()->unit_price)->toBe(18_000)
        ->and($sale->items->sole()->line_total)->toBe(36_000);
});

test('each channel stores its own price and platform sales have no payment method', function () {
    $w = $this->w;

    $gofood = sell($w->staffA, [[$w->paket, 1], [$w->satuan, 2]], SalesChannel::Gofood, PaymentMethod::Cash, 999);
    $grab = sell($w->staffA, [[$w->paket, 1]], SalesChannel::Grabfood);

    expect($gofood->payment_method)->toBeNull()
        ->and($gofood->cash_received)->toBeNull()
        ->and($gofood->change)->toBeNull()
        ->and($gofood->total)->toBe(22_000 + 2 * 2_500)
        ->and(SaleItem::where('sale_id', $gofood->id)->where('product_id', $w->paket->id)->value('unit_price'))->toBe(22_000)
        ->and($grab->items->sole()->unit_price)->toBe(21_000);
});

test('a platform order number is stored for platform sales only', function () {
    $w = $this->w;

    Livewire::actingAs($w->staffA)
        ->test('pages::outlet.cashier')
        ->call('checkout', 'shopeefood', [['product_id' => $w->satuan->id, 'qty' => 1]], null, null, 'SF-998877')
        ->assertHasNoErrors();

    expect(Sale::sole()->platform_order_id)->toBe('SF-998877')
        ->and(Sale::sole()->total)->toBe(2_500);
});

test('QRIS sales have no cash received or change', function () {
    $sale = sell($this->w->staffA, [[$this->w->satuan, 1]], SalesChannel::Offline, PaymentMethod::Qris, 50_000);

    expect($sale->payment_method)->toBe(PaymentMethod::Qris)
        ->and($sale->cash_received)->toBeNull()
        ->and($sale->change)->toBeNull();
});

test('cash received below the total is rejected', function () {
    Livewire::actingAs($this->w->staffA)
        ->test('pages::outlet.cashier')
        ->call('checkout', 'offline', [['product_id' => $this->w->paket->id, 'qty' => 1]], 'cash', 15_000)
        ->assertHasErrors('checkout');

    expect(Sale::count())->toBe(0);
});

test('not enough stock rejects the sale with the item name and rolls everything back (BR-2)', function () {
    $w = $this->w;
    $ledgerBefore = StockMovement::count();

    // 11 packages need 110 tahu, only 100 available (bumbu & kemasan would be enough for 11? no: 20 each → fine).
    expect(fn () => sell($w->staffA, [[$w->paket, 11]]))
        ->toThrow(InsufficientStockException::class, 'Tahu mentah');

    Livewire::actingAs($w->staffA)
        ->test('pages::outlet.cashier')
        ->call('checkout', 'offline', [['product_id' => $w->paket->id, 'qty' => 11]], 'cash', 1_000_000)
        ->assertHasErrors('checkout')
        ->assertSee('Tahu mentah');

    expect(Sale::count())->toBe(0)
        ->and(SaleItem::count())->toBe(0)
        ->and(StockMovement::count())->toBe($ledgerBefore)
        ->and(stockOf($w->outletA, $w->tahu))->toBe('100.000')
        ->and(stockOf($w->outletA, $w->bumbu))->toBe('20.000');
});

test('products without a recipe or a channel price cannot be sold', function () {
    $w = $this->w;

    $noRecipe = makeProduct('Tanpa resep', ['offline' => 5_000, 'gofood' => 5_000, 'grabfood' => 5_000, 'shopeefood' => 5_000], []);
    $noGofood = makeProduct('Offline saja', ['offline' => 5_000, 'gofood' => 0], [[$w->tahu, 1]]);

    expect(fn () => sell($w->staffA, [[$noRecipe, 1]]))->toThrow(SaleException::class, 'Resep Tanpa resep belum diatur');
    expect(fn () => sell($w->staffA, [[$noGofood, 1]], SalesChannel::Gofood))->toThrow(SaleException::class, 'Harga Offline saja untuk GoFood belum diatur');
    expect(fn () => sell($w->staffA, [[$noGofood, 1]], SalesChannel::Grabfood))->toThrow(SaleException::class, 'belum diatur');
    expect(fn () => sell($w->staffA, [[Product::factory()->inactive()->withPrices()->create(), 1]]))->toThrow(SaleException::class);
    expect(fn () => sell($w->staffA, []))->toThrow(SaleException::class, 'Keranjang masih kosong');

    expect(Sale::count())->toBe(0);
});

test('the cashier card shows why a product cannot be sold', function () {
    makeProduct('Tanpa resep', ['offline' => 5_000], []);

    Livewire::actingAs($this->w->staffA)
        ->test('pages::outlet.cashier')
        ->assertOk()
        ->assertSee('Paket isi 10')
        ->assertSee('no_recipe', escape: false);
});

test('without an open shift no sale is possible (BR-4)', function () {
    $w = $this->w;
    $w->shiftA->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $w->staffA->id]);

    expect(fn () => sell($w->staffA, [[$w->satuan, 1]]))->toThrow(SaleException::class, 'Shift belum dibuka');

    $this->actingAs($w->staffA)->get(route('outlet.cashier'))->assertRedirect(route('outlet.home'));
    $this->actingAs($w->staffB)->get(route('outlet.cashier'))->assertRedirect(route('outlet.home'));

    expect(Sale::count())->toBe(0);
});

test('only outlet staff can sell', function () {
    expect(fn () => sell($this->w->owner, [[$this->w->satuan, 1]]))
        ->toThrow(AuthorizationException::class);
});

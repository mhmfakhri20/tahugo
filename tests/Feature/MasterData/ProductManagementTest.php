<?php

use App\Enums\ProductType;
use App\Enums\SalesChannel;
use App\Models\Item;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
});

function allPrices(int $offline = 15_000, int $platform = 18_000): array
{
    return [
        SalesChannel::Offline->value => $offline,
        SalesChannel::Gofood->value => $platform,
        SalesChannel::Grabfood->value => $platform,
        SalesChannel::Shopeefood->value => $platform,
    ];
}

test('owner creates a paket product with prices per channel and a recipe', function () {
    $tahu = Item::factory()->create(['unit' => 'pcs']);
    $bumbu = Item::factory()->create(['unit' => 'gram']);

    Livewire::actingAs($this->owner)
        ->test('pages::owner.settings.product-edit')
        ->set('name', 'Paket isi 10')
        ->set('type', ProductType::Paket->value)
        ->set('prices', array_map('strval', allPrices()))
        ->call('addLine')
        ->set('recipe.0.item_id', $tahu->id)
        ->set('recipe.0.qty', '10')
        ->call('addLine')
        ->set('recipe.1.item_id', $bumbu->id)
        ->set('recipe.1.qty', '2.5')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('owner.settings.products'));

    $product = Product::with('prices', 'recipes')->where('name', 'Paket isi 10')->firstOrFail();

    expect($product->type)->toBe(ProductType::Paket)
        ->and($product->priceFor(SalesChannel::Offline))->toBe(15_000)
        ->and($product->priceFor(SalesChannel::Gofood))->toBe(18_000)
        ->and($product->prices)->toHaveCount(4)
        ->and($product->recipes->firstWhere('item_id', $tahu->id)->qty)->toEqual('10.000')
        ->and($product->recipes->firstWhere('item_id', $bumbu->id)->qty)->toEqual('2.500');
});

test('all four channel prices are required', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::owner.settings.product-edit')
        ->set('name', 'Tahu Go Reguler')
        ->set('prices.offline', '1500')
        ->call('save')
        ->assertHasErrors(['prices.gofood' => 'required', 'prices.grabfood' => 'required', 'prices.shopeefood' => 'required']);

    expect(fn () => app(ProductService::class)->save(null, ['name' => 'X', 'type' => ProductType::Single], [SalesChannel::Offline->value => 1000], []))
        ->toThrow(ValidationException::class);
});

test('a zero price is allowed', function () {
    $product = app(ProductService::class)->save(null, ['name' => 'Gratis', 'type' => ProductType::Single], allPrices(0, 0), []);

    expect($product->priceFor(SalesChannel::Offline))->toBe(0);
});

test('a recipe cannot contain the same item twice', function () {
    $tahu = Item::factory()->create();

    Livewire::actingAs($this->owner)
        ->test('pages::owner.settings.product-edit')
        ->set('name', 'Ganda')
        ->set('prices', array_map('strval', allPrices()))
        ->set('recipe', [['item_id' => $tahu->id, 'qty' => '1'], ['item_id' => $tahu->id, 'qty' => '2']])
        ->call('save')
        ->assertHasErrors(['recipe.0.item_id' => 'distinct']);

    expect(fn () => app(ProductService::class)->save(null, ['name' => 'Ganda', 'type' => ProductType::Single], allPrices(), [
        ['item_id' => $tahu->id, 'qty' => 1], ['item_id' => $tahu->id, 'qty' => 2],
    ]))->toThrow(ValidationException::class);
});

test('a recipe cannot use an inactive item or a zero quantity', function () {
    $inactive = Item::factory()->inactive()->create();
    $active = Item::factory()->create();

    try {
        app(ProductService::class)->save(null, ['name' => 'Salah', 'type' => ProductType::Single], allPrices(), [
            ['item_id' => $inactive->id, 'qty' => 1],
            ['item_id' => $active->id, 'qty' => 0],
        ]);
        $this->fail('Expected validation exception.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKeys(['recipe.0.item_id', 'recipe.1.qty']);
    }

    expect(Product::where('name', 'Salah')->exists())->toBeFalse();
});

test('saving a product replaces its old recipe and keeps one price per channel', function () {
    $old = Item::factory()->create();
    $new = Item::factory()->create();
    $service = app(ProductService::class);

    $product = $service->save(null, ['name' => 'Paket isi 5', 'type' => ProductType::Paket], allPrices(), [['item_id' => $old->id, 'qty' => 5]]);
    $service->save($product, ['name' => 'Paket isi 5', 'type' => ProductType::Paket], allPrices(9_000), [['item_id' => $new->id, 'qty' => 5]]);

    $product->refresh()->load('prices', 'recipes');

    expect($product->recipes)->toHaveCount(1)
        ->and($product->recipes->first()->item_id)->toBe($new->id)
        ->and($product->prices)->toHaveCount(4)
        ->and($product->priceFor(SalesChannel::Offline))->toBe(9_000);
});

test('the edit page loads existing prices and recipe', function () {
    $item = Item::factory()->create();
    $product = Product::factory()->withPrices(2_500)->create();
    $product->recipes()->create(['item_id' => $item->id, 'qty' => 1.5]);

    Livewire::actingAs($this->owner)
        ->test('pages::owner.settings.product-edit', ['product' => $product])
        ->assertSet('name', $product->name)
        ->assertSet('prices.offline', '2500')
        ->assertSet('recipe', [['item_id' => $item->id, 'qty' => '1.5']]);
});

test('products list shows prices in rupiah and warns about empty recipes', function () {
    Product::factory()->withPrices(15_000)->create(['name' => 'Tahu Go Reguler']);

    $this->actingAs($this->owner)
        ->get(route('owner.settings.products'))
        ->assertOk()
        ->assertSee('Tahu Go Reguler')
        ->assertSee('Rp15.000')
        ->assertSee('Belum ada');
});

test('only the owner manages products, everyone can read them', function () {
    $product = Product::factory()->create();
    $staff = User::factory()->outletStaff()->create();

    expect($staff->can('view', $product))->toBeTrue()
        ->and($staff->can('update', $product))->toBeFalse();

    $this->actingAs($staff)->get(route('owner.settings.products.edit', $product))->assertForbidden();
});

test('owner deactivates a product', function () {
    $product = Product::factory()->create();

    Livewire::actingAs($this->owner)
        ->test('pages::owner.settings.products')
        ->call('confirmToggle', $product->id)
        ->call('toggleActive');

    expect($product->fresh()->is_active)->toBeFalse();
});

<?php

use App\Enums\ItemCategory;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use App\Services\ItemService;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
});

test('owner creates an item with minimum stock per location', function () {
    $warehouse = Location::factory()->warehouse()->create();
    $outlet = Location::factory()->outlet()->create();

    Livewire::actingAs($this->owner)
        ->test('pages::owner.settings.items')
        ->call('create')
        ->set('name', 'Tahu mentah')
        ->set('unit', 'pcs')
        ->set('category', ItemCategory::Tahu->value)
        ->set('last_purchase_price', '500')
        ->set("minStocks.{$warehouse->id}", '200')
        ->set("minStocks.{$outlet->id}", '12.5')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    $item = Item::where('name', 'Tahu mentah')->firstOrFail();

    expect($item->last_purchase_price)->toBe(500)
        ->and($item->minStocks()->where('location_id', $warehouse->id)->value('min_qty'))->toEqual('200.000')
        ->and($item->minStocks()->where('location_id', $outlet->id)->value('min_qty'))->toEqual('12.500');
});

test('clearing a minimum stock removes it', function () {
    $outlet = Location::factory()->outlet()->create();
    $item = Item::factory()->create();
    $item->minStocks()->create(['location_id' => $outlet->id, 'min_qty' => 10]);

    app(ItemService::class)->save($item, $item->only('name', 'unit', 'category', 'last_purchase_price'), [$outlet->id => '']);

    expect($item->minStocks()->count())->toBe(0);
});

test('item name must be unique and fields are required', function () {
    Item::factory()->create(['name' => 'Saus']);

    Livewire::actingAs($this->owner)
        ->test('pages::owner.settings.items')
        ->call('create')
        ->set('name', 'Saus')
        ->call('save')
        ->assertHasErrors(['name' => 'unique', 'unit' => 'required', 'category' => 'required']);
});

test('negative minimum stock is rejected', function () {
    $outlet = Location::factory()->outlet()->create();

    Livewire::actingAs($this->owner)
        ->test('pages::owner.settings.items')
        ->set('name', 'Plastik')
        ->set('unit', 'pcs')
        ->set('category', ItemCategory::Packaging->value)
        ->set("minStocks.{$outlet->id}", '-1')
        ->call('save')
        ->assertHasErrors(["minStocks.{$outlet->id}" => 'min']);
});

test('owner deactivates an item', function () {
    $item = Item::factory()->create();

    Livewire::actingAs($this->owner)
        ->test('pages::owner.settings.items')
        ->call('confirmToggle', $item->id)
        ->call('toggleActive');

    expect($item->fresh()->is_active)->toBeFalse();
});

test('everyone can read items but only the owner manages them', function () {
    $item = Item::factory()->create();
    $staff = User::factory()->outletStaff()->create();
    $admin = User::factory()->warehouseAdmin()->create();

    expect($staff->can('view', $item))->toBeTrue()
        ->and($admin->can('viewAny', Item::class))->toBeTrue()
        ->and($staff->can('update', $item))->toBeFalse()
        ->and($admin->can('create', Item::class))->toBeFalse();

    Livewire::actingAs($admin)->test('pages::owner.settings.items')->assertForbidden();
});

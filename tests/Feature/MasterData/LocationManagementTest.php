<?php

use App\Enums\LocationType;
use App\Models\Location;
use App\Models\User;
use App\Services\LocationService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
});

test('owner creates an outlet with an uppercase code and default radius', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::owner.settings.locations')
        ->call('create')
        ->set('code', 'slo')
        ->set('name', 'Tahu Go Solo Baru')
        ->set('latitude', '-7.5605')
        ->set('longitude', '110.8261')
        ->set('open_time', '10:00')
        ->set('close_time', '21:00')
        ->call('save')
        ->assertHasNoErrors();

    $location = Location::where('code', 'SLO')->firstOrFail();

    expect($location->type)->toBe(LocationType::Outlet)
        ->and($location->attendance_radius_m)->toBe(100)
        ->and($location->open_time)->toStartWith('10:00');
});

test('location code must be unique', function () {
    Location::factory()->create(['code' => 'FJI']);

    Livewire::actingAs($this->owner)
        ->test('pages::owner.settings.locations')
        ->call('create')
        ->set('code', 'fji')
        ->set('name', 'Duplikat')
        ->call('save')
        ->assertHasErrors(['code' => 'unique']);
});

test('closing time must be after opening time', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::owner.settings.locations')
        ->call('create')
        ->set('code', 'ABC')
        ->set('name', 'Outlet Baru')
        ->set('open_time', '21:00')
        ->set('close_time', '10:00')
        ->call('save')
        ->assertHasErrors(['close_time' => 'after']);
});

test('warehouses do not keep opening hours or target', function () {
    $location = app(LocationService::class)->save(null, [
        'code' => 'GD2', 'name' => 'Gudang Dua', 'type' => LocationType::Warehouse,
        'open_time' => '08:00', 'close_time' => '17:00', 'monthly_target' => 1_000_000,
    ]);

    expect($location->open_time)->toBeNull()
        ->and($location->close_time)->toBeNull()
        ->and($location->monthly_target)->toBeNull();
});

test('an outlet with active staff cannot be deactivated', function () {
    $outlet = Location::factory()->outlet()->create();
    User::factory()->outletStaff($outlet)->create();

    Livewire::actingAs($this->owner)
        ->test('pages::owner.settings.locations')
        ->call('confirmToggle', $outlet->id)
        ->call('toggleActive')
        ->assertDispatched('notify', variant: 'danger');

    expect($outlet->fresh()->is_active)->toBeTrue();
});

test('an outlet without active staff can be deactivated', function () {
    $outlet = Location::factory()->outlet()->create();
    User::factory()->outletStaff($outlet)->inactive()->create();

    app(LocationService::class)->setActive($outlet, false);

    expect($outlet->fresh()->is_active)->toBeFalse();
});

test('an outlet with active staff cannot become a warehouse', function () {
    $outlet = Location::factory()->outlet()->create();
    User::factory()->outletStaff($outlet)->create();

    expect(fn () => app(LocationService::class)->save($outlet, [
        'code' => $outlet->code, 'name' => $outlet->name, 'type' => LocationType::Warehouse,
    ]))->toThrow(ValidationException::class);
});

test('outlet staff can only view their own outlet', function () {
    $own = Location::factory()->outlet()->create();
    $other = Location::factory()->outlet()->create();
    $staff = User::factory()->outletStaff($own)->create();

    expect($staff->can('view', $own))->toBeTrue()
        ->and($staff->can('view', $other))->toBeFalse()
        ->and($staff->can('viewAny', Location::class))->toBeFalse()
        ->and($staff->can('update', $own))->toBeFalse();
});

test('warehouse admin can view but not manage locations', function () {
    $admin = User::factory()->warehouseAdmin()->create();
    $outlet = Location::factory()->outlet()->create();

    expect($admin->can('view', $outlet))->toBeTrue()
        ->and($admin->can('create', Location::class))->toBeFalse();

    $this->actingAs($admin)->get(route('owner.settings.locations'))->assertForbidden();
});

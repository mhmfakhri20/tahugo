<?php

use App\Enums\LocationType;
use App\Enums\Role;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

test('database seeder creates placeholder locations and dev users', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Location::pluck('type', 'code')->all())->toEqual([
        'GDG' => LocationType::Warehouse,
        'FJI' => LocationType::Outlet,
        'BKK' => LocationType::Outlet,
    ]);

    $users = User::with('location')->get()->keyBy('username');

    expect($users->keys()->sort()->values()->all())->toBe(['gudang', 'outlet_bkk', 'outlet_fji', 'owner']);
    expect($users['owner']->role)->toBe(Role::Owner);
    expect($users['gudang']->role)->toBe(Role::WarehouseAdmin);
    expect($users['outlet_fji']->role)->toBe(Role::OutletStaff);
    expect($users['outlet_fji']->location->code)->toBe('FJI');
    expect($users['outlet_bkk']->location->code)->toBe('BKK');
    expect(Hash::check('password', $users['owner']->password))->toBeTrue();
});

test('database seeder can be run twice without duplicates', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Location::count())->toBe(3);
    expect(User::count())->toBe(4);
});

test('dev users are not seeded in production', function () {
    app()->detectEnvironment(fn () => 'production');

    // Invoke directly: `db:seed` asks for confirmation in production.
    app(DatabaseSeeder::class)->setContainer(app())->__invoke();

    expect(Location::count())->toBe(3);
    expect(User::count())->toBe(0);
});

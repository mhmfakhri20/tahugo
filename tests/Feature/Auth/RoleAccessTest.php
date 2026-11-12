<?php

use App\Models\User;

$areas = [
    'owner' => ['owner.dashboard', 'owner.verification', 'owner.reports', 'owner.attendance',
        'owner.settings.users', 'owner.settings.locations', 'owner.settings.items', 'owner.settings.products',
        'owner.settings.products.create', 'owner.settings.expense-categories', 'owner.stock', 'owner.requests.create',
    ],
    'outlet' => ['outlet.home', 'outlet.cashier', 'outlet.expenses', 'outlet.stock', 'outlet.requests.create'],
    'warehouse' => ['warehouse.home', 'warehouse.stock', 'warehouse.purchases', 'warehouse.shipments', 'warehouse.reports', 'warehouse.purchases.create'],
];

$users = [
    'owner' => fn () => User::factory()->owner()->create(),
    'outlet' => fn () => User::factory()->outletStaff()->create(),
    'warehouse' => fn () => User::factory()->warehouseAdmin()->create(),
];

test('guests are redirected to the login page', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(array_merge(['dashboard'], ...array_values($areas)));

foreach ($users as $role => $makeUser) {
    test("{$role} can open every page of its own area", function () use ($makeUser, $areas, $role) {
        $this->actingAs($makeUser());

        foreach ($areas[$role] as $route) {
            $this->get(route($route))->assertOk();
        }
    });

    test("{$role} gets 403 in other roles' areas", function () use ($makeUser, $areas, $role) {
        $this->actingAs($makeUser());

        foreach ($areas as $area => $routes) {
            if ($area === $role) {
                continue;
            }

            foreach ($routes as $route) {
                $this->get(route($route))->assertForbidden();
            }
        }
    });
}

test('unknown urls in another role area return 403, not 404', function (string $uri) {
    $this->actingAs(User::factory()->outletStaff()->create())
        ->get($uri)
        ->assertForbidden();
})->with(['/owner/dashboard', '/owner/does-not-exist', '/owner/a/b/c', '/warehouse/anything']);

test('unknown urls inside the user own area still return 404', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->get('/owner/does-not-exist')
        ->assertNotFound();
});

test('guests opening an unknown url in a role area are sent to login', function () {
    $this->get('/owner/does-not-exist')->assertRedirect(route('login'));
});

test('outlet staff without an assigned outlet is forbidden from the outlet area', function () {
    $user = User::factory()->outletStaff()->create(['location_id' => null]);

    $this->actingAs($user)->get(route('outlet.home'))->assertForbidden();
});

test('outlet staff sees the bottom navigation and their outlet name', function () {
    $user = User::factory()->outletStaff()->create();

    $this->actingAs($user)->get(route('outlet.home'))
        ->assertOk()
        ->assertSee($user->location->name)
        ->assertSeeInOrder(['Beranda', 'Kasir', 'Pengeluaran', 'Stok', 'Akun']);
});

test('owner sees the owner menu', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->get(route('owner.dashboard'))
        ->assertSeeInOrder(['Dashboard', 'Verifikasi', 'Laporan', 'Absensi', 'Pengaturan']);
});

test('warehouse admin sees the warehouse menu', function () {
    $this->actingAs(User::factory()->warehouseAdmin()->create())
        ->get(route('warehouse.home'))
        ->assertSeeInOrder(['Beranda', 'Stok Gudang', 'Pembelian', 'Pengiriman', 'Laporan Persediaan']);
});

<?php

use App\Enums\Role;
use App\Models\Location;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
});

test('owner can open the users page, other roles get 403', function () {
    $this->actingAs($this->owner)->get(route('owner.settings.users'))->assertOk()->assertSee('Pengguna');

    $this->actingAs(User::factory()->warehouseAdmin()->create())->get(route('owner.settings.users'))->assertForbidden();
    $this->actingAs(User::factory()->outletStaff()->create())->get(route('owner.settings.users'))->assertForbidden();
});

test('settings root redirects to the users tab', function () {
    $this->actingAs($this->owner)->get(route('owner.settings'))->assertRedirect(route('owner.settings.users'));
});

test('owner creates an outlet staff account bound to an outlet', function () {
    $outlet = Location::factory()->outlet()->create();

    Livewire::actingAs($this->owner)
        ->test('pages::owner.settings.users')
        ->call('create')
        ->set('name', 'Siti Rahma')
        ->set('username', 'Siti_Solo')
        ->set('role', Role::OutletStaff->value)
        ->set('location_id', $outlet->id)
        ->set('password', 'rahasia123')
        ->set('password_confirmation', 'rahasia123')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    $user = User::where('username', 'siti_solo')->firstOrFail();

    expect($user->role)->toBe(Role::OutletStaff)
        ->and($user->location_id)->toBe($outlet->id)
        ->and($user->is_active)->toBeTrue()
        ->and(Hash::check('rahasia123', $user->password))->toBeTrue();
});

test('outlet staff without an outlet is rejected', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::owner.settings.users')
        ->set('name', 'Tanpa Outlet')
        ->set('username', 'tanpa_outlet')
        ->set('role', Role::OutletStaff->value)
        ->set('password', 'rahasia123')
        ->set('password_confirmation', 'rahasia123')
        ->call('save')
        ->assertHasErrors(['location_id' => 'required']);

    expect(User::where('username', 'tanpa_outlet')->exists())->toBeFalse();
});

test('outlet staff cannot be assigned to a warehouse or inactive outlet', function () {
    $warehouse = Location::factory()->warehouse()->create();
    $inactive = Location::factory()->outlet()->inactive()->create();

    foreach ([$warehouse, $inactive] as $location) {
        expect(fn () => app(UserService::class)->create([
            'name' => 'X', 'username' => 'x_'.$location->id, 'role' => Role::OutletStaff,
            'location_id' => $location->id, 'password' => 'rahasia123',
        ]))->toThrow(ValidationException::class);
    }
});

test('non outlet roles never keep a location', function () {
    $outlet = Location::factory()->outlet()->create();

    $user = app(UserService::class)->create([
        'name' => 'Admin', 'username' => 'admin2', 'role' => Role::WarehouseAdmin,
        'location_id' => $outlet->id, 'password' => 'rahasia123',
    ]);

    expect($user->location_id)->toBeNull();
});

test('username is unique regardless of letter case', function () {
    User::factory()->create(['username' => 'budi']);

    Livewire::actingAs($this->owner)
        ->test('pages::owner.settings.users')
        ->set('name', 'Budi Lain')
        ->set('username', 'BUDI')
        ->set('role', Role::WarehouseAdmin->value)
        ->set('password', 'rahasia123')
        ->set('password_confirmation', 'rahasia123')
        ->call('save')
        ->assertHasErrors(['username' => 'unique']);
});

test('owner edits a user role and outlet', function () {
    $staff = User::factory()->outletStaff()->create();

    Livewire::actingAs($this->owner)
        ->test('pages::owner.settings.users')
        ->call('edit', $staff->id)
        ->assertSet('username', $staff->username)
        ->set('role', Role::WarehouseAdmin->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($staff->fresh())
        ->role->toBe(Role::WarehouseAdmin)
        ->location_id->toBeNull();
});

test('owner cannot change their own role', function () {
    expect(fn () => app(UserService::class)->update($this->owner, $this->owner, [
        'name' => $this->owner->name, 'username' => $this->owner->username, 'role' => Role::WarehouseAdmin,
    ]))->toThrow(ValidationException::class);

    expect($this->owner->fresh()->role)->toBe(Role::Owner);
});

test('deactivated user can no longer log in', function () {
    $staff = User::factory()->outletStaff()->create();

    Livewire::actingAs($this->owner)
        ->test('pages::owner.settings.users')
        ->call('confirmToggle', $staff->id)
        ->call('toggleActive')
        ->assertDispatched('notify');

    expect($staff->fresh()->is_active)->toBeFalse();

    auth()->logout();

    $this->post(route('login.store'), ['username' => $staff->username, 'password' => 'password'])
        ->assertSessionHasErrors('username');
    $this->assertGuest();
});

test('owner cannot deactivate their own account', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::owner.settings.users')
        ->call('confirmToggle', $this->owner->id)
        ->assertForbidden();

    expect(fn () => app(UserService::class)->setActive($this->owner, $this->owner, false))
        ->toThrow(ValidationException::class);

    expect($this->owner->fresh()->is_active)->toBeTrue();
});

test('reset password sets a new password and removes the user sessions', function () {
    $staff = User::factory()->outletStaff()->create();
    DB::table('sessions')->insert([
        'id' => 'sesi-lama', 'user_id' => $staff->id, 'payload' => '', 'last_activity' => now()->timestamp,
    ]);

    Livewire::actingAs($this->owner)
        ->test('pages::owner.settings.users')
        ->call('confirmResetPassword', $staff->id)
        ->set('password', 'baru12345')
        ->set('password_confirmation', 'baru12345')
        ->call('resetPassword')
        ->assertHasNoErrors();

    expect(Hash::check('baru12345', $staff->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $staff->id)->exists())->toBeFalse();
});

test('non owners cannot call user management actions directly', function () {
    $admin = User::factory()->warehouseAdmin()->create();
    $staff = User::factory()->outletStaff()->create();

    Livewire::actingAs($admin)->test('pages::owner.settings.users')->assertForbidden();

    expect($admin->can('update', $staff))->toBeFalse()
        ->and($admin->can('resetPassword', $staff))->toBeFalse()
        ->and($staff->can('create', User::class))->toBeFalse();
});

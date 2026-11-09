<?php

use App\Enums\Role;
use App\Models\User;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('profile.edit'))->assertOk();
});

test('profile name and phone can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', 'Siti Penjaga')
        ->set('phone', '0812-3456-7890')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Siti Penjaga');
    expect($user->phone)->toBe('0812-3456-7890');
});

test('empty phone is stored as null', function () {
    $user = User::factory()->create(['phone' => '0812']);

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('phone', '')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->refresh()->phone)->toBeNull();
});

test('profile can not change username, role or outlet', function () {
    $user = User::factory()->outletStaff()->create();
    $originalLocation = $user->location_id;

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', 'Nama Baru')
        ->call('updateProfileInformation');

    $user->refresh();

    expect($user->role)->toBe(Role::OutletStaff);
    expect($user->location_id)->toBe($originalLocation);
});

test('users can not delete their own account', function () {
    expect(fn () => Livewire::test('pages::settings.delete-user-modal'))->toThrow(Exception::class);
});

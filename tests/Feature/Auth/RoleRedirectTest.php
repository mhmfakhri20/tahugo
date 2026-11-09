<?php

use App\Models\User;

dataset('roles', [
    'owner' => [fn () => User::factory()->owner()->create(), 'owner.dashboard'],
    'outlet staff' => [fn () => User::factory()->outletStaff()->create(), 'outlet.home'],
    'warehouse admin' => [fn () => User::factory()->warehouseAdmin()->create(), 'warehouse.home'],
]);

test('after login each role lands on its own home page', function (User $user, string $homeRoute) {
    $this->post(route('login.store'), [
        'username' => $user->username,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->get(route('dashboard'))->assertRedirect(route($homeRoute));
    $this->get(route($homeRoute))->assertOk();
})->with('roles');

test('the root url sends signed-in users to their home page', function (User $user, string $homeRoute) {
    $this->actingAs($user)
        ->followingRedirects()
        ->get('/')
        ->assertOk();

    expect(url()->current())->toBe(route($homeRoute));
})->with('roles');

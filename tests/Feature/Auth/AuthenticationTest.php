<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Username');
});

test('users can authenticate with their username', function () {
    $user = User::factory()->create(['username' => 'outlet_fji']);

    $response = $this->post(route('login.store'), [
        'username' => 'outlet_fji',
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('username is case insensitive on login', function () {
    User::factory()->create(['username' => 'gudang']);

    $this->post(route('login.store'), [
        'username' => 'GUDANG',
        'password' => 'password',
    ])->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'username' => $user->username,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('username');

    $this->assertGuest();
});

test('email can no longer be used to log in', function () {
    $this->post(route('login.store'), [
        'email' => 'owner@example.com',
        'password' => 'password',
    ])->assertSessionHasErrors('username');

    $this->assertGuest();
});

test('deactivated users can not authenticate', function () {
    $user = User::factory()->inactive()->create();

    $this->post(route('login.store'), [
        'username' => $user->username,
        'password' => 'password',
    ])->assertSessionHasErrors(['username' => 'Akun Anda dinonaktifkan. Hubungi pemilik.']);

    $this->assertGuest();
});

test('last login time is recorded', function () {
    $this->freezeSecond();
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'username' => $user->username,
        'password' => 'password',
    ]);

    expect($user->refresh()->last_login_at?->equalTo(now()))->toBeTrue();
});

test('users deactivated during a session are logged out on the next request', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $user->update(['is_active' => false]);

    $this->get(route('owner.dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

test('login attempts are throttled', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), ['username' => $user->username, 'password' => 'wrong']);
    }

    $this->post(route('login.store'), ['username' => $user->username, 'password' => 'password'])
        ->assertStatus(429);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

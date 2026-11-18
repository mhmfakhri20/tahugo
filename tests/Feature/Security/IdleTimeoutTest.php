<?php

use App\Models\Location;
use App\Models\User;

test('the owner is logged out after 60 idle minutes (AUTH-6)', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->get(route('owner.dashboard'))->assertOk();

    $this->travel(59)->minutes();
    $this->get(route('owner.dashboard'))->assertOk();

    $this->travel(61)->minutes();
    $this->get(route('owner.dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', fn ($message) => str_contains($message, 'tidak ada aktivitas'));

    $this->assertGuest();
});

test('outlet staff stay signed in during a long shift but not forever', function () {
    $staff = User::factory()->outletStaff(Location::factory()->outlet()->create())->create();

    $this->actingAs($staff)->get(route('outlet.home'))->assertOk();

    $this->travel(3)->hours();
    $this->get(route('outlet.home'))->assertOk();

    $this->travel(10)->hours();
    $this->travel(1)->minutes();
    $this->get(route('outlet.home'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('the idle limit is configurable', function () {
    config(['tahugo.idle_timeout.owner' => 5]);
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->get(route('owner.dashboard'))->assertOk();
    $this->travel(6)->minutes();
    $this->get(route('owner.dashboard'))->assertRedirect(route('login'));
});

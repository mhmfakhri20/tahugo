<?php

use App\Services\ShiftService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->w = attendanceWorld();
    Storage::fake('local');
    atWib('08:00');
});

test('cashier and expenses redirect to the home page without an open shift (BR-4)', function (string $route) {
    $this->actingAs($this->w->staffA)
        ->get(route($route))
        ->assertRedirect(route('outlet.home'))
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'Buka shift dulu'));

    $this->actingAs($this->w->staffA)
        ->getJson(route($route))
        ->assertStatus(409);
})->with(['outlet.cashier', 'outlet.expenses']);

test('cashier and expenses open once the outlet has an open shift', function (string $route) {
    clockIn($this->w->staffA);
    app(ShiftService::class)->open($this->w->staffA, 100_000);

    $this->actingAs($this->w->staffA)->get(route($route))->assertOk();

    // The shift is per outlet: the coworker can use it too, staff of outlet B cannot.
    $this->actingAs($this->w->staffA2)->get(route($route))->assertOk();
    $this->actingAs($this->w->staffB)->get(route($route))->assertRedirect(route('outlet.home'));
})->with(['outlet.cashier', 'outlet.expenses']);

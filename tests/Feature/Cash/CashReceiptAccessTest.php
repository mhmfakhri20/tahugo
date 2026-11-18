<?php

use App\Models\Shift;

beforeEach(function () {
    $this->w = cashWorld();
});

test('a receipt photo is visible only to the owner and the staff who recorded it', function () {
    $w = $this->w;
    $entry = spend($w->staffA, 10_000);

    $this->actingAs($w->staffA)->get(route('cash-entries.receipt', $entry))->assertOk();
    $this->actingAs($w->owner)->get(route('cash-entries.receipt', $entry))->assertOk();

    $this->actingAs($w->staffA2)->get(route('cash-entries.receipt', $entry))->assertForbidden();
    $this->actingAs($w->staffB)->get(route('cash-entries.receipt', $entry))->assertForbidden();
    $this->actingAs($w->admin)->get(route('cash-entries.receipt', $entry))->assertForbidden();
});

test('staff of another outlet cannot see the expense in any list (BR-7)', function () {
    $w = $this->w;
    $entry = spend($w->staffA, 10_000);
    Shift::factory()->atOutlet($w->outletB, $w->staffB)->create();

    expect($w->staffB->can('view', $entry))->toBeFalse();
    $this->actingAs($w->staffB)->get(route('outlet.expenses'))->assertOk()->assertDontSee($entry->number());
});

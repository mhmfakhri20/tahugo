<?php

use App\Enums\ExpenseStatus;
use App\Enums\FundSource;
use App\Models\CashEntry;
use App\Services\CashService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = cashWorld();
});

test('owner and warehouse admin record business expenses without a shift, approved (CASH-6)', function (string $who, string $route) {
    $w = $this->w;
    $user = $w->{$who};

    Livewire::actingAs($user)
        ->test('pages::business-expenses')
        ->set('locationId', (string) $w->outletA->id)
        ->set('amount', '1500000')
        ->set('description', 'Sewa tempat November')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    $entry = CashEntry::sole();

    expect($entry->fund_source)->toBe(FundSource::Business)
        ->and($entry->status)->toBe(ExpenseStatus::Approved)
        ->and($entry->shift_id)->toBeNull()
        ->and($entry->location_id)->toBe($w->outletA->id)
        ->and($entry->user_id)->toBe($user->id);

    $this->actingAs($user)->get(route($route))->assertOk()->assertSee('Sewa tempat November');
})->with([
    'owner' => ['owner', 'owner.business-expenses'],
    'admin' => ['admin', 'warehouse.business-expenses'],
]);

test('outlet staff cannot record business expenses', function () {
    $w = $this->w;

    expect(fn () => app(CashService::class)->recordBusinessExpense($w->staffA, ['amount' => 1_000, 'description' => 'Coba']))
        ->toThrow(AuthorizationException::class);

    $this->actingAs($w->staffA)->get(route('owner.business-expenses'))->assertForbidden();
    $this->actingAs($w->staffA)->get(route('warehouse.business-expenses'))->assertForbidden();
});

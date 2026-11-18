<?php

use App\Enums\ExpenseStatus;
use App\Exceptions\CashException;
use App\Services\CashService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = cashWorld();
    $this->cash = app(CashService::class);
});

test('only the owner can approve or reject (CASH-3)', function () {
    $w = $this->w;
    $entry = spend($w->staffA, 10_000);

    foreach ([$w->staffA, $w->staffA2, $w->admin] as $user) {
        expect(fn () => $this->cash->approve($entry, $user))->toThrow(AuthorizationException::class);
        expect(fn () => $this->cash->reject($entry, $user, 'Tidak valid'))->toThrow(AuthorizationException::class);
    }

    $this->actingAs($w->staffA)->get(route('owner.verification'))->assertForbidden();

    expect($entry->fresh()->status)->toBe(ExpenseStatus::Pending);
});

test('the owner approves from the verification tab', function () {
    $w = $this->w;
    $entry = spend($w->staffA, 10_000);

    atWib('10:15');

    Livewire::actingAs($w->owner)
        ->test('pages::owner.verification', ['tab' => 'expenses'])
        ->set('tab', 'expenses')
        ->assertSee($entry->number())
        ->call('review', $entry->id)
        ->assertSee(route('cash-entries.receipt', $entry), escape: false)
        ->call('approveExpense')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    $entry->refresh();

    expect($entry->status)->toBe(ExpenseStatus::Approved)
        ->and($entry->verified_by)->toBe($w->owner->id)
        ->and($entry->verified_at->format('H:i'))->toBe('10:15')
        ->and($entry->reject_reason)->toBeNull();

    // A verified expense cannot be verified again.
    expect(fn () => $this->cash->reject($entry, $w->owner, 'Berubah pikiran'))->toThrow(CashException::class, 'sudah diverifikasi');
});

test('rejecting requires a reason (CASH-3) and marks it for accountability (CASH-5)', function () {
    $w = $this->w;
    $entry = spend($w->staffA, 10_000);

    $component = Livewire::actingAs($w->owner)
        ->test('pages::owner.verification')
        ->set('tab', 'expenses')
        ->call('review', $entry->id)
        ->set('rejectReason', '')
        ->call('rejectExpense')
        ->assertHasErrors(['rejectReason' => 'required']);

    expect($entry->fresh()->status)->toBe(ExpenseStatus::Pending);
    expect(fn () => $this->cash->reject($entry, $w->owner, ' '))->toThrow(CashException::class);

    $component->set('rejectReason', 'Nota duplikat')->call('rejectExpense')->assertHasNoErrors();

    $entry->refresh();
    expect($entry->status)->toBe(ExpenseStatus::Rejected)
        ->and($entry->reject_reason)->toBe('Nota duplikat');

    $this->actingAs($w->staffA)->get(route('outlet.expenses'))
        ->assertSee('Harus dipertanggungjawabkan')
        ->assertSee('Nota duplikat');
});

test('the verification menu badge counts pending expenses and requests', function () {
    $w = $this->w;
    spend($w->staffA, 10_000);
    spend($w->staffA2, 5_000);

    $this->actingAs($w->owner)->get(route('owner.dashboard'))
        ->assertOk()
        ->assertSee('Pengeluaran menunggu verifikasi')
        ->assertSee('Rp15.000');
});

<?php

use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Services\CashService;
use App\Services\ShiftService;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = cashWorld();
    $this->cash = app(CashService::class);
    $this->shifts = app(ShiftService::class);
});

test('expectedCash = opening + offline cash sales + income − outlet-cash expenses not rejected (§6.3)', function () {
    $w = $this->w;

    sell($w->staffA, [[$w->paket, 1]]);                                              // +18.000
    sell($w->staffA, [[$w->paket, 1]], SalesChannel::Offline, PaymentMethod::Qris); // not counted
    $this->cash->recordIncome($w->staffA, 5_000, 'Titipan pemilik');                // +5.000
    spend($w->staffA, 7_000);                                                        // −7.000 (pending)
    $approved = spend($w->staffA, 3_000);                                            // −3.000 (approved)
    $this->cash->approve($approved, $w->owner);
    $rejected = spend($w->staffA, 4_000);                                            // rejected: not counted
    $this->cash->reject($rejected, $w->owner, 'Nota palsu');
    spend($w->staffA, 9_000, 'personal');                                            // personal: not counted

    expect($this->shifts->expectedCash($w->shiftA->fresh()))->toBe(100_000 + 18_000 + 5_000 - 7_000 - 3_000);

    $summary = $this->shifts->cashSummary($w->shiftA->fresh());

    expect($summary['expense_pending'])->toBe(7_000)
        ->and($summary['expense_approved'])->toBe(3_000)
        ->and($summary['expense_rejected'])->toBe(4_000)
        ->and($summary['personal'])->toBe(9_000)
        ->and($summary['income'])->toBe(5_000)
        ->and($summary['cash_sales'])->toBe(18_000)
        ->and($summary['variance_after_verification'])->toBeNull(); // shift still open
});

test('the variance after verification changes when an expense is rejected after closing (CASH-5)', function () {
    $w = $this->w;

    sell($w->staffA, [[$w->paket, 1]]);       // +18.000
    $late = spend($w->staffA, 8_000);         // pending at closing time

    atWib('16:00');

    // Drawer: 100.000 + 18.000 − 8.000 = 110.000, staff counts exactly that.
    Livewire::actingAs($w->staffA)
        ->test('pages::outlet.shift-close')
        ->assertSee('Rp110.000')
        ->set('countedCash', '110000')
        ->set('photo', selfie())
        ->call('close')
        ->assertHasNoErrors();

    $shift = $w->shiftA->fresh();
    expect($shift->expected_cash)->toBe(110_000)
        ->and($shift->variance)->toBe(0)
        ->and($this->shifts->cashSummary($shift)['variance_after_verification'])->toBe(0);

    // The owner rejects it the next day: the 8.000 left the drawer without a valid reason.
    atWib('09:00', '2026-11-19');
    $this->cash->reject($late, $w->owner, 'Tidak ada pembelian');

    $summary = $this->shifts->cashSummary($shift->fresh());

    expect($shift->fresh()->expected_cash)->toBe(110_000)          // stored value unchanged
        ->and($shift->fresh()->variance)->toBe(0)
        ->and($summary['expected_now'])->toBe(118_000)
        ->and($summary['variance_after_verification'])->toBe(-8_000)
        ->and($summary['rejected_entries'])->toHaveCount(1);

    // Visible to the owner in the shift recap.
    Livewire::actingAs($w->owner)
        ->test('pages::owner.attendance')
        ->set('from', '2026-11-18')
        ->set('tab', 'shifts')
        ->assertSee('Kurang Rp8.000');
});

test('personal-fund expenses show as staff advance on the closing screen', function () {
    $w = $this->w;
    spend($w->staffA, 9_000, 'personal');

    Livewire::actingAs($w->staffA)
        ->test('pages::outlet.shift-close')
        ->assertSee('Talangan penjaga')
        ->assertSee('Rp9.000')
        ->assertSee('Rp100.000');
});

<?php

use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Services\ShiftService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = attendanceWorld();
    Storage::fake('local');
    atWib('07:55');
});

test('staff of outlet A cannot clock in at outlet B (always checked against their own outlet)', function () {
    // Standing exactly at outlet B's coordinates.
    expect(fn () => clockIn($this->w->staffA, -7.6, 110.95))
        ->toThrow(AttendanceException::class, 'dari outlet');

    expect(Attendance::count())->toBe(0);
});

test('attendance selfies are visible to the owner and the person only', function () {
    $mine = clockIn($this->w->staffA);
    $other = clockIn($this->w->staffB);

    $this->actingAs($this->w->staffA)->get(route('attendances.photo', $mine))->assertOk();
    $this->actingAs($this->w->owner)->get(route('attendances.photo', $mine))->assertOk();
    $this->actingAs($this->w->owner)->get(route('attendances.photo', $other))->assertOk();

    // A coworker of the same outlet and staff of another outlet cannot.
    $this->actingAs($this->w->staffA2)->get(route('attendances.photo', $mine))->assertForbidden();
    $this->actingAs($this->w->staffA)->get(route('attendances.photo', $other))->assertForbidden();
});

test('shift closing photos are visible to the owner and the staff who closed it', function () {
    clockIn($this->w->staffB);
    $shiftB = app(ShiftService::class)->open($this->w->staffB, 0);
    app(ShiftService::class)->close($shiftB, $this->w->staffB, 0, selfie());

    $this->actingAs($this->w->staffB)->get(route('shifts.photo', $shiftB))->assertOk();
    $this->actingAs($this->w->owner)->get(route('shifts.photo', $shiftB))->assertOk();
    $this->actingAs($this->w->staffA)->get(route('shifts.photo', $shiftB))->assertForbidden();
});

test('staff of outlet A cannot close or see the shift of outlet B', function () {
    clockIn($this->w->staffB);
    $shiftB = app(ShiftService::class)->open($this->w->staffB, 0);

    expect($this->w->staffA->can('view', $shiftB))->toBeFalse();
    expect(fn () => app(ShiftService::class)->close($shiftB, $this->w->staffA, 0, selfie()))
        ->toThrow(AuthorizationException::class);

    // The close page of A only ever looks at A's shift: none is open there.
    Livewire::actingAs($this->w->staffA)
        ->test('pages::outlet.shift-close')
        ->assertRedirect(route('outlet.home'));

    expect($shiftB->fresh()->isOpen())->toBeTrue();
});

test('only the owner can open the attendance recap', function () {
    $this->actingAs($this->w->staffA)->get(route('owner.attendance'))->assertForbidden();
    $this->actingAs($this->w->owner)->get(route('owner.attendance'))->assertOk();
});

test('the owner recap lists attendance with flags, map link and shift summary (ATT-7)', function () {
    atWib('08:12');
    $in = clockIn($this->w->staffA);
    $shift = app(ShiftService::class)->open($this->w->staffA, 100_000);
    atWib('16:10');
    app(ShiftService::class)->close($shift, $this->w->staffA, 90_000, selfie());

    Livewire::actingAs($this->w->owner)
        ->test('pages::owner.attendance')
        ->assertSee('Siti')
        ->assertSee('Terlambat 12m')
        ->assertSee($in->mapsUrl(), escape: false)
        ->assertSee(route('attendances.photo', $in), escape: false)
        ->assertSee('Rina') // belum absen hari ini
        ->set('statusFilter', 'late')
        ->assertSee('Siti')
        ->set('tab', 'shifts')
        ->assertSee('Kurang Rp10.000')
        ->assertSee(route('shifts.photo', $shift), escape: false);

    // Filter on outlet B hides outlet A rows.
    Livewire::actingAs($this->w->owner)
        ->test('pages::owner.attendance')
        ->set('outletFilter', (string) $this->w->outletB->id)
        ->assertDontSee($in->mapsUrl(), escape: false);
});

test('the owner dashboard shows today\'s attendance per outlet', function () {
    atWib('08:20');
    clockIn($this->w->staffA);

    $this->actingAs($this->w->owner)->get(route('owner.dashboard'))
        ->assertOk()
        ->assertSee('Absensi hari ini')
        ->assertSee('Terlambat 20m')
        ->assertSee('Belum absen');
});

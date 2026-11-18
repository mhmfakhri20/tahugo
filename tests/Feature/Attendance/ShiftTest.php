<?php

use App\Enums\ShiftStatus;
use App\Exceptions\AttendanceException;
use App\Exceptions\ShiftException;
use App\Models\Attendance;
use App\Models\Shift;
use App\Services\AttendanceService;
use App\Services\ShiftService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = attendanceWorld();
    Storage::fake('local');
    atWib('07:55');
});

test('a shift cannot be opened before clocking in (BR-5)', function () {
    expect(fn () => app(ShiftService::class)->open($this->w->staffA, 100_000))
        ->toThrow(ShiftException::class, 'Absen masuk dulu');

    Livewire::actingAs($this->w->staffA)
        ->test('pages::outlet.shift-open')
        ->assertRedirect(route('outlet.home'));

    expect(Shift::count())->toBe(0);
});

test('opening a shift stores the opening cash and links the clock-in', function () {
    $in = clockIn($this->w->staffA);

    Livewire::actingAs($this->w->staffA)
        ->test('pages::outlet.shift-open')
        ->set('openingCash', '150000')
        ->call('open')
        ->assertHasNoErrors()
        ->assertRedirect(route('outlet.home'));

    $shift = Shift::sole();

    expect($shift->status)->toBe(ShiftStatus::Open)
        ->and($shift->opening_cash)->toBe(150_000)
        ->and($shift->opened_by)->toBe($this->w->staffA->id)
        ->and($shift->location_id)->toBe($this->w->outletA->id)
        ->and($in->fresh()->shift_id)->toBe($shift->id);
});

test('an outlet can only have one open shift; the second staff joins it (SHF-1)', function () {
    clockIn($this->w->staffA);
    $shift = app(ShiftService::class)->open($this->w->staffA, 100_000);

    $in2 = clockIn($this->w->staffA2);

    expect(fn () => app(ShiftService::class)->open($this->w->staffA2, 50_000))
        ->toThrow(ShiftException::class, 'Shift sudah dibuka');

    expect(Shift::count())->toBe(1)
        ->and($in2->shift_id)->toBe($shift->id);

    // Another outlet is independent.
    clockIn($this->w->staffB);
    app(ShiftService::class)->open($this->w->staffB, 0);
    expect(Shift::open()->count())->toBe(2);
});

test('closing a shift stores counted_cash, expected_cash and variance (SHF-3)', function () {
    clockIn($this->w->staffA);
    $shift = app(ShiftService::class)->open($this->w->staffA, 100_000);

    atWib('16:05');

    Livewire::actingAs($this->w->staffA)
        ->test('pages::outlet.shift-close')
        ->set('countedCash', '95000')
        ->assertSee('Rp5.000')
        ->set('photo', selfie())
        ->call('close')
        ->assertHasNoErrors()
        ->assertRedirect(route('outlet.home'));

    $shift->refresh();

    expect($shift->status)->toBe(ShiftStatus::Closed)
        ->and($shift->counted_cash)->toBe(95_000)
        ->and($shift->expected_cash)->toBe(100_000) // = opening_cash until sales (F4) & cash (F5) exist
        ->and($shift->variance)->toBe(-5_000)
        ->and($shift->closed_by)->toBe($this->w->staffA->id)
        ->and($shift->closed_at->format('H:i'))->toBe('16:05');

    Storage::disk('local')->assertExists($shift->closing_photo_path);
});

test('expectedCash() follows the PRD formula with what exists so far', function () {
    $shift = Shift::factory()->atOutlet($this->w->outletA, $this->w->staffA)->create(['opening_cash' => 75_000]);

    expect(app(ShiftService::class)->expectedCash($shift))->toBe(75_000);
});

test('the closing photo and counted cash are required', function () {
    clockIn($this->w->staffA);
    app(ShiftService::class)->open($this->w->staffA, 100_000);

    Livewire::actingAs($this->w->staffA)
        ->test('pages::outlet.shift-close')
        ->call('close')
        ->assertHasErrors(['countedCash' => 'required', 'photo' => 'required']);

    expect(Shift::sole()->isOpen())->toBeTrue();
});

test('a closed shift cannot be changed by staff (BR-6)', function () {
    clockIn($this->w->staffA);
    $shift = app(ShiftService::class)->open($this->w->staffA, 100_000);
    app(ShiftService::class)->close($shift, $this->w->staffA, 100_000, selfie());

    expect(fn () => app(ShiftService::class)->close($shift->fresh(), $this->w->staffA, 1, selfie()))
        ->toThrow(ShiftException::class, 'sudah ditutup');

    expect($this->w->staffA->can('close', $shift->fresh()))->toBeFalse();

    // The close page no longer finds an open shift.
    Livewire::actingAs($this->w->staffA)
        ->test('pages::outlet.shift-close')
        ->assertRedirect(route('outlet.home'));

    expect($shift->fresh()->counted_cash)->toBe(100_000);
});

test('the last staff present cannot clock out while the shift is open', function () {
    $service = app(AttendanceService::class);

    clockIn($this->w->staffA);
    $shift = app(ShiftService::class)->open($this->w->staffA, 100_000);
    clockIn($this->w->staffA2);

    atWib('16:00');

    // staffA2 is still there, so staffA may leave first.
    $outA = $service->clockOut($this->w->staffA, selfie(), -7.56, 110.82, 10);
    expect($outA->shift_id)->toBe($shift->id);

    // staffA2 is now the last one: close the shift first.
    expect(fn () => $service->clockOut($this->w->staffA2, selfie(), -7.56, 110.82, 10))
        ->toThrow(AttendanceException::class, 'Tutup shift dulu');

    app(ShiftService::class)->close($shift, $this->w->staffA2, 100_000, selfie());

    expect($service->clockOut($this->w->staffA2, selfie(), -7.56, 110.82, 10))->toBeInstanceOf(Attendance::class);
    expect($service->todayState($this->w->staffA2)['step'])->toBe('done');
});

test('home steps follow the day: clock in → open shift → on duty → clock out → done', function () {
    $service = app(AttendanceService::class);
    $step = fn () => $service->todayState($this->w->staffA->fresh())['step'];

    expect($step())->toBe('clock_in');
    clockIn($this->w->staffA);
    expect($step())->toBe('open_shift');
    $shift = app(ShiftService::class)->open($this->w->staffA, 0);
    expect($step())->toBe('on_duty');
    atWib('16:00');
    app(ShiftService::class)->close($shift, $this->w->staffA, 0, selfie());
    expect($step())->toBe('clock_out');
    $service->clockOut($this->w->staffA, selfie(), -7.56, 110.82, 10);
    expect($step())->toBe('done');
});

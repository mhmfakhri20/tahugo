<?php

use App\Enums\AttendanceType;
use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Models\Location;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = attendanceWorld();
    Storage::fake('local');
    atWib('07:55');
});

test('clock in inside the radius is accepted and the selfie is stored privately (BR-5)', function () {
    // ±7 m from the outlet point.
    Livewire::actingAs($this->w->staffA)
        ->test('pages::outlet.attendance')
        ->set('photo', selfie())
        ->call('submit', -7.5600500, 110.8200400, 8.5)
        ->assertHasNoErrors()
        ->assertRedirect(route('outlet.home'));

    $attendance = Attendance::sole();

    expect($attendance->type)->toBe(AttendanceType::ClockIn)
        ->and($attendance->user_id)->toBe($this->w->staffA->id)
        ->and($attendance->location_id)->toBe($this->w->outletA->id)
        ->and((float) $attendance->distance_m)->toBeLessThan(10)
        ->and((float) $attendance->accuracy_m)->toBe(8.5)
        ->and($attendance->user_agent)->not->toBeNull()
        ->and($attendance->flags)->toBeNull();

    Storage::disk('local')->assertExists($attendance->photo_path);
    expect($attendance->photo_path)->toStartWith('attendances/2026/11/');
});

test('clock in outside the radius is rejected and no photo is kept (BR-5)', function () {
    // ~1,1 km south of outlet A.
    Livewire::actingAs($this->w->staffA)
        ->test('pages::outlet.attendance')
        ->set('photo', selfie())
        ->call('submit', -7.5700000, 110.8200000, 10)
        ->assertHasErrors('attendance');

    expect(Attendance::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('attendances'))->toBe([]);
});

test('a GPS accuracy worse than the radius asks the user to try again', function () {
    expect(fn () => clockIn($this->w->staffA, accuracy: 150))
        ->toThrow(AttendanceException::class, 'Sinyal GPS kurang akurat');

    expect(Attendance::count())->toBe(0);
});

test('clock in is rejected when the outlet has no coordinates yet', function () {
    $outlet = Location::factory()->outlet()->withCoordinates(null, null)->create();
    $staff = User::factory()->outletStaff($outlet)->create();

    expect(fn () => clockIn($staff, -7.56, 110.82))
        ->toThrow(AttendanceException::class, 'Lokasi outlet belum diatur pemilik');
});

test('the selfie is required and must be an image of at most 5 MB', function () {
    Livewire::actingAs($this->w->staffA)
        ->test('pages::outlet.attendance')
        ->call('submit', -7.56, 110.82, 10)
        ->assertHasErrors(['photo' => 'required']);

    Livewire::actingAs($this->w->staffA)
        ->test('pages::outlet.attendance')
        ->set('photo', UploadedFile::fake()->image('besar.png')->size(6000))
        ->call('submit', -7.56, 110.82, 10)
        ->assertHasErrors(['photo' => 'max']);

    expect(Attendance::count())->toBe(0);
});

test('the attendance time is the server time and late is flagged after the opening time (ATT-3, ATT-5)', function () {
    atWib('08:07');

    Livewire::actingAs($this->w->staffA)
        ->test('pages::outlet.attendance')
        ->set('photo', selfie())
        ->call('submit', -7.56, 110.82, 10)
        ->assertHasNoErrors();

    $attendance = Attendance::sole();

    // The client never sends a time: what is stored is the frozen server clock (WIB).
    expect($attendance->server_time->format('Y-m-d H:i'))->toBe('2026-11-18 08:07')
        ->and($attendance->server_time->timezoneName)->toBe('Asia/Jakarta')
        ->and($attendance->isLate())->toBeTrue()
        ->and($attendance->flags['late_minutes'])->toBe(7);
});

test('on time and outlets without standard hours get no flag', function () {
    atWib('08:00');
    expect(clockIn($this->w->staffA)->flags)->toBeNull();

    $outlet = Location::factory()->outlet()->withCoordinates(-7.5, 110.8)->withHours(null, null)->create();
    $staff = User::factory()->outletStaff($outlet)->create();

    atWib('13:00');
    expect(clockIn($staff)->flags)->toBeNull();
});

test('clocking out before the closing time is flagged early_leave', function () {
    clockIn($this->w->staffA);

    atWib('15:30');
    $out = app(AttendanceService::class)->clockOut($this->w->staffA, selfie(), -7.56, 110.82, 10);

    expect($out->isEarlyLeave())->toBeTrue()
        ->and($out->flags['early_minutes'])->toBe(30);
});

test('a user clocks in and out at most once per day', function () {
    clockIn($this->w->staffA);

    expect(fn () => clockIn($this->w->staffA))->toThrow(AttendanceException::class, 'sudah absen masuk');

    // Next day it works again.
    atWib('07:58', '2026-11-19');
    expect(clockIn($this->w->staffA)->server_time->toDateString())->toBe('2026-11-19');
});

test('the outlet home shows the next step', function () {
    $this->actingAs($this->w->staffA)->get(route('outlet.home'))
        ->assertOk()
        ->assertSee('Absen masuk');

    clockIn($this->w->staffA);

    $this->actingAs($this->w->staffA)->get(route('outlet.home'))
        ->assertOk()
        ->assertSee('Buka shift')
        ->assertSee('07.55');
});

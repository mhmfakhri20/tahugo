<?php

use App\Services\ShiftService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->w = attendanceWorld();
    Storage::fake('local');
});

test('attendance:prune deletes selfies older than 90 days and keeps the rows, new photos and shift photos (NFR-5)', function () {
    atWib('08:00', '2026-08-01');
    $old = clockIn($this->w->staffA);
    $shift = app(ShiftService::class)->open($this->w->staffA, 0);
    app(ShiftService::class)->close($shift, $this->w->staffA, 0, selfie());

    atWib('08:00', '2026-11-10');
    $recent = clockIn($this->w->staffA);

    atWib('09:00', '2026-11-18');

    $this->artisan('attendance:prune')->assertSuccessful();

    $disk = Storage::disk('local');

    expect($old->fresh()->photo_path)->toBeNull()
        ->and($old->fresh()->exists)->toBeTrue()
        ->and($recent->fresh()->photo_path)->toBe($recent->photo_path);

    $disk->assertMissing($old->photo_path);
    $disk->assertExists($recent->photo_path);
    $disk->assertExists($shift->fresh()->closing_photo_path);

    // A pruned photo is gone for the owner too.
    $this->actingAs($this->w->owner)->get(route('attendances.photo', $old))->assertNotFound();
});

test('attendance:prune is scheduled daily', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'attendance:prune'));

    expect($events)->toHaveCount(1);
});

<?php

namespace App\Services;

use App\Enums\AttendanceType;
use App\Enums\Role;
use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use App\Support\Geo;
use App\Support\WorkTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Clock in / clock out with a live selfie + GPS (ATT-1..6, BR-5) and photo retention (NFR-5).
 *
 * The outlet is ALWAYS the user's own outlet (BR-7): there is no outlet parameter, so staff of
 * outlet A cannot clock in at outlet B. Time is ALWAYS the server clock (ATT-3).
 */
class AttendanceService
{
    public const PHOTO_DISK = 'local';

    public const PHOTO_RETENTION_DAYS = 90;

    public function clockIn(User $user, UploadedFile $photo, float $lat, float $lng, float $accuracy, ?string $userAgent = null): Attendance
    {
        return $this->record(AttendanceType::ClockIn, $user, $photo, $lat, $lng, $accuracy, $userAgent);
    }

    public function clockOut(User $user, UploadedFile $photo, float $lat, float $lng, float $accuracy, ?string $userAgent = null): Attendance
    {
        return $this->record(AttendanceType::ClockOut, $user, $photo, $lat, $lng, $accuracy, $userAgent);
    }

    /**
     * Server-side distance check (ATT-2). Returns the distance in meters.
     *
     * @throws AttendanceException
     */
    public function checkPosition(Location $outlet, float $lat, float $lng, float $accuracy): float
    {
        if (! $outlet->hasCoordinates()) {
            throw new AttendanceException('Lokasi outlet belum diatur pemilik. Minta pemilik mengisi titik lokasi outlet, lalu coba lagi.');
        }

        if (abs($lat) > 90 || abs($lng) > 180 || $accuracy < 0) {
            throw new AttendanceException('Lokasi dari HP tidak terbaca. Nyalakan GPS lalu coba lagi.');
        }

        $radius = $outlet->attendance_radius_m;

        if ($accuracy > $radius) {
            throw new AttendanceException(sprintf(
                'Sinyal GPS kurang akurat (±%s m). Pindah ke tempat terbuka, tunggu sebentar, lalu ketuk "Cari lokasi lagi".',
                number_format($accuracy, 0, ',', '.'),
            ));
        }

        $distance = Geo::distanceMeters($lat, $lng, (float) $outlet->latitude, (float) $outlet->longitude);

        if ($distance > $radius) {
            throw new AttendanceException(sprintf(
                'Anda berada %s m dari outlet (batas %s m). Absen hanya bisa dilakukan di outlet.',
                number_format($distance, 0, ',', '.'),
                number_format($radius, 0, ',', '.'),
            ));
        }

        return $distance;
    }

    /**
     * What the outlet home page should show (§11 Beranda): today's records and the next step.
     *
     * Steps: clock_in → open_shift → on_duty → clock_out → done.
     *
     * @return array{
     *     step: string,
     *     clockIn: Attendance|null,
     *     clockOut: Attendance|null,
     *     openShift: Shift|null,
     *     staleShift: bool,
     *     othersPresent: Collection<int, User>,
     *     canClockOut: bool,
     * }
     */
    public function todayState(User $user): array
    {
        $outlet = $user->location;
        $clockIn = $this->today($user, AttendanceType::ClockIn);
        $clockOut = $this->today($user, AttendanceType::ClockOut);
        $openShift = $outlet->openShift()->with('opener')->first();
        $othersPresent = $this->presentUsers($outlet, except: $user);

        $step = match (true) {
            $clockOut !== null => 'done',
            $clockIn === null => 'clock_in',
            $openShift !== null => 'on_duty',
            $this->shiftClosedSince($outlet, $clockIn->server_time) => 'clock_out',
            default => 'open_shift',
        };

        return [
            'step' => $step,
            'clockIn' => $clockIn,
            'clockOut' => $clockOut,
            'openShift' => $openShift,
            'staleShift' => $openShift !== null && $openShift->opened_at->lt(today()),
            'othersPresent' => $othersPresent,
            'canClockOut' => $clockIn !== null && $clockOut === null && ($openShift === null || $othersPresent->isNotEmpty()),
        ];
    }

    /**
     * The user's attendance of the given type today (Asia/Jakarta day).
     */
    public function today(User $user, AttendanceType $type): ?Attendance
    {
        return Attendance::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->whereBetween('server_time', [today(), today()->endOfDay()])
            ->latest('server_time')
            ->first();
    }

    /**
     * Staff who clocked in at the outlet today and have not clocked out yet.
     *
     * @return Collection<int, User>
     */
    public function presentUsers(Location $outlet, ?User $except = null): Collection
    {
        $records = Attendance::query()
            ->with('user')
            ->where('location_id', $outlet->id)
            ->whereBetween('server_time', [today(), today()->endOfDay()])
            ->get()
            ->groupBy('user_id');

        return $records
            ->filter(fn (Collection $rows) => $rows->contains('type', AttendanceType::ClockIn)
                && ! $rows->contains('type', AttendanceType::ClockOut))
            ->map(fn (Collection $rows) => $rows->first()->user)
            ->reject(fn (User $user) => $user->id === $except?->id)
            ->values();
    }

    /**
     * Today's attendance per outlet for the owner (REP-1 "status absensi hari ini", ATT-7).
     *
     * @param  Collection<int, Location>|\Illuminate\Database\Eloquent\Collection<int, Location>  $outlets
     * @return Collection<int, array{
     *     outlet: Location,
     *     openShift: Shift|null,
     *     closedShift: Shift|null,
     *     present: Collection<int, array{user: User, in: Attendance, out: Attendance|null}>,
     *     absent: Collection<int, User>,
     * }>
     */
    public function todaySummary(Collection|\Illuminate\Database\Eloquent\Collection $outlets): Collection
    {
        $ids = $outlets->pluck('id');
        $range = [today(), today()->endOfDay()];

        $records = Attendance::query()->with('user')
            ->whereIn('location_id', $ids)
            ->whereBetween('server_time', $range)
            ->orderBy('server_time')
            ->get()
            ->groupBy('location_id');

        $staff = User::query()->active()
            ->where('role', Role::OutletStaff)
            ->whereIn('location_id', $ids)
            ->orderBy('name')
            ->get()
            ->groupBy('location_id');

        $open = Shift::query()->open()->whereIn('location_id', $ids)->get()->keyBy('location_id');
        $closed = Shift::query()->whereIn('location_id', $ids)->whereBetween('closed_at', $range)
            ->orderByDesc('closed_at')->get()->unique('location_id')->keyBy('location_id');

        return collect($outlets)->map(function (Location $outlet) use ($records, $staff, $open, $closed): array {
            $present = collect($records->get($outlet->id, []))
                ->groupBy('user_id')
                ->map(fn (Collection $rows) => [
                    'user' => $rows->first()->user,
                    'in' => $rows->firstWhere('type', AttendanceType::ClockIn),
                    'out' => $rows->firstWhere('type', AttendanceType::ClockOut),
                ])
                ->filter(fn (array $row) => $row['in'] !== null)
                ->values();

            return [
                'outlet' => $outlet,
                'openShift' => $open->get($outlet->id),
                'closedShift' => $closed->get($outlet->id),
                'present' => $present,
                'absent' => collect($staff->get($outlet->id, []))
                    ->reject(fn (User $user) => $present->contains(fn (array $row) => $row['user']->id === $user->id))
                    ->values(),
            ];
        })->values();
    }

    /**
     * NFR-5: delete attendance selfies older than $days. Rows (time, location, flags) are kept.
     * Shift closing photos are not attendance photos and are kept.
     *
     * @return int number of photos deleted
     */
    public function prunePhotos(int $days = self::PHOTO_RETENTION_DAYS): int
    {
        $cutoff = now()->subDays($days);
        $disk = Storage::disk(self::PHOTO_DISK);
        $count = 0;

        Attendance::query()
            ->whereNotNull('photo_path')
            ->where('server_time', '<', $cutoff)
            ->chunkById(200, function (Collection $attendances) use ($disk, &$count): void {
                foreach ($attendances as $attendance) {
                    $disk->delete($attendance->photo_path);
                    $attendance->forceFill(['photo_path' => null])->saveQuietly();
                    $count++;
                }
            });

        return $count;
    }

    private function record(AttendanceType $type, User $user, UploadedFile $photo, float $lat, float $lng, float $accuracy, ?string $userAgent): Attendance
    {
        $outlet = $user->location;

        if (! $user->isOutletStaff() || $outlet === null || ! $outlet->isOutlet()) {
            throw new AttendanceException('Absen hanya untuk penjaga outlet.');
        }

        // Checked before the photo is stored, so a rejected attempt leaves no file behind.
        $distance = $this->checkPosition($outlet, $lat, $lng, $accuracy);

        $now = now();
        $path = $photo->store('attendances/'.$now->format('Y/m'), self::PHOTO_DISK);

        try {
            return DB::transaction(function () use ($type, $user, $outlet, $lat, $lng, $accuracy, $userAgent, $distance, $now, $path): Attendance {
                // Serialize attendance & shift changes of this outlet.
                Location::query()->whereKey($outlet->id)->lockForUpdate()->first();

                $shiftId = $type === AttendanceType::ClockIn
                    ? $this->guardClockIn($user, $outlet)
                    : $this->guardClockOut($user, $outlet);

                return Attendance::create([
                    'user_id' => $user->id,
                    'location_id' => $outlet->id,
                    'shift_id' => $shiftId,
                    'type' => $type,
                    'server_time' => $now,
                    'photo_path' => $path,
                    'latitude' => round($lat, 7),
                    'longitude' => round($lng, 7),
                    'accuracy_m' => round($accuracy, 1),
                    'distance_m' => round($distance, 1),
                    'flags' => $this->flags($type, $outlet, $now),
                    'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 512) : null,
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk(self::PHOTO_DISK)->delete($path);

            throw $e;
        }
    }

    /**
     * @return int|null the open shift a second staff member joins
     */
    private function guardClockIn(User $user, Location $outlet): ?int
    {
        if ($existing = $this->today($user, AttendanceType::ClockIn)) {
            throw new AttendanceException('Anda sudah absen masuk hari ini pukul '.$existing->server_time->format('H.i').'.');
        }

        return $outlet->openShift()->value('id');
    }

    /**
     * @return int|null the shift this attendance belongs to
     */
    private function guardClockOut(User $user, Location $outlet): ?int
    {
        $clockIn = $this->today($user, AttendanceType::ClockIn)
            ?? throw new AttendanceException('Anda belum absen masuk hari ini.');

        if ($this->today($user, AttendanceType::ClockOut)) {
            throw new AttendanceException('Anda sudah absen pulang hari ini.');
        }

        $openShiftId = $outlet->openShift()->value('id');

        if ($openShiftId !== null && $this->presentUsers($outlet, except: $user)->isEmpty()) {
            throw new AttendanceException('Shift masih buka. Tutup shift dulu sebelum absen pulang.');
        }

        return $clockIn->shift_id ?? $openShiftId;
    }

    /**
     * ATT-5: late / early leave against the outlet's standard hours (no flag when hours are empty).
     *
     * @return array<string, bool|int>|null
     */
    private function flags(AttendanceType $type, Location $outlet, CarbonImmutable $now): ?array
    {
        if ($type === AttendanceType::ClockIn) {
            $late = WorkTime::lateMinutes($outlet->open_time, $now);

            return $late ? ['late' => true, 'late_minutes' => $late] : null;
        }

        $early = WorkTime::earlyMinutes($outlet->close_time, $now);

        return $early ? ['early_leave' => true, 'early_minutes' => $early] : null;
    }

    private function shiftClosedSince(Location $outlet, \DateTimeInterface $since): bool
    {
        return Shift::query()
            ->where('location_id', $outlet->id)
            ->whereNotNull('closed_at')
            ->where('closed_at', '>=', $since)
            ->exists();
    }
}

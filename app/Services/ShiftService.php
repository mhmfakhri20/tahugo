<?php

namespace App\Services;

use App\Enums\AttendanceType;
use App\Enums\AuditAction;
use App\Enums\ShiftStatus;
use App\Exceptions\ShiftException;
use App\Models\Attendance;
use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Outlet shifts (SHF-1..5, BR-4, BR-5, BR-6).
 */
class ShiftService
{
    public const PHOTO_DISK = 'local';

    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly SaleService $sales,
        private readonly CashService $cash,
    ) {}

    /**
     * The open shift of an outlet (at most one, SHF-1).
     */
    public function openShiftFor(Location $outlet): ?Shift
    {
        return Shift::query()->open()->where('location_id', $outlet->id)->latest('opened_at')->first();
    }

    /**
     * Open a shift at the user's own outlet with the opening cash (SHF-2).
     * Requires a clock-in today (BR-5); a second staff member just clocks in and joins (SHF-1).
     */
    public function open(User $user, int $openingCash): Shift
    {
        Gate::forUser($user)->authorize('open', Shift::class);

        if ($openingCash < 0) {
            throw new ShiftException('Kas awal tidak boleh kurang dari Rp0.');
        }

        $outlet = $user->location;

        return DB::transaction(function () use ($user, $outlet, $openingCash): Shift {
            // One open shift per outlet: serialize on the outlet row.
            Location::query()->whereKey($outlet->id)->lockForUpdate()->first();

            if ($existing = $this->openShiftFor($outlet)) {
                throw new ShiftException(sprintf(
                    'Shift sudah dibuka oleh %s pukul %s. Anda tidak perlu membuka shift lagi.',
                    $existing->opener->name,
                    $existing->opened_at->format('H.i'),
                ));
            }

            if ($this->attendance->today($user, AttendanceType::ClockIn) === null) {
                throw new ShiftException('Absen masuk dulu sebelum membuka shift.');
            }

            if ($this->attendance->today($user, AttendanceType::ClockOut) !== null) {
                throw new ShiftException('Anda sudah absen pulang hari ini, jadi tidak bisa membuka shift.');
            }

            $shift = Shift::create([
                'location_id' => $outlet->id,
                'opened_by' => $user->id,
                'opened_at' => now(),
                'opening_cash' => $openingCash,
                'status' => ShiftStatus::Open,
            ]);

            // Everyone who clocked in at this outlet today and is not yet linked works this shift.
            Attendance::query()
                ->where('location_id', $outlet->id)
                ->where('type', AttendanceType::ClockIn)
                ->whereNull('shift_id')
                ->whereBetween('server_time', [today(), today()->endOfDay()])
                ->update(['shift_id' => $shift->id]);

            return $shift;
        });
    }

    /**
     * Close the shift (SHF-3/4): counted cash + live photo of the outlet; expected cash and variance
     * are computed by the server. A closed shift is locked (SHF-5, BR-6).
     */
    public function close(Shift $shift, User $user, int $countedCash, UploadedFile $photo): Shift
    {
        Gate::forUser($user)->authorize('view', $shift);

        if (! $shift->isOpen()) {
            throw new ShiftException('Shift ini sudah ditutup dan tidak bisa diubah lagi.');
        }

        Gate::forUser($user)->authorize('close', $shift);

        if ($countedCash < 0) {
            throw new ShiftException('Uang tunai fisik tidak boleh kurang dari Rp0.');
        }

        $path = $photo->store('shifts/'.now()->format('Y/m'), self::PHOTO_DISK);

        try {
            return DB::transaction(function () use ($shift, $user, $countedCash, $path): Shift {
                $locked = Shift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();

                if (! $locked->isOpen()) {
                    throw new ShiftException('Shift ini sudah ditutup dan tidak bisa diubah lagi.');
                }

                $expected = $this->expectedCash($locked);

                $locked->update([
                    'status' => ShiftStatus::Closed,
                    'closed_by' => $user->id,
                    'closed_at' => now(),
                    'counted_cash' => $countedCash,
                    'expected_cash' => $expected,
                    'variance' => $countedCash - $expected,
                    'closing_photo_path' => $path,
                ]);

                audit(AuditAction::ShiftClose, $locked,
                    ['status' => ShiftStatus::Open],
                    ['status' => ShiftStatus::Closed, 'counted_cash' => $countedCash, 'expected_cash' => $expected, 'variance' => $countedCash - $expected],
                    $user,
                );

                return $locked;
            });
        } catch (\Throwable $e) {
            Storage::disk(self::PHOTO_DISK)->delete($path);

            throw $e;
        }
    }

    /**
     * SHF-5 / BR-6: the OWNER corrects the counted cash of a closed shift (e.g. a counting
     * mistake). The stored expected cash stays; the variance is recomputed. Always audited.
     */
    public function correct(Shift $shift, User $owner, int $countedCash, string $reason): Shift
    {
        Gate::forUser($owner)->authorize('correct', $shift);

        $reason = trim($reason);

        if (mb_strlen($reason) < 3) {
            throw new ShiftException('Tulis alasan koreksi (minimal 3 huruf).');
        }

        if ($countedCash < 0) {
            throw new ShiftException('Uang tunai fisik tidak boleh kurang dari Rp0.');
        }

        return DB::transaction(function () use ($shift, $owner, $countedCash, $reason): Shift {
            $locked = Shift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if ($locked->isOpen()) {
                throw new ShiftException('Shift yang masih buka tidak perlu dikoreksi.');
            }

            $old = ['counted_cash' => $locked->counted_cash, 'variance' => $locked->variance];

            $locked->update([
                'counted_cash' => $countedCash,
                'variance' => $countedCash - (int) $locked->expected_cash,
            ]);

            audit(AuditAction::ShiftCorrect, $locked, $old,
                ['counted_cash' => $locked->counted_cash, 'variance' => $locked->variance, 'reason' => $reason],
                $owner,
            );

            return $locked;
        });
    }

    /**
     * Cash that should be in the drawer (PRD §6.3):
     *
     *   expected_cash = opening_cash
     *                 + Σ offline sales paid in cash
     *                 + Σ other cash income
     *                 − Σ cash expenses of the shift (any status except rejected)
     *
     * Platform (GoFood/GrabFood/ShopeeFood) and QRIS sales are NOT counted.
     */
    public function expectedCash(Shift $shift): int
    {
        // "Uang pribadi" expenses are paid by the staff, not from the drawer: they don't reduce it.
        return $shift->opening_cash
            + $this->sales->cashSalesTotal($shift)          // completed offline cash sales (void excluded)
            + $this->cash->incomeTotal($shift)              // other cash income
            - $this->cash->outletCashExpenseTotal($shift);  // outlet-cash expenses, pending or approved
    }

    /**
     * Full cash summary of a shift (SHF-3/4, CASH-5).
     *
     * `expected_at_close` / `variance_at_close` are what was stored when the shift was closed.
     * Expenses can be verified later: `variance_after_verification` recomputes expected cash NOW,
     * so an expense rejected afterwards increases the shortage the staff must account for.
     *
     * @return array<string, mixed>
     */
    public function cashSummary(Shift $shift): array
    {
        $breakdown = $this->cash->shiftBreakdown($shift);
        $expectedNow = $this->expectedCash($shift);
        $closed = ! $shift->isOpen();

        return [
            'sales' => $this->sales->shiftSummary($shift),
            'opening_cash' => $shift->opening_cash,
            'cash_sales' => $this->sales->cashSalesTotal($shift),
            'income' => $breakdown['income'],
            'expense_pending' => $breakdown['pending'],
            'expense_approved' => $breakdown['approved'],
            'expense_rejected' => $breakdown['rejected'],
            'personal' => $breakdown['personal'],
            'rejected_entries' => $breakdown['rejected_entries'],
            'expected_now' => $expectedNow,
            'expected_at_close' => $closed ? $shift->expected_cash : null,
            'counted_cash' => $shift->counted_cash,
            'variance_at_close' => $closed ? $shift->variance : null,
            'variance_after_verification' => $closed && $shift->counted_cash !== null ? $shift->counted_cash - $expectedNow : null,
        ];
    }
}

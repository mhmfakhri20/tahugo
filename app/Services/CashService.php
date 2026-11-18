<?php

namespace App\Services;

use App\Enums\CashDirection;
use App\Enums\ExpenseStatus;
use App\Enums\FundSource;
use App\Exceptions\CashException;
use App\Models\CashEntry;
use App\Models\ExpenseCategory;
use App\Models\Location;
use App\Models\Purchase;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Cash flow (PRD §6.5): outlet expenses with receipt + owner verification, other income,
 * business expenses and purchases (CASH-7). Money is integer rupiah.
 */
class CashService
{
    public const RECEIPT_DISK = 'local';

    public const MAX_AMOUNT = 100_000_000;

    /**
     * CASH-1: an outlet expense in the open shift of the user's outlet, status `pending`.
     * The receipt photo is mandatory (live camera in the UI).
     *
     * @param  array{category_id: int|string, amount: int|string, description: string, fund_source: FundSource|string}  $data
     */
    public function recordExpense(User $user, array $data, ?UploadedFile $receipt): CashEntry
    {
        Gate::forUser($user)->authorize('createOutlet', CashEntry::class);

        [$category, $amount, $description, $fund] = $this->expenseFields($data);

        if ($receipt === null) {
            throw new CashException('Foto nota wajib diambil.');
        }

        $path = $this->storeReceipt($receipt);

        try {
            return DB::transaction(function () use ($user, $category, $amount, $description, $fund, $path): CashEntry {
                $shift = $this->lockOpenShift($user->location);

                return CashEntry::create([
                    'location_id' => $shift->location_id,
                    'shift_id' => $shift->id,
                    'user_id' => $user->id,
                    'direction' => CashDirection::Out,
                    'category_id' => $category->id,
                    'amount' => $amount,
                    'description' => $description,
                    'fund_source' => $fund,
                    'receipt_photo_path' => $path,
                    'status' => ExpenseStatus::Pending,
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk(self::RECEIPT_DISK)->delete($path);

            throw $e;
        }
    }

    /**
     * Staff edit their own pending expense while the shift is open (BR-6). A new photo replaces the old one.
     *
     * @param  array{category_id: int|string, amount: int|string, description: string, fund_source: FundSource|string}  $data
     */
    public function updateExpense(CashEntry $entry, User $user, array $data, ?UploadedFile $receipt = null): CashEntry
    {
        [$category, $amount, $description, $fund] = $this->expenseFields($data);

        $path = $receipt ? $this->storeReceipt($receipt) : null;

        try {
            $oldPath = DB::transaction(function () use ($entry, $user, $category, $amount, $description, $fund, $path): ?string {
                $locked = $this->lockEntry($entry);
                Gate::forUser($user)->authorize('update', $locked);

                $oldPath = $locked->receipt_photo_path;

                $locked->update(array_filter([
                    'category_id' => $category->id,
                    'amount' => $amount,
                    'description' => $description,
                    'fund_source' => $fund,
                    'receipt_photo_path' => $path,
                ], fn ($value) => $value !== null));

                return $path ? $oldPath : null;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk(self::RECEIPT_DISK)->delete($path);
            }

            throw $e;
        }

        if ($oldPath) {
            Storage::disk(self::RECEIPT_DISK)->delete($oldPath);
        }

        return $entry->refresh();
    }

    /**
     * Delete a still-editable entry (own pending expense / own income, shift open).
     */
    public function deleteEntry(CashEntry $entry, User $user): void
    {
        $path = DB::transaction(function () use ($entry, $user): ?string {
            $locked = $this->lockEntry($entry);
            Gate::forUser($user)->authorize('delete', $locked);

            $locked->delete();

            return $locked->receipt_photo_path;
        });

        if ($path) {
            Storage::disk(self::RECEIPT_DISK)->delete($path);
        }
    }

    /**
     * CASH-2: other cash income in the open shift (rare). Goes straight to `approved`.
     */
    public function recordIncome(User $user, int $amount, string $description): CashEntry
    {
        Gate::forUser($user)->authorize('createOutlet', CashEntry::class);

        $amount = $this->amount($amount);
        $description = $this->description($description);

        return DB::transaction(function () use ($user, $amount, $description): CashEntry {
            $shift = $this->lockOpenShift($user->location);

            return CashEntry::create([
                'location_id' => $shift->location_id,
                'shift_id' => $shift->id,
                'user_id' => $user->id,
                'direction' => CashDirection::In,
                'amount' => $amount,
                'description' => $description,
                'fund_source' => FundSource::OutletCash,
                'status' => ExpenseStatus::Approved,
            ]);
        });
    }

    /**
     * CASH-3: the owner approves a pending outlet expense.
     */
    public function approve(CashEntry $entry, User $owner): CashEntry
    {
        return $this->verify($entry, $owner, ExpenseStatus::Approved);
    }

    /**
     * CASH-3/5: the owner rejects with a reason; the staff must account for it.
     */
    public function reject(CashEntry $entry, User $owner, string $reason): CashEntry
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 3) {
            throw new CashException('Tulis alasan penolakan agar penjaga tahu yang harus diperbaiki.');
        }

        return $this->verify($entry, $owner, ExpenseStatus::Rejected, $reason);
    }

    /**
     * CASH-6: business-level expense (rent, salary, ...) by the owner or warehouse admin.
     * Not tied to a shift, approved directly.
     *
     * @param  array{location_id?: int|string|null, category_id?: int|string|null, amount: int|string, description: string}  $data
     */
    public function recordBusinessExpense(User $user, array $data, ?UploadedFile $receipt = null): CashEntry
    {
        Gate::forUser($user)->authorize('createBusiness', CashEntry::class);

        $amount = $this->amount($data['amount'] ?? 0);
        $description = $this->description($data['description'] ?? '');
        $locationId = filled($data['location_id'] ?? null) ? Location::query()->findOrFail($data['location_id'])->id : null;
        $categoryId = filled($data['category_id'] ?? null) ? ExpenseCategory::query()->findOrFail($data['category_id'])->id : null;

        $path = $receipt ? $this->storeReceipt($receipt) : null;

        try {
            return CashEntry::create([
                'location_id' => $locationId,
                'user_id' => $user->id,
                'direction' => CashDirection::Out,
                'category_id' => $categoryId,
                'amount' => $amount,
                'description' => $description,
                'fund_source' => FundSource::Business,
                'receipt_photo_path' => $path,
                'status' => ExpenseStatus::Approved,
                'verified_at' => now(),
            ]);
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk(self::RECEIPT_DISK)->delete($path);
            }

            throw $e;
        }
    }

    /**
     * CASH-7: a warehouse purchase is a business expense. Call inside the purchase transaction.
     */
    public function recordPurchase(Purchase $purchase): CashEntry
    {
        $entry = new CashEntry;

        // forceFill: created_at follows the purchase so period reports stay right after a backfill.
        $entry->forceFill([
            'location_id' => $purchase->location_id,
            'user_id' => $purchase->user_id,
            'direction' => CashDirection::Out,
            'amount' => $purchase->total,
            'description' => mb_substr("Pembelian bahan {$purchase->number()} · {$purchase->supplier}", 0, 500),
            'fund_source' => FundSource::Business,
            'status' => ExpenseStatus::Approved,
            'verified_at' => $purchase->created_at ?? now(),
            'source_type' => $purchase->getMorphClass(),
            'source_id' => $purchase->id,
            'created_at' => $purchase->created_at ?? now(),
        ])->save();

        return $entry;
    }

    /**
     * Create the missing CASH-7 entries for purchases recorded before F5. Idempotent.
     */
    public function backfillPurchases(): int
    {
        $count = 0;

        Purchase::query()->whereDoesntHave('cashEntry')->orderBy('id')
            ->chunkById(200, function (Collection $purchases) use (&$count): void {
                foreach ($purchases as $purchase) {
                    DB::transaction(fn () => $this->recordPurchase($purchase));
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Σ other cash income of a shift (§6.3).
     */
    public function incomeTotal(Shift $shift): int
    {
        return (int) CashEntry::query()
            ->where('shift_id', $shift->id)
            ->where('direction', CashDirection::In)
            ->where('status', '!=', ExpenseStatus::Rejected)
            ->sum('amount');
    }

    /**
     * Σ expenses paid from the outlet drawer, any status except rejected (§6.3).
     */
    public function outletCashExpenseTotal(Shift $shift): int
    {
        return (int) CashEntry::query()
            ->where('shift_id', $shift->id)
            ->where('direction', CashDirection::Out)
            ->where('fund_source', FundSource::OutletCash)
            ->where('status', '!=', ExpenseStatus::Rejected)
            ->sum('amount');
    }

    /**
     * Outlet-cash expenses of a shift per status, personal advances ("talangan") and income.
     *
     * @return array{pending: int, approved: int, rejected: int, personal: int, income: int, rejected_entries: \Illuminate\Database\Eloquent\Collection<int, CashEntry>}
     */
    public function shiftBreakdown(Shift $shift): array
    {
        $entries = CashEntry::query()->with(['category', 'user', 'verifier'])->where('shift_id', $shift->id)->get();
        $outletCash = $entries->where('direction', CashDirection::Out)->where('fund_source', FundSource::OutletCash);

        return [
            'pending' => (int) $outletCash->where('status', ExpenseStatus::Pending)->sum('amount'),
            'approved' => (int) $outletCash->where('status', ExpenseStatus::Approved)->sum('amount'),
            'rejected' => (int) $outletCash->where('status', ExpenseStatus::Rejected)->sum('amount'),
            'personal' => (int) $entries->where('direction', CashDirection::Out)->where('fund_source', FundSource::Personal)
                ->where('status', '!=', ExpenseStatus::Rejected)->sum('amount'),
            'income' => (int) $entries->where('direction', CashDirection::In)->where('status', '!=', ExpenseStatus::Rejected)->sum('amount'),
            'rejected_entries' => $entries->where('direction', CashDirection::Out)->where('status', ExpenseStatus::Rejected)->values(),
        ];
    }

    /**
     * Owner dashboard: pending outlet expenses per outlet + today's outlet expenses.
     *
     * @param  Collection<int, Location>|\Illuminate\Database\Eloquent\Collection<int, Location>  $outlets
     * @return Collection<int, array{outlet: Location, pending_count: int, pending_total: int, today_total: int}>
     */
    public function pendingByOutlet(Collection|\Illuminate\Database\Eloquent\Collection $outlets): Collection
    {
        $pending = CashEntry::query()
            ->where('direction', CashDirection::Out)
            ->whereNotNull('shift_id')
            ->where('status', ExpenseStatus::Pending)
            ->selectRaw('location_id, COUNT(*) as n, SUM(amount) as total')
            ->groupBy('location_id')
            ->get()
            ->keyBy('location_id');

        $today = CashEntry::query()
            ->where('direction', CashDirection::Out)
            ->whereNotNull('shift_id')
            ->where('status', '!=', ExpenseStatus::Rejected)
            ->whereBetween('created_at', [today(), today()->endOfDay()])
            ->selectRaw('location_id, SUM(amount) as total')
            ->groupBy('location_id')
            ->pluck('total', 'location_id');

        return collect($outlets)->map(fn (Location $outlet) => [
            'outlet' => $outlet,
            'pending_count' => (int) ($pending->get($outlet->id)?->n ?? 0),
            'pending_total' => (int) ($pending->get($outlet->id)?->total ?? 0),
            'today_total' => (int) ($today->get($outlet->id) ?? 0),
        ])->values();
    }

    private function verify(CashEntry $entry, User $owner, ExpenseStatus $status, ?string $reason = null): CashEntry
    {
        return DB::transaction(function () use ($entry, $owner, $status, $reason): CashEntry {
            $locked = $this->lockEntry($entry);

            if (! $locked->isPending() && $owner->isOwner()) {
                throw new CashException("Pengeluaran {$locked->number()} sudah diverifikasi ({$locked->status->label()}).");
            }

            Gate::forUser($owner)->authorize('verify', $locked);

            // TODO(F7, AUD-1): record the verification (old/new status, reason, IP) in audit_logs.
            $locked->update([
                'status' => $status,
                'verified_by' => $owner->id,
                'verified_at' => now(),
                'reject_reason' => $reason !== null ? mb_substr($reason, 0, 500) : null,
            ]);

            return $locked;
        });
    }

    /**
     * @return array{0: ExpenseCategory, 1: int, 2: string, 3: FundSource}
     */
    private function expenseFields(array $data): array
    {
        $category = ExpenseCategory::query()->where('is_active', true)->find($data['category_id'] ?? null)
            ?? throw new CashException('Pilih kategori pengeluaran yang masih aktif.');

        $fund = $data['fund_source'] instanceof FundSource ? $data['fund_source'] : FundSource::tryFrom((string) ($data['fund_source'] ?? ''));

        if (! in_array($fund, [FundSource::OutletCash, FundSource::Personal], true)) {
            throw new CashException('Pilih sumber dana: Kas outlet atau Uang pribadi.');
        }

        return [$category, $this->amount($data['amount'] ?? 0), $this->description($data['description'] ?? ''), $fund];
    }

    private function amount(int|string|null $amount): int
    {
        if (! is_numeric($amount) || (int) $amount != $amount || (int) $amount < 1 || (int) $amount > self::MAX_AMOUNT) {
            throw new CashException('Nominal harus angka rupiah antara Rp1 dan '.rupiah(self::MAX_AMOUNT).'.');
        }

        return (int) $amount;
    }

    private function description(?string $description): string
    {
        $description = trim((string) $description);

        if (mb_strlen($description) < 3) {
            throw new CashException('Tulis keterangan (minimal 3 huruf).');
        }

        return mb_substr($description, 0, 500);
    }

    private function storeReceipt(UploadedFile $receipt): string
    {
        return $receipt->store('cash/'.now()->format('Y/m'), self::RECEIPT_DISK);
    }

    /**
     * BR-4: entries belong to the open shift of the user's outlet. Locks the shift row so it
     * cannot be closed at the same moment.
     */
    private function lockOpenShift(?Location $outlet): Shift
    {
        return Shift::query()->open()->where('location_id', $outlet?->id)->lockForUpdate()->first()
            ?? throw new CashException('Shift belum dibuka. Buka shift dulu sebelum mencatat kas.');
    }

    private function lockEntry(CashEntry $entry): CashEntry
    {
        $locked = CashEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();

        if ($locked->shift_id) {
            $locked->setRelation('shift', Shift::query()->whereKey($locked->shift_id)->lockForUpdate()->first());
        }

        return $locked;
    }
}

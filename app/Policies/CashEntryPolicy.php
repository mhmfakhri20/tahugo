<?php

namespace App\Policies;

use App\Enums\CashDirection;
use App\Models\CashEntry;
use App\Models\User;

/**
 * Cash entries (CASH-1..7). Staff only see their outlet (BR-7) and only change their own
 * pending entries while the shift is open (BR-6). Only the owner verifies (CASH-3).
 */
class CashEntryPolicy
{
    public function view(User $user, CashEntry $entry): bool
    {
        if ($user->isOutletStaff()) {
            return $entry->shift_id !== null && $user->location_id === $entry->location_id;
        }

        return $user->isOwner() || ($user->isWarehouseAdmin() && $entry->shift_id === null);
    }

    /**
     * Receipt photos: the owner and the person who recorded the entry.
     */
    public function viewReceipt(User $user, CashEntry $entry): bool
    {
        return $user->isOwner() || $entry->user_id === $user->id;
    }

    /**
     * Outlet expense / other income (the service also checks the open shift, BR-4).
     */
    public function createOutlet(User $user): bool
    {
        return $user->isOutletStaff() && $user->location_id !== null;
    }

    public function update(User $user, CashEntry $entry): bool
    {
        return $this->ownOpen($user, $entry)
            && $entry->direction === CashDirection::Out
            && $entry->isPending();
    }

    public function delete(User $user, CashEntry $entry): bool
    {
        return $this->ownOpen($user, $entry)
            && ($entry->direction === CashDirection::In || $entry->isPending());
    }

    public function verify(User $user, CashEntry $entry): bool
    {
        return $user->isOwner() && $entry->isOutletExpense() && $entry->isPending();
    }

    /**
     * CASH-6: business expenses by the owner or the warehouse admin.
     */
    public function createBusiness(User $user): bool
    {
        return $user->isOwner() || $user->isWarehouseAdmin();
    }

    private function ownOpen(User $user, CashEntry $entry): bool
    {
        return $user->isOutletStaff()
            && $entry->user_id === $user->id
            && $entry->location_id === $user->location_id
            && $entry->shift !== null
            && $entry->shift->isOpen();
    }
}

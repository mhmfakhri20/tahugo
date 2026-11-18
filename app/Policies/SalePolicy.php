<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

/**
 * POS sales (POS-1..8). Staff only see and void sales of their own outlet (BR-7);
 * a sale of a closed shift is locked (BR-6).
 */
class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || ($user->isOutletStaff() && $user->location_id !== null);
    }

    public function view(User $user, Sale $sale): bool
    {
        if ($user->isOutletStaff()) {
            return $user->location_id === $sale->location_id;
        }

        return $user->isOwner();
    }

    /**
     * Staff sell at their own outlet (the service checks the open shift, BR-4).
     */
    public function create(User $user): bool
    {
        return $user->isOutletStaff() && $user->location_id !== null;
    }

    /**
     * POS-8: only while the sale's shift is still open, by staff of that outlet.
     * Owner corrections come with the audit log (F7).
     */
    public function void(User $user, Sale $sale): bool
    {
        return $user->isOutletStaff()
            && $user->location_id === $sale->location_id
            && ! $sale->isVoid()
            && $sale->shift->isOpen();
    }
}

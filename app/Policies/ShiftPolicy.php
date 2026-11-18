<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

/**
 * Outlet shifts (SHF-1..5). Staff only act on the shift of their own outlet (BR-7);
 * a closed shift is locked for staff (BR-6).
 */
class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function view(User $user, Shift $shift): bool
    {
        if ($user->isOutletStaff()) {
            return $user->location_id === $shift->location_id;
        }

        return $user->isOwner();
    }

    /**
     * Staff open a shift at their own outlet (the service checks clock-in & one open shift).
     */
    public function open(User $user): bool
    {
        return $user->isOutletStaff() && $user->location_id !== null;
    }

    public function close(User $user, Shift $shift): bool
    {
        return $user->isOutletStaff()
            && $user->location_id === $shift->location_id
            && $shift->isOpen();
    }

    /**
     * SHF-5: only the owner corrects a closed shift (audited).
     */
    public function correct(User $user, Shift $shift): bool
    {
        return $user->isOwner() && ! $shift->isOpen();
    }

    /**
     * Daily outlet report (REP-2): the owner, or staff of that outlet (BR-7).
     */
    public function viewReport(User $user, Shift $shift): bool
    {
        return $this->view($user, $shift);
    }

    /**
     * Closing photo: the owner, or the staff member who took it.
     */
    public function viewPhoto(User $user, Shift $shift): bool
    {
        return $user->isOwner() || ($shift->closed_by !== null && $shift->closed_by === $user->id);
    }
}

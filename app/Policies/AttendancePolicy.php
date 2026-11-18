<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

/**
 * Attendance records & selfies (ATT-4, ATT-7, NFR-5): the owner sees everything; staff only their own.
 */
class AttendancePolicy
{
    /**
     * The attendance recap (all outlets) is for the owner.
     */
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return $user->isOwner() || $attendance->user_id === $user->id;
    }

    /**
     * Selfies are personal data: owner, or the person in the photo.
     */
    public function viewPhoto(User $user, Attendance $attendance): bool
    {
        return $this->view($user, $attendance);
    }
}

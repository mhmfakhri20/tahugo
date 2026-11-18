<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

/**
 * Audit log (AUD-2): read-only, owner only. There is no create/update/delete ability for users.
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function view(User $user, AuditLog $log): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, AuditLog $log): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $log): bool
    {
        return false;
    }
}

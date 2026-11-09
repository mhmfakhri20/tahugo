<?php

namespace App\Policies;

use App\Models\User;

/**
 * Only the owner manages user accounts (AUTH-5).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isOwner();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isOwner();
    }

    /**
     * Activate/deactivate. The owner can never deactivate their own account.
     */
    public function toggleActive(User $user, User $model): bool
    {
        return $user->isOwner() && ! $user->is($model);
    }

    public function resetPassword(User $user, User $model): bool
    {
        return $user->isOwner();
    }
}

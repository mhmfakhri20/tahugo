<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;

/**
 * Raw materials (MD-2): everyone may read (needed for stock & requests), only the owner manages.
 */
class ItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Item $item): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Item $item): bool
    {
        return $user->isOwner();
    }
}

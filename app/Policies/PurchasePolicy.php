<?php

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;

/**
 * Raw material purchases (INV-3): recorded by the warehouse admin, visible to the owner too.
 */
class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isWarehouseAdmin();
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isWarehouseAdmin();
    }
}

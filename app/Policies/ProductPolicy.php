<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Products, prices & recipes (MD-3, MD-4): everyone may read (needed for the cashier), only the owner manages.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->isOwner();
    }
}

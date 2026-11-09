<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\User;

/**
 * Outlets & warehouses (MD-1). Outlet staff may only see their own outlet (AUTH-3, BR-7).
 */
class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isWarehouseAdmin();
    }

    public function view(User $user, Location $location): bool
    {
        if ($user->isOutletStaff()) {
            return $user->location_id === $location->id;
        }

        return $user->isOwner() || $user->isWarehouseAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Location $location): bool
    {
        return $user->isOwner();
    }
}

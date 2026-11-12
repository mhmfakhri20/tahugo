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

    /**
     * INV-1: owner & warehouse admin see stock of every location; outlet staff only their outlet.
     */
    public function viewStock(User $user, Location $location): bool
    {
        return $this->view($user, $location);
    }

    /**
     * INV-9: outlet staff record waste at their outlet, the warehouse admin at a warehouse.
     */
    public function recordWaste(User $user, Location $location): bool
    {
        if ($user->isOutletStaff()) {
            return $user->location_id === $location->id;
        }

        return $user->isWarehouseAdmin() && $location->isWarehouse();
    }

    /**
     * §6.6: stock adjustments are owner only.
     */
    public function adjustStock(User $user, Location $location): bool
    {
        return $user->isOwner();
    }
}

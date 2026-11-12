<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Models\StockRequest;
use App\Models\User;

/**
 * Stock requests (INV-4..INV-8). Approval is the OWNER's job; outlet staff only see
 * and act on requests for their own outlet (BR-7).
 */
class StockRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, StockRequest $request): bool
    {
        if ($user->isOutletStaff()) {
            return $user->location_id === $request->to_location_id;
        }

        return $user->isOwner() || $user->isWarehouseAdmin();
    }

    /**
     * Outlet staff request for their own outlet; the owner may request for any outlet.
     */
    public function create(User $user): bool
    {
        return $user->isOwner() || ($user->isOutletStaff() && $user->location_id !== null);
    }

    public function approve(User $user, StockRequest $request): bool
    {
        return $user->isOwner() && $request->status === RequestStatus::Pending;
    }

    public function reject(User $user, StockRequest $request): bool
    {
        return $this->approve($user, $request);
    }

    public function ship(User $user, StockRequest $request): bool
    {
        return $user->isWarehouseAdmin() && $request->status === RequestStatus::Approved;
    }

    public function receive(User $user, StockRequest $request): bool
    {
        return $user->isOutletStaff()
            && $user->location_id === $request->to_location_id
            && $request->status === RequestStatus::Shipped;
    }

    /**
     * The owner may cancel before shipping; the requester may cancel while still pending.
     */
    public function cancel(User $user, StockRequest $request): bool
    {
        if ($user->isOwner()) {
            return in_array($request->status, [RequestStatus::Pending, RequestStatus::Approved], true);
        }

        return $request->status === RequestStatus::Pending
            && $request->requested_by === $user->id
            && $this->view($user, $request);
    }
}

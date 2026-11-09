<?php

namespace App\Policies;

use App\Models\ExpenseCategory;
use App\Models\User;

/**
 * Expense categories (MD-5): owner manages, outlet staff pick from them when recording expenses.
 */
class ExpenseCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isOutletStaff();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, ExpenseCategory $category): bool
    {
        return $user->isOwner();
    }
}

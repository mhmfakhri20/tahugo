<?php

namespace App\Services;

use App\Models\ExpenseCategory;

/**
 * Expense categories managed by the owner (MD-5).
 */
class ExpenseCategoryService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function save(?ExpenseCategory $category, array $data): ExpenseCategory
    {
        $category ??= new ExpenseCategory;

        $category->fill([
            'name' => trim($data['name']),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ])->save();

        return $category;
    }

    public function setActive(ExpenseCategory $category, bool $active): void
    {
        $category->update(['is_active' => $active]);
    }
}

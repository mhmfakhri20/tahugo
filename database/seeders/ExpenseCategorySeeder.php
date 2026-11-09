<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

/**
 * Default expense categories (PRD MD-5).
 */
class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $names = ['Bahan dadakan', 'Gas/LPG', 'Kebersihan', 'Listrik', 'Parkir/retribusi', 'Transport', 'Lain-lain'];

        foreach ($names as $name) {
            ExpenseCategory::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}

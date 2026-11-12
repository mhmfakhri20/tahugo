<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            LocationSeeder::class,
            ExpenseCategorySeeder::class,
            ItemSeeder::class,
            ProductSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call([DevUserSeeder::class, StockSeeder::class]);
        }
    }
}

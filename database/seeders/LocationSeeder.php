<?php

namespace Database\Seeders;

use App\Enums\LocationType;
use App\Models\Location;
use Illuminate\Database\Seeder;

/**
 * Placeholder locations (PRD §12). Coordinates & opening hours are [TBD],
 * so they are left null until confirmed by the owner.
 */
class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            ['code' => 'GDG', 'name' => 'Gudang Solo', 'type' => LocationType::Warehouse],
            ['code' => 'FJI', 'name' => 'Tahu Go Fajar Indah', 'type' => LocationType::Outlet],
            ['code' => 'BKK', 'name' => 'Tahu Go Batas Kota Karanganyar', 'type' => LocationType::Outlet],
        ];

        foreach ($locations as $location) {
            Location::updateOrCreate(['code' => $location['code']], $location);
        }
    }
}

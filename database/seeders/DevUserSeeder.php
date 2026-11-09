<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Development-only accounts (PRD §12). Password "password" — NEVER run in production.
 */
class DevUserSeeder extends Seeder
{
    public function run(): void
    {
        $outlets = Location::whereIn('code', ['FJI', 'BKK'])->pluck('id', 'code');

        $users = [
            ['username' => 'owner', 'name' => 'Pemilik', 'role' => Role::Owner, 'location_id' => null],
            ['username' => 'outlet_fji', 'name' => 'Penjaga Fajar Indah', 'role' => Role::OutletStaff, 'location_id' => $outlets['FJI']],
            ['username' => 'outlet_bkk', 'name' => 'Penjaga Batas Kota', 'role' => Role::OutletStaff, 'location_id' => $outlets['BKK']],
            ['username' => 'gudang', 'name' => 'Admin Gudang', 'role' => Role::WarehouseAdmin, 'location_id' => null],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['username' => $user['username']],
                $user + ['password' => 'password', 'is_active' => true],
            );
        }
    }
}

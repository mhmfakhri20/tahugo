<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum Role: string
{
    use HasValues;

    case Owner = 'owner';
    case OutletStaff = 'outlet_staff';
    case WarehouseAdmin = 'warehouse_admin';

    /**
     * Human readable label (Bahasa Indonesia).
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Pemilik',
            self::OutletStaff => 'Penjaga Outlet',
            self::WarehouseAdmin => 'Admin Gudang',
        };
    }

    /**
     * Named route of the home page for this role.
     */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Owner => 'owner.dashboard',
            self::OutletStaff => 'outlet.home',
            self::WarehouseAdmin => 'warehouse.home',
        };
    }
}

<?php

use App\Enums\AttendanceType;
use App\Enums\ExpenseStatus;
use App\Enums\LocationType;
use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\SalesChannel;

test('every enum case has a label and values() matches the cases', function (string $enum) {
    expect($enum::values())->toBe(array_column($enum::cases(), 'value'));

    foreach ($enum::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }

    expect($enum::options())->toHaveKeys($enum::values());
})->with([
    Role::class,
    LocationType::class,
    SalesChannel::class,
    PaymentMethod::class,
    MovementType::class,
    RequestStatus::class,
    ExpenseStatus::class,
    AttendanceType::class,
]);

test('enum values follow the PRD', function () {
    expect(Role::values())->toBe(['owner', 'outlet_staff', 'warehouse_admin']);
    expect(SalesChannel::values())->toBe(['offline', 'gofood', 'grabfood', 'shopeefood']);
    expect(PaymentMethod::values())->toBe(['cash', 'qris']);
    expect(MovementType::values())->toBe(['purchase_in', 'transfer_out', 'transfer_in', 'sale_out', 'sale_void_in', 'adjustment', 'waste']);
    expect(RequestStatus::values())->toBe(['pending', 'approved', 'shipped', 'received', 'rejected', 'cancelled']);
    expect(ExpenseStatus::values())->toBe(['pending', 'approved', 'rejected']);
    expect(AttendanceType::values())->toBe(['clock_in', 'clock_out']);
});

test('each role has an existing home route name', function () {
    expect(Role::Owner->homeRoute())->toBe('owner.dashboard');
    expect(Role::OutletStaff->homeRoute())->toBe('outlet.home');
    expect(Role::WarehouseAdmin->homeRoute())->toBe('warehouse.home');
});

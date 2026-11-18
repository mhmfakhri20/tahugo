<?php

use App\Models\Attendance;
use App\Models\CashEntry;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\StockRequest;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Storage;

test('DemoSeeder builds 14 days of data through the services and the ledger stays consistent', function () {
    Storage::fake('local');
    atWib('14:00', '2026-11-18');

    $this->seed([DatabaseSeeder::class, DemoSeeder::class]);

    expect(Shift::where('status', 'closed')->count())->toBe(DemoSeeder::DAYS * 2)
        ->and(Shift::where('status', 'open')->count())->toBe(2)  // today
        ->and(Sale::count())->toBeGreaterThan(200)
        ->and(Sale::where('status', 'void')->exists())->toBeTrue()
        ->and(Attendance::count())->toBeGreaterThan(DemoSeeder::DAYS * 4 - 1)
        ->and(CashEntry::where('status', 'approved')->whereNotNull('shift_id')->exists())->toBeTrue()
        ->and(CashEntry::where('status', 'rejected')->exists())->toBeTrue()
        ->and(CashEntry::where('status', 'pending')->exists())->toBeTrue()
        ->and(Purchase::count())->toBeGreaterThan(0)
        ->and(StockRequest::where('status', 'received')->count())->toBeGreaterThan(0)
        ->and(Sale::min('created_at'))->toStartWith('2026-11-04');

    $this->artisan('stock:verify')->assertSuccessful();

    // Running it again does not duplicate the data.
    $count = Sale::count();
    $this->seed(DemoSeeder::class);
    expect(Sale::count())->toBe($count);
});

<?php

namespace Database\Seeders;

use App\Enums\CashDirection;
use App\Enums\ExpenseStatus;
use App\Enums\FundSource;
use App\Models\CashEntry;
use App\Models\ExpenseCategory;
use App\Models\Location;
use App\Models\User;
use App\Services\CashService;
use Illuminate\Database\Seeder;

/**
 * Development-only PLACEHOLDER cash data (F5): a few business expenses and the CASH-7 backfill
 * for purchases. Outlet expenses need a real shift, so they come with the DemoSeeder (F6).
 */
class CashEntrySeeder extends Seeder
{
    public function run(CashService $cash): void
    {
        // Development data only: never in production (NFR-4).
        if (app()->isProduction()) {
            return;
        }

        $owner = User::query()->where('username', 'owner')->first();

        if ($owner === null) {
            return;
        }

        $outlets = Location::query()->outlets()->orderBy('id')->get();
        $categories = ExpenseCategory::query()->pluck('id', 'name');

        $samples = [
            ['description' => 'Sewa tempat outlet (placeholder)', 'amount' => 1_500_000, 'location' => $outlets->first(), 'category' => null],
            ['description' => 'Tagihan listrik outlet (placeholder)', 'amount' => 250_000, 'location' => $outlets->first(), 'category' => 'Listrik'],
            ['description' => 'Gaji penjaga (placeholder)', 'amount' => 2_000_000, 'location' => $outlets->last(), 'category' => null],
        ];

        foreach ($samples as $sample) {
            CashEntry::firstOrCreate(['description' => $sample['description']], [
                'location_id' => $sample['location']?->id,
                'user_id' => $owner->id,
                'direction' => CashDirection::Out,
                'category_id' => $sample['category'] ? $categories->get($sample['category']) : null,
                'amount' => $sample['amount'],
                'fund_source' => FundSource::Business,
                'status' => ExpenseStatus::Approved,
                'verified_at' => now(),
            ]);
        }

        $cash->backfillPurchases();
    }
}

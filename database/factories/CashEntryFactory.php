<?php

namespace Database\Factories;

use App\Enums\CashDirection;
use App\Enums\ExpenseStatus;
use App\Enums\FundSource;
use App\Models\CashEntry;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashEntry>
 */
class CashEntryFactory extends Factory
{
    /**
     * A business expense by default (no shift).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => null,
            'shift_id' => null,
            'user_id' => User::factory()->owner(),
            'direction' => CashDirection::Out,
            'amount' => 50_000,
            'description' => fake()->sentence(4),
            'fund_source' => FundSource::Business,
            'status' => ExpenseStatus::Approved,
        ];
    }

    /**
     * A pending outlet-cash expense of $user in $shift.
     */
    public function outletExpense(Shift $shift, User $user, int $amount = 25_000, FundSource $fund = FundSource::OutletCash): static
    {
        return $this->state(fn () => [
            'location_id' => $shift->location_id,
            'shift_id' => $shift->id,
            'user_id' => $user->id,
            'amount' => $amount,
            'fund_source' => $fund,
            'status' => ExpenseStatus::Pending,
            'receipt_photo_path' => 'cash/test.png',
        ]);
    }
}

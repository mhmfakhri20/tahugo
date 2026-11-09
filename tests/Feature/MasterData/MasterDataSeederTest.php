<?php

use App\Enums\SalesChannel;
use App\Models\ExpenseCategory;
use App\Models\Item;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductRecipe;
use Database\Seeders\DatabaseSeeder;

test('master data placeholders are seeded with Rp0 prices and empty recipes', function () {
    $this->seed(DatabaseSeeder::class);

    expect(ExpenseCategory::count())->toBe(7)
        ->and(Item::pluck('unit', 'name')->all())->toMatchArray([
            'Tahu mentah' => 'pcs',
            'Bumbu tabur' => 'gram',
            'Minyak goreng' => 'liter',
        ])
        ->and(Item::count())->toBe(7)
        ->and(Product::pluck('name')->sort()->values()->all())->toBe(['Paket isi 10', 'Paket isi 5', 'Tahu Go Reguler'])
        ->and(ProductPrice::count())->toBe(3 * count(SalesChannel::cases()))
        ->and(ProductPrice::where('price', '!=', 0)->exists())->toBeFalse()
        ->and(ProductRecipe::count())->toBe(0);
});

test('re-seeding keeps master data unique and does not overwrite owner edits', function () {
    $this->seed(DatabaseSeeder::class);

    ProductPrice::query()->update(['price' => 15_000]);

    $this->seed(DatabaseSeeder::class);

    expect(Item::count())->toBe(7)
        ->and(Product::count())->toBe(3)
        ->and(ExpenseCategory::count())->toBe(7)
        ->and(ProductPrice::where('price', 15_000)->count())->toBe(12);
});

<?php

use App\Enums\SalesChannel;
use App\Models\ExpenseCategory;
use App\Models\Item;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductRecipe;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\ItemSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\ProductSeeder;

test('master data placeholders are seeded with Rp0 prices and empty recipes', function () {
    // The seeders that also run in production.
    $this->seed([LocationSeeder::class, ExpenseCategorySeeder::class, ItemSeeder::class, ProductSeeder::class]);

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

test('the dev seeder fills placeholder prices & recipes so the cashier can be tested (F4)', function () {
    $this->seed(DatabaseSeeder::class);

    $paket10 = Product::with(['prices', 'recipes.item'])->where('name', 'Paket isi 10')->sole();

    expect(ProductPrice::where('price', 0)->exists())->toBeFalse()
        ->and($paket10->priceFor(SalesChannel::Gofood))->toBeGreaterThan($paket10->priceFor(SalesChannel::Offline))
        ->and($paket10->recipes->pluck('item.name')->all())->toContain('Tahu mentah', 'Kemasan besar')
        ->and(Product::doesntHave('recipes')->exists())->toBeFalse();
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

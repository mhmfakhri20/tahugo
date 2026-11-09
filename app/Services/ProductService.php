<?php

namespace App\Services;

use App\Enums\SalesChannel;
use App\Models\Item;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Products, per-channel prices (MD-3) and recipe/BOM (MD-4).
 */
class ProductService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $prices  channel value => price in rupiah (all channels required)
     * @param  list<array{item_id: int|string, qty: int|float|string}>  $recipe
     */
    public function save(?Product $product, array $data, array $prices, array $recipe): Product
    {
        $product ??= new Product;

        $this->ensureValidPrices($prices);
        $this->ensureValidRecipe($recipe);

        return DB::transaction(function () use ($product, $data, $prices, $recipe): Product {
            $product->fill([
                'name' => trim($data['name']),
                'type' => $data['type'],
                'is_active' => (bool) ($data['is_active'] ?? true),
            ])->save();

            foreach (SalesChannel::cases() as $channel) {
                $product->prices()->updateOrCreate(
                    ['channel' => $channel],
                    ['price' => (int) $prices[$channel->value]],
                );
            }

            $product->recipes()->delete();

            foreach ($recipe as $line) {
                $product->recipes()->create([
                    'item_id' => (int) $line['item_id'],
                    'qty' => $line['qty'],
                ]);
            }

            return $product->load('prices', 'recipes.item');
        });
    }

    public function setActive(Product $product, bool $active): void
    {
        $product->update(['is_active' => $active]);
    }

    /**
     * @param  array<string, mixed>  $prices
     */
    private function ensureValidPrices(array $prices): void
    {
        $errors = [];

        foreach (SalesChannel::cases() as $channel) {
            $price = $prices[$channel->value] ?? null;

            if (blank($price) || filter_var($price, FILTER_VALIDATE_INT) === false || (int) $price < 0) {
                $errors["prices.{$channel->value}"] = "Harga {$channel->label()} wajib diisi dengan angka 0 atau lebih.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  list<array{item_id: int|string, qty: int|float|string}>  $recipe
     */
    private function ensureValidRecipe(array $recipe): void
    {
        $itemIds = array_map(fn (array $line): int => (int) $line['item_id'], $recipe);

        $errors = [];
        $seen = [];

        $activeIds = Item::query()->active()->whereIn('id', $itemIds)->pluck('id')->all();

        foreach ($recipe as $index => $line) {
            $itemId = (int) $line['item_id'];

            if (! in_array($itemId, $activeIds, true)) {
                $errors["recipe.{$index}.item_id"] = 'Pilih bahan yang masih aktif.';
            } elseif (in_array($itemId, $seen, true)) {
                $errors["recipe.{$index}.item_id"] = 'Bahan ini sudah ada di resep. Gabungkan jumlahnya di satu baris.';
            }

            $seen[] = $itemId;

            if (! is_numeric($line['qty']) || (float) $line['qty'] <= 0) {
                $errors["recipe.{$index}.qty"] = 'Jumlah harus lebih dari 0.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}

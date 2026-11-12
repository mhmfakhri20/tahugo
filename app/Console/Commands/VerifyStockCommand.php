<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\Location;
use App\Services\StockService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * BR-3: stock_balances.qty must equal Σ stock_movements.qty for every (location, item).
 */
#[Signature('stock:verify')]
#[Description('Bandingkan saldo stok (stock_balances) dengan total ledger (stock_movements)')]
class VerifyStockCommand extends Command
{
    public function handle(StockService $stock): int
    {
        $mismatches = $stock->verify();

        if ($mismatches->isEmpty()) {
            $this->info('Semua saldo cocok dengan ledger.');

            return self::SUCCESS;
        }

        $locations = Location::query()->pluck('code', 'id');
        $items = Item::query()->pluck('name', 'id');

        $this->error("Ditemukan {$mismatches->count()} saldo yang tidak cocok dengan ledger:");
        $this->table(
            ['Lokasi', 'Bahan', 'Saldo', 'Total ledger'],
            $mismatches->map(fn (array $row) => [
                $locations[$row['location_id']] ?? $row['location_id'],
                $items[$row['item_id']] ?? $row['item_id'],
                $row['balance'],
                $row['ledger'],
            ])->all(),
        );

        return self::FAILURE;
    }
}

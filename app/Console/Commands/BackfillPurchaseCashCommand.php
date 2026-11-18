<?php

namespace App\Console\Commands;

use App\Services\CashService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * CASH-7: purchases recorded before F5 get their business-expense cash entry. Safe to run again.
 */
#[Signature('cash:backfill-purchases')]
#[Description('Catat pembelian bahan lama sebagai pengeluaran usaha (CASH-7)')]
class BackfillPurchaseCashCommand extends Command
{
    public function handle(CashService $cash): int
    {
        $count = $cash->backfillPurchases();

        $this->info("{$count} pembelian dicatat sebagai pengeluaran usaha.");

        return self::SUCCESS;
    }
}

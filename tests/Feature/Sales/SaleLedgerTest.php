<?php

use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Exceptions\InsufficientStockException;
use App\Models\Sale;
use App\Services\SaleService;
use App\Support\Qty;

test('stock:verify still passes after a random series of sales and voids (BR-3)', function () {
    $w = posWorld();
    mt_srand(42);

    $sales = [];

    for ($i = 0; $i < 30; $i++) {
        $channel = SalesChannel::cases()[mt_rand(0, 3)];
        $lines = [[$w->satuan, mt_rand(1, 3)]];

        if (mt_rand(0, 1)) {
            $lines[] = [$w->paket, 1];
        }

        try {
            $sales[] = sell($w->staffA, $lines, $channel, mt_rand(0, 1) ? PaymentMethod::Cash : PaymentMethod::Qris);
        } catch (InsufficientStockException) {
            // stock ran out: rejected without side effects
        }

        if ($sales !== [] && mt_rand(0, 3) === 0) {
            $sale = $sales[array_rand($sales)];

            if (! $sale->fresh()->isVoid()) {
                app(SaleService::class)->void($sale, $w->staffA, 'Uji acak');
            }
        }
    }

    expect(Sale::count())->toBeGreaterThan(5);

    $this->artisan('stock:verify')->assertSuccessful();
    expect(Qty::isNegative(stockOf($w->outletA, $w->tahu)))->toBeFalse();
});

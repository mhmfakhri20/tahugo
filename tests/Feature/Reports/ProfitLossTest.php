<?php

use App\Services\CashService;
use App\Services\PurchaseService;
use App\Services\ReportService;
use App\Services\SaleService;
use App\Support\Period;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = cashWorld(); // posWorld + category, admin, warehouse
    $this->reports = app(ReportService::class);
    $this->w->tahu->update(['last_purchase_price' => 500]);
    $this->w->bumbu->update(['last_purchase_price' => 1_000]);
    $this->w->kemasan->update(['last_purchase_price' => 300]);
});

test('HPP = ingredients used × last purchase price; a void reduces HPP', function () {
    $w = $this->w;

    sell($w->staffA, [[$w->paket, 2]]);  // 20 tahu, 2 bumbu, 2 kemasan
    $void = sell($w->staffA, [[$w->paket, 1]]);
    app(SaleService::class)->void($void, $w->staffA, 'Batal');

    // 20×500 + 2×1.000 + 2×300 = 12.600 (the voided package adds nothing)
    expect($this->reports->cogs(Period::day(now()))->get($w->outletA->id))->toBe(12_600);
});

test('gross profit per outlet = revenue − HPP − approved outlet expenses', function () {
    $w = $this->w;
    $cash = app(CashService::class);

    sell($w->staffA, [[$w->paket, 2]]);                       // 36.000 revenue, HPP 12.600
    $cash->approve(spend($w->staffA, 5_000), $w->owner);       // counted
    spend($w->staffA, 7_000);                                  // pending: not counted
    $cash->reject(spend($w->staffA, 9_000), $w->owner, 'Palsu'); // rejected: not counted

    $row = $this->reports->grossProfitByOutlet(Period::day(now()))->firstWhere('outlet.id', $w->outletA->id);

    expect($row['revenue'])->toBe(36_000)
        ->and($row['cogs'])->toBe(12_600)
        ->and($row['expenses'])->toBe(5_000)
        ->and($row['gross'])->toBe(36_000 - 12_600 - 5_000);
});

test('business P&L does not count ingredient purchases twice', function () {
    $w = $this->w;

    sell($w->staffA, [[$w->paket, 1]]);  // 18.000
    app(CashService::class)->recordIncome($w->staffA, 2_000, 'Titipan');
    app(CashService::class)->approve(spend($w->staffA, 5_000), $w->owner);

    // Purchase 100 tahu × 450 = 45.000 → stock + CASH-7 business expense, counted ONCE.
    app(PurchaseService::class)->record($w->warehouse, ['supplier' => 'Pak Darto', 'purchase_date' => now()->toDateString()],
        [['item_id' => $w->tahu->id, 'qty' => 100, 'unit_price' => 450]], null, $w->admin);
    app(CashService::class)->recordBusinessExpense($w->owner, ['amount' => 100_000, 'description' => 'Sewa']);

    $pl = $this->reports->profitLoss(Period::day(now()));

    expect($pl['revenue'])->toBe(18_000)
        ->and($pl['other_income'])->toBe(2_000)
        ->and($pl['outlet_expenses'])->toBe(5_000)
        ->and($pl['purchases'])->toBe(45_000)
        ->and($pl['business_other'])->toBe(100_000)
        ->and($pl['expenses'])->toBe(5_000 + 45_000 + 100_000)
        ->and($pl['net'])->toBe(20_000 - 150_000);

    $purchaseRows = $this->reports->expensesByCategory(Period::day(now()))->where('kind', 'purchase');
    expect($purchaseRows)->toHaveCount(1)->and($purchaseRows->first()['amount'])->toBe(45_000);

    // Filtering on an outlet excludes the warehouse purchase.
    expect($this->reports->profitLoss(Period::day(now()), $w->outletA->id)['purchases'])->toBe(0);

    Livewire::actingAs($w->owner)
        ->test('pages::reports.cash')
        ->assertSee('Rp45.000')
        ->assertSee('Pembelian bahan')
        ->assertSee('Rugi');
});

<?php

use App\Enums\CashDirection;
use App\Enums\ExpenseStatus;
use App\Enums\FundSource;
use App\Exceptions\CashException;
use App\Models\CashEntry;
use App\Services\CashService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = cashWorld();
});

test('an outlet expense is stored pending, with shift and private receipt photo (CASH-1)', function () {
    $w = $this->w;

    Livewire::actingAs($w->staffA)
        ->test('pages::outlet.expense-form')
        ->set('categoryId', (string) $w->category->id)
        ->set('amount', '15000')
        ->set('description', 'Beli cabai 1/4 kg')
        ->set('fundSource', 'outlet_cash')
        ->set('receipt', selfie())
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('outlet.expenses'));

    $entry = CashEntry::sole();

    expect($entry->status)->toBe(ExpenseStatus::Pending)
        ->and($entry->direction)->toBe(CashDirection::Out)
        ->and($entry->fund_source)->toBe(FundSource::OutletCash)
        ->and($entry->amount)->toBe(15_000)
        ->and($entry->shift_id)->toBe($w->shiftA->id)
        ->and($entry->location_id)->toBe($w->outletA->id)
        ->and($entry->user_id)->toBe($w->staffA->id);

    Storage::disk('local')->assertExists($entry->receipt_photo_path);
    expect($entry->receipt_photo_path)->toStartWith('cash/');
});

test('an expense without a receipt photo is rejected', function () {
    $w = $this->w;

    Livewire::actingAs($w->staffA)
        ->test('pages::outlet.expense-form')
        ->set('categoryId', (string) $w->category->id)
        ->set('amount', '15000')
        ->set('description', 'Beli cabai')
        ->call('save')
        ->assertHasErrors(['receipt' => 'required']);

    expect(fn () => app(CashService::class)->recordExpense($w->staffA, [
        'category_id' => $w->category->id, 'amount' => 15_000, 'description' => 'Beli cabai', 'fund_source' => 'outlet_cash',
    ], null))->toThrow(CashException::class, 'Foto nota wajib');

    expect(CashEntry::count())->toBe(0);
});

test('an expense without an open shift is rejected (BR-4)', function () {
    $w = $this->w;
    $w->shiftA->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $w->staffA->id]);

    expect(fn () => spend($w->staffA, 10_000))->toThrow(CashException::class, 'Shift belum dibuka');

    $this->actingAs($w->staffA)->get(route('outlet.expenses.create'))->assertRedirect(route('outlet.home'));
    // The list stays reachable to see rejections.
    $this->actingAs($w->staffA)->get(route('outlet.expenses'))->assertOk()->assertSee('Buka shift dulu');

    expect(CashEntry::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('cash'))->toBe([]);
});

test('staff cannot use the business fund or invalid amounts', function () {
    $w = $this->w;

    expect(fn () => spend($w->staffA, 10_000, 'business'))->toThrow(CashException::class, 'sumber dana');
    expect(fn () => spend($w->staffA, 0))->toThrow(CashException::class, 'Nominal');

    expect(CashEntry::count())->toBe(0);
});

test('other income is recorded approved in the open shift (CASH-2)', function () {
    $w = $this->w;

    Livewire::actingAs($w->staffA)
        ->test('pages::outlet.expenses')
        ->set('incomeAmount', '20000')
        ->set('incomeDescription', '')
        ->call('saveIncome')
        ->assertHasErrors(['incomeDescription' => 'required'])
        ->set('incomeDescription', 'Titipan uang kembalian dari pemilik')
        ->call('saveIncome')
        ->assertHasNoErrors();

    $income = CashEntry::sole();

    expect($income->direction)->toBe(CashDirection::In)
        ->and($income->status)->toBe(ExpenseStatus::Approved)
        ->and($income->shift_id)->toBe($w->shiftA->id)
        ->and($income->amount)->toBe(20_000);
});

test('the expense list shows the shift entries with their status', function () {
    $w = $this->w;
    $entry = spend($w->staffA, 12_500);

    $this->actingAs($w->staffA)->get(route('outlet.expenses'))
        ->assertOk()
        ->assertSee($entry->number())
        ->assertSee('Rp12.500')
        ->assertSee('Menunggu');
});

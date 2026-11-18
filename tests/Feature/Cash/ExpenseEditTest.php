<?php

use App\Models\CashEntry;
use App\Models\ExpenseCategory;
use App\Models\Shift;
use App\Services\CashService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = cashWorld();
    $this->cash = app(CashService::class);
});

function editPayload(int $amount = 30_000): array
{
    return ['category_id' => ExpenseCategory::query()->firstOrFail()->id, 'amount' => $amount, 'description' => 'Beli gas', 'fund_source' => 'personal'];
}

test('staff can edit their own pending expense, and a new photo replaces the old one', function () {
    $w = $this->w;
    $entry = spend($w->staffA, 10_000);
    $oldPath = $entry->receipt_photo_path;

    Livewire::actingAs($w->staffA)
        ->test('pages::outlet.expense-form', ['cashEntry' => $entry])
        ->assertSet('amount', '10000')
        ->set('amount', '30000')
        ->set('fundSource', 'personal')
        ->set('receipt', selfie())
        ->call('save')
        ->assertHasNoErrors();

    $entry->refresh();

    expect($entry->amount)->toBe(30_000)
        ->and($entry->fund_source->value)->toBe('personal')
        ->and($entry->receipt_photo_path)->not->toBe($oldPath);

    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists($entry->receipt_photo_path);
});

test('an expense is locked after verification (BR-6)', function (string $status) {
    $w = $this->w;
    $entry = spend($w->staffA, 10_000);
    $status === 'approved'
        ? $this->cash->approve($entry, $w->owner)
        : $this->cash->reject($entry, $w->owner, 'Nota tidak terbaca');

    expect(fn () => $this->cash->updateExpense($entry->fresh(), $w->staffA, editPayload()))->toThrow(AuthorizationException::class);
    expect(fn () => $this->cash->deleteEntry($entry->fresh(), $w->staffA))->toThrow(AuthorizationException::class);

    $this->actingAs($w->staffA)->get(route('outlet.expenses.edit', $entry))->assertForbidden();
    expect($entry->fresh()->amount)->toBe(10_000);
})->with(['approved', 'rejected']);

test('an expense is locked once the shift is closed (BR-6)', function () {
    $w = $this->w;
    $entry = spend($w->staffA, 10_000);
    $w->shiftA->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $w->staffA->id]);

    expect(fn () => $this->cash->updateExpense($entry->fresh(), $w->staffA, editPayload()))->toThrow(AuthorizationException::class);
    expect(fn () => $this->cash->deleteEntry($entry->fresh(), $w->staffA))->toThrow(AuthorizationException::class);
});

test('staff cannot edit or delete a coworker\'s or another outlet\'s expense (BR-7)', function () {
    $w = $this->w;
    $mine = spend($w->staffA, 10_000);

    Shift::factory()->atOutlet($w->outletB, $w->staffB)->create();
    $other = spend($w->staffB, 5_000);

    expect(fn () => $this->cash->updateExpense($mine, $w->staffA2, editPayload()))->toThrow(AuthorizationException::class);
    expect(fn () => $this->cash->deleteEntry($mine, $w->staffA2))->toThrow(AuthorizationException::class);
    expect(fn () => $this->cash->updateExpense($other, $w->staffA, editPayload()))->toThrow(AuthorizationException::class);

    $this->actingAs($w->staffA2)->get(route('outlet.expenses.edit', $mine))->assertForbidden();
    $this->actingAs($w->staffA)->get(route('outlet.expenses.edit', $other))->assertForbidden();

    // The other outlet's entries never appear in my list.
    $this->actingAs($w->staffA)->get(route('outlet.expenses'))->assertDontSee($other->number());
});

test('staff can delete their own pending expense; the photo is removed', function () {
    $w = $this->w;
    $entry = spend($w->staffA, 10_000);

    Livewire::actingAs($w->staffA)
        ->test('pages::outlet.expenses')
        ->call('confirmDelete', $entry->id)
        ->call('delete')
        ->assertHasNoErrors();

    expect(CashEntry::count())->toBe(0);
    Storage::disk('local')->assertMissing($entry->receipt_photo_path);
});

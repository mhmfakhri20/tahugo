<?php

use App\Models\ExpenseCategory;
use App\Models\User;
use Livewire\Livewire;

test('owner creates, renames and deactivates an expense category', function () {
    $owner = User::factory()->owner()->create();

    Livewire::actingAs($owner)
        ->test('pages::owner.settings.expense-categories')
        ->call('create')
        ->set('name', 'Air galon')
        ->call('save')
        ->assertHasNoErrors();

    $category = ExpenseCategory::where('name', 'Air galon')->firstOrFail();

    Livewire::actingAs($owner)
        ->test('pages::owner.settings.expense-categories')
        ->call('edit', $category->id)
        ->set('name', 'Air minum')
        ->call('save')
        ->call('toggleActive', $category->id);

    expect($category->fresh())
        ->name->toBe('Air minum')
        ->is_active->toBeFalse();
});

test('expense category names are unique', function () {
    ExpenseCategory::factory()->create(['name' => 'Listrik']);

    Livewire::actingAs(User::factory()->owner()->create())
        ->test('pages::owner.settings.expense-categories')
        ->set('name', 'Listrik')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);
});

test('outlet staff can list categories but not manage them', function () {
    $staff = User::factory()->outletStaff()->create();
    $category = ExpenseCategory::factory()->create();

    expect($staff->can('viewAny', ExpenseCategory::class))->toBeTrue()
        ->and($staff->can('update', $category))->toBeFalse()
        ->and(User::factory()->warehouseAdmin()->create()->can('viewAny', ExpenseCategory::class))->toBeFalse();

    $this->actingAs($staff)->get(route('owner.settings.expense-categories'))->assertForbidden();
});

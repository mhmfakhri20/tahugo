<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create(['name' => 'Bu Nabila']);
    $this->staff = User::factory()->outletStaff()->create(['name' => 'Siti']);

    audit(AuditAction::Login, $this->staff, actor: $this->staff);
    audit(AuditAction::UserUpdate, $this->staff, ['name' => 'Siti'], ['name' => 'Siti R'], $this->owner);
});

test('only the owner can open the audit log', function () {
    $this->actingAs($this->owner)->get(route('owner.settings.audit-log'))->assertOk()->assertSee('Audit log');
    $this->actingAs($this->staff)->get(route('owner.settings.audit-log'))->assertForbidden();
    $this->actingAs(User::factory()->warehouseAdmin()->create())->get(route('owner.settings.audit-log'))->assertForbidden();
});

test('the audit log can be filtered and shows readable old/new values', function () {
    $updateId = AuditLog::where('action', AuditAction::UserUpdate)->value('id');

    Livewire::actingAs($this->owner)
        ->test('pages::owner.settings.audit-log')
        ->assertSee('Masuk')
        ->assertSee('Ubah pengguna')
        ->set('actionFilter', AuditAction::UserUpdate->value)
        ->assertDontSee('Siti</td>', escape: false)
        ->call('show', $updateId)
        ->assertSee('Nama')
        ->assertSee('Siti R')
        ->set('actionFilter', '')
        ->set('userFilter', (string) $this->staff->id)
        ->assertSee('Masuk');
});

test('audit rows are append-only and there is no route to change them', function () {
    $log = AuditLog::first();

    expect(fn () => $log->update(['action' => AuditAction::Logout]))->toThrow(LogicException::class);
    expect(fn () => $log->delete())->toThrow(LogicException::class);
    expect(AuditLog::count())->toBe(2);

    $writeRoutes = collect(Route::getRoutes())
        ->filter(fn ($route) => str_contains($route->uri(), 'audit'))
        ->filter(fn ($route) => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== []);

    expect($writeRoutes)->toBeEmpty();
});

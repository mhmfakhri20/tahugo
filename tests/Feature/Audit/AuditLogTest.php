<?php

use App\Enums\AuditAction;
use App\Enums\Role;
use App\Exceptions\SaleException;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\CashService;
use App\Services\LocationService;
use App\Services\ProductService;
use App\Services\SaleService;
use App\Services\ShiftService;
use App\Services\StockAdjustmentService;
use App\Services\StockRequestService;
use App\Services\UserService;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->w = cashWorld();
});

/**
 * The single audit row of $action.
 */
function auditRow(AuditAction $action): AuditLog
{
    return AuditLog::query()->where('action', $action->value)->sole();
}

test('voiding a sale writes one audit row with old/new status', function () {
    $w = $this->w;
    $sale = sell($w->staffA, [[$w->paket, 1]]);

    app(SaleService::class)->void($sale, $w->staffA, 'Salah input');

    $log = auditRow(AuditAction::SaleVoid);
    expect($log->user_id)->toBe($w->staffA->id)
        ->and($log->auditable_id)->toBe($sale->id)
        ->and($log->location_id)->toBe($w->outletA->id)
        ->and($log->old_values)->toMatchArray(['status' => 'completed', 'total' => 18_000])
        ->and($log->new_values)->toMatchArray(['status' => 'void', 'void_reason' => 'Salah input']);
});

test('closing a shift and the owner correction are audited', function () {
    $w = $this->w;
    atWib('21:00');
    app(ShiftService::class)->close($w->shiftA, $w->staffA, 95_000, selfie());

    expect(auditRow(AuditAction::ShiftClose)->new_values)->toMatchArray(['counted_cash' => 95_000, 'expected_cash' => 100_000, 'variance' => -5_000]);

    app(ShiftService::class)->correct($w->shiftA->fresh(), $w->owner, 100_000, 'Salah hitung');

    $log = auditRow(AuditAction::ShiftCorrect);
    expect($log->user_id)->toBe($w->owner->id)
        ->and($log->old_values)->toBe(['counted_cash' => 95_000, 'variance' => -5_000])
        ->and($log->new_values)->toBe(['counted_cash' => 100_000, 'variance' => 0, 'reason' => 'Salah hitung'])
        ->and($w->shiftA->fresh()->variance)->toBe(0);

    // Staff may not correct.
    expect(fn () => app(ShiftService::class)->correct($w->shiftA->fresh(), $w->staffA, 1, 'Coba'))
        ->toThrow(AuthorizationException::class);
});

test('approving and rejecting expenses are audited', function () {
    $w = $this->w;
    $cash = app(CashService::class);

    $cash->approve(spend($w->staffA, 5_000), $w->owner);
    $cash->reject(spend($w->staffA, 7_000), $w->owner, 'Nota tidak jelas');

    expect(auditRow(AuditAction::ExpenseApprove))
        ->user_id->toBe($w->owner->id)
        ->old_values->toMatchArray(['status' => 'pending', 'amount' => 5_000])
        ->new_values->toBe(['status' => 'approved']);
    expect(auditRow(AuditAction::ExpenseReject)->new_values)->toBe(['status' => 'rejected', 'reject_reason' => 'Nota tidak jelas']);
});

test('stock request approve, reject, ship and receive are audited', function () {
    $w = $this->w;
    $requests = app(StockRequestService::class);
    app(StockAdjustmentService::class)->adjust($w->warehouse, $w->tahu, 500, 'Saldo awal', $w->owner);

    $request = $requests->create($w->outletA, [$w->tahu->id => 50], null, $w->staffA);
    $requests->approve($request, [], $w->owner);
    $requests->ship($request->fresh(), [], null, $w->admin);
    $requests->receive($request->fresh(), [$request->items->first()->id => 48], 'Dua rusak', $w->staffA);

    $rejected = $requests->create($w->outletA, [$w->tahu->id => 10], null, $w->staffA);
    $requests->reject($rejected, 'Stok cukup', $w->owner);

    expect(auditRow(AuditAction::StockRequestApprove)->new_values['items'])->toBe(['Tahu mentah' => '50'])
        ->and(auditRow(AuditAction::StockRequestShip)->user_id)->toBe($w->admin->id)
        ->and(auditRow(AuditAction::StockRequestReceive)->new_values)->toMatchArray(['items' => ['Tahu mentah' => '48'], 'note' => 'Dua rusak'])
        ->and(auditRow(AuditAction::StockRequestReject)->new_values)->toMatchArray(['reject_reason' => 'Stok cukup']);
});

test('stock adjustment and waste are audited', function () {
    $w = $this->w;

    app(StockAdjustmentService::class)->adjust($w->outletA, $w->tahu, -10, 'Hitung ulang', $w->owner);
    app(StockAdjustmentService::class)->waste($w->outletA, $w->tahu, 3, 'Basi', $w->staffA);

    expect(auditRow(AuditAction::StockAdjust)->new_values)->toMatchArray(['item' => 'Tahu mentah', 'qty' => '-10', 'balance_after' => '90', 'reason' => 'Hitung ulang'])
        ->and(auditRow(AuditAction::StockAdjust)->old_values)->toMatchArray(['balance_after' => '100'])
        ->and(auditRow(AuditAction::StockWaste)->user_id)->toBe($w->staffA->id)
        ->and(auditRow(AuditAction::StockWaste)->new_values)->toMatchArray(['qty' => '-3', 'reason' => 'Basi']);
});

test('product price and recipe changes are audited with old/new values', function () {
    $w = $this->w;
    $this->actingAs($w->owner);

    app(ProductService::class)->save($w->paket->fresh(), ['name' => 'Paket isi 10', 'type' => 'paket'],
        ['offline' => 20_000, 'gofood' => 22_000, 'grabfood' => 21_000, 'shopeefood' => 20_000],
        [['item_id' => $w->tahu->id, 'qty' => 10], ['item_id' => $w->bumbu->id, 'qty' => 2], ['item_id' => $w->kemasan->id, 'qty' => 1]],
    );

    $log = auditRow(AuditAction::ProductUpdate);
    expect($log->user_id)->toBe($w->owner->id)
        ->and($log->old_values['prices']['Offline'])->toBe(18_000)
        ->and($log->new_values['prices']['Offline'])->toBe(20_000)
        ->and($log->old_values['recipe']['Bumbu tabur'])->toBe('1')
        ->and($log->new_values['recipe']['Bumbu tabur'])->toBe('2');

    // Saving without changes writes nothing new.
    app(ProductService::class)->save($w->paket->fresh(), ['name' => 'Paket isi 10', 'type' => 'paket'],
        ['offline' => 20_000, 'gofood' => 22_000, 'grabfood' => 21_000, 'shopeefood' => 20_000],
        [['item_id' => $w->tahu->id, 'qty' => 10], ['item_id' => $w->bumbu->id, 'qty' => 2], ['item_id' => $w->kemasan->id, 'qty' => 1]],
    );
    expect(AuditLog::where('action', AuditAction::ProductUpdate)->count())->toBe(1);
});

test('outlet data changes and (de)activation are audited', function () {
    $w = $this->w;
    $this->actingAs($w->owner);
    $service = app(LocationService::class);

    $service->save($w->outletB, ['code' => 'BKK', 'name' => 'Outlet B Baru', 'type' => 'outlet', 'attendance_radius_m' => 150, 'monthly_target' => 5_000_000]);

    $log = auditRow(AuditAction::LocationUpdate);
    expect($log->old_values)->toMatchArray(['name' => 'Outlet B', 'attendance_radius_m' => 100])
        ->and($log->new_values)->toMatchArray(['name' => 'Outlet B Baru', 'attendance_radius_m' => 150, 'monthly_target' => 5_000_000])
        ->and($log->location_id)->toBe($w->outletB->id);

    $w->staffB->update(['is_active' => false]);
    $service->setActive($w->outletB->fresh(), false);
    expect(auditRow(AuditAction::LocationDeactivate)->new_values)->toBe(['is_active' => false]);
});

test('user create, update, deactivate and password reset are audited without passwords', function () {
    $w = $this->w;
    $this->actingAs($w->owner);
    $users = app(UserService::class);

    $user = $users->create(['name' => 'Rina', 'username' => 'rina', 'role' => Role::OutletStaff, 'location_id' => $w->outletA->id, 'password' => 'rahasia-sekali-123']);
    $users->update($w->owner, $user, ['name' => 'Rina W', 'username' => 'rina', 'role' => Role::OutletStaff, 'location_id' => $w->outletB->id]);
    $users->setActive($w->owner, $user->fresh(), false);
    $users->resetPassword($user->fresh(), 'password-baru-456');

    expect(auditRow(AuditAction::UserCreate)->new_values)->toMatchArray(['name' => 'Rina', 'username' => 'rina', 'role' => 'outlet_staff'])
        ->and(auditRow(AuditAction::UserUpdate)->old_values)->toBe(['name' => 'Rina', 'location_id' => $w->outletA->id])
        ->and(auditRow(AuditAction::UserUpdate)->new_values)->toBe(['name' => 'Rina W', 'location_id' => $w->outletB->id])
        ->and(auditRow(AuditAction::UserDeactivate)->new_values)->toBe(['is_active' => false])
        ->and(auditRow(AuditAction::UserPasswordReset)->new_values)->toBe(['password_changed' => true]);

    // No password, hash or token is ever stored.
    $all = AuditLog::query()->get()->map(fn ($log) => json_encode([$log->old_values, $log->new_values]))->implode(' ');
    expect($all)->not->toContain('rahasia-sekali-123')
        ->not->toContain('password-baru-456')
        ->not->toContain('$2y$')
        ->not->toContain('remember_token');
});

test('login success, failure and logout are audited (failure keeps only the username)', function () {
    $user = User::factory()->owner()->create(['username' => 'bos', 'password' => 'benar-123']);

    $this->post(route('login.store'), ['username' => 'bos', 'password' => 'salah-999']);
    $this->post(route('login.store'), ['username' => 'bos', 'password' => 'benar-123']);
    $this->post(route('logout'));

    expect(auditRow(AuditAction::LoginFailed)->new_values)->toBe(['username_attempt' => 'bos'])
        ->and(auditRow(AuditAction::Login)->user_id)->toBe($user->id)
        ->and(auditRow(AuditAction::Login)->ip)->not->toBeNull()
        ->and(auditRow(AuditAction::Logout)->user_id)->toBe($user->id);

    expect(json_encode(AuditLog::pluck('new_values')))->not->toContain('salah-999')->not->toContain('benar-123');
});

test('a failed action leaves no audit log', function () {
    $w = $this->w;
    $sale = sell($w->staffA, [[$w->paket, 1]]);
    $w->shiftA->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $w->staffA->id]);

    expect(fn () => app(SaleService::class)->void($sale, $w->staffA, 'Telat'))->toThrow(SaleException::class);

    expect(AuditLog::where('action', AuditAction::SaleVoid)->count())->toBe(0);
});

test('if writing the audit log fails, the action is rolled back too', function () {
    $w = $this->w;
    $sale = sell($w->staffA, [[$w->paket, 1]]);

    AuditLog::creating(fn () => throw new RuntimeException('disk penuh'));

    expect(fn () => app(SaleService::class)->void($sale, $w->staffA, 'Salah'))->toThrow(RuntimeException::class);

    expect($sale->fresh()->status->value)->toBe('completed')
        ->and(stockOf($w->outletA, $w->tahu))->toBe('90.000');
});

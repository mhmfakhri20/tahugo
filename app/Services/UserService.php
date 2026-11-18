<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * User management by the owner (AUTH-5). Users are never deleted, only deactivated.
 */
class UserService
{
    /** Fields recorded in the audit log (no password / tokens). */
    private const AUDITED = ['name', 'username', 'phone', 'role', 'location_id', 'is_active'];

    /**
     * @param  array{name: string, username: string, phone?: ?string, role: Role|string, location_id?: ?int, password: string}  $data
     */
    public function create(array $data): User
    {
        $attributes = $this->normalize($data);

        return DB::transaction(function () use ($attributes, $data): User {
            $user = User::create($attributes + [
                'password' => $data['password'],
                'is_active' => true,
            ]);

            // The password is never written to the audit log (AuditService drops it anyway).
            audit(AuditAction::UserCreate, $user, null, $user->only(self::AUDITED));

            return $user;
        });
    }

    /**
     * @param  array{name: string, username: string, phone?: ?string, role: Role|string, location_id?: ?int}  $data
     */
    public function update(User $actor, User $user, array $data): User
    {
        $attributes = $this->normalize($data);

        if ($actor->is($user) && $attributes['role'] !== Role::Owner) {
            throw ValidationException::withMessages([
                'role' => 'Anda tidak bisa mengubah peran akun Anda sendiri.',
            ]);
        }

        return DB::transaction(function () use ($actor, $user, $attributes): User {
            $before = $user->only(self::AUDITED);
            $user->update($attributes);

            [$old, $new] = app(AuditService::class)->diff($before, $user->only(self::AUDITED));

            if ($new !== []) {
                audit(AuditAction::UserUpdate, $user, $old, $new, $actor);
            }

            return $user;
        });
    }

    public function setActive(User $actor, User $user, bool $active): void
    {
        if (! $active && $actor->is($user)) {
            throw ValidationException::withMessages([
                'is_active' => 'Anda tidak bisa menonaktifkan akun Anda sendiri.',
            ]);
        }

        if ($active && $user->role === Role::OutletStaff) {
            $this->ensureActiveOutlet($user->location_id);
        }

        DB::transaction(function () use ($actor, $user, $active): void {
            $old = (bool) $user->is_active;
            $user->update(['is_active' => $active]);

            audit($active ? AuditAction::UserActivate : AuditAction::UserDeactivate, $user, ['is_active' => $old], ['is_active' => $active], $actor);
        });
    }

    /**
     * Set a new password and sign the user out of every device.
     */
    public function resetPassword(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('sessions')->where('user_id', $user->id)->delete();

            // Only the fact that it changed — never the password itself.
            audit(AuditAction::UserPasswordReset, $user, null, ['password_changed' => true]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, username: string, phone: ?string, role: Role, location_id: ?int}
     */
    private function normalize(array $data): array
    {
        $role = $data['role'] instanceof Role ? $data['role'] : Role::from($data['role']);
        $locationId = $role === Role::OutletStaff ? ($data['location_id'] ?? null) : null;

        if ($role === Role::OutletStaff) {
            $this->ensureActiveOutlet($locationId);
        }

        return [
            'name' => trim($data['name']),
            'username' => Str::lower(trim($data['username'])),
            'phone' => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
            'role' => $role,
            'location_id' => $locationId !== null ? (int) $locationId : null,
        ];
    }

    private function ensureActiveOutlet(mixed $locationId): void
    {
        $isActiveOutlet = filled($locationId)
            && Location::query()->outlets()->active()->whereKey($locationId)->exists();

        if (! $isActiveOutlet) {
            throw ValidationException::withMessages([
                'location_id' => 'Pilih outlet aktif untuk Penjaga Outlet.',
            ]);
        }
    }
}

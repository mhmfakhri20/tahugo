<?php

namespace App\Services;

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
    /**
     * @param  array{name: string, username: string, phone?: ?string, role: Role|string, location_id?: ?int, password: string}  $data
     */
    public function create(array $data): User
    {
        $attributes = $this->normalize($data);

        return User::create($attributes + [
            'password' => $data['password'],
            'is_active' => true,
        ]);
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

        $user->update($attributes);

        return $user;
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

        $user->update(['is_active' => $active]);
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

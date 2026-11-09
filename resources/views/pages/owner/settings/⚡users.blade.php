<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Models\Location;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pengaturan')] class extends Component {
    use PasswordValidationRules, ProfileValidationRules, WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $roleFilter = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    // Form tambah/ubah pengguna
    public ?int $editingId = null;

    public string $name = '';

    public string $username = '';

    public string $phone = '';

    public string $role = '';

    public ?int $location_id = null;

    public string $password = '';

    public string $password_confirmation = '';

    // Reset password & konfirmasi aktif/nonaktif
    public ?int $targetId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'roleFilter', 'statusFilter'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->with('location')
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('username', 'like', "%{$this->search}%")))
            ->when($this->roleFilter !== '', fn ($query) => $query->where('role', $this->roleFilter))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('is_active', $this->statusFilter === 'active'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(15);
    }

    /**
     * @return Collection<int, Location>
     */
    #[Computed]
    public function outlets(): Collection
    {
        return Location::query()->outlets()->active()->orderBy('name')->get();
    }

    #[Computed]
    public function target(): ?User
    {
        return $this->targetId ? User::find($this->targetId) : null;
    }

    public function create(): void
    {
        $this->authorize('create', User::class);

        $this->resetForm();
        $this->modal('user-form')->show();
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);
        $this->authorize('update', $user);

        $this->resetForm();
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->username = $user->username;
        $this->phone = $user->phone ?? '';
        $this->role = $user->role->value;
        $this->location_id = $user->location_id;

        $this->modal('user-form')->show();
    }

    public function save(UserService $users): void
    {
        $user = $this->editingId ? User::findOrFail($this->editingId) : null;
        $user ? $this->authorize('update', $user) : $this->authorize('create', User::class);

        $this->username = Str::lower(trim($this->username));

        $rules = [
            'name' => $this->nameRules(),
            'username' => ['required', 'string', 'min:3', 'max:30', 'alpha_dash', Rule::unique('users', 'username')->ignore($this->editingId)],
            'phone' => $this->phoneRules(),
            'role' => ['required', Rule::enum(Role::class)],
            'location_id' => [Rule::requiredIf($this->role === Role::OutletStaff->value), 'nullable', 'integer'],
        ];

        if (! $user) {
            $rules['password'] = $this->passwordRules();
        }

        $validated = $this->validate($rules, attributes: [
            'role' => 'peran',
            'location_id' => 'outlet',
        ]);

        if ($user) {
            $users->update(auth()->user(), $user, $validated);
            $message = "Data {$user->name} tersimpan.";
        } else {
            $user = $users->create($validated);
            $message = "Akun {$user->name} berhasil dibuat.";
        }

        $this->modal('user-form')->close();
        $this->resetForm();
        $this->dispatch('notify', message: $message);
    }

    public function confirmToggle(int $id): void
    {
        $user = User::findOrFail($id);
        $this->authorize('toggleActive', $user);

        $this->targetId = $user->id;
        $this->modal('user-toggle')->show();
    }

    public function toggleActive(UserService $users): void
    {
        $user = User::findOrFail($this->targetId);
        $this->authorize('toggleActive', $user);

        try {
            $users->setActive(auth()->user(), $user, ! $user->is_active);
        } catch (ValidationException $e) {
            $this->modal('user-toggle')->close();
            $this->dispatch('notify', message: collect($e->errors())->flatten()->first(), variant: 'danger');

            return;
        }

        $this->modal('user-toggle')->close();
        $this->dispatch('notify', message: $user->is_active ? "Akun {$user->name} diaktifkan." : "Akun {$user->name} dinonaktifkan.");
    }

    public function confirmResetPassword(int $id): void
    {
        $user = User::findOrFail($id);
        $this->authorize('resetPassword', $user);

        $this->resetErrorBag();
        $this->reset('password', 'password_confirmation');
        $this->targetId = $user->id;
        $this->modal('user-password')->show();
    }

    public function resetPassword(UserService $users): void
    {
        $user = User::findOrFail($this->targetId);
        $this->authorize('resetPassword', $user);

        $validated = $this->validate(['password' => $this->passwordRules()], attributes: ['password' => 'password baru']);

        $users->resetPassword($user, $validated['password']);

        $this->modal('user-password')->close();
        $this->reset('password', 'password_confirmation');
        $this->dispatch('notify', message: "Password {$user->name} sudah diganti. Akun ini otomatis keluar dari semua perangkat.");
    }

    private function resetForm(): void
    {
        $this->resetErrorBag();
        $this->reset('editingId', 'name', 'username', 'phone', 'role', 'location_id', 'password', 'password_confirmation');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-page-header title="Pengaturan" description="Kelola pengguna, lokasi, bahan baku, produk, dan kategori pengeluaran." />

    <x-settings-tabs />

    <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-text">Pengguna</h2>
                <p class="text-sm text-muted">Akun dibuat oleh pemilik. Akun tidak dihapus, cukup dinonaktifkan.</p>
            </div>
            <flux:button variant="primary" icon="plus" wire:click="create">Tambah pengguna</flux:button>
        </div>

        <div class="flex flex-col gap-3 md:flex-row">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari nama atau username..." class="md:max-w-sm" aria-label="Cari pengguna" />
            <div class="grid grid-cols-2 gap-3 md:ms-auto md:flex">
                <flux:select wire:model.live="roleFilter" aria-label="Filter peran">
                    <flux:select.option value="">Semua peran</flux:select.option>
                    @foreach (Role::options() as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="statusFilter" aria-label="Filter status">
                    <flux:select.option value="">Semua status</flux:select.option>
                    <flux:select.option value="active">Aktif</flux:select.option>
                    <flux:select.option value="inactive">Nonaktif</flux:select.option>
                </flux:select>
            </div>
        </div>

        @if ($this->users->isEmpty())
            <x-empty-state icon="users" title="Pengguna tidak ditemukan" description="Ubah kata kunci atau filter pencarian." />
        @else
            <x-data-table>
                <x-slot:head>
                    <th>Nama</th>
                    <th>Peran</th>
                    <th>Outlet</th>
                    <th>Login terakhir</th>
                    <th>Status</th>
                    <th class="text-end!">Aksi</th>
                </x-slot:head>

                @foreach ($this->users as $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <td>
                            <div class="font-medium">{{ $user->name }}</div>
                            <div class="text-xs text-muted">{{ '@'.$user->username }}{{ $user->phone ? ' · '.$user->phone : '' }}</div>
                        </td>
                        <td>{{ $user->role->label() }}</td>
                        <td>{{ $user->location?->name ?? '—' }}</td>
                        <td class="text-muted">{{ $user->last_login_at?->translatedFormat('d M Y, H.i') ?? 'Belum pernah' }}</td>
                        <td>
                            <x-status-badge :variant="$user->is_active ? 'success' : 'muted'">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</x-status-badge>
                        </td>
                        <td class="text-end whitespace-nowrap">
                            <flux:button variant="ghost" size="sm" wire:click="edit({{ $user->id }})">Ubah</flux:button>
                            <flux:button variant="ghost" size="sm" wire:click="confirmResetPassword({{ $user->id }})">Reset password</flux:button>
                            @can('toggleActive', $user)
                                <flux:button variant="ghost" size="sm" wire:click="confirmToggle({{ $user->id }})" @class(['text-danger!' => $user->is_active])>
                                    {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </flux:button>
                            @endcan
                        </td>
                    </tr>
                @endforeach

                <x-slot:footer>
                    <span>Menampilkan {{ $this->users->count() }} dari {{ $this->users->total() }} pengguna</span>
                    {{ $this->users->links() }}
                </x-slot:footer>
            </x-data-table>
        @endif
    </section>

    {{-- Dialog tambah / ubah pengguna --}}
    <flux:modal name="user-form" class="w-full max-w-lg">
        <form wire:submit="save" class="flex flex-col gap-5">
            <flux:heading size="lg">{{ $editingId ? 'Ubah pengguna' : 'Tambah pengguna' }}</flux:heading>

            <flux:input wire:model="name" label="Nama *" autocomplete="off" />
            <flux:input wire:model="username" label="Username *" description:trailing="Huruf kecil, angka, - atau _. Dipakai untuk masuk." autocapitalize="none" autocomplete="off" />
            <flux:input wire:model="phone" label="No. HP" type="tel" inputmode="tel" description:trailing="Opsional." />

            <flux:select wire:model.live="role" label="Peran *" placeholder="Pilih peran">
                @foreach (Role::options() as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($role === Role::OutletStaff->value)
                <flux:select wire:model="location_id" label="Outlet *" placeholder="Pilih outlet" description:trailing="Penjaga hanya bisa melihat data outlet ini.">
                    @foreach ($this->outlets as $outlet)
                        <flux:select.option :value="$outlet->id">{{ $outlet->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            @unless ($editingId)
                <flux:input wire:model="password" label="Password *" type="password" viewable autocomplete="new-password" />
                <flux:input wire:model="password_confirmation" label="Ulangi password *" type="password" viewable autocomplete="new-password" />
            @endunless

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button>Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Dialog reset password --}}
    <flux:modal name="user-password" class="w-full max-w-md">
        <form wire:submit="resetPassword" class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">Reset password</flux:heading>
                <flux:text class="mt-1">Buat password baru untuk {{ $this->target?->name }}. Akun ini akan keluar dari semua perangkat.</flux:text>
            </div>

            <flux:input wire:model="password" label="Password baru *" type="password" viewable autocomplete="new-password" />
            <flux:input wire:model="password_confirmation" label="Ulangi password baru *" type="password" viewable autocomplete="new-password" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button>Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">Simpan password</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Konfirmasi aktif / nonaktif --}}
    <flux:modal name="user-toggle" class="w-full max-w-md">
        <div class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">{{ $this->target?->is_active ? 'Nonaktifkan akun?' : 'Aktifkan akun?' }}</flux:heading>
                <flux:text class="mt-1">
                    @if ($this->target?->is_active)
                        {{ $this->target?->name }} tidak bisa masuk lagi dan langsung keluar dari aplikasi. Data yang sudah dicatat tetap tersimpan.
                    @else
                        {{ $this->target?->name }} bisa masuk lagi dengan username dan password yang sama.
                    @endif
                </flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button>Batal</flux:button>
                </flux:modal.close>
                @if ($this->target?->is_active)
                    <flux:button variant="ghost" icon="no-symbol" class="btn-danger" wire:click="toggleActive">Nonaktifkan</flux:button>
                @else
                    <flux:button variant="primary" wire:click="toggleActive">Aktifkan</flux:button>
                @endif
            </div>
        </div>
    </flux:modal>
</div>

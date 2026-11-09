<?php

use App\Enums\LocationType;
use App\Models\Location;
use App\Services\LocationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengaturan')] class extends Component {
    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $type = '';

    public string $address = '';

    public string $latitude = '';

    public string $longitude = '';

    public string $attendance_radius_m = '100';

    public string $open_time = '';

    public string $close_time = '';

    public string $monthly_target = '';

    public bool $is_active = true;

    public ?int $targetId = null;

    public function mount(): void
    {
        // Admin gudang boleh melihat lokasi, tapi halaman pengelolaan ini khusus pemilik.
        $this->authorize('create', Location::class);
    }

    /**
     * @return Collection<int, Location>
     */
    #[Computed]
    public function locations(): Collection
    {
        return Location::query()
            ->withCount(['users as active_staff_count' => fn ($query) => $query->where('is_active', true)])
            ->orderByDesc('is_active')
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function target(): ?Location
    {
        return $this->targetId ? Location::find($this->targetId) : null;
    }

    public function create(): void
    {
        $this->authorize('create', Location::class);

        $this->resetForm();
        $this->type = LocationType::Outlet->value;
        $this->modal('location-form')->show();
    }

    public function edit(int $id): void
    {
        $location = Location::findOrFail($id);
        $this->authorize('update', $location);

        $this->resetForm();
        $this->editingId = $location->id;
        $this->code = $location->code;
        $this->name = $location->name;
        $this->type = $location->type->value;
        $this->address = $location->address ?? '';
        $this->latitude = (string) ($location->latitude ?? '');
        $this->longitude = (string) ($location->longitude ?? '');
        $this->attendance_radius_m = (string) $location->attendance_radius_m;
        $this->open_time = $location->open_time ? substr($location->open_time, 0, 5) : '';
        $this->close_time = $location->close_time ? substr($location->close_time, 0, 5) : '';
        $this->monthly_target = (string) ($location->monthly_target ?? '');
        $this->is_active = $location->is_active;

        $this->modal('location-form')->show();
    }

    public function save(LocationService $service): void
    {
        $location = $this->editingId ? Location::findOrFail($this->editingId) : null;
        $location ? $this->authorize('update', $location) : $this->authorize('create', Location::class);

        $this->code = strtoupper(trim($this->code));
        $isOutlet = $this->type === LocationType::Outlet->value;

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:10', 'alpha_num', Rule::unique('locations', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(LocationType::class)],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'attendance_radius_m' => ['required', 'integer', 'min:10', 'max:5000'],
            'open_time' => [$isOutlet ? 'nullable' : 'exclude', 'date_format:H:i'],
            'close_time' => [$isOutlet ? 'nullable' : 'exclude', 'date_format:H:i', Rule::when(filled($this->open_time), 'after:open_time')],
            'monthly_target' => [$isOutlet ? 'nullable' : 'exclude', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ], attributes: [
            'code' => 'kode',
            'type' => 'tipe',
            'address' => 'alamat',
            'attendance_radius_m' => 'radius absen',
            'open_time' => 'jam buka',
            'close_time' => 'jam tutup',
            'monthly_target' => 'target omzet bulanan',
        ]);

        $validated = array_map(fn ($value) => $value === '' ? null : $value, $validated);

        $location = $service->save($location, $validated);

        $this->modal('location-form')->close();
        $this->resetForm();
        $this->dispatch('notify', message: "{$location->name} tersimpan.");
    }

    public function confirmToggle(int $id): void
    {
        $location = Location::findOrFail($id);
        $this->authorize('update', $location);

        $this->targetId = $location->id;
        $this->modal('location-toggle')->show();
    }

    public function toggleActive(LocationService $service): void
    {
        $location = Location::findOrFail($this->targetId);
        $this->authorize('update', $location);

        $this->modal('location-toggle')->close();

        try {
            $service->setActive($location, ! $location->is_active);
        } catch (ValidationException $e) {
            $this->dispatch('notify', message: collect($e->errors())->flatten()->first(), variant: 'danger');

            return;
        }

        $this->dispatch('notify', message: $location->is_active ? "{$location->name} diaktifkan." : "{$location->name} dinonaktifkan.");
    }

    private function resetForm(): void
    {
        $this->resetErrorBag();
        $this->reset('editingId', 'code', 'name', 'type', 'address', 'latitude', 'longitude', 'attendance_radius_m', 'open_time', 'close_time', 'monthly_target', 'is_active');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-page-header title="Pengaturan" description="Kelola pengguna, lokasi, bahan baku, produk, dan kategori pengeluaran." />

    <x-settings-tabs />

    <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-text">Outlet & gudang</h2>
                <p class="text-sm text-muted">Lokasi penyimpanan stok. Koordinat, radius, dan jam buka dipakai untuk absensi.</p>
            </div>
            <flux:button variant="primary" icon="plus" wire:click="create">Tambah lokasi</flux:button>
        </div>

        <x-data-table>
            <x-slot:head>
                <th>Lokasi</th>
                <th>Tipe</th>
                <th>Jam buka</th>
                <th>Titik absen</th>
                <th>Penjaga aktif</th>
                <th>Status</th>
                <th class="text-end!">Aksi</th>
            </x-slot:head>

            @foreach ($this->locations as $location)
                <tr wire:key="location-{{ $location->id }}">
                    <td>
                        <div class="font-medium">{{ $location->name }}</div>
                        <div class="text-xs text-muted">{{ $location->code }}{{ $location->address ? ' · '.$location->address : '' }}</div>
                    </td>
                    <td>
                        <x-status-badge :variant="$location->isOutlet() ? 'soft' : 'muted'">{{ $location->type->label() }}</x-status-badge>
                    </td>
                    <td>
                        @if ($location->open_time && $location->close_time)
                            {{ substr($location->open_time, 0, 5) }}–{{ substr($location->close_time, 0, 5) }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if ($location->latitude !== null)
                            <span>Radius {{ $location->attendance_radius_m }} m</span>
                        @elseif ($location->isOutlet())
                            <x-status-badge variant="warning">Belum diatur</x-status-badge>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $location->isOutlet() ? $location->active_staff_count : '—' }}</td>
                    <td>
                        <x-status-badge :variant="$location->is_active ? 'success' : 'muted'">{{ $location->is_active ? 'Aktif' : 'Nonaktif' }}</x-status-badge>
                    </td>
                    <td class="text-end whitespace-nowrap">
                        <flux:button variant="ghost" size="sm" wire:click="edit({{ $location->id }})">Ubah</flux:button>
                        <flux:button variant="ghost" size="sm" wire:click="confirmToggle({{ $location->id }})" @class(['text-danger!' => $location->is_active])>
                            {{ $location->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </flux:button>
                    </td>
                </tr>
            @endforeach

            <x-slot:footer>
                <span>Menampilkan {{ $this->locations->count() }} lokasi</span>
            </x-slot:footer>
        </x-data-table>
    </section>

    <flux:modal name="location-form" class="w-full max-w-2xl">
        <form wire:submit="save" class="flex flex-col gap-5">
            <flux:heading size="lg">{{ $editingId ? 'Ubah lokasi' : 'Tambah lokasi' }}</flux:heading>

            <div class="grid gap-5 sm:grid-cols-3">
                <flux:input wire:model="code" label="Kode *" maxlength="10" description:trailing="Mis. FJI. Dipakai di nomor transaksi." class:input="uppercase" />
                <flux:input wire:model="name" label="Nama *" class="sm:col-span-2" />
            </div>

            <flux:select wire:model.live="type" label="Tipe *">
                @foreach (LocationType::options() as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="address" label="Alamat" />

            <div class="grid gap-5 sm:grid-cols-3">
                <flux:input wire:model="latitude" label="Latitude" inputmode="decimal" placeholder="-7.5605" />
                <flux:input wire:model="longitude" label="Longitude" inputmode="decimal" placeholder="110.8261" />
                <flux:input wire:model="attendance_radius_m" label="Radius absen *" type="number" min="10" inputmode="numeric">
                    <x-slot name="iconTrailing"><span class="text-sm text-muted">m</span></x-slot>
                </flux:input>
            </div>
            <flux:text class="-mt-3 text-xs">Salin koordinat dari Google Maps (klik kanan di titik outlet). Absen di luar radius akan ditolak.</flux:text>

            @if ($type === LocationType::Outlet->value)
                <div class="grid gap-5 sm:grid-cols-3">
                    <flux:input wire:model="open_time" label="Jam buka" type="time" />
                    <flux:input wire:model="close_time" label="Jam tutup" type="time" />
                    <flux:input wire:model="monthly_target" label="Target omzet bulanan" type="number" min="0" inputmode="numeric" description:trailing="Opsional, dalam rupiah.">
                        <x-slot name="iconLeading"><span class="text-sm text-muted">Rp</span></x-slot>
                    </flux:input>
                </div>
            @endif

            <flux:error name="is_active" />
            <flux:error name="type" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button>Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="location-toggle" class="w-full max-w-md">
        <div class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">{{ $this->target?->is_active ? 'Nonaktifkan lokasi?' : 'Aktifkan lokasi?' }}</flux:heading>
                <flux:text class="mt-1">
                    @if ($this->target?->is_active)
                        {{ $this->target?->name }} tidak bisa dipilih lagi untuk penjaga dan transaksi baru. Data lama tetap tersimpan.
                    @else
                        {{ $this->target?->name }} bisa dipakai lagi.
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

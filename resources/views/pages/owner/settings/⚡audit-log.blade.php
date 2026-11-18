<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\User;
use App\Services\AuditService;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Audit log (AUD-2): pemilik saja, hanya baca. Filter tanggal, pengguna, outlet, jenis aksi.
 */
new #[Title('Audit log')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $userFilter = '';

    #[Url(except: '')]
    public string $outletFilter = '';

    #[Url(except: '')]
    public string $actionFilter = '';

    #[Locked]
    public ?int $detailId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', AuditLog::class);
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    private function date(string $value): ?CarbonImmutable
    {
        return rescue(fn () => $value !== '' ? CarbonImmutable::createFromFormat('!Y-m-d', $value) : null, null, false);
    }

    #[Computed]
    public function logs(): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with(['user', 'location'])
            ->when($this->date($this->from), fn ($q, $d) => $q->where('created_at', '>=', $d->startOfDay()))
            ->when($this->date($this->to), fn ($q, $d) => $q->where('created_at', '<=', $d->endOfDay()))
            ->when($this->userFilter !== '', fn ($q) => $q->where('user_id', $this->userFilter))
            ->when($this->outletFilter !== '', fn ($q) => $q->where('location_id', $this->outletFilter))
            ->when($this->actionFilter !== '', fn ($q) => $q->where('action', $this->actionFilter))
            ->latest('id')
            ->paginate(25);
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function users(): Collection
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return Collection<int, Location>
     */
    #[Computed]
    public function locations(): Collection
    {
        return Location::query()->orderBy('type')->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function detail(): ?AuditLog
    {
        return $this->detailId ? AuditLog::with(['user', 'location'])->find($this->detailId) : null;
    }

    public function show(int $id): void
    {
        $this->authorize('view', AuditLog::findOrFail($id));
        $this->detailId = $id;
        unset($this->detail);

        Flux::modal('audit-detail')->show();
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-page-header title="Pengaturan" description="Jejak aksi penting di sistem. Catatan tidak bisa diubah atau dihapus." />

    <x-settings-tabs />

    <section class="grid gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2 lg:grid-cols-5">
        <flux:input wire:model.live="from" type="date" label="Dari tanggal" />
        <flux:input wire:model.live="to" type="date" label="Sampai tanggal" />
        <flux:select wire:model.live="userFilter" label="Pengguna">
            <flux:select.option value="">Semua pengguna</flux:select.option>
            @foreach ($this->users as $user)
                <flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="outletFilter" label="Lokasi">
            <flux:select.option value="">Semua lokasi</flux:select.option>
            @foreach ($this->locations as $location)
                <flux:select.option :value="$location->id">{{ $location->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="actionFilter" label="Jenis aksi">
            <flux:select.option value="">Semua aksi</flux:select.option>
            @foreach (AuditAction::cases() as $action)
                <flux:select.option :value="$action->value">{{ $action->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </section>

    <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
        @if ($this->logs->isEmpty())
            <x-empty-state icon="shield-check" title="Belum ada catatan" description="Tidak ada aksi yang cocok dengan filter ini." />
        @else
            <x-data-table>
                <x-slot:head>
                    <th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Data</th><th>Lokasi</th><th>IP</th><th class="text-end!">Detail</th>
                </x-slot:head>
                @foreach ($this->logs as $log)
                    <tr wire:key="log-{{ $log->id }}">
                        <td class="whitespace-nowrap">{{ $log->created_at->translatedFormat('d M Y, H.i') }}</td>
                        <td>{{ $log->user?->name ?? 'Tidak dikenal' }}</td>
                        <td><x-status-badge :variant="$log->action->badge()">{{ $log->action->label() }}</x-status-badge></td>
                        <td class="text-sm text-muted">{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}</td>
                        <td>{{ $log->location?->name ?? '—' }}</td>
                        <td class="text-sm text-muted">{{ $log->ip ?? '—' }}</td>
                        <td class="text-end"><flux:button variant="ghost" size="sm" wire:click="show({{ $log->id }})">Detail</flux:button></td>
                    </tr>
                @endforeach
                <x-slot:footer>
                    <span>Menampilkan {{ $this->logs->count() }} dari {{ $this->logs->total() }} catatan</span>
                    {{ $this->logs->links() }}
                </x-slot:footer>
            </x-data-table>
        @endif
    </section>

    <flux:modal name="audit-detail" class="w-full max-w-2xl">
        @if ($log = $this->detail)
            @php
                $old = $log->old_values ?? [];
                $new = $log->new_values ?? [];
                $keys = array_unique([...array_keys($old), ...array_keys($new)]);
            @endphp
            <div class="flex flex-col gap-4">
                <div class="pe-8">
                    <flux:heading size="lg">{{ $log->action->label() }}</flux:heading>
                    <p class="text-sm text-muted">
                        {{ $log->user?->name ?? 'Tidak dikenal' }} · {{ $log->created_at->translatedFormat('d F Y, H.i.s') }} WIB · IP {{ $log->ip ?? '—' }}
                    </p>
                </div>
                @if ($keys === [])
                    <p class="text-sm text-muted">Tidak ada data perubahan.</p>
                @else
                    <x-data-table>
                        <x-slot:head><th>Data</th><th>Sebelum</th><th>Sesudah</th></x-slot:head>
                        @foreach ($keys as $key)
                            <tr>
                                <td class="font-medium">{{ AuditService::fieldLabel($key) }}</td>
                                <td class="text-sm text-muted">{{ AuditService::display($old[$key] ?? null) }}</td>
                                <td class="text-sm">{{ AuditService::display($new[$key] ?? null) }}</td>
                            </tr>
                        @endforeach
                    </x-data-table>
                @endif
                @if ($log->user_agent)
                    <p class="text-xs break-all text-muted">Perangkat: {{ $log->user_agent }}</p>
                @endif
            </div>
        @endif
    </flux:modal>
</div>

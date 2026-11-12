<?php

use App\Enums\RequestStatus;
use App\Models\Location;
use App\Models\StockRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Verifikasi pemilik (§11). F2: persetujuan permintaan bahan (INV-5).
 * Tab pengeluaran diisi di F5.
 */
new #[Title('Verifikasi')] class extends Component {
    use WithPagination;

    #[Url(except: 'requests')]
    public string $tab = 'requests';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $outletFilter = '';

    #[Url(except: 'pending')]
    public string $statusFilter = 'pending';

    public function mount(): void
    {
        $this->authorize('viewAny', StockRequest::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'outletFilter', 'statusFilter'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return StockRequest::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * @return Collection<int, Location>
     */
    #[Computed]
    public function outlets(): Collection
    {
        return Location::query()->outlets()->orderBy('name')->get();
    }

    #[Computed]
    public function requests(): LengthAwarePaginator
    {
        return StockRequest::query()
            ->with(['items.item', 'toLocation', 'requester'])
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('number', 'like', "%{$this->search}%")
                ->orWhereHas('requester', fn ($query) => $query->where('name', 'like', "%{$this->search}%"))))
            ->when($this->outletFilter !== '', fn ($query) => $query->where('to_location_id', $this->outletFilter))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate(15);
    }
}; ?>

@php
    $counts = $this->counts;
@endphp

<div class="flex flex-col gap-6">
    <x-page-header title="Verifikasi" description="Tinjau permintaan bahan dari outlet sebelum dikirim gudang.">
        <flux:button variant="primary" icon="plus" :href="route('owner.requests.create')" wire:navigate>Buat permintaan</flux:button>
    </x-page-header>

    <nav class="-mx-1 overflow-x-auto border-b border-border" aria-label="Jenis verifikasi">
        <ul class="flex min-w-max gap-6 px-1">
            @foreach (['requests' => 'Permintaan bahan', 'expenses' => 'Pengeluaran'] as $key => $label)
                <li>
                    <button
                        type="button"
                        wire:click="$set('tab', '{{ $key }}')"
                        @if ($tab === $key) aria-current="page" @endif
                        @class([
                            '-mb-px flex h-12 items-center gap-2 border-b-2 text-sm',
                            'border-primary font-semibold text-text' => $tab === $key,
                            'border-transparent text-muted hover:text-text' => $tab !== $key,
                        ])
                    >
                        {{ $label }}
                        @if ($key === 'requests' && ($counts['pending'] ?? 0) > 0)
                            <span class="rounded-full bg-primary px-2 text-xs font-semibold text-[#38332B]">{{ $counts['pending'] }}</span>
                        @endif
                    </button>
                </li>
            @endforeach
        </ul>
    </nav>

    @if ($tab === 'expenses')
        <div class="rounded-xl border border-border bg-surface p-4 sm:p-6">
            <x-empty-state icon="receipt-percent" title="Verifikasi pengeluaran segera hadir" description="Pengeluaran outlet beserta foto nota akan muncul di sini untuk disetujui atau ditolak." />
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-3">
            <x-metric-card label="Menunggu keputusan" :value="($counts['pending'] ?? 0).' permintaan'" icon="clipboard-document-list" hint="Perlu Anda tinjau" />
            <x-metric-card label="Disetujui, belum dikirim" :value="($counts['approved'] ?? 0).' permintaan'" icon="clipboard-document-check" :hint="($counts['shipped'] ?? 0).' sedang dikirim · '.($counts['received'] ?? 0).' diterima'" />
            <x-metric-card label="Ditolak" :value="($counts['rejected'] ?? 0).' permintaan'" icon="x-circle" hint="Alasan terlihat oleh outlet" />
        </div>

        <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <div>
                <h2 class="text-lg font-semibold text-text">Daftar permintaan outlet</h2>
                <p class="text-sm text-muted">Persetujuan pemilik dan pengiriman gudang ditampilkan terpisah.</p>
            </div>

            <div class="flex flex-col gap-3 md:flex-row">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari nomor atau pengaju..." class="md:max-w-sm" aria-label="Cari permintaan" />
                <div class="grid grid-cols-2 gap-3 md:ms-auto md:flex">
                    <flux:select wire:model.live="outletFilter" aria-label="Filter cabang">
                        <flux:select.option value="">Semua cabang</flux:select.option>
                        @foreach ($this->outlets as $outlet)
                            <flux:select.option :value="$outlet->id">{{ $outlet->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="statusFilter" aria-label="Filter status">
                        <flux:select.option value="">Semua status</flux:select.option>
                        @foreach (RequestStatus::options() as $value => $label)
                            <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            @if ($this->requests->isEmpty())
                <x-empty-state icon="clipboard-document-check" title="Tidak ada permintaan" description="Belum ada permintaan dengan filter ini. Ubah filter atau buat permintaan baru." />
            @else
                <x-data-table>
                    <x-slot:head>
                        <th>Permintaan / pengaju</th>
                        <th>Cabang</th>
                        <th>Bahan diminta</th>
                        <th>Status</th>
                        <th class="text-end!">Aksi</th>
                    </x-slot:head>

                    @foreach ($this->requests as $request)
                        <tr wire:key="req-{{ $request->id }}">
                            <td>
                                <div class="font-medium">{{ $request->number }}</div>
                                <div class="text-xs text-muted">{{ $request->requester->name }} · {{ $request->created_at->translatedFormat('d M, H.i') }}</div>
                            </td>
                            <td>{{ $request->toLocation->name }}</td>
                            <td class="text-sm">
                                {{ $request->items->map(fn ($line) => $line->item->name.' '.qty($line->qty_requested).' '.$line->item->unit)->implode(' · ') }}
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    <x-status-badge :variant="$request->status->badge()">{{ $request->status->label() }}</x-status-badge>
                                    @if ($request->status === RequestStatus::Received && $request->hasDiscrepancy())
                                        <x-status-badge variant="warning">Selisih</x-status-badge>
                                    @endif
                                </div>
                            </td>
                            <td class="text-end whitespace-nowrap">
                                @if ($request->status === RequestStatus::Pending)
                                    <flux:button variant="primary" size="sm" :href="route('owner.requests.show', $request)" wire:navigate>Tinjau</flux:button>
                                @else
                                    <flux:button variant="ghost" size="sm" :href="route('owner.requests.show', $request)" wire:navigate>Detail</flux:button>
                                @endif
                            </td>
                        </tr>
                    @endforeach

                    <x-slot:footer>
                        <span>Menampilkan {{ $this->requests->count() }} dari {{ $this->requests->total() }} permintaan</span>
                        {{ $this->requests->links() }}
                    </x-slot:footer>
                </x-data-table>
            @endif
        </section>
    @endif
</div>

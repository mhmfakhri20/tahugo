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
 * Proses permintaan & pengiriman (INV-6). Hanya permintaan yang sudah disetujui pemilik yang bisa dikirim.
 */
new #[Title('Pengiriman')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $outletFilter = '';

    #[Url(except: '')]
    public string $historyStatus = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->isWarehouseAdmin(), 403);
    }

    public function updated(): void
    {
        $this->resetPage();
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

    /**
     * Open queue: approved (ready) first, then pending (locked until the owner decides).
     *
     * @return Collection<int, StockRequest>
     */
    #[Computed]
    public function queue(): Collection
    {
        return StockRequest::query()
            ->with(['items.item', 'toLocation', 'requester', 'approver'])
            ->whereIn('status', [RequestStatus::Approved, RequestStatus::Pending])
            ->when($this->outletFilter !== '', fn ($query) => $query->where('to_location_id', $this->outletFilter))
            ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function history(): LengthAwarePaginator
    {
        $statuses = [RequestStatus::Shipped, RequestStatus::Received, RequestStatus::Rejected, RequestStatus::Cancelled];

        return StockRequest::query()
            ->with(['items.item', 'toLocation', 'shipper'])
            ->when(
                $this->historyStatus !== '',
                fn ($query) => $query->where('status', $this->historyStatus),
                fn ($query) => $query->whereIn('status', $statuses),
            )
            ->when($this->outletFilter !== '', fn ($query) => $query->where('to_location_id', $this->outletFilter))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(10);
    }
}; ?>

@php $counts = $this->counts; @endphp

<div class="flex flex-col gap-6">
    <x-page-header title="Pengiriman" description="Siapkan dan kirim bahan untuk permintaan yang sudah disetujui pemilik." />

    <div class="grid gap-4 sm:grid-cols-3">
        <x-metric-card label="Siap diproses gudang" :value="($counts['approved'] ?? 0).' permintaan'" icon="clipboard-document-check" hint="Sudah disetujui pemilik" />
        <x-metric-card label="Menunggu persetujuan" :value="($counts['pending'] ?? 0).' permintaan'" icon="lock-closed" hint="Belum bisa dikirim" />
        <x-metric-card label="Dalam perjalanan" :value="($counts['shipped'] ?? 0).' permintaan'" icon="truck" hint="Menunggu konfirmasi outlet" />
    </div>

    <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-text">Antrean pemenuhan bahan</h2>
                <p class="text-sm text-muted">Persetujuan pemilik tidak dapat digantikan admin gudang.</p>
            </div>
            <flux:select wire:model.live="outletFilter" aria-label="Filter cabang" class="sm:w-56!">
                <flux:select.option value="">Semua cabang</flux:select.option>
                @foreach ($this->outlets as $outlet)
                    <flux:select.option :value="$outlet->id">{{ $outlet->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($this->queue->isEmpty())
            <x-empty-state icon="truck" title="Tidak ada antrean" description="Permintaan yang disetujui pemilik akan muncul di sini." />
        @else
            <x-data-table>
                <x-slot:head>
                    <th>Permintaan / cabang</th>
                    <th>Bahan disetujui</th>
                    <th>Persetujuan pemilik</th>
                    <th class="text-end!">Aksi</th>
                </x-slot:head>

                @foreach ($this->queue as $request)
                    @php $ready = $request->status === RequestStatus::Approved; @endphp
                    <tr wire:key="queue-{{ $request->id }}">
                        <td>
                            <div class="font-medium">{{ $request->number }}</div>
                            <div class="text-xs text-muted">{{ $request->toLocation->name }} · {{ $request->requester->name }}</div>
                        </td>
                        <td class="text-sm">
                            {{ $request->items->map(fn ($line) => $line->item->name.' '.qty($line->qty_approved ?? $line->qty_requested).' '.$line->item->unit)->implode(' · ') }}
                        </td>
                        <td>
                            @if ($ready)
                                <x-status-badge variant="success">Disetujui</x-status-badge>
                                <div class="mt-1 text-xs text-muted">{{ $request->approver?->name }} · {{ $request->approved_at?->translatedFormat('d M, H.i') }}</div>
                            @else
                                <x-status-badge variant="warning">Menunggu pemilik</x-status-badge>
                            @endif
                        </td>
                        <td class="text-end whitespace-nowrap">
                            @if ($ready)
                                <flux:button variant="primary" size="sm" icon="truck" :href="route('warehouse.requests.show', $request)" wire:navigate>Proses</flux:button>
                            @else
                                <flux:button size="sm" icon="lock-closed" disabled>Terkunci</flux:button>
                            @endif
                        </td>
                    </tr>
                @endforeach

                <x-slot:footer>
                    <span>{{ $this->queue->count() }} permintaan terbuka · hanya yang disetujui dapat dikirim</span>
                </x-slot:footer>
            </x-data-table>
        @endif
    </section>

    <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-text">Riwayat proses</h2>
                <p class="text-sm text-muted">Pengiriman, penerimaan, dan penolakan tetap tercatat.</p>
            </div>
            <flux:select wire:model.live="historyStatus" aria-label="Filter status riwayat" class="sm:w-56!">
                <flux:select.option value="">Semua status</flux:select.option>
                @foreach ([RequestStatus::Shipped, RequestStatus::Received, RequestStatus::Rejected, RequestStatus::Cancelled] as $status)
                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($this->history->isEmpty())
            <x-empty-state icon="clock" title="Belum ada riwayat" description="Permintaan yang sudah dikirim atau ditolak akan tampil di sini." />
        @else
            <x-data-table>
                <x-slot:head>
                    <th>Permintaan</th>
                    <th>Cabang</th>
                    <th>Status</th>
                    <th>Catatan</th>
                    <th class="text-end!">Aksi</th>
                </x-slot:head>

                @foreach ($this->history as $request)
                    <tr wire:key="hist-{{ $request->id }}">
                        <td>
                            <div class="font-medium">{{ $request->number }}</div>
                            @if ($request->shipped_at)
                                <div class="text-xs text-muted">Dikirim {{ $request->shipper?->name }} · {{ $request->shipped_at->translatedFormat('d M, H.i') }}</div>
                            @endif
                        </td>
                        <td>{{ $request->toLocation->name }}</td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                <x-status-badge :variant="$request->status->badge()">{{ $request->status->label() }}</x-status-badge>
                                @if ($request->status === RequestStatus::Received && $request->hasDiscrepancy())
                                    <x-status-badge variant="warning">Selisih kirim-terima</x-status-badge>
                                @endif
                            </div>
                        </td>
                        <td class="text-sm text-muted">{{ $request->reject_reason ?? $request->receive_note ?? $request->ship_note ?? '—' }}</td>
                        <td class="text-end">
                            <flux:button variant="ghost" size="sm" :href="route('warehouse.requests.show', $request)" wire:navigate>Detail</flux:button>
                        </td>
                    </tr>
                @endforeach

                <x-slot:footer>
                    <span>Menampilkan {{ $this->history->count() }} dari {{ $this->history->total() }} permintaan</span>
                    {{ $this->history->links() }}
                </x-slot:footer>
            </x-data-table>
        @endif
    </section>
</div>

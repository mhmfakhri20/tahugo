<?php

use App\Livewire\Concerns\WithReportFilters;
use App\Models\Location;
use App\Services\ReportService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Laporan persediaan (REP-5) untuk pemilik & admin gudang: saldo per lokasi, mutasi per tipe, pembelian,
 * pengiriman (selisih kirim-terima), waste. Bahan bisa dibuka di kartu stok (INV-10).
 */
new #[Title('Laporan persediaan')] class extends Component {
    use WithReportFilters;

    public function mount(): void
    {
        Gate::authorize('view-inventory-reports');
    }

    /**
     * @return Collection<int, Location>
     */
    #[Computed]
    public function locationOptions(): Collection
    {
        return Location::query()->active()->orderBy('type')->orderBy('name')->get();
    }

    #[Computed]
    public function report(): array
    {
        return app(ReportService::class)->inventory($this->period, $this->outletId());
    }

    public function cardRoute(int $locationId, int $itemId): string
    {
        return auth()->user()->isOwner()
            ? route('owner.stock.card', [$locationId, $itemId])
            : route('warehouse.stock.card', [$locationId, $itemId]);
    }
}; ?>

@php $r = $this->report; @endphp

<div class="flex flex-col gap-6">
    @if (auth()->user()->isOwner())
        <x-page-header title="Laporan" :description="'Persediaan · '.$this->period->label()" />
        <x-report-tabs current="owner.reports.inventory" :query="['from' => $from, 'to' => $to, 'outlet' => $outlet]" />
    @else
        <x-page-header title="Laporan persediaan" :description="$this->period->label()" />
    @endif
    <x-report-filters :options="$this->locationOptions" label="Lokasi" all-label="Semua lokasi" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-metric-card label="Bahan perlu perhatian" :value="$r['balances']->filter(fn ($b) => $b['level']->needsAttention())->count().' bahan'" icon="exclamation-triangle" hint="Di bawah minimum atau habis" />
        <x-metric-card label="Pembelian" :value="rupiah($r['purchase_total'])" icon="shopping-cart" :hint="$r['purchases']->count().' pembelian dalam periode'" />
        <x-metric-card label="Pengiriman" :value="$r['shipments']->count().' kiriman'" icon="truck" :hint="$r['shipment_discrepancies'].' ada selisih kirim-terima'" />
        <x-metric-card label="Bahan rusak/terbuang" :value="$r['waste']->count().' catatan'" icon="trash" hint="Waste dalam periode" />
    </div>

    {{-- Saldo per lokasi --}}
    <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
        <div>
            <h2 class="text-lg font-semibold">Saldo stok per lokasi</h2>
            <p class="text-sm text-muted">Posisi saat ini. Ketuk bahan untuk melihat kartu stok.</p>
        </div>
        @if ($r['balances']->isEmpty())
            <x-empty-state icon="archive-box" title="Belum ada stok" />
        @else
            <x-data-table>
                <x-slot:head><th>Lokasi</th><th>Bahan</th><th class="text-end!">Stok</th><th class="text-end!">Minimum</th><th>Status</th></x-slot:head>
                @foreach ($r['balances'] as $row)
                    <tr wire:key="bal-{{ $row['location']->id }}-{{ $row['item']->id }}">
                        <td>{{ $row['location']->name }}</td>
                        <td><a href="{{ $this->cardRoute($row['location']->id, $row['item']->id) }}" wire:navigate class="font-medium underline-offset-2 hover:underline">{{ $row['item']->name }}</a></td>
                        <td class="text-end">{{ qty($row['qty']) }} {{ $row['item']->unit }}</td>
                        <td class="text-end text-muted">{{ $row['min'] !== null ? qty($row['min']).' '.$row['item']->unit : '—' }}</td>
                        <td><x-status-badge :variant="$row['level']->badge()">{{ $row['level']->label() }}</x-status-badge></td>
                    </tr>
                @endforeach
            </x-data-table>
        @endif
    </section>

    {{-- Mutasi per tipe --}}
    <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
        <h2 class="text-lg font-semibold">Mutasi periode per tipe</h2>
        @if ($r['movements']->isEmpty())
            <x-empty-state icon="arrows-right-left" title="Tidak ada mutasi" description="Tidak ada perubahan stok pada periode ini." />
        @else
            <x-data-table>
                <x-slot:head><th>Lokasi</th><th>Tipe mutasi</th><th class="text-end!">Jumlah catatan</th><th class="text-end!">Total qty (semua bahan)</th></x-slot:head>
                @foreach ($r['movements'] as $row)
                    <tr>
                        <td>{{ $row['location']->name }}</td>
                        <td class="font-medium">{{ $row['type']->label() }}</td>
                        <td class="text-end">{{ $row['count'] }}</td>
                        <td @class(['text-end', 'text-success' => (float) $row['qty'] > 0, 'text-danger' => (float) $row['qty'] < 0])>{{ (float) $row['qty'] > 0 ? '+' : '' }}{{ qty($row['qty']) }}</td>
                    </tr>
                @endforeach
            </x-data-table>
            <p class="text-xs text-muted">Satuan bahan berbeda-beda; buka kartu stok untuk rincian per bahan.</p>
        @endif
    </section>

    <div class="grid items-start gap-6 lg:grid-cols-2">
        <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <h2 class="text-lg font-semibold">Pembelian</h2>
            @if ($r['purchases']->isEmpty())
                <x-empty-state icon="shopping-cart" title="Belum ada pembelian" />
            @else
                <x-data-table>
                    <x-slot:head><th>No. / tanggal</th><th>Supplier</th><th>Bahan</th><th class="text-end!">Total</th></x-slot:head>
                    @foreach ($r['purchases'] as $purchase)
                        <tr>
                            <td><div class="font-medium">{{ $purchase->number() }}</div><div class="text-xs text-muted">{{ $purchase->purchase_date->translatedFormat('d M Y') }}</div></td>
                            <td>{{ $purchase->supplier }}</td>
                            <td class="text-sm">{{ $purchase->items->map(fn ($l) => $l->item->name.' '.qty($l->qty).' '.$l->item->unit)->implode(', ') }}</td>
                            <td class="text-end font-medium">{{ rupiah($purchase->total) }}</td>
                        </tr>
                    @endforeach
                    <x-slot:footer><span>Total pembelian: {{ rupiah($r['purchase_total']) }}</span></x-slot:footer>
                </x-data-table>
            @endif
        </section>

        <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <h2 class="text-lg font-semibold">Pengiriman gudang → outlet</h2>
            @if ($r['shipments']->isEmpty())
                <x-empty-state icon="truck" title="Belum ada pengiriman" />
            @else
                <x-data-table>
                    <x-slot:head><th>No. / dikirim</th><th>Tujuan</th><th>Bahan (kirim → terima)</th><th>Status</th></x-slot:head>
                    @foreach ($r['shipments'] as $request)
                        <tr>
                            <td><div class="font-medium">{{ $request->number }}</div><div class="text-xs text-muted">{{ $request->shipped_at->translatedFormat('d M, H.i') }}</div></td>
                            <td>{{ $request->toLocation->name }}</td>
                            <td class="text-sm">
                                @foreach ($request->items as $line)
                                    <div>
                                        {{ $line->item->name }}: {{ qty($line->qty_shipped) }}
                                        @if ($line->qty_received !== null) → {{ qty($line->qty_received) }} @endif
                                        {{ $line->item->unit }}
                                        @if ($line->hasReceiveDiscrepancy())
                                            <span class="text-danger">({{ (float) $line->receiveDifference() > 0 ? '+' : '' }}{{ qty($line->receiveDifference()) }})</span>
                                        @endif
                                    </div>
                                @endforeach
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    <x-status-badge :variant="$request->status->badge()">{{ $request->status->label() }}</x-status-badge>
                                    @if ($request->hasDiscrepancy())<x-status-badge variant="warning">Selisih</x-status-badge>@endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-data-table>
            @endif
        </section>
    </div>

    <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
        <h2 class="text-lg font-semibold">Bahan rusak / terbuang</h2>
        @if ($r['waste']->isEmpty())
            <x-empty-state icon="trash" title="Tidak ada waste" description="Tidak ada bahan rusak atau terbuang pada periode ini." />
        @else
            <x-data-table>
                <x-slot:head><th>Lokasi</th><th>Bahan</th><th class="text-end!">Jumlah</th><th class="text-end!">Catatan</th></x-slot:head>
                @foreach ($r['waste'] as $row)
                    <tr>
                        <td>{{ $row->location->name }}</td>
                        <td><a href="{{ $this->cardRoute($row->location_id, $row->item_id) }}" wire:navigate class="font-medium hover:underline">{{ $row->item->name }}</a></td>
                        <td class="text-end text-danger">{{ qty(abs((float) $row->qty)) }} {{ $row->item->unit }}</td>
                        <td class="text-end">{{ $row->n }}</td>
                    </tr>
                @endforeach
            </x-data-table>
        @endif
    </section>
</div>

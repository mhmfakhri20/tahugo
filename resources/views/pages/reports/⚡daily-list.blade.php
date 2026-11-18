<?php

use App\Livewire\Concerns\WithReportFilters;
use App\Models\Shift;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Daftar Laporan Harian Outlet (REP-2): satu laporan per shift.
 */
new #[Title('Laporan harian outlet')] class extends Component {
    use WithPagination, WithReportFilters;

    public function mount(): void
    {
        Gate::authorize('view-financial-reports');
    }

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function shifts(): LengthAwarePaginator
    {
        return Shift::query()
            ->with(['location', 'opener', 'closer'])
            ->withCount(['sales as sales_count' => fn ($q) => $q->where('status', 'completed')])
            ->withSum(['sales as revenue' => fn ($q) => $q->where('status', 'completed')], 'total')
            ->whereBetween('opened_at', $this->period->bounds())
            ->when($this->outletId(), fn ($q, $id) => $q->where('location_id', $id))
            ->orderByDesc('opened_at')
            ->paginate(20);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-page-header title="Laporan" :description="'Harian outlet · '.$this->period->label()" />
    <x-report-tabs current="owner.reports.daily" :query="['from' => $from, 'to' => $to, 'outlet' => $outlet]" />
    <x-report-filters :options="$this->outletOptions" />

    <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
        <div>
            <h2 class="text-lg font-semibold">Laporan per shift</h2>
            <p class="text-sm text-muted">Dibuat otomatis saat shift ditutup, menggantikan laporan harian lewat WhatsApp.</p>
        </div>

        @if ($this->shifts->isEmpty())
            <x-empty-state icon="document-text" title="Belum ada shift" description="Tidak ada shift pada periode dan cabang ini." />
        @else
            <x-data-table>
                <x-slot:head>
                    <th>Tanggal / cabang</th><th>Buka – tutup</th><th class="text-end!">Transaksi</th><th class="text-end!">Omzet</th><th>Selisih kas</th><th class="text-end!">Aksi</th>
                </x-slot:head>
                @foreach ($this->shifts as $shift)
                    <tr wire:key="shift-{{ $shift->id }}">
                        <td><div class="font-medium">{{ $shift->opened_at->translatedFormat('D, d M Y') }}</div><div class="text-xs text-muted">{{ $shift->location->name }}</div></td>
                        <td class="whitespace-nowrap">{{ $shift->opened_at->format('H.i') }} – {{ $shift->closed_at?->format('H.i') ?? 'masih buka' }}</td>
                        <td class="text-end">{{ $shift->sales_count }}</td>
                        <td class="text-end font-medium">{{ rupiah($shift->revenue) }}</td>
                        <td>
                            @if ($shift->variance === null)
                                <x-status-badge variant="success">Masih buka</x-status-badge>
                            @elseif ($shift->variance === 0)
                                <x-status-badge variant="success">Pas</x-status-badge>
                            @elseif ($shift->variance < 0)
                                <x-status-badge variant="danger">Kurang {{ rupiah(abs($shift->variance)) }}</x-status-badge>
                            @else
                                <x-status-badge variant="warning">Lebih {{ rupiah($shift->variance) }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-end"><flux:button variant="ghost" size="sm" :href="route('reports.daily', $shift)" wire:navigate>Lihat laporan</flux:button></td>
                    </tr>
                @endforeach
                <x-slot:footer>
                    <span>Menampilkan {{ $this->shifts->count() }} dari {{ $this->shifts->total() }} shift</span>
                    {{ $this->shifts->links() }}
                </x-slot:footer>
            </x-data-table>
        @endif
    </section>
</div>

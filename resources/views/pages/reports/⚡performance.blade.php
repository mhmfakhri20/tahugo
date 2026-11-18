<?php

use App\Livewire\Concerns\WithReportFilters;
use App\Services\ReportService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Kinerja outlet (REP-6): omzet antar outlet, pertumbuhan vs periode sebelumnya, progres target bulanan.
 */
new #[Title('Kinerja outlet')] class extends Component {
    use WithReportFilters;

    public function mount(): void
    {
        Gate::authorize('view-financial-reports');
    }

    #[Computed]
    public function rows(): \Illuminate\Support\Collection
    {
        return app(ReportService::class)->outletPerformance($this->period);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-page-header title="Laporan" :description="'Kinerja outlet · '.$this->period->label().' dibanding '.$this->period->previous()->label()">
        <flux:button variant="primary" icon="arrow-down-tray" class="no-print" :href="route('owner.reports.performance.export', array_filter(compact('from', 'to')))">Ekspor Excel</flux:button>
    </x-page-header>
    <x-report-tabs current="owner.reports.performance" :query="['from' => $from, 'to' => $to]" />
    <x-report-filters />

    @if ($this->rows->isEmpty())
        <div class="rounded-xl border border-border bg-surface p-6">
            <x-empty-state icon="building-storefront" title="Belum ada outlet aktif" description="Tambahkan outlet di Pengaturan." />
        </div>
    @else
        <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <div>
                <h2 class="text-lg font-semibold">Perbandingan omzet antar outlet</h2>
                <p class="text-sm text-muted">Periode ini dibanding periode sebelumnya (sama panjang).</p>
            </div>
            <x-chart :config="[
                'type' => 'bar',
                'labels' => $this->rows->map(fn ($r) => $r['outlet']->name)->all(),
                'datasets' => [
                    ['label' => 'Periode sebelumnya', 'data' => $this->rows->pluck('previous')->all(), 'color' => 'border'],
                    ['label' => 'Periode ini', 'data' => $this->rows->pluck('revenue')->all(), 'color' => 'primary'],
                ],
            ]" />

            <x-data-table>
                <x-slot:head>
                    <th>Outlet</th><th class="text-end!">Transaksi</th><th class="text-end!">Omzet</th><th class="text-end!">Kontribusi</th>
                    <th class="text-end!">Periode sebelumnya</th><th>Pertumbuhan</th>
                </x-slot:head>
                @foreach ($this->rows as $row)
                    <tr>
                        <td class="font-medium">{{ $row['outlet']->name }}</td>
                        <td class="text-end">{{ $row['count'] }}</td>
                        <td class="text-end font-medium">{{ rupiah($row['revenue']) }}</td>
                        <td class="text-end text-muted">{{ number_format($row['share'], 1, ',', '.') }}%</td>
                        <td class="text-end">{{ rupiah($row['previous']) }}</td>
                        <td>
                            @if ($row['growth'] === null)
                                <span class="text-sm text-muted">Belum ada pembanding</span>
                            @else
                                <x-status-badge :variant="$row['growth'] >= 0 ? 'success' : 'danger'">
                                    {{ $row['growth'] >= 0 ? '↑' : '↓' }} {{ number_format(abs($row['growth']), 1, ',', '.') }}%
                                </x-status-badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </section>

        <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <div>
                <h2 class="text-lg font-semibold">Target omzet bulan {{ $this->period->to->translatedFormat('F Y') }}</h2>
                <p class="text-sm text-muted">Omzet 1 {{ $this->period->to->translatedFormat('F') }} s.d. {{ $this->period->to->translatedFormat('d F') }}. Target diatur di Pengaturan › Outlet.</p>
            </div>
            <ul class="grid gap-4 md:grid-cols-2">
                @foreach ($this->rows as $row)
                    <li class="flex flex-col gap-2 rounded-xl border border-border p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="font-semibold">{{ $row['outlet']->name }}</div>
                            @if ($row['target'] === null)
                                <x-status-badge variant="muted">Target belum diatur</x-status-badge>
                            @else
                                <x-status-badge :variant="$row['progress'] >= 100 ? 'success' : 'soft'">{{ number_format($row['progress'], 1, ',', '.') }}% tercapai</x-status-badge>
                            @endif
                        </div>
                        <div class="text-sm">
                            {{ rupiah($row['month_revenue']) }}
                            @if ($row['target'] !== null)<span class="text-muted">dari target {{ rupiah($row['target']) }}</span>@endif
                        </div>
                        @if ($row['target'] !== null)
                            <div class="h-2 overflow-hidden rounded-full bg-border" role="progressbar" aria-valuenow="{{ min(100, $row['progress']) }}" aria-valuemin="0" aria-valuemax="100">
                                <div @class(['h-full rounded-full', 'bg-success' => $row['progress'] >= 100, 'bg-primary' => $row['progress'] < 100]) style="width: {{ min(100, $row['progress']) }}%"></div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>

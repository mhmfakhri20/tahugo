<?php

use App\Livewire\Concerns\WithReportFilters;
use App\Services\ReportService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Laporan penjualan (REP-3): periode & cabang; per hari, produk, kanal, metode bayar, cabang. Void tidak dihitung.
 */
new #[Title('Laporan penjualan')] class extends Component {
    use WithReportFilters;

    public function mount(): void
    {
        Gate::authorize('view-financial-reports');
    }

    #[Computed]
    public function report(): array
    {
        $reports = app(ReportService::class);
        $period = $this->period;
        $outlet = $this->outletId();
        $totals = $reports->salesTotals($period, $outlet);
        $previous = $reports->salesTotals($period->previous(), $outlet);

        return [
            'totals' => $totals,
            'change' => $reports->change($totals['revenue'], $previous['revenue']),
            'days' => $reports->salesByDay($period, $outlet),
            'previous_days' => $reports->salesByDay($period->previous(), $outlet)->values(),
            'channels' => $reports->salesByChannel($period, $outlet),
            'methods' => $reports->salesByPaymentMethod($period, $outlet),
            'products' => $reports->salesByProduct($period, $outlet),
            'outlets' => $reports->salesByOutlet($period, $outlet),
        ];
    }
}; ?>

@php
    $r = $this->report;
    $days = $r['days']->values();
@endphp

<div class="flex flex-col gap-6">
    <x-page-header title="Laporan" :description="'Penjualan · '.$this->period->label().' · tanpa transaksi batal'">
        <flux:button variant="primary" icon="arrow-down-tray" class="no-print" :href="route('owner.reports.sales.export', array_filter(compact('from', 'to', 'outlet')))">Ekspor Excel</flux:button>
    </x-page-header>
    <x-report-tabs current="owner.reports.sales" :query="['from' => $from, 'to' => $to, 'outlet' => $outlet]" />
    <x-report-filters :options="$this->outletOptions" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-metric-card label="Total penjualan" :value="rupiah($r['totals']['revenue'])" icon="wallet"
            :trend="$r['change'] !== null ? number_format(abs($r['change']), 1, ',', '.').'% dari periode sebelumnya' : null"
            :trend-direction="($r['change'] ?? 0) < 0 ? 'down' : 'up'"
            :hint="$r['change'] === null ? 'Belum ada pembanding' : null" />
        <x-metric-card label="Transaksi" :value="number_format($r['totals']['count'], 0, ',', '.')" icon="receipt-percent" :hint="'Rata-rata '.rupiah($r['totals']['average']).' / transaksi'" />
        <x-metric-card label="Produk terjual" :value="number_format($r['totals']['pcs'], 0, ',', '.').' pcs'" icon="shopping-bag" />
        <x-metric-card label="Hari dalam periode" :value="$this->period->days().' hari'" icon="calendar-days" :hint="'Rata-rata '.rupiah(intdiv($r['totals']['revenue'], max(1, $this->period->days()))).' / hari'" />
    </div>

    @if ($r['totals']['count'] === 0)
        <div class="rounded-xl border border-border bg-surface p-6">
            <x-empty-state icon="chart-bar" title="Belum ada penjualan" description="Tidak ada transaksi pada periode dan cabang ini. Ubah periode atau pilih cabang lain." />
        </div>
    @else
        <div class="grid items-start gap-6 lg:grid-cols-3">
            <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6 lg:col-span-2">
                <div>
                    <h2 class="text-lg font-semibold">Tren penjualan harian</h2>
                    <p class="text-sm text-muted">Periode ini dibanding periode sebelumnya (sama panjang).</p>
                </div>
                <x-chart :config="[
                    'type' => 'bar',
                    'labels' => $days->map(fn ($d) => \Carbon\CarbonImmutable::parse($d['date'])->translatedFormat('d M'))->all(),
                    'datasets' => [
                        ['label' => 'Periode sebelumnya', 'data' => $days->keys()->map(fn ($i) => $r['previous_days'][$i]['revenue'] ?? 0)->all(), 'color' => 'border'],
                        ['label' => 'Periode ini', 'data' => $days->pluck('revenue')->all(), 'color' => 'primary'],
                    ],
                ]" />
            </section>

            <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
                <div>
                    <h2 class="text-lg font-semibold">Komposisi kanal</h2>
                    <p class="text-sm text-muted">Bagian omzet per kanal penjualan.</p>
                </div>
                <x-chart height="h-44" :config="[
                    'type' => 'doughnut',
                    'labels' => $r['channels']->map(fn ($c) => $c['channel']->label())->all(),
                    'datasets' => [['label' => 'Omzet', 'data' => $r['channels']->pluck('revenue')->all(), 'color' => ['primary', 'brown', 'success', 'warning']]],
                    'legend' => false,
                ]" />
                <ul class="flex flex-col gap-2 text-sm">
                    @foreach ($r['channels'] as $index => $row)
                        <li class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <span @class(['size-2.5 rounded-full', ['bg-primary', 'bg-brown', 'bg-success', 'bg-warning'][$index]])></span>
                                {{ $row['channel']->label() }} <span class="text-muted">· {{ $row['count'] }} trx</span>
                            </span>
                            <span><span class="font-medium">{{ rupiah($row['revenue']) }}</span> <span class="text-muted">{{ number_format($row['share'], 1, ',', '.') }}%</span></span>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>

        <div class="grid items-start gap-6 lg:grid-cols-2">
            <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
                <h2 class="text-lg font-semibold">Per produk</h2>
                <x-data-table>
                    <x-slot:head><th>Produk</th><th class="text-end!">Terjual</th><th class="text-end!">Omzet</th><th class="text-end!">Bagian</th></x-slot:head>
                    @foreach ($r['products'] as $row)
                        <tr>
                            <td class="font-medium">{{ $row['product'] }}</td>
                            <td class="text-end">{{ number_format($row['qty'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ rupiah($row['revenue']) }}</td>
                            <td class="text-end text-muted">{{ number_format($row['share'], 1, ',', '.') }}%</td>
                        </tr>
                    @endforeach
                </x-data-table>
            </section>

            <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
                <h2 class="text-lg font-semibold">Per metode bayar (offline)</h2>
                <x-data-table>
                    <x-slot:head><th>Metode</th><th class="text-end!">Transaksi</th><th class="text-end!">Omzet</th><th class="text-end!">Bagian</th></x-slot:head>
                    @foreach ($r['methods'] as $row)
                        <tr>
                            <td class="font-medium">{{ $row['method']->label() }}</td>
                            <td class="text-end">{{ $row['count'] }}</td>
                            <td class="text-end">{{ rupiah($row['revenue']) }}</td>
                            <td class="text-end text-muted">{{ number_format($row['share'], 1, ',', '.') }}%</td>
                        </tr>
                    @endforeach
                </x-data-table>
                <p class="text-xs text-muted">Kanal GoFood/GrabFood/ShopeeFood tidak memakai metode bayar dan dihitung bruto.</p>
            </section>
        </div>

        <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <h2 class="text-lg font-semibold">Per cabang</h2>
            <x-data-table>
                <x-slot:head><th>Cabang</th><th class="text-end!">Transaksi</th><th class="text-end!">Produk terjual</th><th class="text-end!">Omzet</th><th class="text-end!">Kontribusi</th></x-slot:head>
                @foreach ($r['outlets'] as $row)
                    <tr>
                        <td class="font-medium">{{ $row['outlet']->name }}</td>
                        <td class="text-end">{{ $row['count'] }}</td>
                        <td class="text-end">{{ number_format($row['pcs'], 0, ',', '.') }} pcs</td>
                        <td class="text-end">{{ rupiah($row['revenue']) }}</td>
                        <td class="text-end text-muted">{{ number_format($row['share'], 1, ',', '.') }}%</td>
                    </tr>
                @endforeach
                <x-slot:footer>
                    <span>Total: {{ $r['totals']['count'] }} transaksi · {{ number_format($r['totals']['pcs'], 0, ',', '.') }} pcs · {{ rupiah($r['totals']['revenue']) }}</span>
                </x-slot:footer>
            </x-data-table>
        </section>

        <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <h2 class="text-lg font-semibold">Per hari</h2>
            <x-data-table>
                <x-slot:head><th>Tanggal</th><th class="text-end!">Transaksi</th><th class="text-end!">Omzet</th></x-slot:head>
                @foreach ($r['days']->reverse() as $row)
                    <tr>
                        <td>{{ \Carbon\CarbonImmutable::parse($row['date'])->translatedFormat('l, d M Y') }}</td>
                        <td class="text-end">{{ $row['count'] }}</td>
                        <td class="text-end font-medium">{{ rupiah($row['revenue']) }}</td>
                    </tr>
                @endforeach
            </x-data-table>
        </section>
    @endif
</div>

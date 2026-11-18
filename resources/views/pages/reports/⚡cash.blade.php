<?php

use App\Livewire\Concerns\WithReportFilters;
use App\Services\ReportService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Laporan arus kas & laba-rugi sederhana (REP-4). Pembelian bahan dihitung sekali, sebagai pengeluaran usaha (CASH-7).
 */
new #[Title('Laporan kas & laba-rugi')] class extends Component {
    use WithReportFilters;

    public function mount(): void
    {
        Gate::authorize('view-financial-reports');
    }

    #[Computed]
    public function report(): array
    {
        $reports = app(ReportService::class);

        return [
            'pl' => $reports->profitLoss($this->period, $this->outletId()),
            'categories' => $reports->expensesByCategory($this->period, $this->outletId()),
            'gross' => $reports->grossProfitByOutlet($this->period, $this->outletId()),
        ];
    }
}; ?>

@php
    $r = $this->report;
    $pl = $r['pl'];
    $kinds = ['outlet' => 'Pengeluaran outlet', 'business' => 'Pengeluaran usaha', 'purchase' => 'Pembelian bahan'];
@endphp

<div class="flex flex-col gap-6">
    <x-page-header title="Laporan" :description="'Kas & laba-rugi · '.$this->period->label()" />
    <x-report-tabs current="owner.reports.cash" :query="['from' => $from, 'to' => $to, 'outlet' => $outlet]" />
    <x-report-filters :options="$this->outletOptions" />

    <div class="grid gap-4 sm:grid-cols-3">
        <x-metric-card label="Pemasukan" :value="rupiah($pl['income'])" icon="arrow-down-tray" :hint="'Omzet '.rupiah($pl['revenue']).' + lain-lain '.rupiah($pl['other_income'])" />
        <x-metric-card label="Pengeluaran" :value="rupiah($pl['expenses'])" icon="arrow-up-tray" :hint="'Termasuk pembelian bahan '.rupiah($pl['purchases'])" />
        <div class="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
            <div class="text-sm text-muted">Laba-rugi usaha</div>
            <div @class(['text-[26px] leading-tight font-bold lg:text-3xl', 'text-success' => $pl['net'] >= 0, 'text-danger' => $pl['net'] < 0])>
                {{ $pl['net'] < 0 ? '−' : '' }}{{ rupiah(abs($pl['net'])) }}
            </div>
            <x-status-badge :variant="$pl['net'] >= 0 ? 'success' : 'danger'">{{ $pl['net'] >= 0 ? 'Untung' : 'Rugi' }}</x-status-badge>
        </div>
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-2">
        <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <h2 class="text-lg font-semibold">Laba-rugi sederhana</h2>
            <dl class="flex flex-col divide-y divide-border text-sm">
                <div class="flex justify-between py-2"><dt>Omzet penjualan</dt><dd class="font-medium">{{ rupiah($pl['revenue']) }}</dd></div>
                <div class="flex justify-between py-2"><dt>Pemasukan lain</dt><dd class="font-medium">{{ rupiah($pl['other_income']) }}</dd></div>
                <div class="flex justify-between py-2 font-semibold"><dt>Total pemasukan</dt><dd>{{ rupiah($pl['income']) }}</dd></div>
                <div class="flex justify-between py-2"><dt>− Pengeluaran outlet (disetujui)</dt><dd class="font-medium">{{ rupiah($pl['outlet_expenses']) }}</dd></div>
                <div class="flex justify-between py-2"><dt>− Pembelian bahan (gudang)</dt><dd class="font-medium">{{ rupiah($pl['purchases']) }}</dd></div>
                <div class="flex justify-between py-2"><dt>− Pengeluaran usaha lain</dt><dd class="font-medium">{{ rupiah($pl['business_other']) }}</dd></div>
                <div class="flex justify-between py-2 font-semibold"><dt>Total pengeluaran</dt><dd>{{ rupiah($pl['expenses']) }}</dd></div>
                <div @class(['flex justify-between py-2 text-base font-bold', 'text-success' => $pl['net'] >= 0, 'text-danger' => $pl['net'] < 0])>
                    <dt>Laba-rugi usaha</dt><dd>{{ $pl['net'] < 0 ? '−' : '' }}{{ rupiah(abs($pl['net'])) }}</dd>
                </div>
            </dl>
            <p class="text-xs text-muted">
                Pembelian bahan dihitung sekali sebagai pengeluaran usaha. Pengeluaran yang masih menunggu atau ditolak tidak dihitung.
                @if ($outlet !== '') Pembelian gudang tidak masuk saat memilih satu cabang. @endif
            </p>
        </section>

        <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <h2 class="text-lg font-semibold">Pengeluaran per kategori</h2>
            @if ($r['categories']->isEmpty())
                <x-empty-state icon="receipt-percent" title="Belum ada pengeluaran" description="Tidak ada pengeluaran disetujui pada periode ini." />
            @else
                <x-data-table>
                    <x-slot:head><th>Kategori</th><th>Jenis</th><th class="text-end!">Nominal</th><th class="text-end!">Bagian</th></x-slot:head>
                    @foreach ($r['categories'] as $row)
                        <tr>
                            <td class="font-medium">{{ $row['label'] }}</td>
                            <td><x-status-badge :variant="$row['kind'] === 'purchase' ? 'info' : ($row['kind'] === 'outlet' ? 'soft' : 'muted')">{{ $kinds[$row['kind']] }}</x-status-badge></td>
                            <td class="text-end">{{ rupiah($row['amount']) }}</td>
                            <td class="text-end text-muted">{{ number_format($row['share'], 1, ',', '.') }}%</td>
                        </tr>
                    @endforeach
                </x-data-table>
            @endif
        </section>
    </div>

    <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
        <div>
            <h2 class="text-lg font-semibold">Laba kotor per outlet</h2>
            <p class="text-sm text-muted">Omzet − HPP (bahan terpakai × harga beli terakhir) − pengeluaran outlet disetujui.</p>
        </div>
        @if ($r['gross']->isEmpty())
            <x-empty-state icon="building-storefront" title="Belum ada outlet" />
        @else
            <x-data-table>
                <x-slot:head><th>Outlet</th><th class="text-end!">Omzet</th><th class="text-end!">HPP</th><th class="text-end!">Pengeluaran</th><th class="text-end!">Laba kotor</th></x-slot:head>
                @foreach ($r['gross'] as $row)
                    <tr>
                        <td class="font-medium">{{ $row['outlet']->name }}</td>
                        <td class="text-end">{{ rupiah($row['revenue']) }}</td>
                        <td class="text-end">{{ rupiah($row['cogs']) }}</td>
                        <td class="text-end">{{ rupiah($row['expenses']) }}</td>
                        <td @class(['text-end font-semibold', 'text-success' => $row['gross'] >= 0, 'text-danger' => $row['gross'] < 0])>
                            {{ $row['gross'] < 0 ? '−' : '' }}{{ rupiah(abs($row['gross'])) }}
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        @endif
    </section>
</div>

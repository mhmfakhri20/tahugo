<?php

use App\Enums\RequestStatus;
use App\Models\Location;
use App\Models\StockRequest;
use App\Services\AttendanceService;
use App\Services\CashService;
use App\Services\ReportService;
use App\Services\StockOverviewService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Dashboard pemilik (REP-1): stok menipis (INV-2) paling atas, metrik hari ini vs kemarin, tren 7 hari,
 * komposisi kanal, pengeluaran & permintaan menunggu, absensi hari ini. Filter: semua cabang / satu outlet.
 */
new #[Title('Dashboard')] class extends Component {
    #[Url(except: '')]
    public string $outlet = '';

    public function outletId(): ?int
    {
        return ctype_digit($this->outlet) ? (int) $this->outlet : null;
    }

    /**
     * @return Collection<int, Location>
     */
    #[Computed]
    public function outletOptions(): Collection
    {
        return Location::query()->outlets()->active()->orderBy('name')->get();
    }

    /**
     * Active outlets & warehouses (or only the selected outlet).
     *
     * @return Collection<int, Location>
     */
    #[Computed]
    public function locations(): Collection
    {
        return Location::where('is_active', true)
            ->when($this->outletId(), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('type')->orderBy('name')->get();
    }

    #[Computed]
    public function metrics(): array
    {
        return app(ReportService::class)->dashboard($this->outletId());
    }

    /**
     * INV-2: stock that needs attention is the top widget for the owner.
     */
    #[Computed]
    public function lowStock(): \Illuminate\Support\Collection
    {
        return app(StockOverviewService::class)->attention($this->locations);
    }

    /**
     * REP-1: who has clocked in / is late today, per active outlet.
     */
    #[Computed]
    public function attendanceToday(): \Illuminate\Support\Collection
    {
        return app(AttendanceService::class)->todaySummary($this->locations->filter->isOutlet()->values());
    }

    /**
     * REP-1: outlet expenses waiting for verification + today's outlet expenses, per outlet.
     */
    #[Computed]
    public function expensesByOutlet(): \Illuminate\Support\Collection
    {
        return app(CashService::class)->pendingByOutlet($this->locations->filter->isOutlet()->values());
    }

    #[Computed]
    public function pendingCount(): int
    {
        return StockRequest::query()->inStatus(RequestStatus::Pending)->count();
    }

    /**
     * @return Collection<int, StockRequest>
     */
    #[Computed]
    public function pendingRequests(): Collection
    {
        return StockRequest::query()
            ->inStatus(RequestStatus::Pending)
            ->with(['items.item', 'toLocation', 'requester'])
            ->orderBy('id')
            ->limit(5)
            ->get();
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <x-greeting :name="auth()->user()->name" :description="'Pantau usaha Anda hari ini, '.now()->translatedFormat('l, d F Y').'.'" />
        <flux:select wire:model.live="outlet" class="sm:max-w-60" aria-label="Pilih cabang">
            <flux:select.option value="">Semua cabang</flux:select.option>
            @foreach ($this->outletOptions as $option)
                <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-2">
        {{-- Stok menipis (INV-2) --}}
        <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-text">Stok menipis</h2>
                    <p class="text-sm text-muted">Bahan di bawah stok minimum atau habis.</p>
                </div>
                @if ($this->lowStock->isNotEmpty())
                    <x-status-badge variant="warning">{{ $this->lowStock->count() }} bahan</x-status-badge>
                @endif
            </div>

            @if ($this->lowStock->isEmpty())
                <x-empty-state icon="check-circle" title="Semua stok aman" description="Tidak ada bahan di bawah batas minimum." />
            @else
                <ul class="flex flex-col divide-y divide-border">
                    @foreach ($this->lowStock->take(8) as $row)
                        <li wire:key="low-{{ $row['location']->id }}-{{ $row['item']->id }}">
                            <a href="{{ route('owner.stock.card', [$row['location'], $row['item']]) }}" wire:navigate class="flex items-center justify-between gap-3 py-3 hover:bg-canvas">
                                <div class="min-w-0">
                                    <div class="truncate font-medium">{{ $row['item']->name }}</div>
                                    <div class="truncate text-sm text-muted">{{ $row['location']->name }}</div>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    <span class="text-sm font-semibold">{{ qty($row['qty']) }} {{ $row['item']->unit }}
                                        @if ($row['min'] !== null)<span class="font-normal text-muted">/ min. {{ qty($row['min']) }}</span>@endif
                                    </span>
                                    <x-status-badge :variant="$row['level']->badge()">{{ $row['level']->label() }}</x-status-badge>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div>
                    <flux:button variant="ghost" size="sm" icon:trailing="arrow-right" :href="route('owner.stock', ['attentionOnly' => 1])" wire:navigate>Lihat semua stok</flux:button>
                </div>
            @endif
        </section>

        {{-- Permintaan menunggu --}}
        <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-text">Permintaan menunggu</h2>
                    <p class="text-sm text-muted">Permintaan bahan dari outlet yang perlu Anda setujui.</p>
                </div>
                @if ($this->pendingCount > 0)
                    <x-status-badge variant="soft">{{ $this->pendingCount }} baru</x-status-badge>
                @endif
            </div>

            @if ($this->pendingRequests->isEmpty())
                <x-empty-state icon="clipboard-document-check" title="Tidak ada permintaan menunggu" description="Permintaan baru dari outlet akan muncul di sini." />
            @else
                <ul class="flex flex-col divide-y divide-border">
                    @foreach ($this->pendingRequests as $request)
                        <li wire:key="pending-{{ $request->id }}">
                            <a href="{{ route('owner.requests.show', $request) }}" wire:navigate class="flex items-center gap-3 py-3 hover:bg-canvas">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-soft">
                                    <flux:icon name="building-storefront" class="size-5 text-brown" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-medium">{{ $request->toLocation->name }}</div>
                                    <div class="truncate text-sm text-muted">{{ $request->items->map(fn ($line) => $line->item->name.' '.qty($line->qty_requested).' '.$line->item->unit)->implode(' · ') }}</div>
                                    <div class="text-xs text-muted">{{ $request->number }} · {{ $request->created_at->translatedFormat('d M, H.i') }}</div>
                                </div>
                                <flux:icon name="chevron-right" class="size-5 shrink-0 text-muted" />
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div>
                    <flux:button variant="ghost" size="sm" icon:trailing="arrow-right" :href="route('owner.verification')" wire:navigate>Buka verifikasi</flux:button>
                </div>
            @endif
        </section>
    </div>

    {{-- Metrik hari ini (REP-1) --}}
    @php
        $m = $this->metrics;
        $pct = fn (?float $value) => number_format(abs($value ?? 0), 1, ',', '.').'%';
        $palette = ['bg-primary', 'bg-brown', 'bg-success', 'bg-warning'];
    @endphp
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-metric-card label="Omzet hari ini" :value="rupiah($m['revenue'])" icon="wallet"
            :trend="$m['revenue_change'] !== null ? $pct($m['revenue_change']).' dari kemarin' : null"
            :trend-direction="($m['revenue_change'] ?? 0) < 0 ? 'down' : 'up'"
            :hint="$m['revenue_change'] === null ? 'Kemarin belum ada penjualan' : null" />
        <x-metric-card label="Transaksi" :value="number_format($m['count'], 0, ',', '.')" icon="receipt-percent"
            :hint="($m['count_diff'] >= 0 ? '+' : '').$m['count_diff'].' dari kemarin'" />
        <x-metric-card label="Pengeluaran outlet" :value="rupiah($m['expenses'])" icon="banknotes" hint="Hari ini, kecuali yang ditolak" />
        <x-metric-card label="Laba kotor" :value="($m['gross'] < 0 ? '−' : '').rupiah(abs($m['gross']))" icon="chart-bar" hint="Omzet − HPP − pengeluaran disetujui" />
        <div class="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="text-sm text-muted">Selisih kas shift</div>
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-soft"><flux:icon name="scale" class="size-5 text-brown" /></div>
            </div>
            <div @class(['text-[26px] leading-tight font-bold lg:text-3xl', 'text-danger' => $m['variance'] < 0])>{{ ($m['variance'] < 0 ? '−' : '').rupiah(abs($m['variance'])) }}</div>
            <div class="text-sm text-muted">
                {{ $m['shifts_closed'] }} shift ditutup hari ini @if ($m['shifts_short']) · <span class="text-danger">{{ $m['shifts_short'] }} kurang</span>@endif
            </div>
        </div>
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-3">
        <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6 lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-text">Performa penjualan 7 hari</h2>
                    <p class="text-sm text-muted">{{ \App\Support\Period::lastDays(7)->label() }} dibanding 7 hari sebelumnya</p>
                </div>
                <div class="text-end">
                    <div class="text-2xl font-bold">{{ rupiah($m['week_revenue']) }}</div>
                    @if ($m['week_change'] !== null)
                        <x-status-badge :variant="$m['week_change'] >= 0 ? 'success' : 'danger'">{{ $m['week_change'] >= 0 ? '↑' : '↓' }} {{ $pct($m['week_change']) }}</x-status-badge>
                    @endif
                </div>
            </div>
            <x-chart :config="[
                'type' => 'bar',
                'labels' => $m['trend']->map(fn ($d) => \Carbon\CarbonImmutable::parse($d['date'])->translatedFormat('d M'))->all(),
                'datasets' => [
                    ['label' => '7 hari sebelumnya', 'data' => $m['trend']->pluck('previous')->all(), 'color' => 'border'],
                    ['label' => 'Periode ini', 'data' => $m['trend']->pluck('current')->all(), 'color' => 'primary'],
                ],
            ]" />
            <details class="text-sm">
                <summary class="cursor-pointer text-muted">Lihat angka per hari</summary>
                <ul class="mt-2 grid grid-cols-2 gap-x-6 gap-y-1 sm:grid-cols-4">
                    @foreach ($m['trend'] as $day)
                        <li class="flex justify-between gap-2"><span class="text-muted">{{ \Carbon\CarbonImmutable::parse($day['date'])->translatedFormat('d M') }}</span><span>{{ rupiah($day['current']) }}</span></li>
                    @endforeach
                </ul>
            </details>
        </section>

        <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <div>
                <h2 class="text-lg font-semibold text-text">Komposisi kanal</h2>
                <p class="text-sm text-muted">Omzet 7 hari terakhir per kanal.</p>
            </div>
            @if ($m['channels']->sum('revenue') === 0)
                <x-empty-state icon="chart-pie" title="Belum ada penjualan" />
            @else
                <x-chart height="h-40" :config="[
                    'type' => 'doughnut',
                    'labels' => $m['channels']->map(fn ($c) => $c['channel']->label())->all(),
                    'datasets' => [['label' => 'Omzet', 'data' => $m['channels']->pluck('revenue')->all(), 'color' => ['primary', 'brown', 'success', 'warning']]],
                    'legend' => false,
                ]" />
                <ul class="flex flex-col gap-2 text-sm">
                    @foreach ($m['channels'] as $i => $row)
                        <li class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2"><span class="size-2.5 rounded-full {{ $palette[$i] }}"></span>{{ $row['channel']->label() }}</span>
                            <span><span class="font-medium">{{ rupiah($row['revenue']) }}</span> <span class="text-muted">{{ number_format($row['share'], 1, ',', '.') }}%</span></span>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if (! $outlet && $m['outlets_today']->count() > 1)
                <div class="border-t border-border pt-4">
                    <h3 class="mb-2 font-semibold">Penjualan per cabang hari ini</h3>
                    <ul class="flex flex-col gap-3 text-sm">
                        @foreach ($m['outlets_today'] as $row)
                            <li class="flex flex-col gap-1">
                                <div class="flex justify-between gap-2"><span class="font-medium">{{ $row['outlet']->name }}</span><span class="font-semibold">{{ rupiah($row['revenue']) }}</span></div>
                                <div class="flex justify-between gap-2 text-xs text-muted"><span>{{ $row['count'] }} transaksi</span><span>{{ number_format($row['share'], 1, ',', '.') }}%</span></div>
                                <div class="h-1.5 overflow-hidden rounded-full bg-border"><div class="h-full rounded-full bg-primary" style="width: {{ $row['share'] }}%"></div></div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    </div>

    {{-- Pengeluaran menunggu verifikasi (REP-1, CASH-3) --}}
    @php $expenseRows = $this->expensesByOutlet; @endphp
    <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-text">Pengeluaran menunggu verifikasi</h2>
                <p class="text-sm text-muted">Pengeluaran outlet yang belum Anda periksa, dan total pengeluaran hari ini.</p>
            </div>
            <flux:button variant="ghost" size="sm" icon:trailing="arrow-right" :href="route('owner.verification', ['tab' => 'expenses'])" wire:navigate>Buka verifikasi</flux:button>
        </div>

        @if ($expenseRows->isEmpty())
            <x-empty-state icon="receipt-percent" title="Belum ada outlet aktif" />
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($expenseRows as $row)
                    <div wire:key="exp-outlet-{{ $row['outlet']->id }}" class="flex flex-col gap-2 rounded-xl border border-border p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="truncate font-semibold">{{ $row['outlet']->name }}</div>
                            @if ($row['pending_count'] > 0)
                                <x-status-badge variant="soft">{{ $row['pending_count'] }} menunggu</x-status-badge>
                            @else
                                <x-status-badge variant="success">Tidak ada</x-status-badge>
                            @endif
                        </div>
                        <div class="text-2xl font-bold">{{ rupiah($row['pending_total']) }}</div>
                        <div class="text-sm text-muted">Pengeluaran hari ini: {{ rupiah($row['today_total']) }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Absensi hari ini (REP-1, ATT-7) --}}
    <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-text">Absensi hari ini</h2>
                <p class="text-sm text-muted">Siapa yang sudah absen dan status shift tiap outlet.</p>
            </div>
            <flux:button variant="ghost" size="sm" icon:trailing="arrow-right" :href="route('owner.attendance')" wire:navigate>Rekap absensi</flux:button>
        </div>

        @if ($this->attendanceToday->isEmpty())
            <x-empty-state icon="finger-print" title="Belum ada outlet aktif" description="Tambahkan outlet di Pengaturan untuk memantau absensi." />
        @else
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($this->attendanceToday as $row)
                    <div wire:key="att-today-{{ $row['outlet']->id }}" class="flex flex-col gap-3 rounded-xl border border-border p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-semibold">{{ $row['outlet']->name }}</div>
                                <div class="text-sm text-muted">
                                    Jam standar {{ $row['outlet']->open_time ? substr($row['outlet']->open_time, 0, 5) : '—' }}–{{ $row['outlet']->close_time ? substr($row['outlet']->close_time, 0, 5) : '—' }}
                                </div>
                            </div>
                            @if ($row['openShift'])
                                <x-status-badge variant="success">Buka sejak {{ $row['openShift']->opened_at->format('H.i') }}</x-status-badge>
                            @elseif ($row['closedShift'])
                                <x-status-badge variant="muted">Tutup {{ $row['closedShift']->closed_at->format('H.i') }}</x-status-badge>
                            @else
                                <x-status-badge variant="warning">Belum buka</x-status-badge>
                            @endif
                        </div>

                        <ul class="flex flex-col divide-y divide-border">
                            @foreach ($row['present'] as $person)
                                <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                    <span class="truncate font-medium">{{ $person['user']->name }}</span>
                                    <span class="flex shrink-0 items-center gap-2">
                                        <span class="text-muted">{{ $person['in']->server_time->format('H.i') }}{{ $person['out'] ? '–'.$person['out']->server_time->format('H.i') : '' }}</span>
                                        @php $badge = $person['in']->statusBadge(); @endphp
                                        <x-status-badge :variant="$badge['variant']">{{ $badge['label'] }}</x-status-badge>
                                    </span>
                                </li>
                            @endforeach
                            @foreach ($row['absent'] as $person)
                                <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                    <span class="truncate font-medium">{{ $person->name }}</span>
                                    <x-status-badge variant="danger">Belum absen</x-status-badge>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

</div>

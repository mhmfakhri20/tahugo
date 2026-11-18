<?php

use App\Enums\CashDirection;
use App\Enums\SalesChannel;
use App\Models\Shift;
use App\Services\ReportService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Laporan Harian Outlet (REP-2, SHF-4): satu shift, siap cetak. Pemilik semua outlet, penjaga hanya outletnya (BR-7).
 */
new #[Title('Laporan harian outlet')] class extends Component {
    #[Locked]
    public int $shiftId;

    public function mount(Shift $shift): void
    {
        $this->authorize('viewReport', $shift);
        $this->shiftId = $shift->id;
    }

    #[Computed]
    public function report(): array
    {
        $shift = Shift::findOrFail($this->shiftId);
        $this->authorize('viewReport', $shift);

        return app(ReportService::class)->daily($shift);
    }

    public function backUrl(): string
    {
        return auth()->user()->isOwner() ? route('owner.reports.daily') : route('outlet.home');
    }
}; ?>

@php
    $r = $this->report;
    $shift = $r['shift'];
    $cash = $r['cash'];
    $sales = $cash['sales'];
    $location = $shift->location;
    $std = fn (?string $time) => $time ? substr($time, 0, 5) : '—';
    $money = fn (?int $value) => $value === null ? '—' : ($value < 0 ? '−' : '').rupiah(abs($value));
@endphp

<div class="flex flex-col gap-5">
    <div class="no-print flex flex-wrap items-center justify-between gap-3">
        <a href="{{ $this->backUrl() }}" wire:navigate class="inline-flex min-h-12 items-center gap-1 text-base font-medium text-muted">
            <flux:icon name="arrow-left" class="size-5" /> Kembali
        </a>
        <flux:button icon="printer" x-on:click="window.print()">Cetak laporan</flux:button>
    </div>

    <header class="print-card flex flex-col gap-2 rounded-xl border border-border bg-surface p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-sm text-muted">Laporan Harian Outlet</div>
                <h1 class="text-[26px] leading-tight font-bold">{{ $location->name }}</h1>
                <div class="text-base">{{ $shift->opened_at->translatedFormat('l, d F Y') }}</div>
            </div>
            <x-status-badge :variant="$shift->isOpen() ? 'success' : 'muted'">{{ $shift->isOpen() ? 'Shift masih buka (sementara)' : 'Shift ditutup' }}</x-status-badge>
        </div>
        <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            <div>
                <dt class="text-muted">Buka (standar {{ $std($location->open_time) }})</dt>
                <dd class="font-semibold">{{ $shift->opened_at->format('H.i') }} @if ($r['opened_late'])<span class="text-warning">· telat {{ $r['opened_late'] }}m</span>@endif</dd>
            </div>
            <div>
                <dt class="text-muted">Tutup (standar {{ $std($location->close_time) }})</dt>
                <dd class="font-semibold">{{ $shift->closed_at?->format('H.i') ?? '—' }} @if ($r['closed_early'])<span class="text-warning">· awal {{ $r['closed_early'] }}m</span>@endif</dd>
            </div>
            <div><dt class="text-muted">Dibuka oleh</dt><dd class="font-semibold">{{ $shift->opener->name }}</dd></div>
            <div><dt class="text-muted">Ditutup oleh</dt><dd class="font-semibold">{{ $shift->closer?->name ?? '—' }}</dd></div>
        </dl>
        @if ($r['attendances']->isNotEmpty())
            <div class="text-sm text-muted">
                Penjaga:
                {{ $r['attendances']->map(fn ($a) => $a['user']->name.' ('.($a['in']?->server_time->format('H.i') ?? '—').'–'.($a['out']?->server_time->format('H.i') ?? '…').')')->implode(', ') }}
            </div>
        @endif
    </header>

    {{-- Kas --}}
    <section class="print-card flex flex-col gap-3 rounded-xl border border-border bg-surface p-4 sm:p-6">
        <h2 class="text-lg font-semibold">Kas</h2>
        <dl class="grid gap-x-8 text-sm sm:grid-cols-2">
            @foreach ([
                ['Kas awal', $cash['opening_cash']],
                ['+ Penjualan tunai', $cash['cash_sales']],
                ['+ Pemasukan lain', $cash['income']],
                ['− Pengeluaran kas outlet (disetujui)', $cash['expense_approved']],
                ['− Pengeluaran kas outlet (menunggu)', $cash['expense_pending']],
                ['Kas seharusnya saat tutup', $cash['expected_at_close'] ?? $cash['expected_now']],
                ['Kas fisik dihitung', $cash['counted_cash']],
                ['Talangan penjaga (uang pribadi)', $cash['personal']],
            ] as [$label, $value])
                <div class="flex justify-between gap-3 border-b border-border py-2"><dt class="text-muted">{{ $label }}</dt><dd class="font-medium">{{ $money($value) }}</dd></div>
            @endforeach
        </dl>
        <div class="grid gap-3 sm:grid-cols-2">
            <div @class(['rounded-xl p-4', 'bg-success/10' => ($cash['variance_at_close'] ?? 0) >= 0, 'bg-danger/10' => ($cash['variance_at_close'] ?? 0) < 0])>
                <div class="text-sm text-muted">Selisih saat tutup</div>
                <div class="text-xl font-bold">{{ $money($cash['variance_at_close']) }}</div>
            </div>
            <div @class(['rounded-xl p-4', 'bg-success/10' => ($cash['variance_after_verification'] ?? 0) >= 0, 'bg-danger/10' => ($cash['variance_after_verification'] ?? 0) < 0])>
                <div class="text-sm text-muted">Selisih setelah verifikasi pengeluaran</div>
                <div class="text-xl font-bold">{{ $money($cash['variance_after_verification']) }}</div>
                @if ($cash['expense_rejected'] > 0)
                    <div class="text-sm text-danger">Termasuk pengeluaran ditolak {{ rupiah($cash['expense_rejected']) }} (harus dipertanggungjawabkan)</div>
                @endif
            </div>
        </div>
    </section>

    {{-- Penjualan --}}
    <section class="print-card flex flex-col gap-3 rounded-xl border border-border bg-surface p-4 sm:p-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-lg font-semibold">Penjualan</h2>
            <div class="text-sm text-muted">{{ $sales['count'] }} transaksi · {{ $sales['void_count'] }} dibatalkan · omzet <span class="font-semibold text-text">{{ rupiah($sales['total']) }}</span></div>
        </div>
        @if ($r['products']->isEmpty())
            <x-empty-state icon="shopping-cart" title="Tidak ada penjualan" />
        @else
            <x-data-table>
                <x-slot:head>
                    <th>Produk</th>
                    @foreach (SalesChannel::cases() as $channel)<th class="text-end!">{{ $channel->label() }}</th>@endforeach
                    <th class="text-end!">Total</th>
                </x-slot:head>
                @foreach ($r['products'] as $row)
                    <tr>
                        <td class="font-medium">{{ $row['product'] }}</td>
                        @foreach (SalesChannel::cases() as $channel)
                            @php $cell = $row['channels'][$channel->value] ?? null; @endphp
                            <td class="text-end">{!! $cell ? e($cell['qty']).' <span class="text-xs text-muted">'.e(rupiah($cell['revenue'])).'</span>' : '<span class="text-muted">—</span>' !!}</td>
                        @endforeach
                        <td class="text-end font-semibold">{{ $row['qty'] }} · {{ rupiah($row['revenue']) }}</td>
                    </tr>
                @endforeach
                <x-slot:footer>
                    <span>Per kanal: {{ collect(SalesChannel::cases())->map(fn ($c) => $c->label().' '.rupiah($sales['per_channel'][$c->value]))->implode(' · ') }} · Tunai {{ rupiah($sales['cash']) }} · QRIS {{ rupiah($sales['qris']) }}</span>
                </x-slot:footer>
            </x-data-table>
        @endif
    </section>

    <div class="grid items-start gap-5 lg:grid-cols-2">
        {{-- Pengeluaran & pemasukan --}}
        <section class="print-card flex flex-col gap-3 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <h2 class="text-lg font-semibold">Pengeluaran & pemasukan lain</h2>
            @if ($r['entries']->isEmpty())
                <x-empty-state icon="banknotes" title="Tidak ada catatan kas" />
            @else
                <ul class="flex flex-col divide-y divide-border text-sm">
                    @foreach ($r['entries'] as $entry)
                        @php $badge = $entry->statusBadge(); $isIn = $entry->direction === CashDirection::In; @endphp
                        <li class="flex items-start justify-between gap-3 py-2">
                            <div class="min-w-0">
                                <div class="font-medium">{{ $isIn ? 'Pemasukan lain' : $entry->category?->name }} · {{ $entry->number() }}</div>
                                <div class="text-muted">{{ $entry->description }} · {{ $entry->user->name }} @unless ($isIn) · {{ $entry->fund_source?->label() }} @endunless</div>
                                @if ($entry->reject_reason)<div class="text-danger">Ditolak: {{ $entry->reject_reason }}</div>@endif
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1">
                                <span class="font-semibold">{{ $isIn ? '+' : '' }}{{ rupiah($entry->amount) }}</span>
                                @unless ($isIn)<x-status-badge :variant="$badge['variant']">{{ $badge['label'] }}</x-status-badge>@endunless
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Bahan --}}
        <section class="print-card flex flex-col gap-3 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <h2 class="text-lg font-semibold">Penggunaan bahan (resep)</h2>
            @if ($sales['usage']->isEmpty())
                <x-empty-state icon="archive-box" title="Tidak ada bahan terpakai" />
            @else
                <ul class="flex flex-col divide-y divide-border text-sm">
                    @foreach ($sales['usage'] as $row)
                        <li class="flex justify-between gap-3 py-2"><span>{{ $row['item']->name }}</span><span class="font-medium">{{ qty($row['qty']) }} {{ $row['item']->unit }}</span></li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <p class="text-xs text-muted">Dicetak {{ now()->translatedFormat('d F Y, H.i') }} WIB · tahugo.</p>
</div>

@extends('pdf.layout', ['title' => 'Laporan Harian Outlet'])

@php
    $location = $shift->location;
    $sales = $cash['sales'];
    $money = fn (?int $v) => $v === null ? '—' : ($v < 0 ? '−' : '').rupiah(abs($v));
    $std = fn (?string $t) => $t ? substr($t, 0, 5) : '—';
@endphp

@section('content')
    <h1>Laporan Harian Outlet — {{ $location->name }}</h1>
    <div class="muted">{{ $shift->opened_at->translatedFormat('l, d F Y') }} · {{ $shift->isOpen() ? 'Shift masih buka (laporan sementara)' : 'Shift ditutup' }}</div>

    <table class="grid" style="margin-top:8px">
        <tr>
            <td>Buka: <b>{{ $shift->opened_at->format('H.i') }}</b> (standar {{ $std($location->open_time) }}){{ $opened_late ? ' · telat '.$opened_late.' menit' : '' }}</td>
            <td>Tutup: <b>{{ $shift->closed_at?->format('H.i') ?? '—' }}</b> (standar {{ $std($location->close_time) }}){{ $closed_early ? ' · awal '.$closed_early.' menit' : '' }}</td>
        </tr>
        <tr>
            <td>Dibuka oleh: <b>{{ $shift->opener->name }}</b></td>
            <td>Ditutup oleh: <b>{{ $shift->closer?->name ?? '—' }}</b></td>
        </tr>
    </table>

    <h2>Kas</h2>
    <table>
        @foreach ([
            ['Kas awal', $cash['opening_cash']],
            ['+ Penjualan tunai', $cash['cash_sales']],
            ['+ Pemasukan lain', $cash['income']],
            ['− Pengeluaran kas outlet (disetujui)', $cash['expense_approved']],
            ['− Pengeluaran kas outlet (menunggu)', $cash['expense_pending']],
            ['Kas seharusnya saat tutup', $cash['expected_at_close'] ?? $cash['expected_now']],
            ['Kas fisik dihitung', $cash['counted_cash']],
            ['Selisih saat tutup', $cash['variance_at_close']],
            ['Selisih setelah verifikasi pengeluaran', $cash['variance_after_verification']],
            ['Talangan penjaga (uang pribadi)', $cash['personal']],
            ['Pengeluaran ditolak (harus dipertanggungjawabkan)', $cash['expense_rejected']],
        ] as [$label, $value])
            <tr><td>{{ $label }}</td><td class="num bold">{{ $money($value) }}</td></tr>
        @endforeach
    </table>

    <h2>Penjualan — {{ $sales['count'] }} transaksi, {{ $sales['void_count'] }} dibatalkan, omzet {{ rupiah($sales['total']) }}</h2>
    <table>
        <tr>
            <th>Produk</th>
            @foreach (\App\Enums\SalesChannel::cases() as $channel)<th class="num">{{ $channel->label() }}</th>@endforeach
            <th class="num">Total</th>
        </tr>
        @forelse ($products as $row)
            <tr>
                <td>{{ $row['product'] }}</td>
                @foreach (\App\Enums\SalesChannel::cases() as $channel)
                    @php $cell = $row['channels'][$channel->value] ?? null; @endphp
                    <td class="num">{{ $cell ? $cell['qty'].' · '.rupiah($cell['revenue']) : '—' }}</td>
                @endforeach
                <td class="num bold">{{ $row['qty'] }} · {{ rupiah($row['revenue']) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">Tidak ada penjualan.</td></tr>
        @endforelse
    </table>
    <div class="muted" style="margin-top:4px">Tunai {{ rupiah($sales['cash']) }} · QRIS {{ rupiah($sales['qris']) }}</div>

    <h2>Pengeluaran & pemasukan lain</h2>
    <table>
        <tr><th>No.</th><th>Keterangan</th><th>Oleh</th><th>Status</th><th class="num">Nominal</th></tr>
        @forelse ($entries as $entry)
            @php $isIn = $entry->direction === \App\Enums\CashDirection::In; @endphp
            <tr>
                <td>{{ $entry->number() }}</td>
                <td>{{ $isIn ? 'Pemasukan lain' : $entry->category?->name }} — {{ $entry->description }}{{ $entry->reject_reason ? ' (ditolak: '.$entry->reject_reason.')' : '' }}</td>
                <td>{{ $entry->user->name }}</td>
                <td>{{ $isIn ? '—' : $entry->statusBadge()['label'] }}{{ ! $isIn && $entry->fund_source ? ' · '.$entry->fund_source->label() : '' }}</td>
                <td class="num {{ $isIn ? 'success' : '' }}">{{ $isIn ? '+' : '' }}{{ rupiah($entry->amount) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">Tidak ada catatan kas.</td></tr>
        @endforelse
    </table>

    <h2>Penggunaan bahan (resep)</h2>
    <table>
        <tr><th>Bahan</th><th class="num">Jumlah</th></tr>
        @forelse ($sales['usage'] as $row)
            <tr><td>{{ $row['item']->name }}</td><td class="num">{{ qty($row['qty']) }} {{ $row['item']->unit }}</td></tr>
        @empty
            <tr><td colspan="2" class="muted">Tidak ada bahan terpakai.</td></tr>
        @endforelse
    </table>

    @if ($attendances->isNotEmpty())
        <h2>Penjaga</h2>
        <table>
            <tr><th>Nama</th><th>Masuk</th><th>Pulang</th></tr>
            @foreach ($attendances as $a)
                <tr><td>{{ $a['user']->name }}</td><td>{{ $a['in']?->server_time->format('H.i') ?? '—' }}</td><td>{{ $a['out']?->server_time->format('H.i') ?? '—' }}</td></tr>
            @endforeach
        </table>
    @endif
@endsection

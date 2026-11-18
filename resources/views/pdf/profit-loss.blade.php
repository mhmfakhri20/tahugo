@extends('pdf.layout', ['title' => 'Laporan Laba-Rugi'])

@php
    $money = fn (int $v) => ($v < 0 ? '−' : '').rupiah(abs($v));
    $kinds = ['outlet' => 'Pengeluaran outlet', 'business' => 'Pengeluaran usaha', 'purchase' => 'Pembelian bahan'];
@endphp

@section('content')
    <h1>Laporan Arus Kas & Laba-Rugi</h1>
    <div class="muted">{{ $outletName }} · {{ $period->label() }}</div>

    <h2>Laba-rugi sederhana</h2>
    <table>
        <tr><td>Omzet penjualan</td><td class="num">{{ $money($pl['revenue']) }}</td></tr>
        <tr><td>Pemasukan lain</td><td class="num">{{ $money($pl['other_income']) }}</td></tr>
        <tr><td class="bold">Total pemasukan</td><td class="num bold">{{ $money($pl['income']) }}</td></tr>
        <tr><td>− Pengeluaran outlet (disetujui)</td><td class="num">{{ $money($pl['outlet_expenses']) }}</td></tr>
        <tr><td>− Pembelian bahan (gudang)</td><td class="num">{{ $money($pl['purchases']) }}</td></tr>
        <tr><td>− Pengeluaran usaha lain</td><td class="num">{{ $money($pl['business_other']) }}</td></tr>
        <tr><td class="bold">Total pengeluaran</td><td class="num bold">{{ $money($pl['expenses']) }}</td></tr>
        <tr><td class="bold">Laba-rugi usaha</td><td class="num bold {{ $pl['net'] < 0 ? 'danger' : 'success' }}">{{ $money($pl['net']) }}</td></tr>
    </table>
    <div class="muted" style="margin-top:4px">Pembelian bahan dihitung sekali sebagai pengeluaran usaha. Pengeluaran menunggu/ditolak tidak dihitung.</div>

    <h2>Pengeluaran per kategori</h2>
    <table>
        <tr><th>Kategori</th><th>Jenis</th><th class="num">Nominal</th><th class="num">Bagian</th></tr>
        @forelse ($categories as $row)
            <tr><td>{{ $row['label'] }}</td><td>{{ $kinds[$row['kind']] }}</td><td class="num">{{ $money($row['amount']) }}</td><td class="num">{{ number_format($row['share'], 1, ',', '.') }}%</td></tr>
        @empty
            <tr><td colspan="4" class="muted">Tidak ada pengeluaran disetujui.</td></tr>
        @endforelse
    </table>

    <h2>Laba kotor per outlet</h2>
    <table>
        <tr><th>Outlet</th><th class="num">Omzet</th><th class="num">HPP</th><th class="num">Pengeluaran</th><th class="num">Laba kotor</th></tr>
        @foreach ($gross as $row)
            <tr>
                <td>{{ $row['outlet']->name }}</td>
                <td class="num">{{ $money($row['revenue']) }}</td>
                <td class="num">{{ $money($row['cogs']) }}</td>
                <td class="num">{{ $money($row['expenses']) }}</td>
                <td class="num bold {{ $row['gross'] < 0 ? 'danger' : '' }}">{{ $money($row['gross']) }}</td>
            </tr>
        @endforeach
    </table>
@endsection

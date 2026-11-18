<?php

namespace App\Http\Controllers;

use App\Exports\Sheet;
use App\Exports\Workbook;
use App\Http\Requests\ReportFilterRequest;
use App\Models\Attendance;
use App\Models\Shift;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Report exports (REP-7). Same filters and the same Gate/Policy as the report pages;
 * all numbers come from ReportService. Rupiah stays numeric in Excel.
 */
class ReportExportController extends Controller
{
    private const R = Sheet::RUPIAH;

    public function __construct(private readonly ReportService $reports) {}

    public function sales(ReportFilterRequest $request): BinaryFileResponse
    {
        Gate::authorize('view-financial-reports');

        $period = $request->period();
        $outlet = $request->outletId();

        return Excel::download(new Workbook([
            new Sheet('Per hari', ['Tanggal', 'Transaksi', 'Omzet'],
                $this->reports->salesByDay($period, $outlet)->map(fn ($d) => [$d['date'], $d['count'], $d['revenue']])->values()->all(),
                ['C' => self::R]),
            new Sheet('Per produk', ['Produk', 'Terjual', 'Omzet', 'Bagian (%)'],
                $this->reports->salesByProduct($period, $outlet)->map(fn ($p) => [$p['product'], $p['qty'], $p['revenue'], $p['share']])->all(),
                ['C' => self::R]),
            new Sheet('Per kanal', ['Kanal', 'Transaksi', 'Omzet', 'Bagian (%)'],
                $this->reports->salesByChannel($period, $outlet)->map(fn ($c) => [$c['channel']->label(), $c['count'], $c['revenue'], $c['share']])->all(),
                ['C' => self::R]),
            new Sheet('Per metode bayar', ['Metode (offline)', 'Transaksi', 'Omzet', 'Bagian (%)'],
                $this->reports->salesByPaymentMethod($period, $outlet)->map(fn ($m) => [$m['method']->label(), $m['count'], $m['revenue'], $m['share']])->all(),
                ['C' => self::R]),
            new Sheet('Per cabang', ['Cabang', 'Transaksi', 'Produk terjual', 'Omzet', 'Kontribusi (%)'],
                $this->reports->salesByOutlet($period, $outlet)->map(fn ($o) => [$o['outlet']->name, $o['count'], $o['pcs'], $o['revenue'], $o['share']])->all(),
                ['D' => self::R]),
        ]), $request->filename('penjualan'));
    }

    public function cash(ReportFilterRequest $request): BinaryFileResponse
    {
        Gate::authorize('view-financial-reports');

        $period = $request->period();
        $outlet = $request->outletId();
        $pl = $this->reports->profitLoss($period, $outlet);
        $kinds = ['outlet' => 'Pengeluaran outlet', 'business' => 'Pengeluaran usaha', 'purchase' => 'Pembelian bahan'];

        return Excel::download(new Workbook([
            new Sheet('Laba-rugi', ['Pos', 'Nominal'], [
                ['Omzet penjualan', $pl['revenue']],
                ['Pemasukan lain', $pl['other_income']],
                ['Total pemasukan', $pl['income']],
                ['Pengeluaran outlet (disetujui)', $pl['outlet_expenses']],
                ['Pembelian bahan (gudang)', $pl['purchases']],
                ['Pengeluaran usaha lain', $pl['business_other']],
                ['Total pengeluaran', $pl['expenses']],
                ['Laba-rugi usaha', $pl['net']],
            ], ['B' => self::R]),
            new Sheet('Per kategori', ['Kategori', 'Jenis', 'Nominal', 'Bagian (%)'],
                $this->reports->expensesByCategory($period, $outlet)->map(fn ($c) => [$c['label'], $kinds[$c['kind']], $c['amount'], $c['share']])->all(),
                ['C' => self::R]),
            new Sheet('Laba kotor outlet', ['Outlet', 'Omzet', 'HPP', 'Pengeluaran', 'Laba kotor'],
                $this->reports->grossProfitByOutlet($period, $outlet)->map(fn ($g) => [$g['outlet']->name, $g['revenue'], $g['cogs'], $g['expenses'], $g['gross']])->all(),
                ['B' => self::R, 'C' => self::R, 'D' => self::R, 'E' => self::R]),
        ]), $request->filename('kas-laba-rugi'));
    }

    public function cashPdf(ReportFilterRequest $request): Response
    {
        Gate::authorize('view-financial-reports');

        $period = $request->period();
        $outlet = $request->outletId();

        return Pdf::loadView('pdf.profit-loss', [
            'period' => $period,
            'outletName' => $outlet ? $this->reports->outlets($outlet)->first()?->name : 'Semua cabang',
            'pl' => $this->reports->profitLoss($period, $outlet),
            'categories' => $this->reports->expensesByCategory($period, $outlet),
            'gross' => $this->reports->grossProfitByOutlet($period, $outlet),
        ])->setPaper('a4')->download($request->filename('laba-rugi', 'pdf'));
    }

    public function inventory(ReportFilterRequest $request): BinaryFileResponse
    {
        Gate::authorize('view-inventory-reports');

        $data = $this->reports->inventory($request->period(), $request->outletId());

        return Excel::download(new Workbook([
            new Sheet('Saldo', ['Lokasi', 'Bahan', 'Stok', 'Satuan', 'Minimum', 'Status'],
                $data['balances']->map(fn ($b) => [$b['location']->name, $b['item']->name, (float) $b['qty'], $b['item']->unit, $b['min'] !== null ? (float) $b['min'] : null, $b['level']->label()])->all(),
                ['C' => Sheet::NUMBER, 'E' => Sheet::NUMBER]),
            new Sheet('Mutasi', ['Lokasi', 'Tipe mutasi', 'Jumlah catatan', 'Total qty'],
                $data['movements']->map(fn ($m) => [$m['location']->name, $m['type']->label(), $m['count'], (float) $m['qty']])->all(),
                ['D' => Sheet::NUMBER]),
            new Sheet('Pembelian', ['Nomor', 'Tanggal', 'Supplier', 'Bahan', 'Qty', 'Harga satuan', 'Subtotal'],
                $data['purchases']->flatMap(fn ($p) => $p->items->map(fn ($l) => [$p->number(), $p->purchase_date->toDateString(), $p->supplier, $l->item->name, (float) $l->qty, $l->unit_price, $l->line_total]))->all(),
                ['E' => Sheet::NUMBER, 'F' => self::R, 'G' => self::R]),
            new Sheet('Pengiriman', ['Nomor', 'Dikirim', 'Tujuan', 'Bahan', 'Qty kirim', 'Qty terima', 'Selisih', 'Status'],
                $data['shipments']->flatMap(fn ($r) => $r->items->map(fn ($l) => [
                    $r->number, $r->shipped_at->format('Y-m-d H:i'), $r->toLocation->name, $l->item->name,
                    (float) $l->qty_shipped, $l->qty_received !== null ? (float) $l->qty_received : null,
                    $l->qty_received !== null ? (float) $l->receiveDifference() : null, $r->status->label(),
                ]))->all(),
                ['E' => Sheet::NUMBER, 'F' => Sheet::NUMBER, 'G' => Sheet::NUMBER]),
            new Sheet('Waste', ['Lokasi', 'Bahan', 'Jumlah', 'Satuan', 'Catatan'],
                $data['waste']->map(fn ($w) => [$w->location->name, $w->item->name, abs((float) $w->qty), $w->item->unit, (int) $w->n])->all(),
                ['C' => Sheet::NUMBER]),
        ]), $request->filename('persediaan'));
    }

    public function performance(ReportFilterRequest $request): BinaryFileResponse
    {
        Gate::authorize('view-financial-reports');

        return Excel::download(new Workbook([
            new Sheet('Kinerja outlet', ['Outlet', 'Transaksi', 'Omzet', 'Periode sebelumnya', 'Pertumbuhan (%)', 'Kontribusi (%)', 'Omzet bulan berjalan', 'Target bulanan', 'Progres target (%)'],
                $this->reports->outletPerformance($request->period())->map(fn ($r) => [
                    $r['outlet']->name, $r['count'], $r['revenue'], $r['previous'], $r['growth'], $r['share'],
                    $r['month_revenue'], $r['target'] ?? 'Target belum diatur', $r['progress'],
                ])->all(),
                ['C' => self::R, 'D' => self::R, 'G' => self::R, 'H' => self::R]),
        ]), $request->filename('kinerja-outlet'));
    }

    public function attendance(ReportFilterRequest $request): BinaryFileResponse
    {
        Gate::authorize('viewAny', Attendance::class);

        $rows = $this->reports->attendanceRecap(
            $request->period(),
            $request->outletId(),
            $request->validated('userFilter') !== null ? (int) $request->validated('userFilter') : null,
            $request->validated('statusFilter'),
        );

        return Excel::download(new Workbook([
            new Sheet('Rekap absensi', ['Tanggal', 'Karyawan', 'Outlet', 'Masuk', 'Terlambat (menit)', 'Pulang', 'Pulang awal (menit)', 'Jarak masuk (m)', 'Akurasi masuk (m)', 'Peta masuk'],
                $rows->map(fn ($r) => [
                    $r['in']->server_time->toDateString(), $r['in']->user->name, $r['in']->location->name,
                    $r['in']->server_time->format('H:i'), $r['in']->flags['late_minutes'] ?? 0,
                    $r['out']?->server_time->format('H:i'), $r['out']?->flags['early_minutes'] ?? 0,
                    (float) $r['in']->distance_m, (float) $r['in']->accuracy_m, $r['in']->mapsUrl(),
                ])->all(),
                ['H' => Sheet::NUMBER, 'I' => Sheet::NUMBER]),
        ]), $request->filename('absensi'));
    }

    public function dailyPdf(Shift $shift): Response
    {
        Gate::authorize('viewReport', $shift);

        $report = $this->reports->daily($shift);

        return Pdf::loadView('pdf.daily', $report)
            ->setPaper('a4')
            ->download(sprintf('laporan-harian_%s_%s.pdf', $shift->location->code, $shift->opened_at->toDateString()));
    }
}

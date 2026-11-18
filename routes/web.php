<?php

use App\Http\Controllers\AttendancePhotoController;
use App\Http\Controllers\CashReceiptController;
use App\Http\Controllers\PurchaseReceiptController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\ShiftPhotoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth'])->group(function () {
    // Fortify "home": send every user to the home page of their role (AUTH-2).
    Route::get('dashboard', fn (Request $request) => redirect()->route($request->user()->role->homeRoute()))
        ->name('dashboard');

    // Foto nota pembelian: disk privat, hanya lewat route terotorisasi (NFR-4).
    Route::get('purchases/{purchase}/receipt', PurchaseReceiptController::class)->name('purchases.receipt');
    // Foto absen & foto tutup shift: hanya pemilik atau pemilik foto (ATT-4, NFR-5).
    Route::get('attendances/{attendance}/photo', AttendancePhotoController::class)->name('attendances.photo');
    Route::get('shifts/{shift}/photo', ShiftPhotoController::class)->name('shifts.photo');
    // Laporan Harian Outlet (REP-2): pemilik & penjaga outlet tersebut.
    Route::livewire('reports/daily/{shift}', 'pages::reports.daily')->name('reports.daily');
    Route::get('reports/daily/{shift}/pdf', [ReportExportController::class, 'dailyPdf'])->name('reports.daily.pdf');
    // Bantuan (NFR-8): isi sesuai peran.
    Route::livewire('help', 'pages::help')->name('help');
    // Foto nota pengeluaran: pemilik & pencatatnya (CASH-1, CASH-4).
    Route::get('cash-entries/{cashEntry}/receipt', CashReceiptController::class)->name('cash-entries.receipt');

    // Each role area ends with a catch-all so that unknown URLs in another role's area
    // also return 403 (the gate runs before the 404), and existing URLs can't be probed.

    // Pemilik
    Route::middleware('can:access-owner')->prefix('owner')->name('owner.')->group(function () {
        Route::livewire('/', 'pages::owner.dashboard')->name('dashboard');
        Route::livewire('verification', 'pages::owner.verification')->name('verification');
        Route::livewire('business-expenses', 'pages::business-expenses')->name('business-expenses');
        // Persediaan (F2)
        Route::livewire('stock', 'pages::owner.stock')->name('stock');
        Route::livewire('stock/{location}/{item}', 'pages::stock.card')->name('stock.card');
        Route::livewire('requests/create', 'pages::stock-requests.create')->name('requests.create');
        Route::livewire('requests/{stockRequest}', 'pages::stock-requests.show')->name('requests.show');
        // Laporan (F6, REP-2..6)
        Route::get('reports', fn () => redirect()->route('owner.reports.sales'))->name('reports');
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::livewire('sales', 'pages::reports.sales')->name('sales');
            Route::livewire('cash', 'pages::reports.cash')->name('cash');
            Route::livewire('inventory', 'pages::reports.inventory')->name('inventory');
            Route::livewire('performance', 'pages::reports.performance')->name('performance');
            Route::livewire('daily', 'pages::reports.daily-list')->name('daily');
            // Ekspor (REP-7): filter & Gate sama dengan halamannya.
            Route::get('sales/export', [ReportExportController::class, 'sales'])->name('sales.export');
            Route::get('cash/export', [ReportExportController::class, 'cash'])->name('cash.export');
            Route::get('cash/pdf', [ReportExportController::class, 'cashPdf'])->name('cash.pdf');
            Route::get('inventory/export', [ReportExportController::class, 'inventory'])->name('inventory.export');
            Route::get('performance/export', [ReportExportController::class, 'performance'])->name('performance.export');
        });
        Route::get('attendance/export', [ReportExportController::class, 'attendance'])->name('attendance.export');
        Route::livewire('attendance', 'pages::owner.attendance')->name('attendance');
        // Pengaturan: master data & user management (F1).
        Route::get('settings', fn () => redirect()->route('owner.settings.users'))->name('settings');
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::livewire('users', 'pages::owner.settings.users')->name('users');
            Route::livewire('locations', 'pages::owner.settings.locations')->name('locations');
            Route::livewire('items', 'pages::owner.settings.items')->name('items');
            Route::livewire('products', 'pages::owner.settings.products')->name('products');
            Route::livewire('products/create', 'pages::owner.settings.product-edit')->name('products.create');
            Route::livewire('products/{product}', 'pages::owner.settings.product-edit')->name('products.edit');
            Route::livewire('expense-categories', 'pages::owner.settings.expense-categories')->name('expense-categories');
            // Audit log (AUD-2): hanya baca, tidak ada route ubah/hapus.
            Route::livewire('audit-log', 'pages::owner.settings.audit-log')->name('audit-log');
        });
        Route::get('{path}', fn () => abort(404))->where('path', '.*')->name('fallback');
    });

    // Penjaga Outlet
    Route::middleware('can:access-outlet')->prefix('outlet')->name('outlet.')->group(function () {
        Route::livewire('/', 'pages::outlet.home')->name('home');
        // Absen & shift (F3)
        Route::livewire('attendance', 'pages::outlet.attendance')->name('attendance');
        Route::livewire('shift/open', 'pages::outlet.shift-open')->name('shift.open');
        Route::livewire('shift/close', 'pages::outlet.shift-close')->name('shift.close');
        // Daftar pengeluaran tetap bisa dibuka tanpa shift (melihat penolakan pemilik, CASH-5).
        Route::livewire('expenses', 'pages::outlet.expenses')->name('expenses');
        // Kasir & input kas hanya saat shift terbuka (BR-4).
        Route::middleware('open-shift')->group(function () {
            Route::livewire('cashier', 'pages::outlet.cashier')->name('cashier');
            Route::livewire('sales', 'pages::outlet.sales')->name('sales');
            Route::livewire('expenses/create', 'pages::outlet.expense-form')->name('expenses.create');
            Route::livewire('expenses/{cashEntry}/edit', 'pages::outlet.expense-form')->name('expenses.edit');
        });
        Route::livewire('stock', 'pages::outlet.stock')->name('stock');
        Route::livewire('stock/{item}', 'pages::stock.card')->name('stock.card');
        Route::livewire('requests/create', 'pages::stock-requests.create')->name('requests.create');
        Route::livewire('requests/{stockRequest}', 'pages::stock-requests.show')->name('requests.show');
        Route::get('{path}', fn () => abort(404))->where('path', '.*')->name('fallback');
    });

    // Admin Gudang
    Route::middleware('can:access-warehouse')->prefix('warehouse')->name('warehouse.')->group(function () {
        Route::livewire('/', 'pages::warehouse.home')->name('home');
        Route::livewire('stock', 'pages::warehouse.stock')->name('stock');
        Route::livewire('stock/{location}/{item}', 'pages::stock.card')->name('stock.card');
        Route::livewire('purchases', 'pages::warehouse.purchases')->name('purchases');
        Route::livewire('purchases/create', 'pages::warehouse.purchase-create')->name('purchases.create');
        Route::livewire('purchases/{purchase}', 'pages::warehouse.purchase-show')->name('purchases.show');
        Route::livewire('shipments', 'pages::warehouse.shipments')->name('shipments');
        Route::livewire('business-expenses', 'pages::business-expenses')->name('business-expenses');
        Route::livewire('requests/{stockRequest}', 'pages::stock-requests.show')->name('requests.show');
        Route::livewire('reports', 'pages::reports.inventory')->name('reports');
        Route::get('reports/export', [ReportExportController::class, 'inventory'])->name('reports.export');
        Route::get('{path}', fn () => abort(404))->where('path', '.*')->name('fallback');
    });
});

require __DIR__.'/settings.php';

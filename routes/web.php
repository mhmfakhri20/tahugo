<?php

use App\Http\Controllers\PurchaseReceiptController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth'])->group(function () {
    // Fortify "home": send every user to the home page of their role (AUTH-2).
    Route::get('dashboard', fn (Request $request) => redirect()->route($request->user()->role->homeRoute()))
        ->name('dashboard');

    // Foto nota pembelian: disk privat, hanya lewat route terotorisasi (NFR-4).
    Route::get('purchases/{purchase}/receipt', PurchaseReceiptController::class)->name('purchases.receipt');

    // Each role area ends with a catch-all so that unknown URLs in another role's area
    // also return 403 (the gate runs before the 404), and existing URLs can't be probed.

    // Pemilik
    Route::middleware('can:access-owner')->prefix('owner')->name('owner.')->group(function () {
        Route::livewire('/', 'pages::owner.dashboard')->name('dashboard');
        Route::livewire('verification', 'pages::owner.verification')->name('verification');
        // Persediaan (F2)
        Route::livewire('stock', 'pages::owner.stock')->name('stock');
        Route::livewire('stock/{location}/{item}', 'pages::stock.card')->name('stock.card');
        Route::livewire('requests/create', 'pages::stock-requests.create')->name('requests.create');
        Route::livewire('requests/{stockRequest}', 'pages::stock-requests.show')->name('requests.show');
        Route::view('reports', 'pages.coming-soon', ['title' => 'Laporan'])->name('reports');
        Route::view('attendance', 'pages.coming-soon', ['title' => 'Absensi'])->name('attendance');
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
        });
        Route::get('{path}', fn () => abort(404))->where('path', '.*')->name('fallback');
    });

    // Penjaga Outlet
    Route::middleware('can:access-outlet')->prefix('outlet')->name('outlet.')->group(function () {
        Route::livewire('/', 'pages::outlet.home')->name('home');
        Route::view('cashier', 'pages.coming-soon', ['title' => 'Kasir'])->name('cashier');
        Route::view('expenses', 'pages.coming-soon', ['title' => 'Pengeluaran'])->name('expenses');
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
        Route::livewire('requests/{stockRequest}', 'pages::stock-requests.show')->name('requests.show');
        Route::view('reports', 'pages.coming-soon', ['title' => 'Laporan Persediaan'])->name('reports');
        Route::get('{path}', fn () => abort(404))->where('path', '.*')->name('fallback');
    });
});

require __DIR__.'/settings.php';

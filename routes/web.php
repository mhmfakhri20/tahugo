<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth'])->group(function () {
    // Fortify "home": send every user to the home page of their role (AUTH-2).
    Route::get('dashboard', fn (Request $request) => redirect()->route($request->user()->role->homeRoute()))
        ->name('dashboard');

    // Each role area ends with a catch-all so that unknown URLs in another role's area
    // also return 403 (the gate runs before the 404), and existing URLs can't be probed.

    // Pemilik
    Route::middleware('can:access-owner')->prefix('owner')->name('owner.')->group(function () {
        Route::livewire('/', 'pages::owner.dashboard')->name('dashboard');
        Route::view('verification', 'pages.coming-soon', ['title' => 'Verifikasi'])->name('verification');
        Route::view('reports', 'pages.coming-soon', ['title' => 'Laporan'])->name('reports');
        Route::view('attendance', 'pages.coming-soon', ['title' => 'Absensi'])->name('attendance');
        Route::view('settings', 'pages.coming-soon', ['title' => 'Pengaturan'])->name('settings');
        Route::get('{path}', fn () => abort(404))->where('path', '.*')->name('fallback');
    });

    // Penjaga Outlet
    Route::middleware('can:access-outlet')->prefix('outlet')->name('outlet.')->group(function () {
        Route::livewire('/', 'pages::outlet.home')->name('home');
        Route::view('cashier', 'pages.coming-soon', ['title' => 'Kasir'])->name('cashier');
        Route::view('expenses', 'pages.coming-soon', ['title' => 'Pengeluaran'])->name('expenses');
        Route::view('stock', 'pages.coming-soon', ['title' => 'Stok'])->name('stock');
        Route::get('{path}', fn () => abort(404))->where('path', '.*')->name('fallback');
    });

    // Admin Gudang
    Route::middleware('can:access-warehouse')->prefix('warehouse')->name('warehouse.')->group(function () {
        Route::livewire('/', 'pages::warehouse.home')->name('home');
        Route::view('stock', 'pages.coming-soon', ['title' => 'Stok Gudang'])->name('stock');
        Route::view('purchases', 'pages.coming-soon', ['title' => 'Pembelian'])->name('purchases');
        Route::view('shipments', 'pages.coming-soon', ['title' => 'Pengiriman'])->name('shipments');
        Route::view('reports', 'pages.coming-soon', ['title' => 'Laporan Persediaan'])->name('reports');
        Route::get('{path}', fn () => abort(404))->where('path', '.*')->name('fallback');
    });
});

require __DIR__.'/settings.php';

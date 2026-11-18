<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Sensitive actions recorded in audit_logs (AUD-1).
 */
enum AuditAction: string
{
    use HasValues;

    case Login = 'auth.login';
    case LoginFailed = 'auth.login_failed';
    case Logout = 'auth.logout';
    case SaleVoid = 'sale.void';
    case ShiftClose = 'shift.close';
    case ShiftCorrect = 'shift.correct';
    case ExpenseApprove = 'expense.approve';
    case ExpenseReject = 'expense.reject';
    case StockRequestApprove = 'stock_request.approve';
    case StockRequestReject = 'stock_request.reject';
    case StockRequestShip = 'stock_request.ship';
    case StockRequestReceive = 'stock_request.receive';
    case StockAdjust = 'stock.adjust';
    case StockWaste = 'stock.waste';
    case ProductCreate = 'product.create';
    case ProductUpdate = 'product.update';
    case LocationCreate = 'location.create';
    case LocationUpdate = 'location.update';
    case LocationActivate = 'location.activate';
    case LocationDeactivate = 'location.deactivate';
    case UserCreate = 'user.create';
    case UserUpdate = 'user.update';
    case UserActivate = 'user.activate';
    case UserDeactivate = 'user.deactivate';
    case UserPasswordReset = 'user.password_reset';

    /**
     * Human readable label (Bahasa Indonesia).
     */
    public function label(): string
    {
        return match ($this) {
            self::Login => 'Masuk',
            self::LoginFailed => 'Gagal masuk',
            self::Logout => 'Keluar',
            self::SaleVoid => 'Batalkan transaksi',
            self::ShiftClose => 'Tutup shift',
            self::ShiftCorrect => 'Koreksi shift',
            self::ExpenseApprove => 'Setujui pengeluaran',
            self::ExpenseReject => 'Tolak pengeluaran',
            self::StockRequestApprove => 'Setujui permintaan stok',
            self::StockRequestReject => 'Tolak permintaan stok',
            self::StockRequestShip => 'Kirim permintaan stok',
            self::StockRequestReceive => 'Terima kiriman stok',
            self::StockAdjust => 'Penyesuaian stok',
            self::StockWaste => 'Catat bahan rusak',
            self::ProductCreate => 'Tambah produk',
            self::ProductUpdate => 'Ubah produk/harga/resep',
            self::LocationCreate => 'Tambah lokasi',
            self::LocationUpdate => 'Ubah data lokasi',
            self::LocationActivate => 'Aktifkan lokasi',
            self::LocationDeactivate => 'Nonaktifkan lokasi',
            self::UserCreate => 'Tambah pengguna',
            self::UserUpdate => 'Ubah pengguna',
            self::UserActivate => 'Aktifkan pengguna',
            self::UserDeactivate => 'Nonaktifkan pengguna',
            self::UserPasswordReset => 'Reset password',
        };
    }

    /**
     * Variant for <x-status-badge>.
     */
    public function badge(): string
    {
        return match ($this) {
            self::LoginFailed, self::SaleVoid, self::ExpenseReject, self::StockRequestReject,
            self::UserDeactivate, self::LocationDeactivate, self::StockWaste => 'danger',
            self::ShiftCorrect, self::StockAdjust, self::UserPasswordReset => 'warning',
            self::Login, self::Logout => 'muted',
            default => 'info',
        };
    }
}

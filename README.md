# Tahu Go — Sistem Operasional & Inventory

Aplikasi web *mobile-first* untuk UMKM franchise **Tahu Go** (Capstone Design Project, S1 Sistem Informasi, Universitas Telkom). Mencatat penjualan (kasir), kas & pengeluaran, persediaan bahan baku gudang → outlet, absensi selfie + GPS, dan menyajikan dashboard & laporan untuk pemilik.

- Spesifikasi: [`docs/prd.md`](docs/prd.md)
- Keputusan & asumsi implementasi: [`docs/ASSUMPTIONS.md`](docs/ASSUMPTIONS.md)
- Panduan pengguna: [`docs/USER_GUIDE.md`](docs/USER_GUIDE.md) (juga ada di menu **Bantuan** aplikasi)

## Stack

Laravel 13 · Livewire 4 + Flux UI · Tailwind CSS 4 · MySQL 8 · Pest · Chart.js · maatwebsite/excel · laravel-dompdf

## Kebutuhan

- PHP **8.3+** dengan ekstensi `pdo_mysql`, `mbstring`, `xml`, `zip`, `gd`, `fileinfo`, `bcmath`
- Composer 2
- Node.js **20+** & npm
- MySQL 8 (mis. lewat XAMPP)

## Setup untuk anggota tim

```bash
git clone <url-repo> tahugo
cd tahugo

cp .env.example .env            # Windows: copy .env.example .env
# atur DB_DATABASE / DB_USERNAME / DB_PASSWORD di .env, lalu buat database kosong "tahugo"

composer install
npm install
php artisan key:generate

php artisan migrate --seed                  # master data + akun dev + stok placeholder
php artisan db:seed --class=DemoSeeder      # opsional: 14 hari data demo untuk dashboard & laporan

composer run dev                            # server + Vite (http://localhost:8000)
```

Bila port 8000 bentrok: `php artisan serve --port=8080` dan `npm run dev` di terminal lain.

> Kamera & GPS (absen, foto nota) hanya bekerja di **HTTPS** atau `localhost`. Untuk mencoba dari HP, gunakan tunnel HTTPS.

## Akun demo (hanya lingkungan non-production)

| Peran | Username | Password |
|---|---|---|
| Pemilik | `owner` | `password` |
| Penjaga outlet Fajar Indah | `outlet_fji` | `password` |
| Penjaga outlet Batas Kota | `outlet_bkk` | `password` |
| Admin gudang | `gudang` | `password` |

Akun ini **tidak** dibuat saat `APP_ENV=production`. Sebelum mencoba absensi, isi titik lokasi & jam buka outlet di *Pengaturan › Outlet & gudang* (DemoSeeder mengisinya dengan placeholder).

## Perintah penting

| Perintah | Fungsi |
|---|---|
| `php artisan test` | Menjalankan seluruh test (Pest) |
| `vendor/bin/pint` | Merapikan format kode |
| `php artisan migrate:fresh --seed` | Reset database + seed ulang |
| `php artisan db:seed --class=DemoSeeder` | Data demo 14 hari (tidak jalan di production / bila sudah ada penjualan) |
| `php artisan stock:verify` | Cek saldo stok = total ledger (BR-3) |
| `php artisan attendance:prune` | Hapus foto absen > 90 hari (terjadwal harian) |
| `php artisan cash:backfill-purchases` | Catat pembelian lama sebagai pengeluaran usaha (CASH-7) |
| `php artisan schedule:work` | Menjalankan scheduler secara lokal (production: cron `schedule:run` tiap menit) |

## Struktur singkat

```
app/Services/      logika bisnis (StockService, SaleService, ShiftService, CashService, ReportService, AuditService, ...)
app/Policies/      otorisasi per data (BR-7)
resources/views/pages/   halaman Livewire per peran (owner, outlet, warehouse, reports)
tests/Feature/     test per fitur (Inventory, Attendance, Sales, Cash, Reports, Audit, Export, Security)
docs/              PRD, asumsi, panduan pengguna, desain
```

# PRD — Sistem Informasi Operasional & Inventory Tahu Go

> **Dokumen ini ditulis untuk AI coding agent.** Ikuti spesifikasi apa adanya. Kalau ada hal yang tidak diatur di sini, pilih solusi paling sederhana yang konsisten dengan dokumen ini dan catat asumsinya di `docs/ASSUMPTIONS.md`. Bagian bertanda `[TBD]` wajib dikonfirmasi ke tim sebelum dianggap final.

| Item | Nilai |
|---|---|
| Proyek | Capstone Design Project, S1 Sistem Informasi, Universitas Telkom (Ganjil 2026/2027) |
| Tim | Synerv: Siti Bilqis (Development), Muhammad Fakhri Abdullah (BPM), Fulca Liya Rufaidah (Data/Information) |
| Mitra | UMKM franchise Tahu Go, pemilik Ibu Nabila (Solo & Karanganyar) |
| Metode | Prototyping, ±10 minggu (M5–M14) |
| Versi PRD | 1.0 (draft) |

---

## 1. Ringkasan Produk

Aplikasi web *mobile-first* untuk mencatat **penjualan, kas (pemasukan/pengeluaran), persediaan bahan baku, distribusi gudang → outlet, dan absensi** di franchise Tahu Go secara terpusat. Aplikasi ini menggantikan pencatatan manual yang selama ini tersebar di buku, Excel, dan grup WhatsApp.

Sistem dipakai oleh tiga peran: **Pemilik**, **Penjaga Outlet**, dan **Admin Gudang**. Saat ini ada 2 outlet aktif (Fajar Indah Solo dan Batas Kota Karanganyar) plus 1 gudang/depo. Sistem harus mendukung penambahan outlet baru.

## 2. Latar Belakang & Masalah

Ringkasan dari wawancara dengan pemilik (7 Oktober 2026):

| # | Masalah | Dampak |
|---|---|---|
| P1 | Semua pencatatan manual (buku, Excel, WA), data tiap outlet terpisah | Rekap lama, data lama sulit dicari (WA error/hilang) |
| P2 | Uang setoran tidak cocok dengan jumlah tahu yang keluar, terutama untuk pesanan **paket** | Kebocoran pendapatan tidak terdeteksi |
| P3 | Karyawan memanipulasi laporan pengeluaran (contoh: mengaku beli cabai padahal tidak) | Kerugian dan kecurangan |
| P4 | Absen hanya lewat foto ke grup WA, foto bisa diedit, jam buka/tutup outlet tidak sesuai | Jam operasional tidak bisa diawasi |
| P5 | Laporan penjualan harian dibuat manual oleh penjaga outlet, paling memakan waktu | Beban kerja dan salah catat |
| P6 | Tidak ada visibilitas stok bahan baku yang akurat di gudang dan outlet | Kehabisan stok, pemborosan |
| P7 | Pemilik harus merekap manual untuk mengevaluasi omzet tiap outlet | Keputusan (tutup/relokasi outlet) kurang berbasis data |

**Prioritas dari pemilik:** (1) pencatatan pemasukan & pengeluaran, (2) sistem absen, (3) pencatatan penjualan otomatis, (4) stok bahan baku, yang menurut pemilik paling penting untuk bisa dilihat kapan saja.

## 3. Tujuan & Indikator Keberhasilan

| Tujuan | Indikator | Target |
|---|---|---|
| T1. Penjualan & kas tercatat terpusat | 100% transaksi outlet tercatat di sistem selama periode uji | Laporan harian otomatis, tanpa rekap Excel |
| T2. Persediaan gudang & outlet terpantau | Stok sistem vs hitung fisik | Jumlah kejadian selisih per minggu bisa dideteksi dan dibandingkan dengan kondisi sebelum ada sistem |
| T3. Sistem sesuai kebutuhan 3 peran | UAT per persona | Semua skenario UAT berhasil |
| T4. Mudah dipakai staf berliterasi digital rendah | Skor SUS | ≥ 68 |
| T5. Rekap lebih cepat | Waktu rekap laporan harian (menit) | Lebih cepat dibanding proses manual |
| T6. Selisih kas terdeteksi | Kejadian selisih setoran vs penjualan per minggu | Selisih tampil otomatis di laporan closing shift |

## 4. Pengguna & Peran

| Peran | Kode | Perangkat | Tugas utama di sistem |
|---|---|---|---|
| Pemilik | `owner` | HP / laptop | Dashboard, laporan per outlet, verifikasi pengeluaran, approve permintaan bahan baku, pantau absensi, kelola user/outlet/produk/bahan |
| Penjaga Outlet | `outlet_staff` | HP (Android, kuota/Wi‑Fi), **belum terbiasa digital** | Absen, buka/tutup shift, input penjualan, input pengeluaran + foto nota, lihat stok outlet, ajukan permintaan bahan, konfirmasi terima kiriman |
| Admin Gudang | `warehouse_admin` | HP / laptop | Kelola stok gudang, catat pembelian bahan, proses & kirim permintaan, laporan persediaan |

Pelanggan **bukan** pengguna sistem.

## 5. Ruang Lingkup

### 5.1 In-scope (MVP)
1. Autentikasi & hak akses berbasis peran (KF-06)
2. Master data: outlet, gudang, bahan baku, produk, resep/BOM produk, user
3. POS sederhana: transaksi penjualan dengan kanal offline / GoFood / GrabFood / ShopeeFood (KF-01)
4. Arus kas: pemasukan & pengeluaran outlet, nota wajib, verifikasi pemilik (KF-02)
5. Persediaan: stok per lokasi, pembelian, permintaan, pengiriman, penerimaan, ledger mutasi (KF-03)
6. Absensi selfie live + GPS dan shift buka/tutup outlet (KF-04)
7. Laporan & dashboard terpusat: penjualan, kas, laba-rugi sederhana, persediaan, kinerja outlet (KF-05)
8. Audit log untuk aksi sensitif

### 5.2 Out-of-scope (jangan dibangun)
- Integrasi API GoFood/GrabFood/ShopeeFood (transaksi platform diinput manual)
- Data pelanggan/CRM, promo & diskon, loyalty
- Penggajian, akuntansi penuh (jurnal, neraca), pajak
- Mode offline/PWA sinkronisasi (internet di outlet dilaporkan lancar)
- Cetak struk ke printer thermal
- Aplikasi native Android/iOS
- Stok opname/hitung fisik di dalam aplikasi *(kandidat fase 2; untuk evaluasi T2, hitung fisik dilakukan di luar sistem)*

## 6. Kebutuhan Fungsional

Prioritas: **M** = Must, **S** = Should, **C** = Could.

### 6.1 Autentikasi & Hak Akses (KF-06)

| ID | Kebutuhan | Prio |
|---|---|---|
| AUTH-1 | Login dengan username + password. Password di-hash (bcrypt/argon, bawaan Laravel). | M |
| AUTH-2 | Setelah login, user diarahkan ke beranda sesuai peran. | M |
| AUTH-3 | `outlet_staff` terikat ke **satu outlet**, hanya bisa melihat dan mengubah data outlet tersebut. | M |
| AUTH-4 | Otorisasi di backend memakai Policy/Gate Laravel, tidak cukup hanya menyembunyikan menu di UI. | M |
| AUTH-5 | Pemilik bisa membuat, mengedit, menonaktifkan user, mengatur peran & outlet, dan reset password. User tidak dihapus permanen (soft delete / `is_active`). | M |
| AUTH-6 | Pembatasan percobaan login (throttle) dan logout otomatis setelah idle. | S |

**Acceptance criteria:** penjaga Outlet A yang mengakses URL/ID milik Outlet B mendapat respons 403.

### 6.2 Master Data

| ID | Kebutuhan | Prio |
|---|---|---|
| MD-1 | **Lokasi stok**: tipe `warehouse` / `outlet`. Outlet punya nama, alamat, koordinat (lat/lng), radius absen (meter, default 100), jam buka & tutup standar, status aktif. | M |
| MD-2 | **Bahan baku** (`items`): nama, satuan (pcs, kg, gram, pack, liter, dll.), kategori (tahu / bumbu / packaging / lainnya), harga beli terakhir, batas stok minimum per lokasi. | M |
| MD-3 | **Produk jual**: nama, tipe (`single` / `paket`), harga jual **per kanal** (offline, GoFood, GrabFood, ShopeeFood; harga platform boleh berbeda), status aktif. | M |
| MD-4 | **Resep/BOM**: tiap produk punya daftar komponen bahan baku + kuantitas per 1 unit produk. Dipakai untuk pengurangan stok otomatis. | M |
| MD-5 | Kategori pengeluaran bisa dikelola pemilik (default: bahan dadakan, gas/LPG, kebersihan, listrik, parkir/retribusi, transport, lain-lain). | M |

Data awal di-*seed* dengan **placeholder** (lihat §12). Nama, harga, dan resep asli `[TBD]`.

### 6.3 Shift & Absensi (KF-04)

Alur harian outlet (pengganti foto absen & foto gerobak di grup WA):

```
Absen Masuk (selfie + GPS) → Buka Shift (input kas awal)
→ [penjualan & pengeluaran sepanjang hari]
→ Tutup Shift (hitung uang fisik, foto kondisi outlet) → Absen Pulang (selfie + GPS)
```

| ID | Kebutuhan | Prio |
|---|---|---|
| ATT-1 | Absen masuk/pulang wajib **foto selfie yang diambil langsung dari kamera** di halaman web (`getUserMedia` atau `<input type="file" accept="image/*" capture="user">`). **Tidak boleh** memilih dari galeri. | M |
| ATT-2 | Sistem mengambil lokasi GPS (`navigator.geolocation`, `enableHighAccuracy`), menghitung jarak ke koordinat outlet (Haversine) **di server**, dan menolak absen bila di luar radius. Akurasi GPS ikut disimpan. | M |
| ATT-3 | Cap waktu memakai **waktu server** (zona `Asia/Jakarta`), bukan waktu perangkat. | M |
| ATT-4 | Foto disimpan di storage privat, dikompres (maks ±1280px, JPEG), hanya bisa dilihat pemilik. Metadata yang disimpan: user, outlet, tipe, waktu server, lat/lng, akurasi, jarak, user agent. | M |
| ATT-5 | Absen yang terlambat dari jam buka standar atau pulang lebih awal ditandai (`late` / `early_leave`) dan tampil di rekap pemilik. | M |
| ATT-6 | Bila izin kamera/lokasi ditolak, tampilkan instruksi sederhana bergambar cara mengaktifkannya. | M |
| ATT-7 | Pemilik melihat rekap absensi per outlet/karyawan/periode beserta foto & peta titik absen. | M |
| SHF-1 | Penjaga hanya bisa input penjualan & pengeluaran jika ada **shift terbuka** di outletnya. Satu outlet hanya boleh punya satu shift terbuka. | M |
| SHF-2 | Buka shift: input kas awal (modal kembalian). | M |
| SHF-3 | Tutup shift: input **uang tunai fisik** yang dihitung, lalu sistem menampilkan *kas seharusnya* dan **selisih**. Wajib upload foto kondisi outlet/gerobak (kamera live). | M |
| SHF-4 | Tutup shift otomatis menghasilkan **Laporan Harian Outlet** (lihat REP-2). Laporan ini menggantikan laporan manual yang dulu dikirim ke WA. | M |
| SHF-5 | Setelah ditutup, data shift terkunci. Koreksi hanya oleh pemilik dan tercatat di audit log. | M |

**Rumus kas seharusnya (tunai):**
```
expected_cash = opening_cash
              + Σ penjualan kanal offline dengan metode tunai
              + Σ pemasukan lain (tunai)
              − Σ pengeluaran tunai shift tersebut (status apa pun selain rejected)
variance = counted_cash − expected_cash
```
Penjualan via platform pesan-antar dan QRIS **tidak** dihitung ke kas fisik.

### 6.4 Penjualan / POS (KF-01)

| ID | Kebutuhan | Prio |
|---|---|---|
| POS-1 | Layar kasir berupa **grid tombol produk besar** (minimal 48px, idealnya kartu ±100px), ketuk untuk menambah qty, tombol −/+ di keranjang. | M |
| POS-2 | Pilih **kanal**: Offline (default), GoFood, GrabFood, ShopeeFood. Harga mengikuti harga kanal (MD-3). | M |
| POS-3 | Metode bayar untuk offline: Tunai (default) atau QRIS. Untuk tunai tersedia input uang diterima → tampil kembalian. Kanal platform tidak memakai metode bayar. | M |
| POS-4 | Untuk platform: field opsional nomor/ID pesanan platform. | S |
| POS-5 | Simpan transaksi dalam **satu DB transaction**: header + item + pengurangan stok outlet via BOM + ledger mutasi. Bila salah satu gagal, semua dibatalkan. | M |
| POS-6 | Validasi stok: bila stok bahan di outlet tidak cukup untuk BOM, transaksi **ditolak** dengan pesan jelas berisi nama bahan yang kurang (sesuai skenario use case). `[TBD: apakah perlu opsi "izinkan stok minus" yang bisa diatur pemilik]` | M |
| POS-7 | Nomor transaksi otomatis: `{KODE_OUTLET}-{YYYYMMDD}-{urut}`. | M |
| POS-8 | Riwayat transaksi shift berjalan bisa dilihat penjaga. Pembatalan (void) hanya untuk transaksi di shift yang masih terbuka, wajib alasan, mengembalikan stok, dan tercatat di audit log. | M |
| POS-9 | Seluruh alur input 1 transaksi offline sederhana bisa diselesaikan dalam ≤ 5 ketukan. | S |

**Acceptance criteria:** menjual 1 "Paket 10" (BOM: 10 pcs tahu, 1 bumbu, 1 kemasan) mengurangi stok outlet tepat sebanyak itu, dan muncul di laporan dengan kanal yang benar.

### 6.5 Arus Kas (KF-02)

| ID | Kebutuhan | Prio |
|---|---|---|
| CASH-1 | Penjaga mencatat **pengeluaran**: kategori, nominal, keterangan, sumber dana (kas outlet / uang pribadi), dan **foto nota wajib** (kamera live atau galeri `[TBD: perlu dibatasi kamera live?]`). | M |
| CASH-2 | Penjaga dapat mencatat **pemasukan lain** di luar penjualan (jarang terjadi), dengan keterangan wajib. | S |
| CASH-3 | Setiap pengeluaran berstatus `pending` → diverifikasi pemilik menjadi `approved` atau `rejected` (alasan wajib bila ditolak). | M |
| CASH-4 | Halaman verifikasi pemilik: daftar pending (filter outlet/tanggal), detail berisi foto nota yang bisa di-zoom, tombol Setujui / Tolak. | M |
| CASH-5 | Pengeluaran `rejected` ditandai sebagai **selisih yang harus dipertanggungjawabkan** penjaga di laporan. | M |
| CASH-6 | Pemilik/admin dapat mencatat pengeluaran tingkat usaha (sewa, gaji, dll.) yang tidak terikat shift, untuk laporan laba-rugi. | S |
| CASH-7 | Pembelian bahan baku oleh admin gudang (INV-3) otomatis tercatat sebagai pengeluaran usaha. | M |

### 6.6 Persediaan (KF-03)

**Prinsip:** setiap perubahan stok **wajib** lewat tabel ledger `stock_movements`. Saldo `stock_balances` diperbarui di transaksi DB yang sama dan hanya berfungsi sebagai cache yang selalu bisa direkonstruksi dari ledger.

Tipe mutasi: `purchase_in`, `transfer_out`, `transfer_in`, `sale_out`, `sale_void_in`, `adjustment` (khusus pemilik, alasan wajib), `waste` (rusak/basi, alasan wajib).

| ID | Kebutuhan | Prio |
|---|---|---|
| INV-1 | Lihat stok per lokasi. Pemilik melihat semua lokasi, admin melihat gudang + semua outlet, penjaga hanya outletnya. Bahan di bawah batas minimum ditandai merah. | M |
| INV-2 | Dashboard stok menjadi info yang paling mudah diakses pemilik (widget teratas). | M |
| INV-3 | **Pembelian** (admin gudang): supplier (teks bebas), tanggal, item (bahan, qty, harga satuan), total, foto nota opsional → stok gudang bertambah. | M |
| INV-4 | **Permintaan bahan** (penjaga atau pemilik): pilih bahan + qty → status `pending`. | M |
| INV-5 | Pemilik **menyetujui** (boleh mengubah qty) atau menolak permintaan. Pemilik juga bisa langsung membuat permintaan yang sudah approved (sesuai praktik sekarang: owner yang memesan). | M |
| INV-6 | **Pengiriman** (admin): proses permintaan approved, isi qty kirim (boleh kurang dari disetujui jika stok gudang kurang, wajib catatan), tanggal → stok gudang berkurang (`transfer_out`), status `shipped`. | M |
| INV-7 | **Penerimaan** (penjaga): konfirmasi qty yang benar-benar diterima → stok outlet bertambah (`transfer_in`), status `received`. Bila qty terima ≠ qty kirim, selisih ditandai di laporan persediaan. | M |
| INV-8 | Status permintaan: `pending → approved → shipped → received` atau `rejected` / `cancelled`. Semua perubahan status mencatat siapa dan kapan. | M |
| INV-9 | Pencatatan bahan rusak/terbuang (`waste`) oleh penjaga atau admin. | S |
| INV-10 | Riwayat mutasi per bahan per lokasi (kartu stok). | M |

### 6.7 Laporan & Dashboard (KF-05)

| ID | Kebutuhan | Prio |
|---|---|---|
| REP-1 | **Dashboard pemilik**: hari ini & 7 hari terakhir per outlet, berisi omzet total & per kanal, jumlah transaksi, pengeluaran, laba kotor sederhana, selisih kas shift, stok menipis, pengeluaran pending, permintaan pending, status absensi hari ini (siapa sudah absen/terlambat). | M |
| REP-2 | **Laporan Harian Outlet** (dari tutup shift): rincian penjualan per produk & kanal, pengeluaran, kas awal, kas seharusnya, kas fisik, selisih, penggunaan bahan (hasil BOM), jam buka/tutup aktual vs standar. | M |
| REP-3 | **Laporan penjualan**: filter periode & outlet; rincian per hari, produk, kanal. | M |
| REP-4 | **Laporan arus kas & laba-rugi sederhana**: pendapatan − (pengeluaran outlet approved + pengeluaran usaha + pembelian bahan) per periode/outlet. | M |
| REP-5 | **Laporan persediaan**: saldo per lokasi, mutasi periode, pembelian, pengiriman, selisih kirim-terima, waste. | M |
| REP-6 | **Kinerja outlet**: perbandingan omzet antar outlet, pertumbuhan vs periode sebelumnya, pencapaian target omzet bulanan (target diisi pemilik). | S |
| REP-7 | Ekspor laporan ke Excel (`.xlsx`) dan/atau PDF. | S |
| REP-8 | Grafik sederhana (tren omzet harian, komposisi kanal). | C |

### 6.8 Audit Log

| ID | Kebutuhan | Prio |
|---|---|---|
| AUD-1 | Catat: login, void transaksi, koreksi shift, verifikasi pengeluaran, approve/reject permintaan, adjustment stok, perubahan user/harga/BOM. Simpan user, aksi, entitas, nilai lama/baru (JSON), IP, waktu. | M |
| AUD-2 | Halaman audit log hanya untuk pemilik, dengan filter. | S |

## 7. Kebutuhan Non-Fungsional

| ID | Kebutuhan |
|---|---|
| NFR-1 Usability (KNF-01) | Mobile-first (viewport 360px). Teks min 16px, tombol aksi utama besar dan kontras. Bahasa Indonesia sederhana tanpa istilah teknis. Ikon + label. Maks 1 aksi utama per layar. Konfirmasi untuk aksi yang tidak bisa dibatalkan. Pesan error menjelaskan cara memperbaiki. |
| NFR-2 Kompatibilitas (KNF-02) | Chrome Android terbaru & 2 versi sebelumnya, Chrome/Edge desktop. Harus berjalan di HTTPS (syarat kamera & GPS). |
| NFR-3 Kinerja (KNF-03) | Halaman kasir siap < 2 detik di 4G. Simpan transaksi < 1 detik (server). Aset dikompres, gambar di-resize di sisi klien sebelum upload. |
| NFR-4 Keamanan (KNF-04, ISO/IEC 27001) | RBAC via Policy, CSRF aktif, validasi semua input via FormRequest, file upload dibatasi tipe & ukuran (≤ 5 MB) dan disimpan di disk privat dengan akses lewat route terotorisasi, `.env` tidak di-commit, mode debug mati di production. |
| NFR-5 Privasi (UU PDP No. 27/2022) | Kumpulkan data pribadi seminimal mungkin (nama, username, no. HP opsional, foto absen, lokasi saat absen saja, **tanpa** tracking lokasi terus-menerus). Foto absen hanya bisa diakses pemilik. Retensi foto absen `[TBD, usulan 90 hari]` lalu dihapus via scheduled job. |
| NFR-6 Integritas data | Operasi multi-tabel memakai DB transaction + row locking (`lockForUpdate`) pada saldo stok. Uang disimpan sebagai integer rupiah (`BIGINT`) tanpa float. |
| NFR-7 Maintainability | Kode mengikuti konvensi Laravel, business logic di Service/Action class (bukan di controller/view), test otomatis untuk logika inti. |
| NFR-8 Dukungan (KNF-05) | Tersedia halaman "Bantuan" berisi panduan bergambar per peran dan `docs/USER_GUIDE.md`. |

## 8. Arsitektur & Tech Stack

| Lapisan | Pilihan |
|---|---|
| Backend | **Laravel 13** (versi stabil terbaru), PHP sesuai syarat Laravel 13 |
| Database | **MySQL 8** |
| Frontend | **Laravel Livewire Starter Kit**: Blade + Livewire 4 + Flux UI + Tailwind CSS (monolit, tanpa SPA terpisah) |
| Auth | Laravel Fortify (bawaan starter kit). Diubah agar login memakai `username`. **Matikan** fitur registration, email verification, dan 2FA di `config/fortify.php` (akun dibuat oleh pemilik) |
| Otorisasi | Kolom `role` (enum) + Laravel Policies/Gates (tanpa package tambahan kecuali benar-benar perlu) |
| Storage file | Laravel Storage disk `private` (lokal), bisa diganti S3-compatible |
| Ekspor | `maatwebsite/excel` (xlsx), `barryvdh/laravel-dompdf` (PDF) — **S** |
| Grafik | Chart.js — **C** |
| Testing | Pest (atau PHPUnit) + factories |
| Deployment | VPS/shared hosting dengan PHP sesuai syarat Laravel 13 + MySQL + HTTPS (Let's Encrypt). `[TBD: penyedia hosting]` |
| Zona waktu | `APP_TIMEZONE=Asia/Jakarta`, locale `id` |

Struktur kode yang diharapkan:
```
app/
  Enums/            Role, SalesChannel, PaymentMethod, MovementType, RequestStatus, ExpenseStatus, AttendanceType
  Models/
  Policies/
  Services/         StockService, SaleService, ShiftService, AttendanceService, ReportService
  Http/Requests/
  Livewire/         Owner/*, Outlet/*, Warehouse/*
database/migrations, seeders, factories
tests/Feature, tests/Unit
docs/               ASSUMPTIONS.md, USER_GUIDE.md, ERD.md
```

## 9. Model Data (rancangan awal)

> Tim Data/Information boleh menyempurnakan ERD ini. Agent memakai rancangan di bawah sebagai acuan migrasi. Semua tabel punya `id`, `created_at`, `updated_at`. Uang dalam `BIGINT` rupiah.

```
users             id, name, username (unique), phone?, password, role enum(owner,outlet_staff,warehouse_admin),
                  location_id? (FK locations, wajib untuk outlet_staff), is_active, last_login_at
locations         id, code (unique, mis. FJI, BKK, GDG), name, type enum(warehouse,outlet), address,
                  latitude?, longitude?, attendance_radius_m (default 100), open_time?, close_time?,
                  monthly_target?, is_active
items             id, name, unit, category enum(tahu,bumbu,packaging,lainnya), last_purchase_price, is_active
item_min_stocks   id, item_id, location_id, min_qty
products          id, name, type enum(single,paket), is_active
product_prices    id, product_id, channel enum(offline,gofood,grabfood,shopeefood), price
product_recipes   id, product_id, item_id, qty DECIMAL(12,3)
stock_balances    id, location_id, item_id, qty DECIMAL(12,3)            UNIQUE(location_id,item_id)
stock_movements   id, location_id, item_id, type enum(...), qty DECIMAL(12,3) (+ masuk / − keluar),
                  balance_after, reference_type, reference_id (morph), note?, user_id
shifts            id, location_id, opened_by, opened_at, opening_cash, closed_by?, closed_at?,
                  counted_cash?, expected_cash?, variance?, closing_photo_path?, status enum(open,closed)
attendances       id, user_id, location_id, shift_id?, type enum(clock_in,clock_out), server_time,
                  photo_path, latitude, longitude, accuracy_m, distance_m, flags JSON (late, early_leave), user_agent
sales             id, number (unique), location_id, shift_id, user_id, channel, payment_method enum(cash,qris)?,
                  platform_order_id?, subtotal, total, cash_received?, change?, status enum(completed,void),
                  void_reason?, voided_by?, voided_at?
sale_items        id, sale_id, product_id, qty, unit_price, line_total
expense_categories id, name, is_active
cash_entries      id, location_id?, shift_id?, user_id, direction enum(in,out), category_id?, amount,
                  description, fund_source enum(outlet_cash,personal,business)?, receipt_photo_path?,
                  status enum(pending,approved,rejected), verified_by?, verified_at?, reject_reason?,
                  source_type?, source_id? (morph, mis. purchase)
purchases         id, location_id (gudang), supplier, purchase_date, total, receipt_photo_path?, user_id
purchase_items    id, purchase_id, item_id, qty, unit_price, line_total
stock_requests    id, number, from_location_id (gudang), to_location_id (outlet), requested_by, status,
                  approved_by?, approved_at?, reject_reason?, shipped_by?, shipped_at?, received_by?, received_at?, note?
stock_request_items id, stock_request_id, item_id, qty_requested, qty_approved?, qty_shipped?, qty_received?
audit_logs        id, user_id?, action, auditable_type, auditable_id, old_values JSON?, new_values JSON?, ip, created_at
```

## 10. Aturan Bisnis Kunci (wajib diuji)

1. **BR-1 Stok via BOM:** `sale_out` per item = Σ(qty_produk × qty_resep). Void membalik mutasi persis (`sale_void_in`).
2. **BR-2 Tidak ada stok negatif** kecuali lewat `adjustment` pemilik (sampai `[TBD]` POS-6 diputuskan).
3. **BR-3 Ledger konsisten:** untuk setiap (lokasi, bahan), `stock_balances.qty == Σ stock_movements.qty`. Sediakan artisan command `stock:verify` untuk mengeceknya.
4. **BR-4 Shift wajib:** penjualan & pengeluaran outlet harus terkait ke shift terbuka di outlet user.
5. **BR-5 Absen di radius:** absen ditolak bila `distance_m > attendance_radius_m`. Absen masuk wajib dilakukan sebelum buka shift. `[TBD: apakah wajib, atau cukup diberi peringatan]`
6. **BR-6 Data terkunci:** shift tertutup, transaksi, dan pengeluaran yang sudah diverifikasi tidak bisa diedit, hanya dikoreksi pemilik dan tercatat di audit.
7. **BR-7 Isolasi outlet:** semua query untuk `outlet_staff` di-scope ke `location_id` miliknya.
8. **BR-8 Pengiriman:** `qty_shipped ≤ stok gudang`. `qty_received` boleh berbeda dari `qty_shipped` dan selisihnya dilaporkan.

## 11. Alur Layar per Peran

**Penjaga Outlet** (bottom navigation, maks 5 menu): `Beranda` (status shift, tombol besar *Absen/Buka Shift/Tutup Shift*) · `Kasir` · `Pengeluaran` · `Stok` (stok outlet, ajukan permintaan, terima kiriman) · `Akun`

**Admin Gudang:** `Beranda` (permintaan approved yang harus dikirim, stok menipis) · `Stok Gudang` · `Pembelian` · `Pengiriman` · `Laporan Persediaan`

**Pemilik:** `Dashboard` · `Verifikasi` (pengeluaran & permintaan pending, dengan badge jumlah) · `Laporan` (penjualan, kas & laba-rugi, persediaan, kinerja outlet, harian outlet) · `Absensi` · `Pengaturan` (user, outlet, bahan, produk & resep, kategori pengeluaran, audit log)

## 12. Seed Data (placeholder — ganti dengan data asli)

```
Lokasi : GDG "Gudang Solo" (warehouse), FJI "Tahu Go Fajar Indah" (outlet), BKK "Tahu Go Batas Kota Karanganyar" (outlet)
         Koordinat & jam operasional: [TBD]
Bahan  : Tahu mentah (pcs), Bumbu tabur [varian TBD] (gram), Saus (sachet), Minyak goreng (liter),
         Kemasan kecil (pcs), Kemasan besar (pcs), Plastik (pcs)
Produk : "Tahu Go Reguler" single, "Paket isi 5", "Paket isi 10"  — harga & resep [TBD]
User   : owner / outlet_fji / outlet_bkk / gudang (password dev: "password", HANYA di seeder dev)
```
Sediakan `DemoSeeder` terpisah dengan transaksi contoh 14 hari untuk demo dashboard.

## 13. Pengujian & Definition of Done

**Test otomatis wajib (Feature test):**
- Penjualan paket mengurangi stok sesuai BOM, void mengembalikannya (BR-1)
- Penjualan ditolak saat stok kurang (BR-2)
- Perhitungan `expected_cash` & `variance` saat tutup shift
- Absen di luar radius ditolak, di dalam radius diterima (BR-5)
- Penjaga Outlet A mendapat 403 saat mengakses data Outlet B (BR-7)
- Alur permintaan lengkap: pending → approved → shipped → received, saldo gudang & outlet benar (BR-8)
- `stock:verify` lolos setelah serangkaian transaksi acak (BR-3)

**UAT (dijalankan tim bersama pengguna)** mengikuti Tabel 6 proposal: pemilik melihat laporan; penjaga input transaksi & pengeluaran; admin mencatat pengiriman. Setelah itu kuesioner **SUS** (target ≥ 68).

**Definition of Done per fitur:** migrasi + model + policy + UI mobile + validasi + test lulus + tidak ada error di log + dicek di viewport 360px.

## 14. Milestone Pembangunan (untuk agent)

| Fase | Isi | Minggu |
|---|---|---|
| F0 | Setup dari Livewire Starter Kit, ubah login ke username, matikan registrasi/2FA, enum, layout per peran, seeder | M10 |
| F1 | Master data + user management + policies | M10 |
| F2 | Persediaan: ledger, pembelian, permintaan, pengiriman, penerimaan | M11 |
| F3 | Shift + absensi selfie/GPS | M11 |
| F4 | Kasir/POS + BOM + void | M12 |
| F5 | Pengeluaran + verifikasi | M12 |
| F6 | Dashboard & laporan, laporan harian outlet | M13 |
| F7 | Audit log, ekspor, halaman bantuan, hardening, deployment | M13–M14 |

Urutan dipilih supaya stok sudah ada sebelum POS. Fase boleh paralel jika ada lebih dari satu agent atau developer.

## 15. Pertanyaan Terbuka `[TBD]`

1. Daftar produk, varian bumbu, harga per kanal, dan **resep/BOM** tiap produk/paket.
2. Koordinat, radius, dan jam operasional standar tiap outlet.
3. Saat stok tidak cukup: tolak transaksi (default) atau izinkan stok minus?
4. Nota pengeluaran: wajib kamera live, atau boleh dari galeri?
5. Absen masuk sebelum buka shift: wajib atau cukup peringatan? Bagaimana jika 2 penjaga dalam 1 shift?
6. Apakah ada pembayaran QRIS di outlet?
7. Retensi foto absen (usulan 90 hari).
8. Penyedia hosting & domain untuk deployment.
9. Apakah pemilik butuh target omzet per outlet (REP-6)?

## 16. Referensi
- Proposal Capstone Design Project Ganjil 2627 — Synerv (Drive: `CP/`)
- Pertanyaan dan Jawaban Wawancara Problem Owner — Synerv (Drive: `CP/`)
- Standar: ISO/IEC/IEEE 12207, ISO/IEC 25010, ISO/IEC 27001, UU No. 27/2022 (PDP)

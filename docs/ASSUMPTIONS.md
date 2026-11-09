# Asumsi Implementasi

Catatan keputusan untuk hal yang tidak diatur PRD (lihat aturan di `CLAUDE.md`). Setiap entri menyebut fase dan alasannya. Bila tim/pemilik memutuskan lain, ubah di sini dan di kode.

## F0 — Setup

| # | Asumsi | Alasan |
|---|---|---|
| F0-1 | Kolom `email` & `email_verified_at` dihapus dari `users`; tabel `password_reset_tokens` dihapus. | §9 PRD tidak mencantumkan email; login memakai username (AUTH-1). |
| F0-2 | Fitur **lupa password via email dimatikan** (`fortify.features = []`). Password lupa → pemilik yang mereset (AUTH-5, UI di F1). | Tanpa email tidak ada kanal pengiriman tautan reset. |
| F0-3 | Username tidak peka huruf besar/kecil (`fortify.lowercase_usernames = true`, bawaan). Username disimpan huruf kecil. | Memudahkan staf berliterasi digital rendah. |
| F0-4 | Tabel `locations` (migrasi + model + seed) dibuat di F0, tanpa UI. CRUD outlet tetap di F1. | `users.location_id` butuh FK ke `locations`. |
| F0-5 | User **tidak bisa menghapus akunnya sendiri**; form hapus akun starter kit dihapus. User hanya bisa mengubah nama, no. HP, password, dan tampilan. Username/peran/outlet hanya diubah pemilik. | AUTH-5: tanpa hapus permanen, user dikelola pemilik. |
| F0-6 | Login user nonaktif ditolak dengan pesan "Akun Anda dinonaktifkan. Hubungi pemilik."; user yang dinonaktifkan saat sesi berjalan langsung di-logout pada request berikutnya (middleware `EnsureUserIsActive`). | AUTH-5 `is_active`. |
| F0-7 | URL area per peran: `/owner`, `/outlet`, `/warehouse`. `/` dan `/dashboard` mengarahkan user ke beranda perannya (`Role::homeRoute()`). | AUTH-2. Kode/URL berbahasa Inggris sesuai konvensi. |
| F0-8 | Akses area dikunci dengan Gate `access-owner`, `access-outlet`, `access-warehouse` (middleware `can:`). `outlet_staff` tanpa `location_id` ditolak (403). Isolasi per data (BR-7) akan memakai Policy di fase fitur. | AUTH-3, AUTH-4. |
| F0-9 | Menu yang fiturnya belum dibangun membuka halaman "Fitur ini sedang disiapkan" (`pages/coming-soon.blade.php`). | Supaya navigasi §11 lengkap sejak awal. |
| F0-10 | Owner & admin gudang memakai sidebar (laci di HP). Penjaga outlet memakai **bottom navigation** 5 menu; menu "Akun" membuka halaman profil yang juga berisi tombol Keluar. | §11 PRD. |
| F0-11 | Label bottom navigation berukuran 12px (ikon 24px, area sentuh ≥ 64px). Teks lain ≥ 16px. | 5 menu harus muat di lebar 360px ("Pengeluaran" tidak muat di 16px). |
| F0-12 | Koordinat, alamat, dan jam operasional outlet di seed = `null`. | `[TBD]` PRD §12/§15-2. Wajib diisi sebelum fitur absensi (F3) dipakai. |
| F0-13 | Akun dev (`owner`, `outlet_fji`, `outlet_bkk`, `gudang`, password `password`) hanya di-seed bila `APP_ENV` bukan `production` (`DevUserSeeder`). | §12 PRD. |
| F0-14 | Ditambah enum `LocationType` (warehouse/outlet) di luar daftar §8. | Dipakai kolom `locations.type`. |
| F0-15 | Teks UI Indonesia ditulis langsung di view baru; string bawaan starter kit yang masih memakai `__()` diterjemahkan di `lang/id.json`. Pesan validasi & login di `lang/id/*.php`. | Locale `id`. |
| F0-17 | Setiap area peran (`/owner/*`, `/outlet/*`, `/warehouse/*`) diakhiri route catch-all yang berada di balik Gate. URL apa pun di area peran lain → 403 (termasuk URL yang tidak ada); URL tak dikenal di area sendiri → 404. | Konsisten dengan acceptance criteria AUTH (403) dan mencegah menebak URL yang ada dari beda 403/404. |
| F0-16 | Logout otomatis saat idle memakai `SESSION_LIFETIME` bawaan (120 menit). Throttle login 5×/menit per username+IP (bawaan). | AUTH-6 (prioritas S). Nilai bisa disesuaikan lewat `.env`. |

## Design system "tahugo."

| # | Asumsi | Alasan |
|---|---|---|
| DS-1 | Ramp warna `zinc-*` Tailwind ditimpa dengan nada hangat (50 `#FBF7EE` … 200 = border, 500 = muted, 800 = text, 950 `#1C1915`). Kode aplikasi tetap hanya memakai token (`bg-canvas`, `text-muted`, dst.). | Komponen internal Flux memakai `zinc-*`; dengan ramp hangat, label/menu/border Flux otomatis sesuai tema tanpa override satu per satu. |
| DS-2 | Palet **mode gelap** diturunkan sendiri: canvas `#1C1915`, surface `#26221D`, border `#3A342C`, text `#F3EDE2`, muted `#A89E8F`, soft `#3D331C`, brown `#C49A6C`, primary tetap `#F4C542`, warna status sedikit lebih terang. | Design system hanya berisi palet terang; user memilih tetap menyediakan versi gelap. |
| DS-3 | Mode terang menjadi bawaan (`class="dark"` paksa dihapus). Mode gelap/ikuti HP dipilih lewat Pengaturan > Tampilan. | Sesuai design system; pilihan tetap disediakan. |
| DS-4 | "Pusat bantuan" (mockup login) dan widget metrik/grafik/tabel (mockup dashboard pemilik) tidak dibuat di F0; diganti empty state "segera hadir". | Belum ada halaman tujuan; widget dashboard termasuk F6 dan tidak boleh memakai data karangan. |
| DS-5 | Label login "Username" (bukan "Username atau email"); teks bantuan "Hubungi pemilik untuk mendapatkan akun." tanpa tautan. | Login hanya pakai username dan reset lewat pemilik (F0-1, F0-2). |
| DS-6 | Token radius Tailwind ditimpa: `md` = 8px, `lg` = 14px, `xl`/`2xl` = 20px. | Tombol/input Flux memakai `rounded-lg` dan kartu `rounded-xl`, jadi radius 8/14/20 berlaku otomatis. |
| DS-7 | Salam beranda (`x-greeting`) mengikuti jam Asia/Jakarta: pagi 04.00–10.59, siang 11.00–14.59, sore 15.00–17.59, malam di luar itu. | Mockup memakai "Selamat siang, …"; pembagian jam mengikuti kebiasaan umum. |
| DS-8 | Top bar desktop pemilik/gudang hanya berisi breadcrumb "Workspace / {peran}", judul halaman, dan menu profil. Lonceng notifikasi, pemilih cabang, indikator "Semua data tersinkron", dan "Pusat bantuan" dari mockup tidak dibuat. | Fitur tersebut belum ada di PRD/fase saat ini; tidak boleh menambah fitur. |
| DS-9 | Tombol **Keluar** ada di bawah sidebar (sesuai mockup); profil + menu akun pindah ke top bar kanan. Pemilik: Pengaturan dikelompokkan di "Lainnya". | Mockup dashboard pemilik/gudang. Menu tetap mengikuti §11 PRD. |
| DS-10 | Penjaga outlet tetap memakai bottom navigation, walau mockup outlet di `docs/design/penjaga_outlet` berformat desktop dengan sidebar. | CLAUDE.md: outlet mobile-first (NFR-1), mengalahkan mockup. |
| DS-11 | Komponen `x-metric-card` dibuat tetapi belum dipakai; dashboard pemilik F0 tanpa kartu metrik. | Keputusan pemilik proyek: data metrik baru ada di F6. |
| DS-12 | Layout auth `card` & `split` bawaan starter kit dihapus (tidak dipakai, memakai warna Tailwind bawaan). | Semua halaman wajib memakai token. |
| DS-13 | Toast flash (`x-flash`) hanya membaca `session('success')`. `session('status')` dari Fortify berisi kode (mis. `password-updated`) dan hanya ditampilkan di halaman auth lewat `x-auth-session-status`. | Mencegah kode teknis tampil ke pengguna. |

## F1 — Master data, manajemen user, policies

| # | Asumsi | Alasan |
|---|---|---|
| F1-1 | Satuan bahan (`items.unit`) berupa teks bebas (maks 20 karakter); form menyarankan pcs, kg, gram, pack, liter, sachet. | MD-2 menyebut "dll.", jadi daftar satuan tidak tertutup. |
| F1-2 | Harga di **keempat kanal wajib** diisi (boleh Rp0). Produk placeholder di-seed dengan harga Rp0 di semua kanal dan resep kosong. | Keputusan pemilik proyek; harga & resep asli `[TBD]` §15-1. |
| F1-3 | Master data dan user **tidak pernah dihapus**; hanya diaktifkan/nonaktifkan (`is_active`). | AUTH-5 dan menjaga riwayat transaksi/ledger. |
| F1-4 | Pemilik tidak bisa menonaktifkan akunnya sendiri atau mengubah perannya sendiri. Karena itu selalu ada minimal satu pemilik aktif. | Mencegah terkunci dari sistem. |
| F1-5 | Penjaga outlet wajib ditautkan ke **outlet aktif** (bukan gudang). Peran lain otomatis tanpa lokasi. Mengaktifkan kembali penjaga yang outletnya nonaktif ditolak. | AUTH-3, BR-7. |
| F1-6 | Outlet yang masih punya penjaga aktif tidak bisa dinonaktifkan atau diubah menjadi gudang. | Penjaga tanpa outlet aktif tidak bisa bekerja (F0-8). |
| F1-7 | Reset password oleh pemilik langsung mengeluarkan user dari semua perangkat (sesi di tabel `sessions` dihapus, `remember_token` diganti). | AUTH-5; mencegah sesi lama tetap aktif. |
| F1-8 | Validasi input memakai `validate()` di komponen Livewire + aturan bisnis di Service (`app/Services`), bukan FormRequest. | Form di Livewire tidak lewat HTTP controller; FormRequest tetap dipakai bila nanti ada controller. |
| F1-9 | Akses **baca** untuk peran lain sudah disiapkan di Policy: bahan & produk (semua peran), lokasi (admin gudang semua, penjaga hanya outletnya), kategori pengeluaran (penjaga). Halamannya dibuat di F2–F5. | Dipakai stok, kasir, dan pengeluaran. |
| F1-10 | Perubahan user/harga/BOM **belum** dicatat ke audit log. | Audit log (AUD-1) dijadwalkan di F7. |
| F1-11 | Nama bahan, nama produk, nama kategori pengeluaran, kode lokasi, dan username harus unik. Kode lokasi disimpan huruf besar (maks 10 huruf/angka). | Menghindari data ganda; kode dipakai di nomor transaksi (POS-7). |
| F1-12 | Radius absen 10–5.000 m (default 100). Jam buka/tutup dan target omzet hanya untuk outlet; jam tutup harus setelah jam buka (outlet lewat tengah malam belum didukung). | MD-1; jam operasional asli `[TBD]`. |
| F1-13 | Seeder master data memakai `firstOrCreate`, sehingga menjalankan seeder ulang tidak menimpa harga/data yang sudah diubah pemilik. Master data juga di-seed di production (bukan akun). | §12; aman dijalankan berulang. |
| F1-14 | Stok minimum disimpan per lokasi (`item_min_stocks`); dikosongkan = tidak diawasi di lokasi itu. | MD-2. |
| F1-15 | Notifikasi hasil aksi di halaman Livewire memakai event browser `notify` → toast melayang (`x-flash`), sedangkan setelah redirect memakai `session('success')`. | Sesuai desain toast tahugo. dan tidak perlu memuat ulang halaman. |
| F1-16 | Kategori pengeluaran bisa dinonaktifkan tanpa dialog konfirmasi (dampaknya kecil dan bisa dibalik); aksi nonaktifkan lainnya selalu memakai dialog. | NFR-1: konfirmasi untuk aksi berdampak. |

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

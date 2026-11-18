# Panduan Pengguna Tahu Go

Panduan per peran. Isi yang sama tampil di menu **Bantuan** aplikasi (sumber: `app/Support/HelpContent.php`).

## Daftar isi

- [Penjaga Outlet](#penjaga-outlet)
- [Admin Gudang](#admin-gudang)
- [Pemilik](#pemilik)
- [Masalah umum](#masalah-umum)

## Penjaga Outlet

> Urutan kerja setiap hari: Absen masuk → Buka shift → Jualan di Kasir & catat Pengeluaran → Tutup shift → Absen pulang. Tombol langkah berikutnya selalu muncul besar di Beranda.

### 1. Absen masuk

**Kapan:** Begitu tiba di outlet, sebelum membuka shift.

1. Buka menu Beranda (ikon rumah di bawah layar).
2. Ketuk tombol kuning "Absen masuk".
3. Bila HP bertanya, ketuk "Izinkan" untuk kamera dan lokasi.
4. Tunggu sampai tertulis "Lokasi ditemukan" dengan warna hijau.
5. Arahkan kamera ke wajah Anda, lalu ketuk "Ambil foto". Bila kurang jelas, ketuk "Ulangi foto".
6. Ketuk "Absen masuk sekarang". Anda kembali ke Beranda dan jam masuk tercatat.

**Perhatikan:**

- Foto harus diambil langsung dari kamera; foto dari galeri tidak bisa dipakai.
- Jam absen memakai jam server (WIB), bukan jam di HP. Lewat dari jam buka outlet ditandai "Terlambat".

### 2. Buka shift

**Kapan:** Setelah absen masuk, sebelum mulai berjualan.

1. Di Beranda, ketuk tombol "Buka shift".
2. Hitung uang kembalian yang ada di laci.
3. Tulis jumlahnya di kolom "Kas awal". Tulis 0 bila laci kosong.
4. Ketuk "Buka shift sekarang". Status di Beranda berubah menjadi "Shift buka".

**Perhatikan:**

- Satu outlet hanya punya satu shift. Bila ada dua penjaga, penjaga kedua cukup Absen masuk; ia otomatis ikut shift yang sudah buka.

### 3. Mencatat penjualan (Kasir)

**Kapan:** Setiap ada pembeli, selama shift buka.

1. Buka menu Kasir.
2. Pilih kanal di bagian atas: Offline (beli langsung), GoFood, GrabFood, atau ShopeeFood. Harga menyesuaikan kanal.
3. Ketuk kartu produk yang dibeli. Setiap ketukan menambah 1. Jumlah tampil di pojok kartu.
4. Ketuk tombol kuning "Bayar" di bagian bawah.
5. Periksa daftar pesanan; ubah jumlah dengan tombol − dan +.
6. Untuk Offline pilih cara bayar: Tunai atau QRIS.
7. Tunai: ketuk "Uang pas" atau tombol nominal (10rb, 20rb, 50rb, 100rb), atau tulis uang yang diterima. Kembalian tampil otomatis.
8. Untuk GoFood/GrabFood/ShopeeFood boleh menulis nomor pesanan dari aplikasi ojek online.
9. Ketuk "Selesaikan". Muncul tulisan "Transaksi tersimpan" beserta nomor transaksi dan kembalian.

**Perhatikan:**

- Kartu abu-abu bertuliskan "Resep belum diatur", "Harga belum diatur", atau "Stok bahan kurang" tidak bisa dijual. Hubungi pemilik.
- Salah input? Ketuk "Riwayat" di Kasir, lalu "Batalkan" pada transaksi itu dan tulis alasannya.

### 4. Mencatat pengeluaran

**Kapan:** Setiap kali membeli sesuatu untuk keperluan outlet (bahan dadakan, gas, kebersihan, parkir, dll.).

1. Buka menu Pengeluaran, ketuk "Catat pengeluaran".
2. Pilih kategori yang paling sesuai.
3. Tulis nominal sesuai nota dan keterangan singkat, misalnya "beli cabai 1/4 kg".
4. Pilih sumber dana: "Kas outlet" bila memakai uang laci, atau "Uang pribadi" bila memakai uang Anda sendiri.
5. Foto nota dengan kamera sampai tulisan dan angkanya terbaca jelas.
6. Ketuk "Kirim pengeluaran". Statusnya "Menunggu" sampai diperiksa pemilik.

**Perhatikan:**

- Pengeluaran yang ditolak pemilik muncul di bagian "Harus dipertanggungjawabkan" lengkap dengan alasannya.
- Uang jarang masuk ke laci selain dari penjualan (misalnya titipan pemilik)? Catat lewat tombol "Pemasukan lain".

### 5. Meminta bahan & menerima kiriman

**Kapan:** Saat bahan di outlet menipis, dan saat kiriman dari gudang tiba.

1. Buka menu Stok. Bahan bertanda merah atau kuning berarti habis atau menipis.
2. Ketuk "Minta stok", isi jumlah bahan yang dibutuhkan, lalu kirim. Pemilik akan menyetujui.
3. Saat kiriman datang, buka permintaan yang berstatus "Dikirim".
4. Hitung barang yang benar-benar diterima, lalu tulis jumlahnya.
5. Bila jumlahnya berbeda dari yang dikirim, tulis catatan singkat (misalnya "2 pcs rusak").
6. Ketuk "Konfirmasi terima". Stok outlet bertambah sesuai jumlah yang Anda tulis.

**Perhatikan:**

- Bahan rusak atau basi dicatat lewat tombol "Catat rusak" di menu Stok, dengan alasannya.

### 6. Tutup shift & absen pulang

**Kapan:** Di akhir jam buka, setelah transaksi terakhir.

1. Di Beranda, ketuk tombol "Tutup shift".
2. Periksa ringkasan penjualan dan uang di laci yang tampil di layar.
3. Hitung semua uang tunai di laci (termasuk kas awal), lalu tulis di kolom "Uang tunai fisik di laci".
4. Lihat angka "Selisih": hijau berarti pas, merah berarti uang kurang.
5. Foto kondisi outlet/gerobak dengan kamera.
6. Ketuk "Tutup shift" lalu "Ya, tutup shift". Laporan harian dibuat otomatis.
7. Kembali ke Beranda, ketuk "Absen pulang", ambil selfie, lalu ketuk "Absen pulang sekarang".

**Perhatikan:**

- Setelah ditutup, data shift tidak bisa diubah. Pastikan semua penjualan dan pengeluaran sudah dicatat.
- Bila ada rekan yang masih berjaga, Anda boleh absen pulang lebih dulu; penjaga terakhir yang menutup shift.
- Laporan harian bisa dilihat dari tombol "Laporan harian" di Beranda.

## Admin Gudang

> Tugas utama: mencatat bahan yang dibeli, mengirim bahan ke outlet sesuai permintaan yang sudah disetujui pemilik, dan memantau stok gudang.

### 1. Mencatat pembelian bahan

**Kapan:** Setiap kali bahan baku dibeli untuk gudang.

1. Buka menu Pembelian, klik "Catat pembelian".
2. Isi nama supplier dan tanggal pembelian.
3. Pilih bahan, isi jumlah dan harga satuan. Klik "Tambah bahan" bila lebih dari satu jenis.
4. Periksa total di sebelah kanan.
5. Lampirkan foto nota bila ada (boleh dilewati).
6. Klik "Simpan pembelian".

**Perhatikan:**

- Stok gudang langsung bertambah dan harga beli terakhir bahan diperbarui.
- Pembelian otomatis tercatat sebagai pengeluaran usaha; tidak perlu dicatat dua kali.

### 2. Mengirim bahan ke outlet

**Kapan:** Saat ada permintaan yang sudah disetujui pemilik.

1. Buka menu Pengiriman. Permintaan yang siap dikirim tampil di daftar.
2. Klik permintaan untuk membuka rinciannya.
3. Isi jumlah yang benar-benar dikirim untuk setiap bahan.
4. Bila jumlah kirim kurang dari yang disetujui (misalnya stok gudang kurang), tulis catatannya.
5. Klik "Kirim". Stok gudang berkurang dan status menjadi "Dikirim".

**Perhatikan:**

- Jumlah kirim tidak boleh melebihi stok gudang. Catat pembelian dulu bila stok kurang.
- Penjaga outlet akan mengonfirmasi jumlah yang diterima; selisihnya tampil di laporan persediaan.

### 3. Stok gudang, pengeluaran usaha & laporan

**Kapan:** Kapan saja untuk memantau persediaan.

1. Buka menu Stok Gudang untuk melihat sisa bahan. Bahan bertanda merah/kuning perlu dibeli.
2. Klik nama bahan untuk melihat kartu stok (riwayat keluar-masuk).
3. Bahan rusak di gudang dicatat lewat "Catat rusak" dengan alasannya.
4. Biaya lain di luar pembelian bahan (misalnya sewa gudang) dicatat lewat Pembelian › "Pengeluaran usaha".
5. Buka "Laporan Persediaan", pilih periode dan lokasi, lalu klik "Ekspor Excel" bila perlu dikirim.

## Pemilik

> Tugas utama: memeriksa pengeluaran & permintaan bahan dari outlet, memantau penjualan, kas, stok, dan absensi lewat Dashboard & Laporan, serta mengatur data usaha.

### 1. Memeriksa pengeluaran outlet

**Kapan:** Setiap hari, saat angka muncul di menu Verifikasi.

1. Buka menu Verifikasi, pilih tab "Pengeluaran".
2. Klik "Tinjau" pada pengeluaran yang berstatus "Menunggu".
3. Periksa foto nota (klik foto untuk membukanya dalam ukuran besar), nominal, kategori, dan keterangan.
4. Klik "Setujui" bila sesuai.
5. Bila tidak sesuai, tulis alasannya lalu klik "Tolak pengeluaran". Penjaga akan melihat alasan itu.

**Perhatikan:**

- Pengeluaran yang ditolak menambah kekurangan kas shift dan tampil sebagai "Selisih setelah verifikasi".

### 2. Menyetujui permintaan bahan

**Kapan:** Saat outlet mengajukan permintaan bahan.

1. Buka menu Verifikasi, tab "Permintaan bahan".
2. Klik "Tinjau" pada permintaan yang menunggu.
3. Ubah jumlah bila perlu (isi 0 untuk bahan yang tidak disetujui).
4. Klik "Setujui", atau "Tolak" dengan alasan. Permintaan yang disetujui akan dikirim admin gudang.

**Perhatikan:**

- Anda juga bisa langsung membuat permintaan yang sudah disetujui lewat tombol "Buat permintaan".

### 3. Memantau usaha & laporan

**Kapan:** Kapan saja.

1. Dashboard: stok menipis, omzet hari ini dibanding kemarin, grafik 7 hari, pengeluaran menunggu, dan absensi hari ini. Pilih cabang di kanan atas.
2. Menu Laporan: pilih tab Penjualan, Kas & laba-rugi, Persediaan, Kinerja outlet, atau Harian outlet.
3. Atur periode (tanggal atau tombol cepat "7 hari", "Bulan ini") dan cabang di bagian atas.
4. Klik "Ekspor Excel" atau "Unduh PDF" untuk menyimpan laporan.
5. Harian outlet: buka laporan satu shift untuk melihat penjualan, kas, dan selisihnya; bisa dicetak.

**Perhatikan:**

- Salah hitung kas di suatu shift? Buka laporan harian shift itu, klik "Koreksi kas fisik", isi jumlah yang benar dan alasannya. Koreksi tercatat di Audit log.

### 4. Memantau absensi

**Kapan:** Setiap hari atau saat perlu rekap.

1. Buka menu Absensi.
2. Atur tanggal, cabang, karyawan, atau status (terlambat/pulang awal).
3. Klik foto untuk memperbesar, atau "Lihat di peta" untuk melihat lokasi absen.
4. Tab "Ringkasan shift" menampilkan jam buka/tutup, kas, dan selisih per shift.
5. Klik "Ekspor rekap" untuk mengunduh Excel.

### 5. Mengelola data usaha & pengguna

**Kapan:** Saat ada outlet, pegawai, produk, atau harga baru.

1. Buka menu Pengaturan.
2. Pengguna: tambah akun, ubah peran/outlet, nonaktifkan, atau reset password.
3. Outlet & gudang: isi alamat, titik lokasi (lintang/bujur), radius absen, jam buka-tutup, dan target omzet bulanan.
4. Bahan baku: nama, satuan, kategori, dan stok minimum per lokasi.
5. Produk & resep: harga untuk setiap kanal dan bahan yang dipakai untuk 1 porsi.
6. Audit log: lihat siapa melakukan apa dan kapan (masuk, verifikasi, perubahan harga, dll.).

**Perhatikan:**

- Titik lokasi dan jam buka outlet wajib diisi agar penjaga bisa absen.
- Pengguna dan data tidak dihapus, hanya dinonaktifkan, supaya riwayat tetap utuh.

## Masalah umum

### Izin kamera atau lokasi ditolak

Ketuk ikon gembok di sebelah kiri alamat web (paling atas layar) → pilih "Izin" atau "Setelan situs" → ubah Kamera dan Lokasi menjadi "Izinkan" → kembali ke halaman lalu ketuk "Muat ulang". Pastikan juga Lokasi/GPS di HP menyala (tarik layar dari atas, ketuk ikon Lokasi).

### Absen ditolak karena di luar radius

Absen hanya bisa dilakukan di outlet. Pastikan Anda sudah berada di outlet, keluar ke tempat terbuka agar sinyal GPS lebih tepat, tunggu beberapa detik, lalu ketuk "Cari lokasi lagi". Bila tetap ditolak padahal Anda sudah di outlet, minta pemilik memeriksa titik lokasi outlet di Pengaturan.

### Muncul pesan "Sinyal GPS kurang akurat"

HP belum mendapat lokasi yang tepat. Nyalakan Wi-Fi dan GPS, keluar dari ruangan tertutup, tunggu 10–20 detik, lalu ketuk "Cari lokasi lagi".

### Kasir menolak transaksi karena stok tidak cukup

Pesan menyebut nama bahan yang kurang. Buka menu Stok untuk melihat sisa bahan. Bila ada kiriman yang sudah datang, konfirmasi penerimaannya dulu. Bila memang habis, ajukan permintaan bahan ke gudang dan beri tahu pemilik.

### Kasir atau Pengeluaran tidak bisa dibuka

Kasir dan pencatatan pengeluaran hanya bisa dipakai saat shift sedang buka. Buka Beranda, lakukan Absen masuk lalu Buka shift.

### Salah memasukkan transaksi

Buka Kasir → Riwayat, cari transaksinya, ketuk "Batalkan" dan tulis alasannya. Stok bahan otomatis dikembalikan. Pembatalan hanya bisa selama shift masih buka.

### Salah mencatat pengeluaran

Selama pengeluaran masih "Menunggu" dan shift masih buka, ketuk "Ubah" atau "Hapus" di menu Pengeluaran. Setelah diperiksa pemilik atau shift ditutup, data tidak bisa diubah lagi; hubungi pemilik.

### Lupa password

Hubungi pemilik. Pemilik bisa membuat password baru lewat Pengaturan › Pengguna › Reset password, lalu memberikannya kepada Anda.

### Tiba-tiba keluar dari aplikasi

Demi keamanan, aplikasi keluar otomatis bila lama tidak dipakai (penjaga outlet 10 jam, pemilik & admin gudang 1 jam). Masuk lagi dengan username dan password Anda; data yang sudah tersimpan tidak hilang.

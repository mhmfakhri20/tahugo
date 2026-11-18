<?php

namespace App\Support;

use App\Enums\Role;

/**
 * Help page content per role (NFR-8), text only. The same text is in docs/USER_GUIDE.md
 * (generated from this class, see F7a-12 in docs/ASSUMPTIONS.md).
 *
 * Each task: title, when (kapan dilakukan), steps (numbered), tips (hal yang perlu diperhatikan).
 */
final class HelpContent
{
    /**
     * @return list<array{title: string, when: string, steps: list<string>, tips: list<string>}>
     */
    public static function tasks(Role $role): array
    {
        return match ($role) {
            Role::OutletStaff => self::outletStaff(),
            Role::WarehouseAdmin => self::warehouseAdmin(),
            Role::Owner => self::owner(),
        };
    }

    /**
     * Daily order of work, shown above the tasks.
     */
    public static function summary(Role $role): string
    {
        return match ($role) {
            Role::OutletStaff => 'Urutan kerja setiap hari: Absen masuk → Buka shift → Jualan di Kasir & catat Pengeluaran → Tutup shift → Absen pulang. Tombol langkah berikutnya selalu muncul besar di Beranda.',
            Role::WarehouseAdmin => 'Tugas utama: mencatat bahan yang dibeli, mengirim bahan ke outlet sesuai permintaan yang sudah disetujui pemilik, dan memantau stok gudang.',
            Role::Owner => 'Tugas utama: memeriksa pengeluaran & permintaan bahan dari outlet, memantau penjualan, kas, stok, dan absensi lewat Dashboard & Laporan, serta mengatur data usaha.',
        };
    }

    /**
     * @return list<array{title: string, answer: string}>
     */
    public static function problems(): array
    {
        return [
            ['title' => 'Izin kamera atau lokasi ditolak', 'answer' => 'Ketuk ikon gembok di sebelah kiri alamat web (paling atas layar) → pilih "Izin" atau "Setelan situs" → ubah Kamera dan Lokasi menjadi "Izinkan" → kembali ke halaman lalu ketuk "Muat ulang". Pastikan juga Lokasi/GPS di HP menyala (tarik layar dari atas, ketuk ikon Lokasi).'],
            ['title' => 'Absen ditolak karena di luar radius', 'answer' => 'Absen hanya bisa dilakukan di outlet. Pastikan Anda sudah berada di outlet, keluar ke tempat terbuka agar sinyal GPS lebih tepat, tunggu beberapa detik, lalu ketuk "Cari lokasi lagi". Bila tetap ditolak padahal Anda sudah di outlet, minta pemilik memeriksa titik lokasi outlet di Pengaturan.'],
            ['title' => 'Muncul pesan "Sinyal GPS kurang akurat"', 'answer' => 'HP belum mendapat lokasi yang tepat. Nyalakan Wi-Fi dan GPS, keluar dari ruangan tertutup, tunggu 10–20 detik, lalu ketuk "Cari lokasi lagi".'],
            ['title' => 'Kasir menolak transaksi karena stok tidak cukup', 'answer' => 'Pesan menyebut nama bahan yang kurang. Buka menu Stok untuk melihat sisa bahan. Bila ada kiriman yang sudah datang, konfirmasi penerimaannya dulu. Bila memang habis, ajukan permintaan bahan ke gudang dan beri tahu pemilik.'],
            ['title' => 'Kasir atau Pengeluaran tidak bisa dibuka', 'answer' => 'Kasir dan pencatatan pengeluaran hanya bisa dipakai saat shift sedang buka. Buka Beranda, lakukan Absen masuk lalu Buka shift.'],
            ['title' => 'Salah memasukkan transaksi', 'answer' => 'Buka Kasir → Riwayat, cari transaksinya, ketuk "Batalkan" dan tulis alasannya. Stok bahan otomatis dikembalikan. Pembatalan hanya bisa selama shift masih buka.'],
            ['title' => 'Salah mencatat pengeluaran', 'answer' => 'Selama pengeluaran masih "Menunggu" dan shift masih buka, ketuk "Ubah" atau "Hapus" di menu Pengeluaran. Setelah diperiksa pemilik atau shift ditutup, data tidak bisa diubah lagi; hubungi pemilik.'],
            ['title' => 'Lupa password', 'answer' => 'Hubungi pemilik. Pemilik bisa membuat password baru lewat Pengaturan › Pengguna › Reset password, lalu memberikannya kepada Anda.'],
            ['title' => 'Tiba-tiba keluar dari aplikasi', 'answer' => 'Demi keamanan, aplikasi keluar otomatis bila lama tidak dipakai (penjaga outlet 10 jam, pemilik & admin gudang 1 jam). Masuk lagi dengan username dan password Anda; data yang sudah tersimpan tidak hilang.'],
        ];
    }

    /**
     * @return list<array{title: string, when: string, steps: list<string>, tips: list<string>}>
     */
    private static function outletStaff(): array
    {
        return [
            [
                'title' => 'Absen masuk',
                'when' => 'Begitu tiba di outlet, sebelum membuka shift.',
                'steps' => [
                    'Buka menu Beranda (ikon rumah di bawah layar).',
                    'Ketuk tombol kuning "Absen masuk".',
                    'Bila HP bertanya, ketuk "Izinkan" untuk kamera dan lokasi.',
                    'Tunggu sampai tertulis "Lokasi ditemukan" dengan warna hijau.',
                    'Arahkan kamera ke wajah Anda, lalu ketuk "Ambil foto". Bila kurang jelas, ketuk "Ulangi foto".',
                    'Ketuk "Absen masuk sekarang". Anda kembali ke Beranda dan jam masuk tercatat.',
                ],
                'tips' => [
                    'Foto harus diambil langsung dari kamera; foto dari galeri tidak bisa dipakai.',
                    'Jam absen memakai jam server (WIB), bukan jam di HP. Lewat dari jam buka outlet ditandai "Terlambat".',
                ],
            ],
            [
                'title' => 'Buka shift',
                'when' => 'Setelah absen masuk, sebelum mulai berjualan.',
                'steps' => [
                    'Di Beranda, ketuk tombol "Buka shift".',
                    'Hitung uang kembalian yang ada di laci.',
                    'Tulis jumlahnya di kolom "Kas awal". Tulis 0 bila laci kosong.',
                    'Ketuk "Buka shift sekarang". Status di Beranda berubah menjadi "Shift buka".',
                ],
                'tips' => [
                    'Satu outlet hanya punya satu shift. Bila ada dua penjaga, penjaga kedua cukup Absen masuk; ia otomatis ikut shift yang sudah buka.',
                ],
            ],
            [
                'title' => 'Mencatat penjualan (Kasir)',
                'when' => 'Setiap ada pembeli, selama shift buka.',
                'steps' => [
                    'Buka menu Kasir.',
                    'Pilih kanal di bagian atas: Offline (beli langsung), GoFood, GrabFood, atau ShopeeFood. Harga menyesuaikan kanal.',
                    'Ketuk kartu produk yang dibeli. Setiap ketukan menambah 1. Jumlah tampil di pojok kartu.',
                    'Ketuk tombol kuning "Bayar" di bagian bawah.',
                    'Periksa daftar pesanan; ubah jumlah dengan tombol − dan +.',
                    'Untuk Offline pilih cara bayar: Tunai atau QRIS.',
                    'Tunai: ketuk "Uang pas" atau tombol nominal (10rb, 20rb, 50rb, 100rb), atau tulis uang yang diterima. Kembalian tampil otomatis.',
                    'Untuk GoFood/GrabFood/ShopeeFood boleh menulis nomor pesanan dari aplikasi ojek online.',
                    'Ketuk "Selesaikan". Muncul tulisan "Transaksi tersimpan" beserta nomor transaksi dan kembalian.',
                ],
                'tips' => [
                    'Kartu abu-abu bertuliskan "Resep belum diatur", "Harga belum diatur", atau "Stok bahan kurang" tidak bisa dijual. Hubungi pemilik.',
                    'Salah input? Ketuk "Riwayat" di Kasir, lalu "Batalkan" pada transaksi itu dan tulis alasannya.',
                ],
            ],
            [
                'title' => 'Mencatat pengeluaran',
                'when' => 'Setiap kali membeli sesuatu untuk keperluan outlet (bahan dadakan, gas, kebersihan, parkir, dll.).',
                'steps' => [
                    'Buka menu Pengeluaran, ketuk "Catat pengeluaran".',
                    'Pilih kategori yang paling sesuai.',
                    'Tulis nominal sesuai nota dan keterangan singkat, misalnya "beli cabai 1/4 kg".',
                    'Pilih sumber dana: "Kas outlet" bila memakai uang laci, atau "Uang pribadi" bila memakai uang Anda sendiri.',
                    'Foto nota dengan kamera sampai tulisan dan angkanya terbaca jelas.',
                    'Ketuk "Kirim pengeluaran". Statusnya "Menunggu" sampai diperiksa pemilik.',
                ],
                'tips' => [
                    'Pengeluaran yang ditolak pemilik muncul di bagian "Harus dipertanggungjawabkan" lengkap dengan alasannya.',
                    'Uang jarang masuk ke laci selain dari penjualan (misalnya titipan pemilik)? Catat lewat tombol "Pemasukan lain".',
                ],
            ],
            [
                'title' => 'Meminta bahan & menerima kiriman',
                'when' => 'Saat bahan di outlet menipis, dan saat kiriman dari gudang tiba.',
                'steps' => [
                    'Buka menu Stok. Bahan bertanda merah atau kuning berarti habis atau menipis.',
                    'Ketuk "Minta stok", isi jumlah bahan yang dibutuhkan, lalu kirim. Pemilik akan menyetujui.',
                    'Saat kiriman datang, buka permintaan yang berstatus "Dikirim".',
                    'Hitung barang yang benar-benar diterima, lalu tulis jumlahnya.',
                    'Bila jumlahnya berbeda dari yang dikirim, tulis catatan singkat (misalnya "2 pcs rusak").',
                    'Ketuk "Konfirmasi terima". Stok outlet bertambah sesuai jumlah yang Anda tulis.',
                ],
                'tips' => [
                    'Bahan rusak atau basi dicatat lewat tombol "Catat rusak" di menu Stok, dengan alasannya.',
                ],
            ],
            [
                'title' => 'Tutup shift & absen pulang',
                'when' => 'Di akhir jam buka, setelah transaksi terakhir.',
                'steps' => [
                    'Di Beranda, ketuk tombol "Tutup shift".',
                    'Periksa ringkasan penjualan dan uang di laci yang tampil di layar.',
                    'Hitung semua uang tunai di laci (termasuk kas awal), lalu tulis di kolom "Uang tunai fisik di laci".',
                    'Lihat angka "Selisih": hijau berarti pas, merah berarti uang kurang.',
                    'Foto kondisi outlet/gerobak dengan kamera.',
                    'Ketuk "Tutup shift" lalu "Ya, tutup shift". Laporan harian dibuat otomatis.',
                    'Kembali ke Beranda, ketuk "Absen pulang", ambil selfie, lalu ketuk "Absen pulang sekarang".',
                ],
                'tips' => [
                    'Setelah ditutup, data shift tidak bisa diubah. Pastikan semua penjualan dan pengeluaran sudah dicatat.',
                    'Bila ada rekan yang masih berjaga, Anda boleh absen pulang lebih dulu; penjaga terakhir yang menutup shift.',
                    'Laporan harian bisa dilihat dari tombol "Laporan harian" di Beranda.',
                ],
            ],
        ];
    }

    /**
     * @return list<array{title: string, when: string, steps: list<string>, tips: list<string>}>
     */
    private static function warehouseAdmin(): array
    {
        return [
            [
                'title' => 'Mencatat pembelian bahan',
                'when' => 'Setiap kali bahan baku dibeli untuk gudang.',
                'steps' => [
                    'Buka menu Pembelian, klik "Catat pembelian".',
                    'Isi nama supplier dan tanggal pembelian.',
                    'Pilih bahan, isi jumlah dan harga satuan. Klik "Tambah bahan" bila lebih dari satu jenis.',
                    'Periksa total di sebelah kanan.',
                    'Lampirkan foto nota bila ada (boleh dilewati).',
                    'Klik "Simpan pembelian".',
                ],
                'tips' => [
                    'Stok gudang langsung bertambah dan harga beli terakhir bahan diperbarui.',
                    'Pembelian otomatis tercatat sebagai pengeluaran usaha; tidak perlu dicatat dua kali.',
                ],
            ],
            [
                'title' => 'Mengirim bahan ke outlet',
                'when' => 'Saat ada permintaan yang sudah disetujui pemilik.',
                'steps' => [
                    'Buka menu Pengiriman. Permintaan yang siap dikirim tampil di daftar.',
                    'Klik permintaan untuk membuka rinciannya.',
                    'Isi jumlah yang benar-benar dikirim untuk setiap bahan.',
                    'Bila jumlah kirim kurang dari yang disetujui (misalnya stok gudang kurang), tulis catatannya.',
                    'Klik "Kirim". Stok gudang berkurang dan status menjadi "Dikirim".',
                ],
                'tips' => [
                    'Jumlah kirim tidak boleh melebihi stok gudang. Catat pembelian dulu bila stok kurang.',
                    'Penjaga outlet akan mengonfirmasi jumlah yang diterima; selisihnya tampil di laporan persediaan.',
                ],
            ],
            [
                'title' => 'Stok gudang, pengeluaran usaha & laporan',
                'when' => 'Kapan saja untuk memantau persediaan.',
                'steps' => [
                    'Buka menu Stok Gudang untuk melihat sisa bahan. Bahan bertanda merah/kuning perlu dibeli.',
                    'Klik nama bahan untuk melihat kartu stok (riwayat keluar-masuk).',
                    'Bahan rusak di gudang dicatat lewat "Catat rusak" dengan alasannya.',
                    'Biaya lain di luar pembelian bahan (misalnya sewa gudang) dicatat lewat Pembelian › "Pengeluaran usaha".',
                    'Buka "Laporan Persediaan", pilih periode dan lokasi, lalu klik "Ekspor Excel" bila perlu dikirim.',
                ],
                'tips' => [],
            ],
        ];
    }

    /**
     * @return list<array{title: string, when: string, steps: list<string>, tips: list<string>}>
     */
    private static function owner(): array
    {
        return [
            [
                'title' => 'Memeriksa pengeluaran outlet',
                'when' => 'Setiap hari, saat angka muncul di menu Verifikasi.',
                'steps' => [
                    'Buka menu Verifikasi, pilih tab "Pengeluaran".',
                    'Klik "Tinjau" pada pengeluaran yang berstatus "Menunggu".',
                    'Periksa foto nota (klik foto untuk membukanya dalam ukuran besar), nominal, kategori, dan keterangan.',
                    'Klik "Setujui" bila sesuai.',
                    'Bila tidak sesuai, tulis alasannya lalu klik "Tolak pengeluaran". Penjaga akan melihat alasan itu.',
                ],
                'tips' => [
                    'Pengeluaran yang ditolak menambah kekurangan kas shift dan tampil sebagai "Selisih setelah verifikasi".',
                ],
            ],
            [
                'title' => 'Menyetujui permintaan bahan',
                'when' => 'Saat outlet mengajukan permintaan bahan.',
                'steps' => [
                    'Buka menu Verifikasi, tab "Permintaan bahan".',
                    'Klik "Tinjau" pada permintaan yang menunggu.',
                    'Ubah jumlah bila perlu (isi 0 untuk bahan yang tidak disetujui).',
                    'Klik "Setujui", atau "Tolak" dengan alasan. Permintaan yang disetujui akan dikirim admin gudang.',
                ],
                'tips' => [
                    'Anda juga bisa langsung membuat permintaan yang sudah disetujui lewat tombol "Buat permintaan".',
                ],
            ],
            [
                'title' => 'Memantau usaha & laporan',
                'when' => 'Kapan saja.',
                'steps' => [
                    'Dashboard: stok menipis, omzet hari ini dibanding kemarin, grafik 7 hari, pengeluaran menunggu, dan absensi hari ini. Pilih cabang di kanan atas.',
                    'Menu Laporan: pilih tab Penjualan, Kas & laba-rugi, Persediaan, Kinerja outlet, atau Harian outlet.',
                    'Atur periode (tanggal atau tombol cepat "7 hari", "Bulan ini") dan cabang di bagian atas.',
                    'Klik "Ekspor Excel" atau "Unduh PDF" untuk menyimpan laporan.',
                    'Harian outlet: buka laporan satu shift untuk melihat penjualan, kas, dan selisihnya; bisa dicetak.',
                ],
                'tips' => [
                    'Salah hitung kas di suatu shift? Buka laporan harian shift itu, klik "Koreksi kas fisik", isi jumlah yang benar dan alasannya. Koreksi tercatat di Audit log.',
                ],
            ],
            [
                'title' => 'Memantau absensi',
                'when' => 'Setiap hari atau saat perlu rekap.',
                'steps' => [
                    'Buka menu Absensi.',
                    'Atur tanggal, cabang, karyawan, atau status (terlambat/pulang awal).',
                    'Klik foto untuk memperbesar, atau "Lihat di peta" untuk melihat lokasi absen.',
                    'Tab "Ringkasan shift" menampilkan jam buka/tutup, kas, dan selisih per shift.',
                    'Klik "Ekspor rekap" untuk mengunduh Excel.',
                ],
                'tips' => [],
            ],
            [
                'title' => 'Mengelola data usaha & pengguna',
                'when' => 'Saat ada outlet, pegawai, produk, atau harga baru.',
                'steps' => [
                    'Buka menu Pengaturan.',
                    'Pengguna: tambah akun, ubah peran/outlet, nonaktifkan, atau reset password.',
                    'Outlet & gudang: isi alamat, titik lokasi (lintang/bujur), radius absen, jam buka-tutup, dan target omzet bulanan.',
                    'Bahan baku: nama, satuan, kategori, dan stok minimum per lokasi.',
                    'Produk & resep: harga untuk setiap kanal dan bahan yang dipakai untuk 1 porsi.',
                    'Audit log: lihat siapa melakukan apa dan kapan (masuk, verifikasi, perubahan harga, dll.).',
                ],
                'tips' => [
                    'Titik lokasi dan jam buka outlet wajib diisi agar penjaga bisa absen.',
                    'Pengguna dan data tidak dihapus, hanya dinonaktifkan, supaya riwayat tetap utuh.',
                ],
            ],
        ];
    }
}

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 25 Nov 2025 pada 15.52
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `toko_horden2`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `admin`
--

CREATE TABLE `admin` (
  `id_admin` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('utama','staf','viewer') DEFAULT 'staf',
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `admin`
--

INSERT INTO `admin` (`id_admin`, `username`, `password`, `role`, `nama_lengkap`, `email`, `no_hp`, `status`, `last_login`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'utama', 'Harianto', 'admin@luxuryliving.com', '081234567890', 'aktif', '2025-11-24 18:42:00', '2025-11-25 03:27:22'),
(2, 'staff1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staf', 'Siti Rahma', 'staff1@luxuryliving.com', '081298765432', 'aktif', '2025-11-24 15:30:00', '2025-11-25 03:27:22');

-- --------------------------------------------------------

--
-- Struktur dari tabel `backup_log`
--

CREATE TABLE `backup_log` (
  `id_backup` int(11) NOT NULL,
  `nama_file` varchar(255) NOT NULL,
  `ukuran` varchar(50) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `tanggal` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `detail_pesanan`
--

CREATE TABLE `detail_pesanan` (
  `id_detail` int(11) NOT NULL,
  `id_pesanan` int(11) NOT NULL,
  `id_produk` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `harga_satuan` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `detail_pesanan`
--

INSERT INTO `detail_pesanan` (`id_detail`, `id_pesanan`, `id_produk`, `jumlah`, `harga_satuan`, `subtotal`) VALUES
(1, 1, 1, 2, 280000.00, 560000.00),
(2, 1, 2, 1, 420000.00, 420000.00),
(3, 2, 3, 2, 440000.00, 880000.00),
(4, 2, 1, 2, 280000.00, 560000.00),
(5, 3, 2, 2, 420000.00, 840000.00);

-- --------------------------------------------------------

--
-- Struktur dari tabel `galeri`
--

CREATE TABLE `galeri` (
  `id_galeri` int(11) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `kategori` enum('Produk','Banner','Showcase') NOT NULL,
  `gambar` varchar(255) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `urutan` int(11) DEFAULT 0,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `tanggal_upload` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `galeri`
--

INSERT INTO `galeri` (`id_galeri`, `judul`, `kategori`, `gambar`, `deskripsi`, `urutan`, `status`, `tanggal_upload`) VALUES
(1, 'Showroom Korden Modern', 'Showcase', 'showroom1.jpg', 'Tampilan showroom korden modern kami', 1, 'aktif', '2025-11-25 03:27:23'),
(2, 'Banner Diskon Spesial', 'Banner', 'banner1.jpg', 'Banner promosi diskon spesial', 1, 'aktif', '2025-11-25 03:27:23'),
(3, 'Korden Minimalis', 'Produk', 'produk1.jpg', 'Contoh penerapan korden minimalis', 2, 'aktif', '2025-11-25 03:27:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori`
--

CREATE TABLE `kategori` (
  `id_kategori` int(11) NOT NULL,
  `nama_kategori` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `urutan` int(11) DEFAULT 0,
  `tanggal_dibuat` timestamp NOT NULL DEFAULT current_timestamp(),
  `tanggal_diupdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kategori`
--

INSERT INTO `kategori` (`id_kategori`, `nama_kategori`, `deskripsi`, `icon`, `status`, `urutan`, `tanggal_dibuat`, `tanggal_diupdate`) VALUES
(1, 'Korden Ruang Tamu', 'Korden khusus untuk ruang tamu dengan desain elegan', 'fas fa-couch', 'aktif', 1, '2025-11-25 03:27:23', '2025-11-25 03:27:23'),
(2, 'Korden Kamar Tidur', 'Korden untuk kamar tidur dengan bahan nyaman dan privasi', 'fas fa-bed', 'aktif', 2, '2025-11-25 03:27:23', '2025-11-25 06:44:26'),
(3, 'Korden Blackout', 'Korden dengan kemampuan menahan cahaya maksimal', 'fas fa-moon', 'aktif', 3, '2025-11-25 03:27:23', '2025-11-25 03:27:23'),
(4, 'Korden Polos/Motif', 'Korden dengan berbagai pilihan pola dan motif', 'fas fa-palette', 'aktif', 4, '2025-11-25 03:27:23', '2025-11-25 03:27:23'),
(5, 'Korden Minimalis', 'Korden dengan desain simple dan modern', 'fas fa-th-large', 'aktif', 5, '2025-11-25 03:27:23', '2025-11-25 03:27:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `keranjang`
--

CREATE TABLE `keranjang` (
  `id_keranjang` int(11) NOT NULL,
  `id_pelanggan` int(11) NOT NULL,
  `id_produk` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifikasi`
--

CREATE TABLE `notifikasi` (
  `id_notif` int(11) NOT NULL,
  `tipe` enum('Pesanan Baru','Pembayaran','Stok Menipis','Ulasan Baru','Pengiriman') NOT NULL,
  `judul` varchar(255) NOT NULL,
  `pesan` text NOT NULL,
  `status` enum('Belum Dibaca','Dibaca') DEFAULT 'Belum Dibaca',
  `target` enum('admin','user','all') DEFAULT 'admin',
  `id_target` int(11) DEFAULT NULL COMMENT 'ID pesanan/produk dll',
  `link` varchar(255) DEFAULT NULL,
  `tanggal` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `notifikasi`
--

INSERT INTO `notifikasi` (`id_notif`, `tipe`, `judul`, `pesan`, `status`, `target`, `id_target`, `link`, `tanggal`) VALUES
(1, 'Pesanan Baru', 'Pesanan Baru', 'Pesanan baru #ORD-01236 dari Putri', 'Belum Dibaca', 'admin', 1, 'pesanan.php?action=detail&id=1', '2025-11-25 03:27:23'),
(2, 'Pembayaran', 'Pembayaran Berhasil', 'Pembayaran untuk pesanan #ORD-01255 berhasil', 'Belum Dibaca', 'admin', 2, 'pesanan.php?action=detail&id=2', '2025-11-25 03:27:23'),
(3, 'Stok Menipis', 'Stok Menipis', 'Stok Korden Minimalis tinggal 2 unit', 'Belum Dibaca', 'admin', 1, 'produk.php?action=edit&id=1', '2025-11-25 03:27:23'),
(4, 'Ulasan Baru', 'Ulasan Baru', 'Ulasan baru untuk Korden Classic', 'Belum Dibaca', 'admin', 2, 'ulasan.php', '2025-11-25 03:27:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pelanggan`
--

CREATE TABLE `pelanggan` (
  `id_pelanggan` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `kota` varchar(100) DEFAULT NULL,
  `kode_pos` varchar(10) DEFAULT NULL,
  `jenis_kelamin` enum('Laki-laki','Perempuan') DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT 'default.png',
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `email_verified` tinyint(1) DEFAULT 0,
  `tanggal_daftar` timestamp NOT NULL DEFAULT current_timestamp(),
  `terakhir_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pelanggan`
--

INSERT INTO `pelanggan` (`id_pelanggan`, `nama`, `email`, `password`, `no_hp`, `alamat`, `kota`, `kode_pos`, `jenis_kelamin`, `tanggal_lahir`, `foto_profil`, `status`, `email_verified`, `tanggal_daftar`, `terakhir_login`) VALUES
(1, 'Santoso Budi', 'budi@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '08123456789', 'Jl. Merdeka No. 123, Jakarta', 'Jakarta', '12345', 'Laki-laki', '1985-05-15', 'default.png', 'aktif', 1, '2025-11-25 03:27:23', '2025-11-25 20:38:53'),
(2, 'Siti Rahayu', 'siti@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '08129876543', 'Jl. Sudirman No. 456, Jakarta', 'Jakarta', '12346', 'Perempuan', '1990-08-22', 'default.png', 'aktif', 1, '2025-11-25 03:27:23', '2025-11-24 14:30:00'),
(3, 'Andi Wijaya', 'andi@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081233445566', 'Jl. Thamrin No. 789, Jakarta', 'Jakarta', '12347', 'Laki-laki', '1988-12-10', 'default.png', 'aktif', 1, '2025-11-25 03:27:23', '2025-11-23 10:15:00');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id_pembayaran` int(11) NOT NULL,
  `id_pesanan` int(11) NOT NULL,
  `metode_pembayaran` enum('Transfer Bank','COD','E-Wallet') NOT NULL,
  `nama_bank` varchar(50) DEFAULT NULL,
  `no_rekening` varchar(50) DEFAULT NULL,
  `nama_pemilik` varchar(100) DEFAULT NULL,
  `jumlah_bayar` decimal(12,2) NOT NULL,
  `tanggal_bayar` datetime DEFAULT NULL,
  `status` enum('Menunggu','Lunas','Gagal') DEFAULT 'Menunggu',
  `bukti_transfer` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengaturan_toko`
--

CREATE TABLE `pengaturan_toko` (
  `id` int(11) NOT NULL,
  `nama_toko` varchar(255) NOT NULL,
  `deskripsi_singkat` text DEFAULT NULL,
  `deskripsi_lengkap` text DEFAULT NULL,
  `alamat` text NOT NULL,
  `telepon` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `favicon` varchar(255) DEFAULT NULL,
  `meta_keywords` text DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `tema_warna` varchar(50) DEFAULT 'cream-gold',
  `status_toko` enum('buka','tutup','maintenance') DEFAULT 'buka',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pengaturan_toko`
--

INSERT INTO `pengaturan_toko` (`id`, `nama_toko`, `deskripsi_singkat`, `deskripsi_lengkap`, `alamat`, `telepon`, `email`, `logo`, `favicon`, `meta_keywords`, `meta_description`, `tema_warna`, `status_toko`, `updated_at`) VALUES
(1, 'Luxury Living', 'Toko horden premium dengan kualitas terbaik dan pelayanan terpercaya', 'Luxury Living adalah toko horden premium yang menyediakan berbagai macam korden dengan kualitas terbaik. Kami berkomitmen untuk memberikan produk yang elegan dan pelayanan yang memuaskan.', 'Jl. Contoh No. 123, Jakarta', '+62 812-3456-7890', 'info@luxuryliving.com', 'logo.png', NULL, NULL, NULL, 'cream-gold', 'buka', '2025-11-25 03:27:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengiriman`
--

CREATE TABLE `pengiriman` (
  `id_pengiriman` int(11) NOT NULL,
  `id_pesanan` int(11) NOT NULL,
  `kurir` varchar(50) NOT NULL,
  `layanan` varchar(50) NOT NULL,
  `no_resi` varchar(100) DEFAULT NULL,
  `biaya` decimal(10,2) NOT NULL,
  `estimasi` varchar(50) DEFAULT NULL,
  `status` enum('Diproses','Dikirim','Terkirim') DEFAULT 'Diproses',
  `tanggal_kirim` datetime DEFAULT NULL,
  `tanggal_terima` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pesanan`
--

CREATE TABLE `pesanan` (
  `id_pesanan` int(11) NOT NULL,
  `kode_pesanan` varchar(20) NOT NULL,
  `id_pelanggan` int(11) NOT NULL,
  `tanggal_pesanan` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_harga` decimal(12,2) NOT NULL,
  `status_pesanan` enum('Menunggu Pembayaran','Diproses','Dikirim','Selesai','Dibatalkan') DEFAULT 'Menunggu Pembayaran',
  `metode_pembayaran` enum('Transfer Bank','COD','E-Wallet') DEFAULT 'Transfer Bank',
  `status_pembayaran` enum('Menunggu','Lunas','Gagal') DEFAULT 'Menunggu',
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `nama_penerima` varchar(100) NOT NULL,
  `no_hp_penerima` varchar(20) NOT NULL,
  `alamat_pengiriman` text NOT NULL,
  `kota_pengiriman` varchar(100) NOT NULL,
  `kode_pos_pengiriman` varchar(10) NOT NULL,
  `kurir` varchar(50) DEFAULT NULL,
  `no_resi` varchar(100) DEFAULT NULL,
  `ongkir` decimal(10,2) DEFAULT 0.00,
  `catatan` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pesanan`
--

INSERT INTO `pesanan` (`id_pesanan`, `kode_pesanan`, `id_pelanggan`, `tanggal_pesanan`, `total_harga`, `status_pesanan`, `metode_pembayaran`, `status_pembayaran`, `bukti_pembayaran`, `nama_penerima`, `no_hp_penerima`, `alamat_pengiriman`, `kota_pengiriman`, `kode_pos_pengiriman`, `kurir`, `no_resi`, `ongkir`, `catatan`, `updated_at`) VALUES
(1, 'ORD-01236', 1, '2025-11-25 03:27:23', 960000.00, 'Selesai', 'Transfer Bank', 'Lunas', NULL, 'Putri', '081234567890', 'Jl. Melati No. 45, Jakarta', 'Jakarta', '12345', 'JNE', NULL, 25000.00, NULL, '2025-11-25 03:27:23'),
(2, 'ORD-01255', 2, '2025-11-25 03:27:23', 1430000.00, 'Diproses', 'Transfer Bank', 'Lunas', NULL, 'Ahmad', '081298765432', 'Jl. Anggrek No. 78, Jakarta', 'Jakarta', '12346', 'JNE', NULL, 30000.00, NULL, '2025-11-25 03:27:23'),
(3, 'ORD-01234', 3, '2025-11-25 03:27:23', 860000.00, 'Dikirim', 'COD', 'Lunas', NULL, 'Sari', '081233445566', 'Jl. Mawar No. 12, Jakarta', 'Jakarta', '12347', 'JNE', NULL, 20000.00, NULL, '2025-11-25 03:27:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `produk`
--

CREATE TABLE `produk` (
  `id_produk` int(11) NOT NULL,
  `kode_produk` varchar(20) NOT NULL,
  `nama_produk` varchar(255) NOT NULL,
  `id_kategori` int(11) DEFAULT NULL,
  `deskripsi_singkat` text DEFAULT NULL,
  `deskripsi_lengkap` text DEFAULT NULL,
  `harga` decimal(12,2) NOT NULL,
  `harga_diskon` decimal(12,2) DEFAULT NULL,
  `stok` int(11) DEFAULT 0,
  `berat` decimal(8,2) DEFAULT 0.00 COMMENT 'dalam gram',
  `bahan` varchar(100) DEFAULT NULL,
  `ukuran` varchar(50) DEFAULT NULL,
  `warna` varchar(100) DEFAULT NULL,
  `jenis_gantungan` varchar(100) DEFAULT NULL,
  `foto_utama` varchar(255) DEFAULT NULL,
  `status` enum('Tersedia','Habis','Preorder') DEFAULT 'Tersedia',
  `terjual` int(11) DEFAULT 0,
  `rating_rata` decimal(3,2) DEFAULT 0.00,
  `jumlah_ulasan` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `produk`
--

INSERT INTO `produk` (`id_produk`, `kode_produk`, `nama_produk`, `id_kategori`, `deskripsi_singkat`, `deskripsi_lengkap`, `harga`, `harga_diskon`, `stok`, `berat`, `bahan`, `ukuran`, `warna`, `jenis_gantungan`, `foto_utama`, `status`, `terjual`, `rating_rata`, `jumlah_ulasan`, `created_at`, `updated_at`) VALUES
(1, 'KRD-001', 'Korden Minimalis Elegant', 5, 'Korden minimalis dengan bahan premium dan desain elegan', 'Korden dengan bahan blackout berkualitas tinggi, cocok untuk ruang tamu dan kamar tidur. Tersedia dalam berbagai ukuran dan warna.', 350000.00, 280000.00, 25, 1200.00, 'Polyester Blackout', '200x150 cm', 'Cream, Beige, Brown', 'Gantungan Ring', 'produk/utama_1_1764050504.png', 'Tersedia', 330, 4.80, 0, '2025-11-25 03:27:23', '2025-11-25 06:01:44'),
(2, 'KRD-002', 'Korden Classic Premium', 1, 'Korden klasik dengan motif tradisional yang elegan', 'Korden dengan desain klasik dan bahan katun premium. Cocok untuk ruang tamu dengan nuansa tradisional.', 420000.00, NULL, 15, 1500.00, 'Katun Premium', '200x150 cm', 'Maroon, Navy, Emerald', 'Gantungan Hook', 'produk2.jpg', 'Tersedia', 250, 4.60, 0, '2025-11-25 03:27:23', '2025-11-25 03:27:23'),
(3, 'KRD-003', 'Korden Blackout Deluxe', 3, 'Korden blackout dengan kemampuan menahan cahaya 100%', 'Korden khusus dengan teknologi blackout untuk privasi dan kenyamanan tidur maksimal.', 550000.00, 440000.00, 30, 1800.00, 'Blackout Fabric', '200x150 cm', 'Dark Gray, Navy, Black', 'Gantungan Ring', 'produk3.jpg', 'Tersedia', 251, 4.90, 0, '2025-11-25 03:27:23', '2025-11-25 03:27:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `produk_galeri`
--

CREATE TABLE `produk_galeri` (
  `id_galeri` int(11) NOT NULL,
  `id_produk` int(11) NOT NULL,
  `gambar` varchar(255) NOT NULL,
  `urutan` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `ulasan`
--

CREATE TABLE `ulasan` (
  `id_ulasan` int(11) NOT NULL,
  `id_produk` int(11) NOT NULL,
  `id_pelanggan` int(11) NOT NULL,
  `id_pesanan` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `komentar` text DEFAULT NULL,
  `foto_ulasan` varchar(255) DEFAULT NULL,
  `status` enum('Menunggu','Disetujui','Ditolak') DEFAULT 'Menunggu',
  `tanggal` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `ulasan`
--

INSERT INTO `ulasan` (`id_ulasan`, `id_produk`, `id_pelanggan`, `id_pesanan`, `rating`, `komentar`, `foto_ulasan`, `status`, `tanggal`, `updated_at`) VALUES
(1, 1, 1, 1, 5, 'Kordennya sangat bagus dan berkualitas! Pengiriman cepat.', NULL, 'Disetujui', '2025-11-20 03:00:00', '2025-11-25 03:27:23'),
(2, 2, 2, 2, 4, 'Desain klasiknya elegan, cocok untuk ruang tamu.', NULL, 'Disetujui', '2025-11-21 07:30:00', '2025-11-25 03:27:23'),
(3, 3, 3, 3, 5, 'Warna dan bahan sesuai ekspektasi. Recommended!', NULL, 'Disetujui', '2025-11-22 02:15:00', '2025-11-25 03:27:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `wishlist`
--

CREATE TABLE `wishlist` (
  `id_wishlist` int(11) NOT NULL,
  `id_pelanggan` int(11) NOT NULL,
  `id_produk` int(11) NOT NULL,
  `tanggal_ditambahkan` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id_admin`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indeks untuk tabel `backup_log`
--
ALTER TABLE `backup_log`
  ADD PRIMARY KEY (`id_backup`);

--
-- Indeks untuk tabel `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD PRIMARY KEY (`id_detail`),
  ADD KEY `id_pesanan` (`id_pesanan`),
  ADD KEY `id_produk` (`id_produk`);

--
-- Indeks untuk tabel `galeri`
--
ALTER TABLE `galeri`
  ADD PRIMARY KEY (`id_galeri`);

--
-- Indeks untuk tabel `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id_kategori`);

--
-- Indeks untuk tabel `keranjang`
--
ALTER TABLE `keranjang`
  ADD PRIMARY KEY (`id_keranjang`),
  ADD KEY `id_pelanggan` (`id_pelanggan`),
  ADD KEY `id_produk` (`id_produk`);

--
-- Indeks untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD PRIMARY KEY (`id_notif`);

--
-- Indeks untuk tabel `pelanggan`
--
ALTER TABLE `pelanggan`
  ADD PRIMARY KEY (`id_pelanggan`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indeks untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id_pembayaran`),
  ADD KEY `id_pesanan` (`id_pesanan`);

--
-- Indeks untuk tabel `pengaturan_toko`
--
ALTER TABLE `pengaturan_toko`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `pengiriman`
--
ALTER TABLE `pengiriman`
  ADD PRIMARY KEY (`id_pengiriman`),
  ADD KEY `id_pesanan` (`id_pesanan`);

--
-- Indeks untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`id_pesanan`),
  ADD UNIQUE KEY `kode_pesanan` (`kode_pesanan`),
  ADD KEY `id_pelanggan` (`id_pelanggan`);

--
-- Indeks untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id_produk`),
  ADD UNIQUE KEY `kode_produk` (`kode_produk`),
  ADD KEY `id_kategori` (`id_kategori`);

--
-- Indeks untuk tabel `produk_galeri`
--
ALTER TABLE `produk_galeri`
  ADD PRIMARY KEY (`id_galeri`),
  ADD KEY `id_produk` (`id_produk`);

--
-- Indeks untuk tabel `ulasan`
--
ALTER TABLE `ulasan`
  ADD PRIMARY KEY (`id_ulasan`),
  ADD KEY `id_produk` (`id_produk`),
  ADD KEY `id_pelanggan` (`id_pelanggan`),
  ADD KEY `id_pesanan` (`id_pesanan`);

--
-- Indeks untuk tabel `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id_wishlist`),
  ADD UNIQUE KEY `unique_wishlist` (`id_pelanggan`,`id_produk`),
  ADD KEY `id_produk` (`id_produk`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `admin`
--
ALTER TABLE `admin`
  MODIFY `id_admin` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `backup_log`
--
ALTER TABLE `backup_log`
  MODIFY `id_backup` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  MODIFY `id_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `galeri`
--
ALTER TABLE `galeri`
  MODIFY `id_galeri` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id_kategori` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `keranjang`
--
ALTER TABLE `keranjang`
  MODIFY `id_keranjang` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  MODIFY `id_notif` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `pelanggan`
--
ALTER TABLE `pelanggan`
  MODIFY `id_pelanggan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id_pembayaran` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pengaturan_toko`
--
ALTER TABLE `pengaturan_toko`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `pengiriman`
--
ALTER TABLE `pengiriman`
  MODIFY `id_pengiriman` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `id_pesanan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `produk`
--
ALTER TABLE `produk`
  MODIFY `id_produk` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `produk_galeri`
--
ALTER TABLE `produk_galeri`
  MODIFY `id_galeri` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `ulasan`
--
ALTER TABLE `ulasan`
  MODIFY `id_ulasan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id_wishlist` int(11) NOT NULL AUTO_INCREMENT;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD CONSTRAINT `detail_pesanan_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id_pesanan`),
  ADD CONSTRAINT `detail_pesanan_ibfk_2` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id_produk`);

--
-- Ketidakleluasaan untuk tabel `keranjang`
--
ALTER TABLE `keranjang`
  ADD CONSTRAINT `keranjang_ibfk_1` FOREIGN KEY (`id_pelanggan`) REFERENCES `pelanggan` (`id_pelanggan`),
  ADD CONSTRAINT `keranjang_ibfk_2` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id_produk`);

--
-- Ketidakleluasaan untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id_pesanan`);

--
-- Ketidakleluasaan untuk tabel `pengiriman`
--
ALTER TABLE `pengiriman`
  ADD CONSTRAINT `pengiriman_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id_pesanan`);

--
-- Ketidakleluasaan untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  ADD CONSTRAINT `pesanan_ibfk_1` FOREIGN KEY (`id_pelanggan`) REFERENCES `pelanggan` (`id_pelanggan`);

--
-- Ketidakleluasaan untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD CONSTRAINT `produk_ibfk_1` FOREIGN KEY (`id_kategori`) REFERENCES `kategori` (`id_kategori`);

--
-- Ketidakleluasaan untuk tabel `produk_galeri`
--
ALTER TABLE `produk_galeri`
  ADD CONSTRAINT `produk_galeri_ibfk_1` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id_produk`);

--
-- Ketidakleluasaan untuk tabel `ulasan`
--
ALTER TABLE `ulasan`
  ADD CONSTRAINT `ulasan_ibfk_1` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id_produk`),
  ADD CONSTRAINT `ulasan_ibfk_2` FOREIGN KEY (`id_pelanggan`) REFERENCES `pelanggan` (`id_pelanggan`),
  ADD CONSTRAINT `ulasan_ibfk_3` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id_pesanan`);

--
-- Ketidakleluasaan untuk tabel `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`id_pelanggan`) REFERENCES `pelanggan` (`id_pelanggan`) ON DELETE CASCADE,
  ADD CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id_produk`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

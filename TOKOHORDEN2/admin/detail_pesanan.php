<?php
session_start();

// Cek login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Koneksi database
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=toko_horden2;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Ambil data pesanan berdasarkan ID
$id = $_GET['id'] ?? 0;
$pesanan = null;
$detail_pesanan = [];
$pelanggan = [];

try {
    // Data pesanan utama
    $stmt = $pdo->prepare("SELECT p.*, pl.nama as nama_pelanggan, pl.email, pl.no_hp, pl.alamat, pl.kota, pl.kode_pos
                          FROM pesanan p 
                          LEFT JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan 
                          WHERE p.id_pesanan = ?");
    $stmt->execute([$id]);
    $pesanan = $stmt->fetch();
    
    if (!$pesanan) {
        header('Location: pesanan.php');
        exit;
    }
    
    // Detail pesanan (produk yang dipesan)
    $stmt_detail = $pdo->prepare("SELECT dp.*, pr.nama_produk, pr.foto_utama, pr.kode_produk 
                                 FROM detail_pesanan dp 
                                 LEFT JOIN produk pr ON dp.id_produk = pr.id_produk 
                                 WHERE dp.id_pesanan = ?");
    $stmt_detail->execute([$id]);
    $detail_pesanan = $stmt_detail->fetchAll();
    
} catch (Exception $e) {
    $error = "Gagal memuat data pesanan: " . $e->getMessage();
}

// Handle update status
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_status'])) {
        $status_pesanan = $_POST['status_pesanan'];
        $status_pembayaran = $_POST['status_pembayaran'];
        $no_resi = $_POST['no_resi'];
        
        try {
            $stmt = $pdo->prepare("UPDATE pesanan SET 
                                  status_pesanan = ?, 
                                  status_pembayaran = ?, 
                                  no_resi = ?,
                                  updated_at = NOW() 
                                  WHERE id_pesanan = ?");
            $stmt->execute([$status_pesanan, $status_pembayaran, $no_resi, $id]);
            
            $_SESSION['success'] = "Status pesanan berhasil diperbarui";
            header('Location: detail_pesanan.php?id=' . $id);
            exit;
            
        } catch (Exception $e) {
            $error = "Gagal memperbarui status: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan - Luxury Living</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --cream: #F3E8D7;
            --beige: #E7D3B8;
            --gold: #D8A75A;
            --brown: #6A4F37;
            --dark-brown: #4a3828;
            --light-cream: #faf6f0;
            --radius: 10px;
            --shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: var(--light-cream);
            color: var(--brown);
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 250px;
            background: white;
            box-shadow: var(--shadow);
            padding: 20px 0;
        }
        
        .logo {
            text-align: center;
            padding: 20px;
            border-bottom: 1px solid var(--cream);
            margin-bottom: 20px;
        }
        
        .logo h2 {
            color: var(--brown);
            font-size: 24px;
        }
        
        .logo span {
            color: var(--gold);
        }
        
        .nav-links {
            list-style: none;
        }
        
        .nav-links li {
            margin-bottom: 5px;
        }
        
        .nav-links a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--brown);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .nav-links a:hover, .nav-links a.active {
            background: var(--cream);
            border-right: 3px solid var(--gold);
        }
        
        .nav-links i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        
        .card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .btn-primary {
            background: var(--gold);
            color: white;
        }
        
        .btn-secondary {
            background: var(--cream);
            color: var(--brown);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-menunggu { background: #e2e3e5; color: #383d41; }
        .status-diproses { background: #fff3cd; color: #856404; }
        .status-dikirim { background: #cce7ff; color: #004085; }
        .status-selesai { background: #d4edda; color: #155724; }
        .status-dibatalkan { background: #f8d7da; color: #721c24; }
        
        .status-pembayaran-menunggu { background: #e2e3e5; color: #383d41; }
        .status-pembayaran-lunas { background: #d4edda; color: #155724; }
        .status-pembayaran-gagal { background: #f8d7da; color: #721c24; }
        
        .order-detail-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        
        .order-items {
            background: var(--light-cream);
            padding: 20px;
            border-radius: var(--radius);
            margin-bottom: 15px;
        }
        
        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--cream);
        }
        
        .item-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }
        
        .item-image {
            width: 60px;
            height: 60px;
            background: var(--cream);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            font-weight: 600;
            color: var(--dark-brown);
            margin-bottom: 5px;
        }
        
        .item-meta {
            color: #666;
            font-size: 13px;
        }
        
        .item-price {
            text-align: right;
            min-width: 120px;
        }
        
        .item-total {
            font-weight: bold;
            color: var(--gold);
            font-size: 16px;
        }
        
        .item-subtotal {
            color: #666;
            font-size: 13px;
        }
        
        .summary-section {
            border-top: 2px solid var(--gold);
            padding-top: 20px;
            margin-top: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--cream);
        }
        
        .summary-total {
            font-weight: bold;
            font-size: 18px;
            color: var(--gold);
            border-bottom: none;
        }
        
        .info-section {
            margin-bottom: 25px;
        }
        
        .info-section h3 {
            color: var(--gold);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--cream);
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--cream);
        }
        
        .info-label {
            font-weight: 500;
            color: var(--dark-brown);
        }
        
        .info-value {
            color: #666;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--dark-brown);
        }
        
        .form-group select, .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--beige);
            border-radius: 5px;
            font-size: 14px;
        }
        
        .alert {
            padding: 12px;
            border-radius: var(--radius);
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
            margin-top: 20px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--gold);
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -20px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--gold);
            border: 2px solid white;
        }
        
        .timeline-date {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .timeline-content {
            background: var(--light-cream);
            padding: 10px 15px;
            border-radius: var(--radius);
            border-left: 3px solid var(--gold);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .order-detail-grid {
                grid-template-columns: 1fr;
            }
            
            .item-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .item-price {
                text-align: left;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <h2>Luxury<span>Living</span></h2>
            </div>
            <ul class="nav-links">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="produk.php"><i class="fas fa-box"></i> Data Produk</a></li>
                <li><a href="kategori.php"><i class="fas fa-tags"></i> Kategori</a></li>
                <li><a href="pesanan.php" class="active"><i class="fas fa-shopping-cart"></i> Data Pesanan</a></li>
                <li><a href="pelanggan.php"><i class="fas fa-users"></i> Data Pelanggan</a></li>
                <li><a href="ulasan.php"><i class="fas fa-star"></i> Ulasan</a></li>
                <li><a href="laporan.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
                <li><a href="galeri.php"><i class="fas fa-images"></i> Galeri</a></li>
                <li><a href="pengaturan.php"><i class="fas fa-cog"></i> Pengaturan</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div>
                    <h1>Detail Pesanan</h1>
                    <p style="color: #888; margin-top: 5px;"><?php echo $pesanan['kode_pesanan']; ?></p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="pesanan.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                    </a>
                    <a href="pesanan.php?action=print&id=<?php echo $pesanan['id_pesanan']; ?>" class="btn btn-primary" target="_blank">
                        <i class="fas fa-print"></i> Print Invoice
                    </a>
                </div>
            </div>

            <!-- Notifikasi -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if(isset($error)): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="order-detail-grid">
                <!-- Kiri: Detail Produk & Ringkasan -->
                <div>
                    <!-- Detail Produk -->
                    <div class="card">
                        <h2 style="margin-bottom: 20px; color: var(--dark-brown);">Detail Produk</h2>
                        
                        <?php foreach($detail_pesanan as $item): ?>
                        <div class="order-items">
                            <div class="item-row">
                                <div class="item-info">
                                    <div class="item-image">
                                        <?php if($item['foto_utama']): ?>
                                            <img src="../uploads/<?php echo $item['foto_utama']; ?>" alt="<?php echo $item['nama_produk']; ?>" onerror="this.style.display='none'">
                                        <?php else: ?>
                                            <i class="fas fa-image" style="color: var(--gold); font-size: 20px;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-details">
                                        <div class="item-name"><?php echo $item['nama_produk']; ?></div>
                                        <div class="item-meta">
                                            Kode: <?php echo $item['kode_produk']; ?> | 
                                            Jumlah: <?php echo $item['jumlah']; ?> pcs
                                        </div>
                                    </div>
                                </div>
                                <div class="item-price">
                                    <div class="item-total">Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></div>
                                    <div class="item-subtotal">
                                        <?php echo $item['jumlah']; ?> x Rp <?php echo number_format($item['harga_satuan'], 0, ',', '.'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- Ringkasan Pembayaran -->
                        <div class="summary-section">
                            <div class="summary-row">
                                <span>Subtotal Produk:</span>
                                <span>Rp <?php echo number_format($pesanan['total_harga'] - $pesanan['ongkir'], 0, ',', '.'); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Ongkos Kirim:</span>
                                <span>Rp <?php echo number_format($pesanan['ongkir'], 0, ',', '.'); ?></span>
                            </div>
                            <?php if($pesanan['harga_diskon'] > 0): ?>
                            <div class="summary-row">
                                <span>Diskon:</span>
                                <span style="color: #dc3545;">- Rp <?php echo number_format($pesanan['harga_diskon'], 0, ',', '.'); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="summary-row summary-total">
                                <span>Total Pembayaran:</span>
                                <span>Rp <?php echo number_format($pesanan['total_harga'], 0, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline Pesanan -->
                    <div class="card">
                        <h3 style="margin-bottom: 15px;">Status Pesanan</h3>
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-date"><?php echo date('d M Y H:i', strtotime($pesanan['tanggal_pesanan'])); ?></div>
                                <div class="timeline-content">
                                    <strong>Pesanan Dibuat</strong>
                                    <p>Pesanan telah diterima dan menunggu pembayaran</p>
                                </div>
                            </div>
                            
                            <?php if($pesanan['status_pesanan'] == 'Diproses' || $pesanan['status_pesanan'] == 'Dikirim' || $pesanan['status_pesanan'] == 'Selesai'): ?>
                            <div class="timeline-item">
                                <div class="timeline-date">-</div>
                                <div class="timeline-content">
                                    <strong>Pesanan Diproses</strong>
                                    <p>Pesanan sedang dipersiapkan untuk dikirim</p>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($pesanan['status_pesanan'] == 'Dikirim' || $pesanan['status_pesanan'] == 'Selesai'): ?>
                            <div class="timeline-item">
                                <div class="timeline-date">-</div>
                                <div class="timeline-content">
                                    <strong>Pesanan Dikirim</strong>
                                    <p>
                                        <?php if($pesanan['kurir']): ?>
                                            Via <?php echo $pesanan['kurir']; ?>
                                            <?php if($pesanan['no_resi']): ?>
                                                - No. Resi: <?php echo $pesanan['no_resi']; ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            Pesanan telah dikirim
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($pesanan['status_pesanan'] == 'Selesai'): ?>
                            <div class="timeline-item">
                                <div class="timeline-date">-</div>
                                <div class="timeline-content">
                                    <strong>Pesanan Selesai</strong>
                                    <p>Pesanan telah sampai dan diterima oleh customer</p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Kanan: Info Pesanan & Aksi -->
                <div>
                    <!-- Status & Aksi -->
                    <div class="card">
                        <h3 style="margin-bottom: 15px;">Update Status</h3>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="status_pesanan">Status Pesanan</label>
                                <select id="status_pesanan" name="status_pesanan" required>
                                    <option value="Menunggu Pembayaran" <?php echo $pesanan['status_pesanan'] == 'Menunggu Pembayaran' ? 'selected' : ''; ?>>Menunggu Pembayaran</option>
                                    <option value="Diproses" <?php echo $pesanan['status_pesanan'] == 'Diproses' ? 'selected' : ''; ?>>Diproses</option>
                                    <option value="Dikirim" <?php echo $pesanan['status_pesanan'] == 'Dikirim' ? 'selected' : ''; ?>>Dikirim</option>
                                    <option value="Selesai" <?php echo $pesanan['status_pesanan'] == 'Selesai' ? 'selected' : ''; ?>>Selesai</option>
                                    <option value="Dibatalkan" <?php echo $pesanan['status_pesanan'] == 'Dibatalkan' ? 'selected' : ''; ?>>Dibatalkan</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="status_pembayaran">Status Pembayaran</label>
                                <select id="status_pembayaran" name="status_pembayaran" required>
                                    <option value="Menunggu" <?php echo $pesanan['status_pembayaran'] == 'Menunggu' ? 'selected' : ''; ?>>Menunggu</option>
                                    <option value="Lunas" <?php echo $pesanan['status_pembayaran'] == 'Lunas' ? 'selected' : ''; ?>>Lunas</option>
                                    <option value="Gagal" <?php echo $pesanan['status_pembayaran'] == 'Gagal' ? 'selected' : ''; ?>>Gagal</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="no_resi">No. Resi Pengiriman</label>
                                <input type="text" id="no_resi" name="no_resi" value="<?php echo $pesanan['no_resi'] ?? ''; ?>" placeholder="Masukkan nomor resi">
                            </div>
                            
                            <button type="submit" name="update_status" class="btn btn-success" style="width: 100%;">
                                <i class="fas fa-save"></i> Update Status
                            </button>
                        </form>
                        
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--cream);">
                            <div style="text-align: center;">
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $pesanan['status_pesanan'])); ?>" style="font-size: 16px; margin-bottom: 10px; display: block;">
                                    <?php echo $pesanan['status_pesanan']; ?>
                                </span>
                                <span class="status-badge status-pembayaran-<?php echo strtolower($pesanan['status_pembayaran']); ?>" style="font-size: 14px;">
                                    Pembayaran: <?php echo $pesanan['status_pembayaran']; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Info Pengiriman -->
                    <div class="card">
                        <div class="info-section">
                            <h3><i class="fas fa-shipping-fast"></i> Pengiriman</h3>
                            <div class="info-item">
                                <span class="info-label">Kurir</span>
                                <span class="info-value"><?php echo $pesanan['kurir'] ?? '-'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">No. Resi</span>
                                <span class="info-value"><?php echo $pesanan['no_resi'] ?? '-'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Ongkos Kirim</span>
                                <span class="info-value">Rp <?php echo number_format($pesanan['ongkir'], 0, ',', '.'); ?></span>
                            </div>
                        </div>

                        <div class="info-section">
                            <h3><i class="fas fa-user"></i> Penerima</h3>
                            <div class="info-item">
                                <span class="info-label">Nama</span>
                                <span class="info-value"><?php echo $pesanan['nama_penerima']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">No. HP</span>
                                <span class="info-value"><?php echo $pesanan['no_hp_penerima']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Alamat</span>
                                <span class="info-value"><?php echo $pesanan['alamat_pengiriman']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Kota</span>
                                <span class="info-value"><?php echo $pesanan['kota_pengiriman']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Kode Pos</span>
                                <span class="info-value"><?php echo $pesanan['kode_pos_pengiriman']; ?></span>
                            </div>
                        </div>

                        <?php if($pesanan['catatan']): ?>
                        <div class="info-section">
                            <h3><i class="fas fa-sticky-note"></i> Catatan</h3>
                            <p style="background: var(--light-cream); padding: 15px; border-radius: var(--radius); border-left: 3px solid var(--gold);">
                                <?php echo nl2br($pesanan['catatan']); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Info Pelanggan -->
                    <div class="card">
                        <div class="info-section">
                            <h3><i class="fas fa-users"></i> Data Pelanggan</h3>
                            <div class="info-item">
                                <span class="info-label">Nama</span>
                                <span class="info-value"><?php echo $pesanan['nama_pelanggan']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?php echo $pesanan['email']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">No. HP</span>
                                <span class="info-value"><?php echo $pesanan['no_hp']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Alamat</span>
                                <span class="info-value"><?php echo $pesanan['alamat']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ESC key to go back
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    window.location.href = 'pesanan.php';
                }
            });
            
            // Auto-focus on no resi when status changed to Dikirim
            const statusSelect = document.getElementById('status_pesanan');
            const noResiInput = document.getElementById('no_resi');
            
            statusSelect.addEventListener('change', function() {
                if (this.value === 'Dikirim') {
                    noResiInput.focus();
                }
            });
        });
    </script>
</body>
</html>
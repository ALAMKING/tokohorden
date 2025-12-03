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

// Handle actions
if (isset($_GET['action'])) {
    $id_pesanan = $_GET['id'] ?? 0;
    
    switch ($_GET['action']) {
        case 'proses':
            try {
                $stmt = $pdo->prepare("UPDATE pesanan SET status_pesanan = 'Diproses', updated_at = NOW() WHERE id_pesanan = ?");
                $stmt->execute([$id_pesanan]);
                $_SESSION['success'] = "Pesanan berhasil diproses";
            } catch (Exception $e) {
                $_SESSION['error'] = "Gagal memproses pesanan: " . $e->getMessage();
            }
            break;
            
        case 'kirim':
            try {
                $stmt = $pdo->prepare("UPDATE pesanan SET status_pesanan = 'Dikirim', updated_at = NOW() WHERE id_pesanan = ?");
                $stmt->execute([$id_pesanan]);
                $_SESSION['success'] = "Pesanan berhasil dikirim";
            } catch (Exception $e) {
                $_SESSION['error'] = "Gagal mengirim pesanan: " . $e->getMessage();
            }
            break;
            
        case 'selesai':
            try {
                $stmt = $pdo->prepare("UPDATE pesanan SET status_pesanan = 'Selesai', updated_at = NOW() WHERE id_pesanan = ?");
                $stmt->execute([$id_pesanan]);
                $_SESSION['success'] = "Pesanan berhasil diselesaikan";
            } catch (Exception $e) {
                $_SESSION['error'] = "Gagal menyelesaikan pesanan: " . $e->getMessage();
            }
            break;
            
        case 'batal':
            try {
                $stmt = $pdo->prepare("UPDATE pesanan SET status_pesanan = 'Dibatalkan', updated_at = NOW() WHERE id_pesanan = ?");
                $stmt->execute([$id_pesanan]);
                $_SESSION['success'] = "Pesanan berhasil dibatalkan";
            } catch (Exception $e) {
                $_SESSION['error'] = "Gagal membatalkan pesanan: " . $e->getMessage();
            }
            break;
            
        case 'lunas':
            try {
                $stmt = $pdo->prepare("UPDATE pesanan SET status_pembayaran = 'Lunas', updated_at = NOW() WHERE id_pesanan = ?");
                $stmt->execute([$id_pesanan]);
                $_SESSION['success'] = "Status pembayaran berhasil diubah menjadi Lunas";
            } catch (Exception $e) {
                $_SESSION['error'] = "Gagal mengubah status pembayaran: " . $e->getMessage();
            }
            break;
    }
    
    header('Location: pesanan.php');
    exit;
}

// Filter status
$filter_status = $_GET['status'] ?? '';
$filter_pembayaran = $_GET['pembayaran'] ?? '';

// Build query dengan filter
$where_conditions = [];
$params = [];

if ($filter_status) {
    $where_conditions[] = "p.status_pesanan = ?";
    $params[] = $filter_status;
}

if ($filter_pembayaran) {
    $where_conditions[] = "p.status_pembayaran = ?";
    $params[] = $filter_pembayaran;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}

// Ambil data pesanan
try {
    $query = "SELECT p.*, pl.nama as nama_pelanggan, pl.email, pl.no_hp 
              FROM pesanan p 
              LEFT JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan 
              $where_sql
              ORDER BY p.tanggal_pesanan DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $pesanan = $stmt->fetchAll();
    
    // Hitung statistik
    $total_pesanan = $pdo->query("SELECT COUNT(*) as total FROM pesanan")->fetch()['total'];
    $pesanan_baru = $pdo->query("SELECT COUNT(*) as total FROM pesanan WHERE status_pesanan = 'Menunggu Pembayaran'")->fetch()['total'];
    $pesanan_diproses = $pdo->query("SELECT COUNT(*) as total FROM pesanan WHERE status_pesanan = 'Diproses'")->fetch()['total'];
    $pesanan_selesai = $pdo->query("SELECT COUNT(*) as total FROM pesanan WHERE status_pesanan = 'Selesai'")->fetch()['total'];
    
} catch (Exception $e) {
    $pesanan = [];
    $error = "Gagal memuat data pesanan: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pesanan - Luxury Living</title>
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
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
        }
        
        .btn-primary {
            background: var(--gold);
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-secondary {
            background: var(--cream);
            color: var(--brown);
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 11px;
        }
        
        .action-buttons {
            display: flex;
            gap: 3px;
            flex-wrap: wrap;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--cream);
        }
        
        th {
            background: var(--cream);
            font-weight: 600;
            color: var(--dark-brown);
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
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
        
        .filter-section {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-size: 12px;
            font-weight: 500;
            color: var(--dark-brown);
        }
        
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid var(--beige);
            border-radius: 5px;
            font-size: 14px;
            min-width: 150px;
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
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--brown);
        }
        
        .empty-state i {
            font-size: 48px;
            color: var(--cream);
            margin-bottom: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            text-align: center;
            border-top: 4px solid var(--gold);
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: var(--gold);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--brown);
            font-size: 14px;
        }
        
        .badge-new {
            background: #dc3545;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 10px;
            margin-left: 5px;
        }
        
        .order-info {
            font-size: 11px;
            color: #666;
            margin-top: 2px;
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
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .filter-section {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
                <h1>Data Pesanan</h1>
                <div style="display: flex; gap: 10px;">
                    <a href="?status=Menunggu Pembayaran" class="btn btn-warning">
                        <i class="fas fa-clock"></i> Pesanan Baru
                        <?php if($pesanan_baru > 0): ?>
                            <span class="badge-new"><?php echo $pesanan_baru; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>

            <!-- Notifikasi -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if(isset($error)): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Statistik Pesanan -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_pesanan; ?></div>
                    <div class="stat-label">Total Pesanan</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pesanan_baru; ?></div>
                    <div class="stat-label">Menunggu Pembayaran</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pesanan_diproses; ?></div>
                    <div class="stat-label">Sedang Diproses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pesanan_selesai; ?></div>
                    <div class="stat-label">Pesanan Selesai</div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="card">
                <h3 style="margin-bottom: 15px;">Filter Pesanan</h3>
                <form method="GET" action="" class="filter-section">
                    <div class="filter-group">
                        <label for="status">Status Pesanan</label>
                        <select id="status" name="status" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            <option value="Menunggu Pembayaran" <?php echo $filter_status == 'Menunggu Pembayaran' ? 'selected' : ''; ?>>Menunggu Pembayaran</option>
                            <option value="Diproses" <?php echo $filter_status == 'Diproses' ? 'selected' : ''; ?>>Diproses</option>
                            <option value="Dikirim" <?php echo $filter_status == 'Dikirim' ? 'selected' : ''; ?>>Dikirim</option>
                            <option value="Selesai" <?php echo $filter_status == 'Selesai' ? 'selected' : ''; ?>>Selesai</option>
                            <option value="Dibatalkan" <?php echo $filter_status == 'Dibatalkan' ? 'selected' : ''; ?>>Dibatalkan</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="pembayaran">Status Pembayaran</label>
                        <select id="pembayaran" name="pembayaran" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            <option value="Menunggu" <?php echo $filter_pembayaran == 'Menunggu' ? 'selected' : ''; ?>>Menunggu</option>
                            <option value="Lunas" <?php echo $filter_pembayaran == 'Lunas' ? 'selected' : ''; ?>>Lunas</option>
                            <option value="Gagal" <?php echo $filter_pembayaran == 'Gagal' ? 'selected' : ''; ?>>Gagal</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <a href="pesanan.php" class="btn btn-secondary">
                            <i class="fas fa-refresh"></i> Reset Filter
                        </a>
                    </div>
                </form>
            </div>
            
            <div class="card">
                <?php if(empty($pesanan)): ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-cart"></i>
                        <p>Belum ada data pesanan</p>
                        <?php if($filter_status || $filter_pembayaran): ?>
                            <p style="color: #666; margin-top: 10px;">Coba ubah filter atau reset filter</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Kode Pesanan</th>
                                <th>Pelanggan</th>
                                <th>Tanggal</th>
                                <th>Total</th>
                                <th>Status Pesanan</th>
                                <th>Status Bayar</th>
                                <th>Metode</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($pesanan as $order): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $order['kode_pesanan']; ?></strong>
                                    <div class="order-info">
                                        <?php echo $order['kurir'] ?? '-'; ?>
                                        <?php if($order['no_resi']): ?>
                                            | Resi: <?php echo $order['no_resi']; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo $order['nama_pelanggan']; ?></strong>
                                    <div class="order-info">
                                        <?php echo $order['email']; ?><br>
                                        <?php echo $order['no_hp']; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('d M Y', strtotime($order['tanggal_pesanan'])); ?>
                                    <div class="order-info">
                                        <?php echo date('H:i', strtotime($order['tanggal_pesanan'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <strong>Rp <?php echo number_format($order['total_harga'], 0, ',', '.'); ?></strong>
                                    <?php if($order['ongkir'] > 0): ?>
                                        <div class="order-info">
                                            + Ongkir: Rp <?php echo number_format($order['ongkir'], 0, ',', '.'); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $order['status_pesanan'])); ?>">
                                        <?php echo $order['status_pesanan']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-pembayaran-<?php echo strtolower($order['status_pembayaran']); ?>">
                                        <?php echo $order['status_pembayaran']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $order['metode_pembayaran']; ?>
                                </td>
                                <td class="action-buttons">
                                    <a href="detail_pesanan.php?id=<?php echo $order['id_pesanan']; ?>" class="btn btn-info btn-sm" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if($order['status_pesanan'] == 'Menunggu Pembayaran'): ?>
                                        <a href="pesanan.php?action=proses&id=<?php echo $order['id_pesanan']; ?>" class="btn btn-success btn-sm" title="Proses Pesanan" onclick="return confirm('Proses pesanan <?php echo $order['kode_pesanan']; ?>?')">
                                            <i class="fas fa-play"></i>
                                        </a>
                                    <?php elseif($order['status_pesanan'] == 'Diproses'): ?>
                                        <a href="pesanan.php?action=kirim&id=<?php echo $order['id_pesanan']; ?>" class="btn btn-warning btn-sm" title="Kirim Pesanan" onclick="return confirm('Kirim pesanan <?php echo $order['kode_pesanan']; ?>?')">
                                            <i class="fas fa-shipping-fast"></i>
                                        </a>
                                    <?php elseif($order['status_pesanan'] == 'Dikirim'): ?>
                                        <a href="pesanan.php?action=selesai&id=<?php echo $order['id_pesanan']; ?>" class="btn btn-primary btn-sm" title="Selesaikan Pesanan" onclick="return confirm('Selesaikan pesanan <?php echo $order['kode_pesanan']; ?>?')">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if($order['status_pembayaran'] == 'Menunggu' && $order['status_pesanan'] != 'Dibatalkan'): ?>
                                        <a href="pesanan.php?action=lunas&id=<?php echo $order['id_pesanan']; ?>" class="btn btn-success btn-sm" title="Tandai Lunas" onclick="return confirm('Tandai pembayaran <?php echo $order['kode_pesanan']; ?> sebagai LUNAS?')">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if($order['status_pesanan'] == 'Menunggu Pembayaran' || $order['status_pesanan'] == 'Diproses'): ?>
                                        <a href="pesanan.php?action=batal&id=<?php echo $order['id_pesanan']; ?>" class="btn btn-danger btn-sm" title="Batalkan Pesanan" onclick="return confirm('Batalkan pesanan <?php echo $order['kode_pesanan']; ?>?')">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Konfirmasi sebelum aksi
        document.addEventListener('DOMContentLoaded', function() {
            const actionButtons = document.querySelectorAll('.btn-danger, .btn-success, .btn-warning, .btn-primary');
            actionButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Anda yakin ingin melanjutkan aksi ini?')) {
                        e.preventDefault();
                    }
                });
            });
            
            // Auto refresh setiap 30 detik untuk pesanan baru
            setTimeout(function() {
                const newOrders = <?php echo $pesanan_baru; ?>;
                if (newOrders > 0) {
                    const shouldRefresh = confirm(`Ada ${newOrders} pesanan baru. Muat ulang halaman?`);
                    if (shouldRefresh) {
                        window.location.reload();
                    }
                }
            }, 30000);
            
            // ESC key to go back to dashboard
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    window.location.href = 'dashboard.php';
                }
            });
        });
    </script>
</body>
</html>
<?php
session_start();

// Cek login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Koneksi database sederhana
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=toko_horden2;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Ambil data statistik
$stats = [
    'penjualan' => 0,
    'pendapatan' => 0,
    'pelanggan' => 0,
    'produk' => 0
];

// Ambil data untuk grafik
$grafik_penjualan = [];
$grafik_produk_terlaris = [];
$kategori_produk = [];

try {
    // Statistik dasar
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pesanan WHERE status_pesanan = 'Selesai'");
    $stats['penjualan'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT SUM(total_harga) as total FROM pesanan WHERE status_pembayaran = 'Lunas'");
    $stats['pendapatan'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pelanggan WHERE status = 'aktif'");
    $stats['pelanggan'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM produk WHERE status = 'Tersedia'");
    $stats['produk'] = $stmt->fetch()['total'];
    
    // Data grafik penjualan 7 hari terakhir
    $stmt = $pdo->query("
        SELECT 
            DATE(tanggal_pesanan) as tanggal,
            COUNT(*) as jumlah,
            SUM(total_harga) as pendapatan
        FROM pesanan 
        WHERE tanggal_pesanan >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(tanggal_pesanan)
        ORDER BY tanggal
    ");
    $grafik_penjualan = $stmt->fetchAll();
    
    // Data produk terlaris
    $stmt = $pdo->query("
        SELECT nama_produk, terjual 
        FROM produk 
        ORDER BY terjual DESC 
        LIMIT 5
    ");
    $grafik_produk_terlaris = $stmt->fetchAll();
    
    // Data kategori produk
    $stmt = $pdo->query("
        SELECT k.nama_kategori, COUNT(p.id_produk) as jumlah_produk
        FROM kategori k 
        LEFT JOIN produk p ON k.id_kategori = p.id_kategori 
        WHERE k.status = 'aktif'
        GROUP BY k.id_kategori
    ");
    $kategori_produk = $stmt->fetchAll();
    
    // Pesanan terbaru
    $pesanan_terbaru = $pdo->query("
        SELECT p.*, pl.nama as nama_pelanggan 
        FROM pesanan p 
        LEFT JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan 
        ORDER BY p.tanggal_pesanan DESC 
        LIMIT 5
    ")->fetchAll();
    
} catch (Exception $e) {
    // Biarkan data kosong jika ada error
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Luxury Living</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --cream: #F3E8D7; --beige: #E7D3B8; --gold: #D8A75A; 
            --brown: #6A4F37; --dark-brown: #4a3828; --light-cream: #faf6f0;
            --radius: 10px; --shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: var(--light-cream); color: var(--brown); }
        .container { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 250px; background: white; box-shadow: var(--shadow); padding: 20px 0; }
        .logo { text-align: center; padding: 20px; border-bottom: 1px solid var(--cream); margin-bottom: 20px; }
        .logo h2 { color: var(--brown); font-size: 24px; }
        .logo span { color: var(--gold); }
        .nav-links { list-style: none; }
        .nav-links li { margin-bottom: 5px; }
        .nav-links a { display: flex; align-items: center; padding: 12px 20px; color: var(--brown); text-decoration: none; transition: all 0.3s; }
        .nav-links a:hover, .nav-links a.active { background: var(--cream); border-right: 3px solid var(--gold); }
        .nav-links i { margin-right: 10px; width: 20px; text-align: center; }
        
        /* Main Content */
        .main-content { flex: 1; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: white; padding: 20px; border-radius: var(--radius); box-shadow: var(--shadow); }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-avatar { width: 40px; height: 40px; background: var(--gold); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: var(--radius); box-shadow: var(--shadow); text-align: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { width: 50px; height: 50px; background: var(--cream); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; color: var(--gold); font-size: 20px; }
        .stat-number { font-size: 28px; font-weight: 700; color: var(--gold); margin-bottom: 5px; }
        .stat-label { color: var(--brown); font-weight: 500; }
        
        /* Content Grid */
        .content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px; }
        .chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        .card { background: white; border-radius: var(--radius); box-shadow: var(--shadow); padding: 20px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--cream); }
        .card-title { font-size: 18px; font-weight: 600; color: var(--dark-brown); }
        
        .chart-container { position: relative; height: 300px; width: 100%; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--cream); }
        th { background: var(--cream); font-weight: 600; color: var(--dark-brown); }
        
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-selesai { background: #d4edda; color: #155724; }
        .status-diproses { background: #fff3cd; color: #856404; }
        .status-dikirim { background: #cce7ff; color: #004085; }
        .status-menunggu { background: #e2e3e5; color: #383d41; }
        
        .btn { padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer; font-weight: 500; text-decoration: none; display: inline-block; text-align: center; }
        .btn-primary { background: var(--gold); color: white; }
        .btn-primary:hover { background: var(--dark-brown); }
        
        .empty-state { text-align: center; padding: 20px; color: var(--brown); }
        .empty-state i { font-size: 48px; color: var(--cream); margin-bottom: 10px; }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .chart-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .container { flex-direction: column; }
            .sidebar { width: 100%; }
            .content-grid { grid-template-columns: 1fr; }
            .chart-grid { grid-template-columns: 1fr; }
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
                <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="produk.php"><i class="fas fa-box"></i> Data Produk</a></li>
                <li><a href="kategori.php"><i class="fas fa-tags"></i> Kategori</a></li>
                <li><a href="pesanan.php"><i class="fas fa-shopping-cart"></i> Data Pesanan</a></li>
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
            <!-- Header -->
            <div class="header">
                <h1>Dashboard</h1>
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['admin_nama'], 0, 1)); ?>
                    </div>
                    <div>
                        <div class="user-name"><?php echo $_SESSION['admin_nama']; ?></div>
                        <div class="user-role"><?php echo ucfirst($_SESSION['admin_role']); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['penjualan']); ?></div>
                    <div class="stat-label">Total Penjualan</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-number">Rp <?php echo number_format($stats['pendapatan'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Total Pendapatan</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['pelanggan']); ?></div>
                    <div class="stat-label">Total Pelanggan</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-box"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['produk']); ?></div>
                    <div class="stat-label">Produk Tersedia</div>
                </div>
            </div>
            
            <!-- Grafik Section -->
            <div class="chart-grid">
                <!-- Grafik Penjualan -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Grafik Penjualan 7 Hari Terakhir</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
                
                <!-- Grafik Produk Terlaris -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Produk Terlaris</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="productsChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Left Column -->
                <div class="left-column">
                    <!-- Pesanan Terbaru -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Pesanan Terbaru</h3>
                            <a href="pesanan.php" class="btn btn-primary">Lihat Semua</a>
                        </div>
                        <?php if(empty($pesanan_terbaru)): ?>
                        <div class="empty-state">
                            <i class="fas fa-shopping-cart"></i>
                            <p>Belum ada pesanan</p>
                        </div>
                        <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Kode Pesanan</th>
                                    <th>Pelanggan</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($pesanan_terbaru as $pesanan): ?>
                                <tr>
                                    <td><?php echo $pesanan['kode_pesanan']; ?></td>
                                    <td><?php echo $pesanan['nama_pelanggan'] ?? 'N/A'; ?></td>
                                    <td>Rp <?php echo number_format($pesanan['total_harga'], 0, ',', '.'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($pesanan['status_pesanan']); ?>">
                                            <?php echo $pesanan['status_pesanan']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($pesanan['tanggal_pesanan'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="right-column">
                    <!-- Distribusi Kategori -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Distribusi Kategori</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Data dari PHP ke JavaScript
        const salesData = <?php echo json_encode($grafik_penjualan); ?>;
        const productsData = <?php echo json_encode($grafik_produk_terlaris); ?>;
        const categoryData = <?php echo json_encode($kategori_produk); ?>;

        // Warna tema
        const themeColors = {
            gold: '#D8A75A',
            cream: '#F3E8D7',
            brown: '#6A4F37',
            beige: '#E7D3B8',
            darkBrown: '#4a3828'
        };

        // Grafik Penjualan
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: salesData.map(item => new Date(item.tanggal).toLocaleDateString('id-ID', { 
                    day: 'numeric', 
                    month: 'short' 
                })),
                datasets: [{
                    label: 'Jumlah Pesanan',
                    data: salesData.map(item => item.jumlah),
                    borderColor: themeColors.gold,
                    backgroundColor: themeColors.cream,
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Pendapatan (Rp)',
                    data: salesData.map(item => item.pendapatan / 100000),
                    borderColor: themeColors.brown,
                    backgroundColor: 'transparent',
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Jumlah Pesanan'
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Pendapatan (x100rb)'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        // Grafik Produk Terlaris
        const productsCtx = document.getElementById('productsChart').getContext('2d');
        const productsChart = new Chart(productsCtx, {
            type: 'bar',
            data: {
                labels: productsData.map(item => item.nama_produk),
                datasets: [{
                    label: 'Jumlah Terjual',
                    data: productsData.map(item => item.terjual),
                    backgroundColor: [
                        themeColors.gold,
                        themeColors.brown,
                        themeColors.darkBrown,
                        themeColors.beige,
                        '#A78B6F'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Grafik Kategori
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: categoryData.map(item => item.nama_kategori),
                datasets: [{
                    data: categoryData.map(item => item.jumlah_produk),
                    backgroundColor: [
                        themeColors.gold,
                        themeColors.brown,
                        themeColors.darkBrown,
                        themeColors.beige,
                        '#A78B6F',
                        '#8C6B4F'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Animasi stat cards
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.classList.add('animate__animated', 'animate__fadeInUp');
            });
        });
    </script>
</body>
</html>
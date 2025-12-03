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

// Set default periode
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$report_type = $_GET['report_type'] ?? 'penjualan';

// Validasi tanggal
if (!strtotime($start_date)) $start_date = date('Y-m-01');
if (!strtotime($end_date)) $end_date = date('Y-m-t');

// Ambil data laporan berdasarkan jenis laporan
$laporan_data = [];
$chart_labels = [];
$chart_data = [];

try {
    if ($report_type === 'penjualan') {
        // Laporan Penjualan Harian
        $stmt = $pdo->prepare("
            SELECT 
                DATE(tanggal_pesanan) as tanggal,
                COUNT(*) as jumlah_pesanan,
                SUM(total_harga) as total_penjualan,
                AVG(total_harga) as rata_rata
            FROM pesanan 
            WHERE tanggal_pesanan BETWEEN :start_date AND :end_date + INTERVAL 1 DAY
                AND status_pembayaran = 'Lunas'
            GROUP BY DATE(tanggal_pesanan)
            ORDER BY tanggal
        ");
        $stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
        $laporan_data = $stmt->fetchAll();
        
        $chart_labels = array_map(function($item) {
            return date('d M', strtotime($item['tanggal']));
        }, $laporan_data);
        
        $chart_data = array_map(function($item) {
            return $item['total_penjualan'];
        }, $laporan_data);
        
    } elseif ($report_type === 'produk') {
        // Laporan Produk Terlaris
        $stmt = $pdo->prepare("
            SELECT 
                p.nama_produk,
                k.nama_kategori,
                COUNT(ps.id_pesanan) as jumlah_terjual,
                SUM(ps.jumlah) as total_unit,
                SUM(ps.subtotal) as total_penjualan
            FROM produk p
            LEFT JOIN kategori k ON p.id_kategori = k.id_kategori
            LEFT JOIN pesanan_item ps ON p.id_produk = ps.id_produk
            LEFT JOIN pesanan o ON ps.id_pesanan = o.id_pesanan
            WHERE o.tanggal_pesanan BETWEEN :start_date AND :end_date + INTERVAL 1 DAY
                AND o.status_pembayaran = 'Lunas'
            GROUP BY p.id_produk
            ORDER BY total_penjualan DESC
            LIMIT 10
        ");
        $stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
        $laporan_data = $stmt->fetchAll();
        
        $chart_labels = array_map(function($item) {
            return $item['nama_produk'];
        }, $laporan_data);
        
        $chart_data = array_map(function($item) {
            return $item['total_penjualan'];
        }, $laporan_data);
        
    } elseif ($report_type === 'kategori') {
        // Laporan Kategori
        $stmt = $pdo->prepare("
            SELECT 
                k.nama_kategori,
                COUNT(p.id_produk) as jumlah_produk,
                COUNT(ps.id_pesanan) as jumlah_terjual,
                SUM(ps.subtotal) as total_penjualan
            FROM kategori k
            LEFT JOIN produk p ON k.id_kategori = p.id_kategori
            LEFT JOIN pesanan_item ps ON p.id_produk = ps.id_produk
            LEFT JOIN pesanan o ON ps.id_pesanan = o.id_pesanan
            WHERE o.tanggal_pesanan BETWEEN :start_date AND :end_date + INTERVAL 1 DAY
                AND o.status_pembayaran = 'Lunas'
            GROUP BY k.id_kategori
            ORDER BY total_penjualan DESC
        ");
        $stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
        $laporan_data = $stmt->fetchAll();
        
        $chart_labels = array_map(function($item) {
            return $item['nama_kategori'];
        }, $laporan_data);
        
        $chart_data = array_map(function($item) {
            return $item['total_penjualan'];
        }, $laporan_data);
        
    } elseif ($report_type === 'pelanggan') {
        // Laporan Pelanggan
        $stmt = $pdo->prepare("
            SELECT 
                pl.nama,
                pl.email,
                COUNT(o.id_pesanan) as jumlah_pesanan,
                SUM(o.total_harga) as total_belanja,
                MAX(o.tanggal_pesanan) as terakhir_belanja
            FROM pelanggan pl
            LEFT JOIN pesanan o ON pl.id_pelanggan = o.id_pelanggan
            WHERE o.tanggal_pesanan BETWEEN :start_date AND :end_date + INTERVAL 1 DAY
                AND o.status_pembayaran = 'Lunas'
            GROUP BY pl.id_pelanggan
            ORDER BY total_belanja DESC
            LIMIT 15
        ");
        $stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
        $laporan_data = $stmt->fetchAll();
        
    }
    
    // Ambil statistik summary
    $summary_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_pesanan,
            SUM(total_harga) as total_penjualan,
            AVG(total_harga) as rata_rata,
            MIN(total_harga) as minimal,
            MAX(total_harga) as maksimal
        FROM pesanan 
        WHERE tanggal_pesanan BETWEEN :start_date AND :end_date + INTERVAL 1 DAY
            AND status_pembayaran = 'Lunas'
    ");
    $summary_stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $summary = $summary_stmt->fetch();
    
} catch (Exception $e) {
    $laporan_data = [];
    $summary = [
        'total_pesanan' => 0,
        'total_penjualan' => 0,
        'rata_rata' => 0,
        'minimal' => 0,
        'maksimal' => 0
    ];
}

// Fungsi untuk export PDF
if (isset($_POST['export_pdf'])) {
    // Header untuk download PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="laporan_' . $report_type . '_' . $start_date . '_to_' . $end_date . '.pdf"');
    
    // Simple PDF content (bisa diganti dengan library PDF seperti TCPDF)
    echo "LAPORAN " . strtoupper($report_type) . "\n";
    echo "Periode: $start_date s/d $end_date\n\n";
    
    if ($report_type === 'penjualan') {
        echo "Tanggal\tJumlah Pesanan\tTotal Penjualan\tRata-rata\n";
        foreach ($laporan_data as $data) {
            echo $data['tanggal'] . "\t" . $data['jumlah_pesanan'] . "\tRp " . number_format($data['total_penjualan']) . "\tRp " . number_format($data['rata_rata']) . "\n";
        }
    } elseif ($report_type === 'produk') {
        echo "Produk\tKategori\tJumlah Terjual\tTotal Unit\tTotal Penjualan\n";
        foreach ($laporan_data as $data) {
            echo $data['nama_produk'] . "\t" . $data['nama_kategori'] . "\t" . $data['jumlah_terjual'] . "\t" . $data['total_unit'] . "\tRp " . number_format($data['total_penjualan']) . "\n";
        }
    }
    
    exit;
}

// Fungsi untuk export Excel
if (isset($_POST['export_excel'])) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="laporan_' . $report_type . '_' . $start_date . '_to_' . $end_date . '.xls"');
    
    echo "<table border='1'>";
    echo "<tr><th colspan='4'>LAPORAN " . strtoupper($report_type) . "</th></tr>";
    echo "<tr><th colspan='4'>Periode: $start_date s/d $end_date</th></tr>";
    
    if ($report_type === 'penjualan') {
        echo "<tr><th>Tanggal</th><th>Jumlah Pesanan</th><th>Total Penjualan</th><th>Rata-rata</th></tr>";
        foreach ($laporan_data as $data) {
            echo "<tr>";
            echo "<td>" . $data['tanggal'] . "</td>";
            echo "<td>" . $data['jumlah_pesanan'] . "</td>";
            echo "<td>Rp " . number_format($data['total_penjualan']) . "</td>";
            echo "<td>Rp " . number_format($data['rata_rata']) . "</td>";
            echo "</tr>";
        }
    }
    
    echo "</table>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Luxury Living</title>
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
        
        /* Card */
        .card { background: white; border-radius: var(--radius); box-shadow: var(--shadow); padding: 20px; margin-bottom: 20px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--cream); }
        .card-title { font-size: 18px; font-weight: 600; color: var(--dark-brown); }
        
        /* Filter Controls */
        .filter-container { display: grid; grid-template-columns: 1fr 1fr 1fr auto auto; gap: 15px; margin-bottom: 20px; align-items: end; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-label { margin-bottom: 5px; font-weight: 500; color: var(--dark-brown); }
        .filter-select, .filter-input { padding: 10px 15px; border: 1px solid var(--cream); border-radius: var(--radius); background: white; color: var(--brown); }
        .filter-input:focus, .filter-select:focus { outline: none; border-color: var(--gold); }
        
        /* Export Buttons */
        .export-buttons { display: flex; gap: 10px; margin-bottom: 20px; }
        
        /* Buttons */
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-primary { background: var(--gold); color: white; }
        .btn-primary:hover { background: var(--dark-brown); }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-info:hover { background: #138496; }
        
        /* Chart */
        .chart-container { position: relative; height: 400px; width: 100%; margin-bottom: 30px; }
        
        /* Table */
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--cream); }
        th { background: var(--cream); font-weight: 600; color: var(--dark-brown); position: sticky; top: 0; }
        tr:hover { background: #f9f9f9; }
        
        /* Summary Cards */
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .summary-card { background: var(--cream); padding: 15px; border-radius: var(--radius); text-align: center; }
        .summary-value { font-size: 20px; font-weight: 700; color: var(--gold); margin-bottom: 5px; }
        .summary-label { font-size: 12px; color: var(--brown); text-transform: uppercase; }
        
        /* Empty State */
        .empty-state { text-align: center; padding: 40px 20px; color: var(--brown); }
        .empty-state i { font-size: 48px; color: var(--cream); margin-bottom: 10px; }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .filter-container { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .container { flex-direction: column; }
            .sidebar { width: 100%; }
            .filter-container { grid-template-columns: 1fr; }
            .export-buttons { flex-direction: column; }
            .table-responsive { overflow-x: auto; }
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
                <li><a href="pesanan.php"><i class="fas fa-shopping-cart"></i> Data Pesanan</a></li>
                <li><a href="pelanggan.php"><i class="fas fa-users"></i> Data Pelanggan</a></li>
                <li><a href="ulasan.php"><i class="fas fa-star"></i> Ulasan</a></li>
                <li><a href="laporan.php" class="active"><i class="fas fa-chart-bar"></i> Laporan</a></li>
                <li><a href="galeri.php"><i class="fas fa-images"></i> Galeri</a></li>
                <li><a href="pengaturan.php"><i class="fas fa-cog"></i> Pengaturan</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h1>Laporan & Analitik</h1>
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo isset($_SESSION['admin_nama']) ? strtoupper(substr($_SESSION['admin_nama'], 0, 1)) : 'A'; ?>
                    </div>
                    <div>
                        <div class="user-name"><?php echo $_SESSION['admin_nama'] ?? 'Admin'; ?></div>
                        <div class="user-role"><?php echo isset($_SESSION['admin_role']) ? ucfirst($_SESSION['admin_role']) : 'Administrator'; ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Summary Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="stat-number"><?php echo number_format($summary['total_pesanan']); ?></div>
                    <div class="stat-label">Total Pesanan</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-number">Rp <?php echo number_format($summary['total_penjualan'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Total Penjualan</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-number">Rp <?php echo number_format($summary['rata_rata'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Rata-rata Pesanan</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-exchange-alt"></i></div>
                    <div class="stat-number">Rp <?php echo number_format($summary['maksimal'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Pesanan Tertinggi</div>
                </div>
            </div>
            
            <!-- Filter Controls -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Filter Laporan</h3>
                </div>
                
                <form method="GET" id="reportForm">
                    <div class="filter-container">
                        <div class="filter-group">
                            <label class="filter-label">Jenis Laporan</label>
                            <select name="report_type" class="filter-select" onchange="document.getElementById('reportForm').submit()">
                                <option value="penjualan" <?php echo $report_type === 'penjualan' ? 'selected' : ''; ?>>Laporan Penjualan</option>
                                <option value="produk" <?php echo $report_type === 'produk' ? 'selected' : ''; ?>>Produk Terlaris</option>
                                <option value="kategori" <?php echo $report_type === 'kategori' ? 'selected' : ''; ?>>Laporan Kategori</option>
                                <option value="pelanggan" <?php echo $report_type === 'pelanggan' ? 'selected' : ''; ?>>Laporan Pelanggan</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Tanggal Mulai</label>
                            <input type="date" name="start_date" class="filter-input" value="<?php echo $start_date; ?>" onchange="document.getElementById('reportForm').submit()">
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Tanggal Akhir</label>
                            <input type="date" name="end_date" class="filter-input" value="<?php echo $end_date; ?>" onchange="document.getElementById('reportForm').submit()">
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Terapkan Filter
                            </button>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">&nbsp;</label>
                            <a href="laporan.php" class="btn btn-info">
                                <i class="fas fa-sync"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
                
                <!-- Export Buttons -->
                <div class="export-buttons">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
                        <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
                        <input type="hidden" name="report_type" value="<?php echo $report_type; ?>">
                        <button type="submit" name="export_excel" class="btn btn-success">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>
                    </form>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
                        <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
                        <input type="hidden" name="report_type" value="<?php echo $report_type; ?>">
                        <button type="submit" name="export_pdf" class="btn btn-danger">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </button>
                    </form>
                </div>
                
                <!-- Chart -->
                <?php if (!empty($chart_data)): ?>
                <div class="chart-container">
                    <canvas id="reportChart"></canvas>
                </div>
                <?php endif; ?>
                
                <!-- Data Table -->
                <div class="table-responsive">
                    <?php if(empty($laporan_data)): ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-bar"></i>
                            <p>Tidak ada data laporan untuk periode yang dipilih</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <?php if ($report_type === 'penjualan'): ?>
                                        <th>Tanggal</th>
                                        <th>Jumlah Pesanan</th>
                                        <th>Total Penjualan</th>
                                        <th>Rata-rata</th>
                                    <?php elseif ($report_type === 'produk'): ?>
                                        <th>Produk</th>
                                        <th>Kategori</th>
                                        <th>Jumlah Terjual</th>
                                        <th>Total Unit</th>
                                        <th>Total Penjualan</th>
                                    <?php elseif ($report_type === 'kategori'): ?>
                                        <th>Kategori</th>
                                        <th>Jumlah Produk</th>
                                        <th>Jumlah Terjual</th>
                                        <th>Total Penjualan</th>
                                    <?php elseif ($report_type === 'pelanggan'): ?>
                                        <th>Pelanggan</th>
                                        <th>Email</th>
                                        <th>Jumlah Pesanan</th>
                                        <th>Total Belanja</th>
                                        <th>Terakhir Belanja</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($laporan_data as $data): ?>
                                <tr>
                                    <?php if ($report_type === 'penjualan'): ?>
                                        <td><?php echo date('d M Y', strtotime($data['tanggal'])); ?></td>
                                        <td><?php echo number_format($data['jumlah_pesanan']); ?></td>
                                        <td>Rp <?php echo number_format($data['total_penjualan'], 0, ',', '.'); ?></td>
                                        <td>Rp <?php echo number_format($data['rata_rata'], 0, ',', '.'); ?></td>
                                    <?php elseif ($report_type === 'produk'): ?>
                                        <td><?php echo htmlspecialchars($data['nama_produk']); ?></td>
                                        <td><?php echo htmlspecialchars($data['nama_kategori']); ?></td>
                                        <td><?php echo number_format($data['jumlah_terjual']); ?></td>
                                        <td><?php echo number_format($data['total_unit']); ?></td>
                                        <td>Rp <?php echo number_format($data['total_penjualan'], 0, ',', '.'); ?></td>
                                    <?php elseif ($report_type === 'kategori'): ?>
                                        <td><?php echo htmlspecialchars($data['nama_kategori']); ?></td>
                                        <td><?php echo number_format($data['jumlah_produk']); ?></td>
                                        <td><?php echo number_format($data['jumlah_terjual']); ?></td>
                                        <td>Rp <?php echo number_format($data['total_penjualan'], 0, ',', '.'); ?></td>
                                    <?php elseif ($report_type === 'pelanggan'): ?>
                                        <td><?php echo htmlspecialchars($data['nama']); ?></td>
                                        <td><?php echo htmlspecialchars($data['email']); ?></td>
                                        <td><?php echo number_format($data['jumlah_pesanan']); ?></td>
                                        <td>Rp <?php echo number_format($data['total_belanja'], 0, ',', '.'); ?></td>
                                        <td><?php echo $data['terakhir_belanja'] ? date('d M Y', strtotime($data['terakhir_belanja'])) : '-'; ?></td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Chart Configuration
        <?php if (!empty($chart_data)): ?>
        const chartCtx = document.getElementById('reportChart').getContext('2d');
        const reportChart = new Chart(chartCtx, {
            type: '<?php echo $report_type === 'penjualan' ? 'line' : 'bar'; ?>',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: '<?php echo $report_type === 'penjualan' ? 'Total Penjualan' : 'Penjualan'; ?>',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: '<?php echo $report_type === 'penjualan' ? 'rgba(216, 167, 90, 0.1)' : 'rgba(216, 167, 90, 0.8)'; ?>',
                    borderColor: '#D8A75A',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: <?php echo $report_type === 'penjualan' ? 'true' : 'false'; ?>
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Rp ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Auto-submit form when dates change
        document.querySelectorAll('input[type="date"]').forEach(input => {
            input.addEventListener('change', function() {
                document.getElementById('reportForm').submit();
            });
        });
    </script>
</body>
</html>
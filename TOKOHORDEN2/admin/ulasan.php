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

// Inisialisasi variabel
$search = $_GET['search'] ?? '';
$rating_filter = $_GET['rating'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Query untuk mengambil data ulasan dengan filter
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(pl.nama LIKE :search OR pr.nama_produk LIKE :search OR u.komentar LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($rating_filter)) {
    $where_conditions[] = "u.rating = :rating";
    $params[':rating'] = $rating_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "u.status = :status";
    $params[':status'] = $status_filter;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}

// Query untuk data ulasan
try {
    $sql = "SELECT u.*, pl.nama as nama_pelanggan, pr.nama_produk, pr.gambar as gambar_produk
            FROM ulasan u 
            LEFT JOIN pelanggan pl ON u.id_pelanggan = pl.id_pelanggan 
            LEFT JOIN produk pr ON u.id_produk = pr.id_produk 
            $where_sql 
            ORDER BY u.tanggal_ulasan DESC 
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $ulasan = $stmt->fetchAll();
    
    // Query untuk total ulasan (untuk pagination)
    $count_sql = "SELECT COUNT(*) as total 
                  FROM ulasan u 
                  LEFT JOIN pelanggan pl ON u.id_pelanggan = pl.id_pelanggan 
                  LEFT JOIN produk pr ON u.id_produk = pr.id_produk 
                  $where_sql";
    
    $count_stmt = $pdo->prepare($count_sql);
    
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    
    $count_stmt->execute();
    $total_ulasan = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_ulasan / $limit);
    
} catch (Exception $e) {
    $ulasan = [];
    $total_ulasan = 0;
    $total_pages = 1;
}

// Handle aksi ulasan (approve/reject/hapus)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['id_ulasan'])) {
        $id_ulasan = $_POST['id_ulasan'];
        
        try {
            if ($_POST['action'] === 'approve') {
                // Approve ulasan
                $update_sql = "UPDATE ulasan SET status = 'disetujui' WHERE id_ulasan = :id";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([':id' => $id_ulasan]);
                
                $_SESSION['success_message'] = "Ulasan berhasil disetujui";
                
            } elseif ($_POST['action'] === 'reject') {
                // Reject ulasan
                $update_sql = "UPDATE ulasan SET status = 'ditolak' WHERE id_ulasan = :id";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([':id' => $id_ulasan]);
                
                $_SESSION['success_message'] = "Ulasan berhasil ditolak";
                
            } elseif ($_POST['action'] === 'delete') {
                // Hapus ulasan
                $delete_sql = "DELETE FROM ulasan WHERE id_ulasan = :id";
                $delete_stmt = $pdo->prepare($delete_sql);
                $delete_stmt->execute([':id' => $id_ulasan]);
                
                $_SESSION['success_message'] = "Ulasan berhasil dihapus";
            }
            
            header("Location: ulasan.php");
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Gagal memproses ulasan: " . $e->getMessage();
            header("Location: ulasan.php");
            exit;
        }
    }
}

// Ambil statistik ulasan untuk card
try {
    $stats_sql = "
        SELECT 
            COUNT(*) as total_ulasan,
            AVG(rating) as rata_rata,
            SUM(CASE WHEN status = 'disetujui' THEN 1 ELSE 0 END) as disetujui,
            SUM(CASE WHEN status = 'menunggu' THEN 1 ELSE 0 END) as menunggu,
            SUM(CASE WHEN status = 'ditolak' THEN 1 ELSE 0 END) as ditolak
        FROM ulasan
    ";
    $stats_stmt = $pdo->query($stats_sql);
    $stats_ulasan = $stats_stmt->fetch();
    
} catch (Exception $e) {
    $stats_ulasan = [
        'total_ulasan' => 0,
        'rata_rata' => 0,
        'disetujui' => 0,
        'menunggu' => 0,
        'ditolak' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ulasan - Luxury Living</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        /* Filter & Search */
        .filter-container { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .search-box { position: relative; flex: 1; min-width: 250px; }
        .search-box input { width: 100%; padding: 10px 15px 10px 40px; border: 1px solid var(--cream); border-radius: var(--radius); background: white; }
        .search-box i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--brown); }
        .filter-select { padding: 10px 15px; border: 1px solid var(--cream); border-radius: var(--radius); background: white; color: var(--brown); min-width: 150px; }
        
        /* Table */
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--cream); }
        th { background: var(--cream); font-weight: 600; color: var(--dark-brown); }
        tr:hover { background: #f9f9f9; }
        
        /* Rating Stars */
        .rating-stars { color: #ffc107; margin-bottom: 5px; }
        .rating-stars .far { color: #e4e5e9; }
        
        /* Status Badge */
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-disetujui { background: #d4edda; color: #155724; }
        .status-menunggu { background: #fff3cd; color: #856404; }
        .status-ditolak { background: #f8d7da; color: #721c24; }
        
        /* Buttons */
        .btn { padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer; font-weight: 500; text-decoration: none; display: inline-block; text-align: center; transition: all 0.3s; }
        .btn-primary { background: var(--gold); color: white; }
        .btn-primary:hover { background: var(--dark-brown); }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-small { padding: 5px 10px; font-size: 12px; margin: 2px; }
        
        /* Pagination */
        .pagination { display: flex; justify-content: center; margin-top: 20px; gap: 5px; }
        .pagination a, .pagination span { padding: 8px 12px; border: 1px solid var(--cream); border-radius: 5px; text-decoration: none; color: var(--brown); }
        .pagination a:hover { background: var(--cream); }
        .pagination .active { background: var(--gold); color: white; border-color: var(--gold); }
        
        /* Alert Messages */
        .alert { padding: 12px 15px; border-radius: 5px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* Ulasan Item */
        .ulasan-item { border: 1px solid var(--cream); border-radius: var(--radius); padding: 15px; margin-bottom: 15px; background: white; }
        .ulasan-header { display: flex; justify-content: between; align-items: start; margin-bottom: 10px; }
        .ulasan-info { flex: 1; }
        .ulasan-pelanggan { font-weight: 600; color: var(--dark-brown); }
        .ulasan-produk { color: var(--brown); font-size: 14px; margin-bottom: 5px; }
        .ulasan-date { color: #6c757d; font-size: 12px; }
        .ulasan-komentar { margin: 10px 0; line-height: 1.5; }
        .ulasan-actions { display: flex; gap: 5px; flex-wrap: wrap; }
        
        /* Empty State */
        .empty-state { text-align: center; padding: 40px 20px; color: var(--brown); }
        .empty-state i { font-size: 48px; color: var(--cream); margin-bottom: 10px; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container { flex-direction: column; }
            .sidebar { width: 100%; }
            .filter-container { flex-direction: column; }
            .table-responsive { overflow-x: auto; }
            .ulasan-header { flex-direction: column; }
            .ulasan-actions { margin-top: 10px; justify-content: flex-start; }
            .btn-small { padding: 4px 8px; font-size: 11px; }
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
                <li><a href="ulasan.php" class="active"><i class="fas fa-star"></i> Ulasan</a></li>
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
                <h1>Ulasan Pelanggan</h1>
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
            
            <!-- Alert Messages -->
            <?php if(isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> 
                    <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-star"></i></div>
                    <div class="stat-number"><?php echo number_format($stats_ulasan['total_ulasan']); ?></div>
                    <div class="stat-label">Total Ulasan</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-number"><?php echo number_format($stats_ulasan['rata_rata'], 1); ?></div>
                    <div class="stat-label">Rata-rata Rating</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo number_format($stats_ulasan['disetujui']); ?></div>
                    <div class="stat-label">Disetujui</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?php echo number_format($stats_ulasan['menunggu']); ?></div>
                    <div class="stat-label">Menunggu</div>
                </div>
            </div>
            
            <!-- Filter & Search -->
            <div class="card">
                <div class="filter-container">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="search" placeholder="Cari ulasan..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <select id="rating-filter" class="filter-select">
                        <option value="">Semua Rating</option>
                        <option value="5" <?php echo $rating_filter === '5' ? 'selected' : ''; ?>>5 Bintang</option>
                        <option value="4" <?php echo $rating_filter === '4' ? 'selected' : ''; ?>>4 Bintang</option>
                        <option value="3" <?php echo $rating_filter === '3' ? 'selected' : ''; ?>>3 Bintang</option>
                        <option value="2" <?php echo $rating_filter === '2' ? 'selected' : ''; ?>>2 Bintang</option>
                        <option value="1" <?php echo $rating_filter === '1' ? 'selected' : ''; ?>>1 Bintang</option>
                    </select>
                    <select id="status-filter" class="filter-select">
                        <option value="">Semua Status</option>
                        <option value="disetujui" <?php echo $status_filter === 'disetujui' ? 'selected' : ''; ?>>Disetujui</option>
                        <option value="menunggu" <?php echo $status_filter === 'menunggu' ? 'selected' : ''; ?>>Menunggu</option>
                        <option value="ditolak" <?php echo $status_filter === 'ditolak' ? 'selected' : ''; ?>>Ditolak</option>
                    </select>
                    <button type="button" id="reset-filter" class="btn btn-primary">Reset Filter</button>
                </div>
                
                <!-- Ulasan List -->
                <div class="ulasan-list">
                    <?php if(empty($ulasan)): ?>
                        <div class="empty-state">
                            <i class="fas fa-star"></i>
                            <p>Tidak ada data ulasan</p>
                            <?php if(!empty($search) || !empty($rating_filter) || !empty($status_filter)): ?>
                                <p>Coba ubah filter pencarian Anda</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach($ulasan as $u): ?>
                        <div class="ulasan-item">
                            <div class="ulasan-header">
                                <div class="ulasan-info">
                                    <div class="ulasan-pelanggan"><?php echo htmlspecialchars($u['nama_pelanggan']); ?></div>
                                    <div class="ulasan-produk">Produk: <?php echo htmlspecialchars($u['nama_produk']); ?></div>
                                    <div class="rating-stars">
                                        <?php
                                        $rating = $u['rating'];
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $rating) {
                                                echo '<i class="fas fa-star"></i>';
                                            } else {
                                                echo '<i class="far fa-star"></i>';
                                            }
                                        }
                                        ?>
                                        <span style="margin-left: 5px; color: var(--brown);">(<?php echo $rating; ?>.0)</span>
                                    </div>
                                    <div class="ulasan-date">
                                        <?php echo date('d M Y H:i', strtotime($u['tanggal_ulasan'])); ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="status-badge status-<?php echo $u['status']; ?>">
                                        <?php echo ucfirst($u['status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="ulasan-komentar">
                                <?php echo nl2br(htmlspecialchars($u['komentar'])); ?>
                            </div>
                            
                            <div class="ulasan-actions">
                                <?php if($u['status'] == 'menunggu'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="id_ulasan" value="<?php echo $u['id_ulasan']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-success btn-small" onclick="return confirm('Setujui ulasan ini?')">
                                            <i class="fas fa-check"></i> Setujui
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="id_ulasan" value="<?php echo $u['id_ulasan']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-warning btn-small" onclick="return confirm('Tolak ulasan ini?')">
                                            <i class="fas fa-times"></i> Tolak
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="id_ulasan" value="<?php echo $u['id_ulasan']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-danger btn-small" onclick="return confirm('Hapus ulasan ini? Tindakan ini tidak dapat dibatalkan!')">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div class="pagination">
                    <?php if($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&rating=<?php echo urlencode($rating_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    // Tampilkan maksimal 5 halaman
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $start_page + 4);
                    $start_page = max(1, $end_page - 4);
                    
                    for($i = $start_page; $i <= $end_page; $i++): ?>
                        <?php if($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&rating=<?php echo urlencode($rating_filter); ?>&status=<?php echo urlencode($status_filter); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&rating=<?php echo urlencode($rating_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Filter & Search
        document.getElementById('search').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
        
        document.getElementById('rating-filter').addEventListener('change', applyFilters);
        document.getElementById('status-filter').addEventListener('change', applyFilters);
        document.getElementById('reset-filter').addEventListener('click', function() {
            window.location.href = 'ulasan.php';
        });
        
        function applyFilters() {
            const search = document.getElementById('search').value;
            const rating = document.getElementById('rating-filter').value;
            const status = document.getElementById('status-filter').value;
            
            let url = 'ulasan.php?';
            if (search) url += `search=${encodeURIComponent(search)}&`;
            if (rating) url += `rating=${encodeURIComponent(rating)}&`;
            if (status) url += `status=${encodeURIComponent(status)}`;
            
            window.location.href = url;
        }
        
        // Konfirmasi untuk semua form actions
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const action = this.querySelector('input[name="action"]').value;
                const message = this.getAttribute('onsubmit') ? null : 
                    action === 'delete' ? 'Hapus ulasan ini? Tindakan ini tidak dapat dibatalkan!' :
                    action === 'approve' ? 'Setujui ulasan ini?' :
                    action === 'reject' ? 'Tolak ulasan ini?' : null;
                
                if (message && !confirm(message)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
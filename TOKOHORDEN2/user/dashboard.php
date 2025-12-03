<?php
// Cek session status sebelum memulai session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'check_auth.php';
check_user_auth();

// Koneksi database
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=toko_horden2;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

$user = [
    'id' => $_SESSION['user_id'],
    'nama' => $_SESSION['user_nama'],
    'email' => $_SESSION['user_email'],
    'telepon' => $_SESSION['user_telepon'],
    'alamat' => $_SESSION['user_alamat'],
    'kota' => $_SESSION['user_kota']
];

$user_id = $user['id'];

// Ambil data statistik user
try {
    // Total pesanan
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pesanan WHERE id_pelanggan = ?");
    $stmt->execute([$user_id]);
    $total_pesanan = $stmt->fetch()['total'];
    
    // Pesanan pending
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pesanan WHERE id_pelanggan = ? AND status_pesanan IN ('Menunggu Pembayaran', 'Diproses', 'Dikirim')");
    $stmt->execute([$user_id]);
    $pesanan_pending = $stmt->fetch()['total'];
    
    // Total belanja
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_harga), 0) as total FROM pesanan WHERE id_pelanggan = ? AND status_pembayaran = 'Lunas'");
    $stmt->execute([$user_id]);
    $total_belanja = $stmt->fetch()['total'];
    
    // Jumlah ulasan
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ulasan WHERE id_pelanggan = ?");
    $stmt->execute([$user_id]);
    $total_ulasan = $stmt->fetch()['total'];
    
    // Pesanan terbaru
    $stmt = $pdo->prepare("SELECT * FROM pesanan WHERE id_pelanggan = ? ORDER BY tanggal_pesanan DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $pesanan_terbaru = $stmt->fetchAll();
    
    // Ulasan terbaru
    $stmt = $pdo->prepare("SELECT u.*, p.nama_produk, p.foto_utama 
                          FROM ulasan u 
                          JOIN produk p ON u.id_produk = p.id_produk 
                          WHERE u.id_pelanggan = ? 
                          ORDER BY u.tanggal_ulasan DESC 
                          LIMIT 3");
    $stmt->execute([$user_id]);
    $ulasan_terbaru = $stmt->fetchAll();
    
    // Produk rekomendasi
    $produk_rekomendasi = $pdo->query("
        SELECT * FROM produk 
        WHERE status = 'Tersedia' AND stok > 0
        ORDER BY terjual DESC, rating_rata DESC 
        LIMIT 4
    ")->fetchAll();
    
} catch (Exception $e) {
    // Set default values jika error
    $total_pesanan = 0;
    $pesanan_pending = 0;
    $total_belanja = 0;
    $total_ulasan = 0;
    $pesanan_terbaru = [];
    $ulasan_terbaru = [];
    $produk_rekomendasi = [];
    
    error_log("Error fetching dashboard data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Luxury Living</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --cream: #F3E8D7; --beige: #E7D3B8; --gold: #D8A75A; 
            --brown: #6A4F37; --dark-brown: #4a3828; --light-cream: #faf6f0;
            --radius: 10px; --shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: var(--light-cream); color: var(--brown); line-height: 1.6; }
        
        /* Header */
        .header { 
            background: white; 
            padding: 15px 0;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }
        
        .logo h1 { 
            color: var(--brown); 
            font-size: 24px; 
            font-weight: 700;
        }
        .logo span { color: var(--gold); }
        .logo p { font-size: 12px; color: var(--brown); opacity: 0.8; }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--gold) 0%, var(--dark-brown) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }
        
        .user-info h3 {
            color: var(--dark-brown);
            margin-bottom: 2px;
        }
        
        .user-info p {
            font-size: 12px;
            color: var(--brown);
            opacity: 0.8;
        }
        
        /* Main Content */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, var(--gold) 0%, var(--dark-brown) 100%);
            color: white;
            padding: 30px;
            border-radius: var(--radius);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .welcome-section h1 {
            font-size: 2.2rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .welcome-section p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            text-align: center;
            transition: all 0.3s ease;
            border-left: 4px solid var(--gold);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background: var(--cream);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: var(--gold);
            font-size: 24px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gold);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--brown);
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 968px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 25px;
            margin-bottom: 25px;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--cream);
        }
        
        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark-brown);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-title i {
            color: var(--gold);
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: var(--gold);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--dark-brown);
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--brown);
            border: 2px solid var(--cream);
        }
        
        .btn-outline:hover {
            background: var(--cream);
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        
        /* Table */
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--cream);
        }
        
        th {
            background: var(--cream);
            font-weight: 600;
            color: var(--dark-brown);
            font-size: 0.9rem;
        }
        
        tr:hover {
            background: #fafafa;
        }
        
        /* Status Badge */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-menunggu-pembayaran { background: #fff3cd; color: #856404; }
        .status-diproses { background: #cce7ff; color: #004085; }
        .status-dikirim { background: #d1ecf1; color: #0c5460; }
        .status-selesai { background: #d4edda; color: #155724; }
        .status-dibatalkan { background: #f8d7da; color: #721c24; }
        
        /* Ulasan */
        .ulasan-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .ulasan-item {
            border: 1px solid var(--cream);
            border-radius: var(--radius);
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .ulasan-item:hover {
            border-color: var(--gold);
        }
        
        .ulasan-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .ulasan-produk {
            font-weight: 600;
            color: var(--dark-brown);
            font-size: 1rem;
        }
        
        .rating-stars {
            color: #ffc107;
            font-size: 0.9rem;
        }
        
        .ulasan-komentar {
            color: var(--brown);
            line-height: 1.6;
            margin-bottom: 10px;
        }
        
        .ulasan-date {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            gap: 12px;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: var(--cream);
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--brown);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .action-btn:hover {
            background: white;
            border-color: var(--gold);
            transform: translateX(5px);
        }
        
        .action-icon {
            width: 40px;
            height: 40px;
            background: var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }
        
        .action-text h4 {
            color: var(--dark-brown);
            margin-bottom: 2px;
            font-size: 0.9rem;
        }
        
        .action-text p {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        
        /* Produk Rekomendasi */
        .produk-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        
        .produk-card {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }
        
        .produk-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .produk-image {
            width: 100%;
            height: 150px;
            background: var(--cream);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--brown);
        }
        
        .produk-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .produk-info {
            padding: 15px;
        }
        
        .produk-name {
            font-weight: 600;
            color: var(--dark-brown);
            margin-bottom: 5px;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .produk-price {
            color: var(--gold);
            font-weight: 700;
            font-size: 1rem;
        }
        
        /* Navigation */
        .nav-menu {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .nav-item {
            padding: 12px 20px;
            background: white;
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--brown);
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }
        
        .nav-item:hover, .nav-item.active {
            background: var(--gold);
            color: white;
            transform: translateY(-2px);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--brown);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--cream);
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--dark-brown);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .nav-menu {
                flex-direction: column;
            }
            
            .card-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .welcome-section h1 {
                font-size: 1.8rem;
            }
            
            .produk-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 15px;
            }
            
            .card {
                padding: 20px;
            }
            
            th, td {
                padding: 8px 10px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h1>Luxury<span>Living</span></h1>
                <p>Area Member</p>
            </div>
            
            <div class="user-menu">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['nama'], 0, 1)); ?>
                </div>
                <div class="user-info">
                    <h3><?php echo htmlspecialchars($user['nama']); ?></h3>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- Navigation Menu -->
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item active">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="pesanan.php" class="nav-item">
                <i class="fas fa-shopping-cart"></i> Pesanan Saya
            </a>
            <a href="profil.php" class="nav-item">
                <i class="fas fa-user"></i> Profil Saya
            </a>
            <a href="ulasan.php" class="nav-item">
                <i class="fas fa-star"></i> Ulasan Saya
            </a>
            <a href="wishlist.php" class="nav-item">
                <i class="fas fa-heart"></i> Wishlist
            </a>
            <a href="../index.php" class="nav-item">
                <i class="fas fa-store"></i> Belanja
            </a>
            <a href="logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1>Selamat Datang, <?php echo htmlspecialchars($user['nama']); ?>! 👋</h1>
            <p>Kelola akun dan pantau aktivitas belanja Anda di Luxury Living</p>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <div class="stat-number"><?php echo number_format($total_pesanan); ?></div>
                <div class="stat-label">Total Pesanan</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo number_format($pesanan_pending); ?></div>
                <div class="stat-label">Pesanan Aktif</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-number">Rp <?php echo number_format($total_belanja, 0, ',', '.'); ?></div>
                <div class="stat-label">Total Belanja</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-number"><?php echo number_format($total_ulasan); ?></div>
                <div class="stat-label">Ulasan Diberikan</div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Left Column -->
            <div class="left-column">
                <!-- Pesanan Terbaru -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-history"></i>
                            Pesanan Terbaru
                        </h3>
                        <a href="pesanan.php" class="btn btn-primary">
                            <i class="fas fa-list"></i> Lihat Semua
                        </a>
                    </div>
                    
                    <?php if(empty($pesanan_terbaru)): ?>
                        <div class="empty-state">
                            <i class="fas fa-shopping-cart"></i>
                            <h3>Belum Ada Pesanan</h3>
                            <p>Yuk, mulai belanja produk keren pertama Anda!</p>
                            <a href="../index.php" class="btn btn-primary" style="margin-top: 15px;">
                                <i class="fas fa-shopping-bag"></i> Mulai Belanja
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Kode Pesanan</th>
                                        <th>Tanggal</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($pesanan_terbaru as $pesanan): 
                                        $status_class = strtolower(str_replace(' ', '-', $pesanan['status_pesanan']));
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($pesanan['kode_pesanan']); ?></strong>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($pesanan['tanggal_pesanan'])); ?></td>
                                        <td>Rp <?php echo number_format($pesanan['total_harga'], 0, ',', '.'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $status_class; ?>">
                                                <?php echo htmlspecialchars($pesanan['status_pesanan']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="pesanan_detail.php?id=<?php echo $pesanan['id_pesanan']; ?>" class="btn btn-outline btn-small">
                                                <i class="fas fa-eye"></i> Detail
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Produk Rekomendasi -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-gift"></i>
                            Rekomendasi Untuk Anda
                        </h3>
                        <a href="../produk.php" class="btn btn-outline">
                            <i class="fas fa-arrow-right"></i> Lihat Semua
                        </a>
                    </div>
                    
                    <div class="produk-grid">
                        <?php if(empty($produk_rekomendasi)): ?>
                            <div class="empty-state" style="grid-column: 1 / -1;">
                                <i class="fas fa-box-open"></i>
                                <p>Tidak ada produk rekomendasi saat ini</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($produk_rekomendasi as $produk): ?>
                            <a href="../produk_detail.php?id=<?php echo $produk['id_produk']; ?>" class="produk-card">
                                <div class="produk-image">
                                    <?php if(!empty($produk['foto_utama'])): ?>
                                        <img src="../uploads/produk/<?php echo htmlspecialchars($produk['foto_utama']); ?>" alt="<?php echo htmlspecialchars($produk['nama_produk']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-image"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="produk-info">
                                    <div class="produk-name"><?php echo htmlspecialchars($produk['nama_produk']); ?></div>
                                    <div class="produk-price">Rp <?php echo number_format($produk['harga_diskon'] ?: $produk['harga'], 0, ',', '.'); ?></div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="right-column">
                <!-- Ulasan Terbaru -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-star"></i>
                            Ulasan Terbaru
                        </h3>
                    </div>
                    
                    <?php if(empty($ulasan_terbaru)): ?>
                        <div class="empty-state" style="padding: 20px;">
                            <i class="fas fa-star"></i>
                            <p>Belum ada ulasan</p>
                            <a href="../produk.php" class="btn btn-outline btn-small" style="margin-top: 10px;">
                                Beri Ulasan
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="ulasan-list">
                            <?php foreach($ulasan_terbaru as $ulasan): ?>
                            <div class="ulasan-item">
                                <div class="ulasan-header">
                                    <div class="ulasan-produk"><?php echo htmlspecialchars($ulasan['nama_produk']); ?></div>
                                    <div class="rating-stars">
                                        <?php
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $ulasan['rating']) {
                                                echo '<i class="fas fa-star"></i>';
                                            } else {
                                                echo '<i class="far fa-star"></i>';
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="ulasan-komentar">
                                    "<?php echo htmlspecialchars($ulasan['komentar']); ?>"
                                </div>
                                <div class="ulasan-date">
                                    <?php echo date('d M Y', strtotime($ulasan['tanggal_ulasan'])); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-bolt"></i>
                            Aksi Cepat
                        </h3>
                    </div>
                    <div class="quick-actions">
                        <a href="../index.php" class="action-btn">
                            <div class="action-icon">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <div class="action-text">
                                <h4>Lanjutkan Belanja</h4>
                                <p>Temukan produk terbaru</p>
                            </div>
                        </a>
                        
                        <a href="profil.php" class="action-btn">
                            <div class="action-icon">
                                <i class="fas fa-user-edit"></i>
                            </div>
                            <div class="action-text">
                                <h4>Edit Profil</h4>
                                <p>Perbarui data diri</p>
                            </div>
                        </a>
                        
                        <a href="pesanan.php" class="action-btn">
                            <div class="action-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <div class="action-text">
                                <h4>Riwayat Pesanan</h4>
                                <p>Lihat semua pesanan</p>
                            </div>
                        </a>
                        
                        <a href="wishlist.php" class="action-btn">
                            <div class="action-icon">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div class="action-text">
                                <h4>Wishlist Saya</h4>
                                <p>Produk yang disukai</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Info Akun -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-info-circle"></i>
                            Info Akun
                        </h3>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 500;">Member sejak:</span>
                            <span><?php echo date('M Y'); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 500;">Status:</span>
                            <span style="color: var(--gold); font-weight: 600;">Aktif</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 500;">Poin Loyalty:</span>
                            <span style="color: var(--gold); font-weight: 600;"><?php echo number_format($total_pesanan * 10); ?> pts</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Animasi untuk cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .card, .produk-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Hover effect untuk action buttons
        const actionBtns = document.querySelectorAll('.action-btn');
        actionBtns.forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(8px)';
            });
            
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateX(0)';
            });
        });
    </script>
</body>
</html>
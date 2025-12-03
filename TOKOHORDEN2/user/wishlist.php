<?php
// Cek session status sebelum memulai session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'check_auth.php';

// Koneksi database
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=toko_horden2;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

$user = get_current_user_data();
$user_id = $user['id'];

// Inisialisasi variabel
$success_message = '';
$error_message = '';
$wishlist_items = [];

// Handle tambah ke wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_wishlist'])) {
    $id_produk = $_POST['id_produk'];
    
    try {
        // Cek apakah produk sudah ada di wishlist
        $stmt = $pdo->prepare("SELECT id_wishlist FROM wishlist WHERE id_pelanggan = ? AND id_produk = ?");
        $stmt->execute([$user_id, $id_produk]);
        
        if ($stmt->fetch()) {
            $error_message = "Produk sudah ada dalam wishlist Anda.";
        } else {
            // Cek apakah produk tersedia
            $stmt = $pdo->prepare("SELECT id_produk FROM produk WHERE id_produk = ? AND status = 'Tersedia'");
            $stmt->execute([$id_produk]);
            
            if (!$stmt->fetch()) {
                $error_message = "Produk tidak tersedia.";
            } else {
                // Tambah ke wishlist
                $stmt = $pdo->prepare("INSERT INTO wishlist (id_pelanggan, id_produk) VALUES (?, ?)");
                $stmt->execute([$user_id, $id_produk]);
                
                $success_message = "Produk berhasil ditambahkan ke wishlist!";
                
                // Refresh data
                header("Location: wishlist.php?success=1");
                exit;
            }
        }
    } catch (Exception $e) {
        $error_message = "Terjadi kesalahan saat menambahkan ke wishlist: " . $e->getMessage();
    }
}

// Handle hapus dari wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_wishlist'])) {
    $id_wishlist = $_POST['id_wishlist'];
    
    try {
        // Verifikasi kepemilikan wishlist
        $stmt = $pdo->prepare("SELECT id_wishlist FROM wishlist WHERE id_wishlist = ? AND id_pelanggan = ?");
        $stmt->execute([$id_wishlist, $user_id]);
        
        if (!$stmt->fetch()) {
            $error_message = "Item wishlist tidak ditemukan.";
        } else {
            // Hapus dari wishlist
            $stmt = $pdo->prepare("DELETE FROM wishlist WHERE id_wishlist = ?");
            $stmt->execute([$id_wishlist]);
            
            $success_message = "Produk berhasil dihapus dari wishlist!";
            
            // Refresh data
            header("Location: wishlist.php?success=1");
            exit;
        }
    } catch (Exception $e) {
        $error_message = "Terjadi kesalahan saat menghapus dari wishlist: " . $e->getMessage();
    }
}

// Handle pindah ke keranjang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pindah_keranjang'])) {
    $id_produk = $_POST['id_produk'];
    $id_wishlist = $_POST['id_wishlist'];
    
    try {
        // Cek apakah produk sudah ada di keranjang
        $stmt = $pdo->prepare("SELECT id_keranjang FROM keranjang WHERE id_pelanggan = ? AND id_produk = ?");
        $stmt->execute([$user_id, $id_produk]);
        
        if ($stmt->fetch()) {
            $error_message = "Produk sudah ada dalam keranjang Anda.";
        } else {
            // Cek stok produk
            $stmt = $pdo->prepare("SELECT stok FROM produk WHERE id_produk = ? AND status = 'Tersedia'");
            $stmt->execute([$id_produk]);
            $produk = $stmt->fetch();
            
            if (!$produk || $produk['stok'] <= 0) {
                $error_message = "Produk tidak tersedia atau stok habis.";
            } else {
                // Tambah ke keranjang
                $stmt = $pdo->prepare("INSERT INTO keranjang (id_pelanggan, id_produk, jumlah) VALUES (?, ?, 1)");
                $stmt->execute([$user_id, $id_produk]);
                
                // Hapus dari wishlist
                $stmt = $pdo->prepare("DELETE FROM wishlist WHERE id_wishlist = ?");
                $stmt->execute([$id_wishlist]);
                
                $success_message = "Produk berhasil dipindahkan ke keranjang!";
                
                // Refresh data
                header("Location: wishlist.php?success=1");
                exit;
            }
        }
    } catch (Exception $e) {
        $error_message = "Terjadi kesalahan saat memindahkan ke keranjang: " . $e->getMessage();
    }
}

// Handle hapus semua wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_semua'])) {
    try {
        // Hapus semua wishlist user
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE id_pelanggan = ?");
        $stmt->execute([$user_id]);
        
        $success_message = "Semua produk berhasil dihapus dari wishlist!";
        
        // Refresh data
        header("Location: wishlist.php?success=1");
        exit;
    } catch (Exception $e) {
        $error_message = "Terjadi kesalahan saat menghapus wishlist: " . $e->getMessage();
    }
}

// Ambil data wishlist
try {
    $stmt = $pdo->prepare("
        SELECT 
            w.id_wishlist,
            w.tanggal_ditambahkan,
            p.id_produk,
            p.kode_produk,
            p.nama_produk,
            p.deskripsi_singkat,
            p.harga,
            p.harga_diskon,
            p.stok,
            p.foto_utama,
            p.status as status_produk,
            k.nama_kategori,
            (SELECT COUNT(*) FROM ulasan u WHERE u.id_produk = p.id_produk) as jumlah_ulasan,
            (SELECT AVG(rating) FROM ulasan u WHERE u.id_produk = p.id_produk) as rating_rata
        FROM wishlist w
        JOIN produk p ON w.id_produk = p.id_produk
        LEFT JOIN kategori k ON p.id_kategori = k.id_kategori
        WHERE w.id_pelanggan = ?
        ORDER BY w.tanggal_ditambahkan DESC
    ");
    $stmt->execute([$user_id]);
    $wishlist_items = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = "Terjadi kesalahan saat mengambil data wishlist: " . $e->getMessage();
    error_log("Error fetching wishlist: " . $e->getMessage());
}

// Check success parameter
if (isset($_GET['success'])) {
    $success_message = "Operasi berhasil dilakukan!";
}

// Hitung total item wishlist
$total_wishlist = count($wishlist_items);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wishlist Saya - Luxury Living</title>
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
        
        /* Page Header */
        .page-header {
            background: white;
            padding: 30px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .page-title {
            font-size: 2rem;
            color: var(--dark-brown);
            margin-bottom: 5px;
            font-weight: 700;
        }
        
        .page-subtitle {
            color: var(--brown);
            font-size: 1.1rem;
        }
        
        .wishlist-stats {
            display: flex;
            align-items: center;
            gap: 15px;
            background: var(--light-cream);
            padding: 15px 20px;
            border-radius: var(--radius);
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gold);
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--brown);
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: var(--radius);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
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
        
        /* Action Bar */
        .action-bar {
            background: white;
            padding: 20px 25px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .action-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .total-items {
            font-weight: 600;
            color: var(--dark-brown);
        }
        
        /* Buttons */
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
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger-outline {
            background: transparent;
            color: #dc3545;
            border: 2px solid #dc3545;
        }
        
        .btn-danger-outline:hover {
            background: #dc3545;
            color: white;
        }
        
        /* Wishlist Grid */
        .wishlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 768px) {
            .wishlist-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .wishlist-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .wishlist-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .wishlist-card-header {
            position: relative;
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            background: var(--cream);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .wishlist-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .wishlist-actions {
            position: absolute;
            top: 15px;
            right: 15px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .action-btn {
            width: 35px;
            height: 35px;
            background: white;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
            color: var(--brown);
        }
        
        .action-btn:hover {
            background: var(--gold);
            color: white;
            transform: scale(1.1);
        }
        
        .action-btn.delete:hover {
            background: #dc3545;
        }
        
        .product-badges {
            position: absolute;
            top: 15px;
            left: 15px;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-discount {
            background: var(--gold);
            color: white;
        }
        
        .badge-out-of-stock {
            background: #dc3545;
            color: white;
        }
        
        .badge-category {
            background: var(--dark-brown);
            color: white;
        }
        
        .wishlist-card-body {
            padding: 20px;
        }
        
        .product-category {
            color: var(--brown);
            font-size: 0.8rem;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .product-name {
            font-weight: 600;
            color: var(--dark-brown);
            margin-bottom: 8px;
            font-size: 1.1rem;
            line-height: 1.4;
        }
        
        .product-description {
            color: var(--brown);
            font-size: 0.85rem;
            margin-bottom: 15px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-rating {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .rating-stars {
            color: #ffc107;
            font-size: 0.9rem;
        }
        
        .rating-text {
            color: var(--brown);
            font-size: 0.8rem;
        }
        
        .product-price {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .current-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--gold);
        }
        
        .original-price {
            font-size: 0.9rem;
            color: #6c757d;
            text-decoration: line-through;
        }
        
        .wishlist-card-footer {
            padding: 0 20px 20px;
            display: flex;
            gap: 10px;
        }
        
        .wishlist-card-footer .btn {
            flex: 1;
            justify-content: center;
        }
        
        .added-date {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        
        .empty-state i {
            font-size: 5rem;
            color: var(--cream);
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--dark-brown);
            font-size: 1.5rem;
        }
        
        .empty-state p {
            color: var(--brown);
            margin-bottom: 25px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .quick-action-card {
            background: white;
            padding: 25px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }
        
        .quick-action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .quick-action-icon {
            width: 60px;
            height: 60px;
            background: var(--cream);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: var(--gold);
            font-size: 1.5rem;
        }
        
        .quick-action-title {
            font-weight: 600;
            color: var(--dark-brown);
            margin-bottom: 8px;
        }
        
        .quick-action-desc {
            color: var(--brown);
            font-size: 0.9rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .nav-menu {
                flex-direction: column;
            }
            
            .page-header {
                flex-direction: column;
                text-align: center;
            }
            
            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .wishlist-actions {
                flex-direction: row;
            }
            
            .wishlist-card-footer {
                flex-direction: column;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 15px;
            }
            
            .card {
                padding: 20px;
            }
            
            .page-header {
                padding: 20px;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .wishlist-grid {
                grid-template-columns: 1fr;
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
            <a href="dashboard.php" class="nav-item">
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
            <a href="wishlist.php" class="nav-item active">
                <i class="fas fa-heart"></i> Wishlist
            </a>
            <a href="../index.php" class="nav-item">
                <i class="fas fa-store"></i> Belanja
            </a>
            <a href="logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Wishlist Saya</h1>
                <p class="page-subtitle">Simpan produk favorit Anda untuk dibeli nanti</p>
            </div>
            <div class="wishlist-stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $total_wishlist; ?></div>
                    <div class="stat-label">Total Produk</div>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if(!empty($wishlist_items)): ?>
        <!-- Action Bar -->
        <div class="action-bar">
            <div class="action-info">
                <div class="total-items">
                    <i class="fas fa-heart" style="color: var(--gold);"></i>
                    <?php echo $total_wishlist; ?> produk dalam wishlist
                </div>
            </div>
            <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus semua produk dari wishlist?')">
                <button type="submit" name="hapus_semua" class="btn btn-danger-outline">
                    <i class="fas fa-trash"></i> Hapus Semua
                </button>
            </form>
        </div>

        <!-- Wishlist Grid -->
        <div class="wishlist-grid">
            <?php foreach($wishlist_items as $item): 
                $harga_display = $item['harga_diskon'] ?: $item['harga'];
                $diskon = $item['harga_diskon'] ? (($item['harga'] - $item['harga_diskon']) / $item['harga'] * 100) : 0;
                $rating = $item['rating_rata'] ?: 0;
            ?>
            <div class="wishlist-card">
                <div class="wishlist-card-header">
                    <div class="product-image">
                        <?php if(!empty($item['foto_utama'])): ?>
                            <img src="../uploads/produk/<?php echo htmlspecialchars($item['foto_utama']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['nama_produk']); ?>">
                        <?php else: ?>
                            <i class="fas fa-image" style="font-size: 3rem; color: var(--beige);"></i>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-badges">
                        <?php if($item['nama_kategori']): ?>
                            <span class="badge badge-category"><?php echo htmlspecialchars($item['nama_kategori']); ?></span>
                        <?php endif; ?>
                        <?php if($diskon > 0): ?>
                            <span class="badge badge-discount">-<?php echo number_format($diskon, 0); ?>%</span>
                        <?php endif; ?>
                        <?php if($item['stok'] <= 0): ?>
                            <span class="badge badge-out-of-stock">Stok Habis</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="wishlist-actions">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="id_wishlist" value="<?php echo $item['id_wishlist']; ?>">
                            <button type="submit" name="hapus_wishlist" class="action-btn delete" 
                                    title="Hapus dari Wishlist">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="wishlist-card-body">
                    <div class="product-category">
                        <i class="fas fa-tag"></i>
                        <?php echo htmlspecialchars($item['nama_kategori'] ?? 'Uncategorized'); ?>
                    </div>
                    
                    <h3 class="product-name"><?php echo htmlspecialchars($item['nama_produk']); ?></h3>
                    
                    <?php if(!empty($item['deskripsi_singkat'])): ?>
                    <p class="product-description"><?php echo htmlspecialchars($item['deskripsi_singkat']); ?></p>
                    <?php endif; ?>
                    
                    <div class="product-rating">
                        <div class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $rating): ?>
                                    <i class="fas fa-star"></i>
                                <?php else: ?>
                                    <i class="far fa-star"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <span class="rating-text">(<?php echo $item['jumlah_ulasan']; ?> ulasan)</span>
                    </div>
                    
                    <div class="product-price">
                        <span class="current-price">Rp <?php echo number_format($harga_display, 0, ',', '.'); ?></span>
                        <?php if($item['harga_diskon']): ?>
                            <span class="original-price">Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="wishlist-card-footer">
                    <?php if($item['stok'] > 0 && $item['status_produk'] === 'Tersedia'): ?>
                    <form method="POST" style="flex: 1;">
                        <input type="hidden" name="id_produk" value="<?php echo $item['id_produk']; ?>">
                        <input type="hidden" name="id_wishlist" value="<?php echo $item['id_wishlist']; ?>">
                        <button type="submit" name="pindah_keranjang" class="btn btn-primary">
                            <i class="fas fa-shopping-cart"></i> Keranjang
                        </button>
                    </form>
                    <?php else: ?>
                    <button class="btn btn-outline" disabled style="flex: 1;">
                        <i class="fas fa-times"></i> Stok Habis
                    </button>
                    <?php endif; ?>
                    
                    <a href="../produk_detail.php?id=<?php echo $item['id_produk']; ?>" class="btn btn-outline">
                        <i class="fas fa-eye"></i> Lihat
                    </a>
                </div>
                
                <div class="added-date">
                    <i class="far fa-clock"></i>
                    Ditambahkan pada <?php echo date('d M Y', strtotime($item['tanggal_ditambahkan'])); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state">
            <i class="fas fa-heart"></i>
            <h3>Wishlist Anda Kosong</h3>
            <p>Anda belum menambahkan produk apapun ke wishlist. Jelajahi katalog kami dan temukan produk yang Anda sukai!</p>
            <a href="../index.php" class="btn btn-primary">
                <i class="fas fa-shopping-bag"></i> Mulai Belanja
            </a>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="../produk.php" class="quick-action-card">
                <div class="quick-action-icon">
                    <i class="fas fa-th-large"></i>
                </div>
                <h4 class="quick-action-title">Lihat Semua Produk</h4>
                <p class="quick-action-desc">Jelajahi koleksi lengkap produk korden kami</p>
            </a>
            
            <a href="../kategori.php" class="quick-action-card">
                <div class="quick-action-icon">
                    <i class="fas fa-tags"></i>
                </div>
                <h4 class="quick-action-title">Lihat Kategori</h4>
                <p class="quick-action-desc">Temukan produk berdasarkan kategori favorit</p>
            </a>
            
            <a href="pesanan.php" class="quick-action-card">
                <div class="quick-action-icon">
                    <i class="fas fa-history"></i>
                </div>
                <h4 class="quick-action-title">Lihat Pesanan</h4>
                <p class="quick-action-desc">Cek riwayat dan status pesanan Anda</p>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Animasi untuk cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.wishlist-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
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
                this.style.transform = 'scale(1.15)';
            });
            
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);

        // Konfirmasi hapus
        const deleteForms = document.querySelectorAll('form[onsubmit]');
        deleteForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('Apakah Anda yakin?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
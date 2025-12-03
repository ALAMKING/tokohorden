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
$ulasan = [];
$produk_belum_diulas = [];

// Handle filter
$filter_status = $_GET['status'] ?? 'semua';
$search = $_GET['search'] ?? '';

// Query untuk mengambil ulasan user
try {
    $query = "
        SELECT 
            u.*,
            p.nama_produk,
            p.foto_utama,
            p.harga,
            p.harga_diskon,
            pes.kode_pesanan,
            DATE_FORMAT(u.tanggal, '%d %M %Y') as tanggal_format
        FROM ulasan u
        JOIN produk p ON u.id_produk = p.id_produk
        LEFT JOIN pesanan pes ON u.id_pesanan = pes.id_pesanan
        WHERE u.id_pelanggan = ?
    ";
    
    $params = [$user_id];
    
    // Filter berdasarkan status
    if ($filter_status === 'disetujui') {
        $query .= " AND u.status = 'Disetujui'";
    } elseif ($filter_status === 'menunggu') {
        $query .= " AND u.status = 'Menunggu'";
    } elseif ($filter_status === 'ditolak') {
        $query .= " AND u.status = 'Ditolak'";
    }
    
    // Filter pencarian
    if (!empty($search)) {
        $query .= " AND (p.nama_produk LIKE ? OR u.komentar LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $query .= " ORDER BY u.tanggal DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $ulasan = $stmt->fetchAll();
    
    // Ambil produk yang sudah dibeli tapi belum diulas
    $stmt_belum_ulas = $pdo->prepare("
        SELECT DISTINCT
            p.id_produk,
            p.nama_produk,
            p.foto_utama,
            p.harga,
            p.harga_diskon,
            dp.jumlah,
            pes.kode_pesanan,
            pes.tanggal_pesanan,
            pes.id_pesanan
        FROM detail_pesanan dp
        JOIN produk p ON dp.id_produk = p.id_produk
        JOIN pesanan pes ON dp.id_pesanan = pes.id_pesanan
        LEFT JOIN ulasan u ON p.id_produk = u.id_produk AND u.id_pelanggan = ?
        WHERE pes.id_pelanggan = ? 
        AND pes.status_pesanan = 'Selesai'
        AND u.id_ulasan IS NULL
        ORDER BY pes.tanggal_pesanan DESC
    ");
    $stmt_belum_ulas->execute([$user_id, $user_id]);
    $produk_belum_diulas = $stmt_belum_ulas->fetchAll();
    
} catch (Exception $e) {
    $error_message = "Terjadi kesalahan saat mengambil data ulasan: " . $e->getMessage();
    error_log("Error fetching reviews: " . $e->getMessage());
}

// Handle tambah ulasan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_ulasan'])) {
    $id_produk = $_POST['id_produk'];
    $rating = $_POST['rating'];
    $komentar = $_POST['komentar'];
    $id_pesanan = $_POST['id_pesanan'] ?? null;
    
    try {
        // Validasi apakah user benar-benar membeli produk ini
        $stmt = $pdo->prepare("
            SELECT dp.id_detail 
            FROM detail_pesanan dp
            JOIN pesanan p ON dp.id_pesanan = p.id_pesanan
            WHERE p.id_pelanggan = ? AND dp.id_produk = ? AND p.status_pesanan = 'Selesai'
        ");
        $stmt->execute([$user_id, $id_produk]);
        
        if (!$stmt->fetch()) {
            $error_message = "Anda belum membeli produk ini atau pesanan belum selesai.";
        } else {
            // Cek apakah sudah ada ulasan untuk produk ini
            $stmt = $pdo->prepare("SELECT id_ulasan FROM ulasan WHERE id_pelanggan = ? AND id_produk = ?");
            $stmt->execute([$user_id, $id_produk]);
            
            if ($stmt->fetch()) {
                $error_message = "Anda sudah memberikan ulasan untuk produk ini.";
            } else {
                // Insert ulasan baru
                $stmt = $pdo->prepare("
                    INSERT INTO ulasan (id_pelanggan, id_produk, id_pesanan, rating, komentar, status) 
                    VALUES (?, ?, ?, ?, ?, 'Menunggu')
                ");
                $stmt->execute([$user_id, $id_produk, $id_pesanan, $rating, $komentar]);
                
                $success_message = "Ulasan berhasil dikirim! Menunggu persetujuan admin.";
                
                // Refresh data
                header("Location: ulasan.php?success=1");
                exit;
            }
        }
    } catch (Exception $e) {
        $error_message = "Terjadi kesalahan saat mengirim ulasan: " . $e->getMessage();
    }
}

// Handle edit ulasan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_ulasan'])) {
    $id_ulasan = $_POST['id_ulasan'];
    $rating = $_POST['rating'];
    $komentar = $_POST['komentar'];
    
    try {
        // Verifikasi kepemilikan ulasan
        $stmt = $pdo->prepare("SELECT id_ulasan FROM ulasan WHERE id_ulasan = ? AND id_pelanggan = ?");
        $stmt->execute([$id_ulasan, $user_id]);
        
        if (!$stmt->fetch()) {
            $error_message = "Ulasan tidak ditemukan.";
        } else {
            // Update ulasan
            $stmt = $pdo->prepare("
                UPDATE ulasan 
                SET rating = ?, komentar = ?, status = 'Menunggu' 
                WHERE id_ulasan = ?
            ");
            $stmt->execute([$rating, $komentar, $id_ulasan]);
            
            $success_message = "Ulasan berhasil diperbarui! Menunggu persetujuan admin.";
            
            // Refresh data
            header("Location: ulasan.php?success=1");
            exit;
        }
    } catch (Exception $e) {
        $error_message = "Terjadi kesalahan saat memperbarui ulasan: " . $e->getMessage();
    }
}

// Handle hapus ulasan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_ulasan'])) {
    $id_ulasan = $_POST['id_ulasan'];
    
    try {
        // Verifikasi kepemilikan ulasan
        $stmt = $pdo->prepare("SELECT id_ulasan FROM ulasan WHERE id_ulasan = ? AND id_pelanggan = ?");
        $stmt->execute([$id_ulasan, $user_id]);
        
        if (!$stmt->fetch()) {
            $error_message = "Ulasan tidak ditemukan.";
        } else {
            // Hapus ulasan
            $stmt = $pdo->prepare("DELETE FROM ulasan WHERE id_ulasan = ?");
            $stmt->execute([$id_ulasan]);
            
            $success_message = "Ulasan berhasil dihapus!";
            
            // Refresh data
            header("Location: ulasan.php?success=1");
            exit;
        }
    } catch (Exception $e) {
        $error_message = "Terjadi kesalahan saat menghapus ulasan: " . $e->getMessage();
    }
}

// Check success parameter
if (isset($_GET['success'])) {
    $success_message = "Operasi berhasil dilakukan!";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ulasan Saya - Luxury Living</title>
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
        }
        
        .page-title {
            font-size: 2rem;
            color: var(--dark-brown);
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .page-subtitle {
            color: var(--brown);
            font-size: 1.1rem;
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
        
        /* Filter Section */
        .filter-section {
            background: white;
            padding: 25px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 25px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 15px;
            align-items: end;
        }
        
        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-brown);
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--beige);
            border-radius: var(--radius);
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(216,167,90,0.1);
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
        
        /* Cards */
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
        
        /* Ulasan List */
        .ulasan-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
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
            margin-bottom: 15px;
            gap: 15px;
        }
        
        .produk-info {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            flex: 1;
        }
        
        .produk-image {
            width: 80px;
            height: 80px;
            background: var(--cream);
            border-radius: var(--radius);
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .produk-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .produk-details {
            flex: 1;
        }
        
        .produk-name {
            font-weight: 600;
            color: var(--dark-brown);
            margin-bottom: 5px;
            font-size: 1.1rem;
        }
        
        .produk-meta {
            color: var(--brown);
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        
        .rating-stars {
            color: #ffc107;
            font-size: 1rem;
            margin-bottom: 10px;
        }
        
        .ulasan-komentar {
            color: var(--brown);
            line-height: 1.6;
            margin-bottom: 15px;
            padding: 15px;
            background: var(--light-cream);
            border-radius: var(--radius);
        }
        
        .ulasan-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid var(--cream);
        }
        
        .ulasan-date {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .ulasan-actions {
            display: flex;
            gap: 8px;
        }
        
        /* Status Badge */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-disetujui { background: #d4edda; color: #155724; }
        .status-menunggu { background: #fff3cd; color: #856404; }
        .status-ditolak { background: #f8d7da; color: #721c24; }
        
        /* Produk List */
        .produk-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        
        .produk-card {
            border: 1px solid var(--cream);
            border-radius: var(--radius);
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .produk-card:hover {
            border-color: var(--gold);
            transform: translateY(-2px);
        }
        
        .produk-card-header {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .produk-card-image {
            width: 70px;
            height: 70px;
            background: var(--cream);
            border-radius: var(--radius);
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .produk-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .produk-card-details {
            flex: 1;
        }
        
        .produk-card-name {
            font-weight: 600;
            color: var(--dark-brown);
            margin-bottom: 5px;
            font-size: 1rem;
        }
        
        .produk-card-meta {
            color: var(--brown);
            font-size: 0.8rem;
            margin-bottom: 5px;
        }
        
        .produk-card-price {
            color: var(--gold);
            font-weight: 700;
            font-size: 0.9rem;
        }
        
        /* Rating Input */
        .rating-input {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 5px;
            margin-bottom: 15px;
        }
        
        .rating-input input {
            display: none;
        }
        
        .rating-input label {
            cursor: pointer;
            font-size: 1.5rem;
            color: var(--cream);
            transition: color 0.3s ease;
        }
        
        .rating-input input:checked ~ label,
        .rating-input label:hover,
        .rating-input label:hover ~ label {
            color: #ffc107;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-content {
            background: white;
            border-radius: var(--radius);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid var(--cream);
        }
        
        .modal-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark-brown);
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--brown);
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid var(--cream);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--brown);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--cream);
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--dark-brown);
            font-size: 1.3rem;
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
            
            .ulasan-header {
                flex-direction: column;
            }
            
            .produk-info {
                flex-direction: column;
                text-align: center;
            }
            
            .ulasan-footer {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            
            .ulasan-actions {
                width: 100%;
                justify-content: flex-end;
            }
            
            .produk-grid {
                grid-template-columns: 1fr;
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
            <a href="ulasan.php" class="nav-item active">
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

        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Ulasan Saya</h1>
            <p class="page-subtitle">Kelola ulasan untuk produk yang telah Anda beli</p>
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

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="filter-grid">
                <div class="form-group">
                    <label class="form-label">Cari Ulasan</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Cari berdasarkan nama produk atau komentar..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status Ulasan</label>
                    <select name="status" class="form-control">
                        <option value="semua" <?php echo $filter_status === 'semua' ? 'selected' : ''; ?>>Semua Status</option>
                        <option value="disetujui" <?php echo $filter_status === 'disetujui' ? 'selected' : ''; ?>>Disetujui</option>
                        <option value="menunggu" <?php echo $filter_status === 'menunggu' ? 'selected' : ''; ?>>Menunggu</option>
                        <option value="ditolak" <?php echo $filter_status === 'ditolak' ? 'selected' : ''; ?>>Ditolak</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="margin-top: 24px;">
                        <i class="fas fa-filter"></i> Terapkan Filter
                    </button>
                    <a href="ulasan.php" class="btn btn-outline" style="margin-top: 24px;">
                        <i class="fas fa-refresh"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Produk Belum Diulas -->
        <?php if(!empty($produk_belum_diulas)): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-edit"></i>
                    Produk Menunggu Ulasan
                </h3>
                <span class="status-badge status-menunggu">
                    <?php echo count($produk_belum_diulas); ?> Produk
                </span>
            </div>
            
            <div class="produk-grid">
                <?php foreach($produk_belum_diulas as $produk): ?>
                <div class="produk-card">
                    <div class="produk-card-header">
                        <div class="produk-card-image">
                            <?php if(!empty($produk['foto_utama'])): ?>
                                <img src="../uploads/produk/<?php echo htmlspecialchars($produk['foto_utama']); ?>" 
                                     alt="<?php echo htmlspecialchars($produk['nama_produk']); ?>">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: var(--brown);">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="produk-card-details">
                            <div class="produk-card-name"><?php echo htmlspecialchars($produk['nama_produk']); ?></div>
                            <div class="produk-card-meta">
                                Kode Pesanan: <?php echo htmlspecialchars($produk['kode_pesanan']); ?>
                            </div>
                            <div class="produk-card-price">
                                Rp <?php echo number_format($produk['harga_diskon'] ?: $produk['harga'], 0, ',', '.'); ?>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-small" onclick="openReviewModal(
                        <?php echo $produk['id_produk']; ?>, 
                        '<?php echo htmlspecialchars($produk['nama_produk']); ?>',
                        <?php echo $produk['id_pesanan']; ?>
                    )">
                        <i class="fas fa-star"></i> Beri Ulasan
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Daftar Ulasan -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list"></i>
                    Daftar Ulasan Saya
                </h3>
                <span class="status-badge status-disetujui">
                    <?php echo count($ulasan); ?> Ulasan
                </span>
            </div>
            
            <?php if(empty($ulasan)): ?>
                <div class="empty-state">
                    <i class="fas fa-star"></i>
                    <h3>Belum Ada Ulasan</h3>
                    <p>Anda belum memberikan ulasan untuk produk apapun.</p>
                    <?php if(empty($produk_belum_diulas)): ?>
                        <p style="margin-top: 10px;">Beli produk terlebih dahulu untuk dapat memberikan ulasan.</p>
                        <a href="../index.php" class="btn btn-primary" style="margin-top: 15px;">
                            <i class="fas fa-shopping-bag"></i> Mulai Belanja
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="ulasan-list">
                    <?php foreach($ulasan as $item): ?>
                    <div class="ulasan-item">
                        <div class="ulasan-header">
                            <div class="produk-info">
                                <div class="produk-image">
                                    <?php if(!empty($item['foto_utama'])): ?>
                                        <img src="../uploads/produk/<?php echo htmlspecialchars($item['foto_utama']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['nama_produk']); ?>">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: var(--brown);">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="produk-details">
                                    <div class="produk-name"><?php echo htmlspecialchars($item['nama_produk']); ?></div>
                                    <div class="produk-meta">
                                        Kode Pesanan: <?php echo htmlspecialchars($item['kode_pesanan'] ?? 'N/A'); ?>
                                    </div>
                                    <div class="rating-stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php if ($i <= $item['rating']): ?>
                                                <i class="fas fa-star"></i>
                                            <?php else: ?>
                                                <i class="far fa-star"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        <span style="color: var(--brown); margin-left: 8px; font-size: 0.9rem;">
                                            (<?php echo $item['rating']; ?>/5)
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <span class="status-badge status-<?php echo strtolower($item['status']); ?>">
                                <?php echo $item['status']; ?>
                            </span>
                        </div>
                        
                        <?php if(!empty($item['komentar'])): ?>
                        <div class="ulasan-komentar">
                            <?php echo nl2br(htmlspecialchars($item['komentar'])); ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="ulasan-footer">
                            <div class="ulasan-date">
                                <i class="fas fa-calendar"></i>
                                <?php echo $item['tanggal_format']; ?>
                            </div>
                            <div class="ulasan-actions">
                                <?php if($item['status'] === 'Menunggu' || $item['status'] === 'Ditolak'): ?>
                                    <button class="btn btn-warning btn-small" 
                                            onclick="openEditModal(
                                                <?php echo $item['id_ulasan']; ?>, 
                                                <?php echo $item['rating']; ?>, 
                                                '<?php echo htmlspecialchars($item['komentar'] ?? ''); ?>'
                                            )">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="id_ulasan" value="<?php echo $item['id_ulasan']; ?>">
                                    <button type="submit" name="hapus_ulasan" class="btn btn-danger btn-small" 
                                            onclick="return confirm('Apakah Anda yakin ingin menghapus ulasan ini?')">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Tambah Ulasan -->
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Beri Ulasan</h3>
                <button class="modal-close" onclick="closeReviewModal()">&times;</button>
            </div>
            <form method="POST" id="reviewForm">
                <div class="modal-body">
                    <input type="hidden" name="id_produk" id="reviewProductId">
                    <input type="hidden" name="id_pesanan" id="reviewOrderId">
                    
                    <div class="form-group">
                        <label class="form-label">Produk</label>
                        <div id="reviewProductName" style="padding: 10px; background: var(--light-cream); border-radius: var(--radius); font-weight: 600; color: var(--dark-brown);"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Rating *</label>
                        <div class="rating-input">
                            <input type="radio" id="star5" name="rating" value="5" required>
                            <label for="star5"><i class="fas fa-star"></i></label>
                            <input type="radio" id="star4" name="rating" value="4">
                            <label for="star4"><i class="fas fa-star"></i></label>
                            <input type="radio" id="star3" name="rating" value="3">
                            <label for="star3"><i class="fas fa-star"></i></label>
                            <input type="radio" id="star2" name="rating" value="2">
                            <label for="star2"><i class="fas fa-star"></i></label>
                            <input type="radio" id="star1" name="rating" value="1">
                            <label for="star1"><i class="fas fa-star"></i></label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Komentar *</label>
                        <textarea name="komentar" class="form-control" rows="5" 
                                  placeholder="Bagikan pengalaman Anda menggunakan produk ini..." 
                                  required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeReviewModal()">Batal</button>
                    <button type="submit" name="tambah_ulasan" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Kirim Ulasan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Ulasan -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Ulasan</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" id="editForm">
                <div class="modal-body">
                    <input type="hidden" name="id_ulasan" id="editReviewId">
                    
                    <div class="form-group">
                        <label class="form-label">Rating *</label>
                        <div class="rating-input" id="editRatingInput">
                            <input type="radio" id="editStar5" name="rating" value="5">
                            <label for="editStar5"><i class="fas fa-star"></i></label>
                            <input type="radio" id="editStar4" name="rating" value="4">
                            <label for="editStar4"><i class="fas fa-star"></i></label>
                            <input type="radio" id="editStar3" name="rating" value="3">
                            <label for="editStar3"><i class="fas fa-star"></i></label>
                            <input type="radio" id="editStar2" name="rating" value="2">
                            <label for="editStar2"><i class="fas fa-star"></i></label>
                            <input type="radio" id="editStar1" name="rating" value="1">
                            <label for="editStar1"><i class="fas fa-star"></i></label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Komentar *</label>
                        <textarea name="komentar" id="editComment" class="form-control" rows="5" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeEditModal()">Batal</button>
                    <button type="submit" name="edit_ulasan" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functionality
        function openReviewModal(productId, productName, orderId = null) {
            document.getElementById('reviewProductId').value = productId;
            document.getElementById('reviewProductName').textContent = productName;
            if (orderId) {
                document.getElementById('reviewOrderId').value = orderId;
            }
            document.getElementById('reviewModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeReviewModal() {
            document.getElementById('reviewModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            document.getElementById('reviewForm').reset();
        }
        
        function openEditModal(reviewId, rating, comment) {
            document.getElementById('editReviewId').value = reviewId;
            document.getElementById('editComment').value = comment;
            
            // Set rating stars
            const ratingInput = document.getElementById('editRatingInput');
            const stars = ratingInput.querySelectorAll('input');
            stars.forEach(star => {
                if (parseInt(star.value) === rating) {
                    star.checked = true;
                }
            });
            
            document.getElementById('editModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                closeReviewModal();
                closeEditModal();
            }
        });
        
        // Rating star interaction
        document.querySelectorAll('.rating-input').forEach(ratingInput => {
            const stars = ratingInput.querySelectorAll('label');
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const input = this.previousElementSibling;
                    input.checked = true;
                });
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
    </script>
</body>
</html>
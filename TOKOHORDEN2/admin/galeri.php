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
$kategori_filter = $_GET['kategori'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Handle upload gambar
$upload_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['gambar'])) {
    $judul = $_POST['judul'] ?? '';
    $deskripsi = $_POST['deskripsi'] ?? '';
    $kategori = $_POST['kategori'] ?? 'Produk';
    $urutan = $_POST['urutan'] ?? 0;
    $status = $_POST['status'] ?? 'aktif';
    
    if (!empty($_FILES['gambar']['name'])) {
        $file = $_FILES['gambar'];
        $filename = time() . '_' . basename($file['name']);
        $upload_dir = '../uploads/galeri/';
        $target_file = $upload_dir . $filename;
        
        // Buat directory jika belum ada
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Validasi file
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($file_extension, $allowed_types)) {
            if ($file['size'] <= 5 * 1024 * 1024) { // Max 5MB
                if (move_uploaded_file($file['tmp_name'], $target_file)) {
                    try {
                        $sql = "INSERT INTO galeri (judul, kategori, gambar, deskripsi, urutan, status, tanggal_upload) 
                                VALUES (:judul, :kategori, :gambar, :deskripsi, :urutan, :status, NOW())";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            ':judul' => $judul,
                            ':kategori' => $kategori,
                            ':gambar' => $filename,
                            ':deskripsi' => $deskripsi,
                            ':urutan' => $urutan,
                            ':status' => $status
                        ]);
                        
                        $_SESSION['success_message'] = "Gambar berhasil diupload!";
                        header("Location: galeri.php");
                        exit;
                    } catch (Exception $e) {
                        $upload_message = "Error: " . $e->getMessage();
                    }
                } else {
                    $upload_message = "Gagal mengupload file.";
                }
            } else {
                $upload_message = "Ukuran file terlalu besar. Maksimal 5MB.";
            }
        } else {
            $upload_message = "Format file tidak didukung. Gunakan JPG, PNG, GIF, atau WebP.";
        }
    } else {
        $upload_message = "Pilih file gambar terlebih dahulu.";
    }
}

// Handle update gambar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_image'])) {
    $id_galeri = $_POST['id_galeri'];
    $judul = $_POST['judul'] ?? '';
    $deskripsi = $_POST['deskripsi'] ?? '';
    $kategori = $_POST['kategori'] ?? 'Produk';
    $urutan = $_POST['urutan'] ?? 0;
    $status = $_POST['status'] ?? 'aktif';
    
    try {
        $sql = "UPDATE galeri SET 
                judul = :judul, 
                kategori = :kategori, 
                deskripsi = :deskripsi, 
                urutan = :urutan, 
                status = :status 
                WHERE id_galeri = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':judul' => $judul,
            ':kategori' => $kategori,
            ':deskripsi' => $deskripsi,
            ':urutan' => $urutan,
            ':status' => $status,
            ':id' => $id_galeri
        ]);
        
        $_SESSION['success_message'] = "Data gambar berhasil diperbarui!";
        header("Location: galeri.php");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Gagal memperbarui data: " . $e->getMessage();
        header("Location: galeri.php");
        exit;
    }
}

// Handle hapus gambar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_image'])) {
    $id_galeri = $_POST['id_galeri'];
    
    try {
        // Ambil nama file gambar
        $sql = "SELECT gambar FROM galeri WHERE id_galeri = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id_galeri]);
        $gambar = $stmt->fetch();
        
        if ($gambar) {
            // Hapus file dari server
            $file_path = '../uploads/galeri/' . $gambar['gambar'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Hapus dari database
            $delete_sql = "DELETE FROM galeri WHERE id_galeri = :id";
            $delete_stmt = $pdo->prepare($delete_sql);
            $delete_stmt->execute([':id' => $id_galeri]);
            
            $_SESSION['success_message'] = "Gambar berhasil dihapus!";
            header("Location: galeri.php");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Gagal menghapus gambar: " . $e->getMessage();
        header("Location: galeri.php");
        exit;
    }
}

// Query untuk mengambil data galeri dengan filter
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(judul LIKE :search OR deskripsi LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($kategori_filter)) {
    $where_conditions[] = "kategori = :kategori";
    $params[':kategori'] = $kategori_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = :status";
    $params[':status'] = $status_filter;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}

// Query untuk data galeri
try {
    $sql = "SELECT * FROM galeri 
            $where_sql 
            ORDER BY urutan ASC, tanggal_upload DESC 
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $galeri = $stmt->fetchAll();
    
    // Query untuk total gambar (untuk pagination)
    $count_sql = "SELECT COUNT(*) as total FROM galeri $where_sql";
    $count_stmt = $pdo->prepare($count_sql);
    
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    
    $count_stmt->execute();
    $total_gambar = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_gambar / $limit);
    
    // Ambil statistik
    $stats_sql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) as aktif,
            SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) as nonaktif,
            COUNT(DISTINCT kategori) as jumlah_kategori
        FROM galeri
    ";
    $stats_stmt = $pdo->query($stats_sql);
    $stats = $stats_stmt->fetch();
    
} catch (Exception $e) {
    $galeri = [];
    $total_gambar = 0;
    $total_pages = 1;
    $stats = [
        'total' => 0,
        'aktif' => 0,
        'nonaktif' => 0,
        'jumlah_kategori' => 0
    ];
}

// Kategori options
$kategori_options = ['Produk', 'Banner', 'Showcase'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galeri - Luxury Living</title>
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
        
        /* Buttons */
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-primary { background: var(--gold); color: white; }
        .btn-primary:hover { background: var(--dark-brown); }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .btn-small { padding: 5px 10px; font-size: 12px; }
        
        /* Upload Form */
        .upload-form { background: var(--cream); padding: 20px; border-radius: var(--radius); margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 500; color: var(--dark-brown); }
        .form-control { width: 100%; padding: 10px; border: 1px solid var(--beige); border-radius: 5px; background: white; }
        .form-control:focus { outline: none; border-color: var(--gold); }
        .file-input { padding: 8px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        
        /* Gallery Grid */
        .gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .gallery-item { background: white; border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow); transition: transform 0.3s; }
        .gallery-item:hover { transform: translateY(-5px); }
        .gallery-image { width: 100%; height: 200px; object-fit: cover; border-bottom: 1px solid var(--cream); }
        .gallery-info { padding: 15px; }
        .gallery-title { font-weight: 600; color: var(--dark-brown); margin-bottom: 5px; font-size: 16px; }
        .gallery-desc { color: var(--brown); font-size: 14px; margin-bottom: 10px; line-height: 1.4; }
        .gallery-meta { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #6c757d; margin-bottom: 10px; }
        .gallery-category { background: var(--cream); padding: 2px 8px; border-radius: 10px; }
        .gallery-status { padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .status-aktif { background: #d4edda; color: #155724; }
        .status-nonaktif { background: #f8d7da; color: #721c24; }
        .gallery-actions { display: flex; gap: 5px; flex-wrap: wrap; }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; padding: 20px; }
        .modal-content { background: white; border-radius: var(--radius); width: 90%; max-width: 600px; max-height: 90vh; overflow: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--cream); }
        .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--brown); }
        .modal-body { padding: 20px; }
        .modal-image { width: 100%; max-height: 400px; object-fit: contain; border-radius: 5px; margin-bottom: 20px; }
        
        /* Pagination */
        .pagination { display: flex; justify-content: center; margin-top: 20px; gap: 5px; }
        .pagination a, .pagination span { padding: 8px 12px; border: 1px solid var(--cream); border-radius: 5px; text-decoration: none; color: var(--brown); }
        .pagination a:hover { background: var(--cream); }
        .pagination .active { background: var(--gold); color: white; border-color: var(--gold); }
        
        /* Alert Messages */
        .alert { padding: 12px 15px; border-radius: 5px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* Empty State */
        .empty-state { text-align: center; padding: 40px 20px; color: var(--brown); grid-column: 1 / -1; }
        .empty-state i { font-size: 48px; color: var(--cream); margin-bottom: 10px; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container { flex-direction: column; }
            .sidebar { width: 100%; }
            .filter-container { flex-direction: column; }
            .gallery-grid { grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); }
            .form-row { grid-template-columns: 1fr; }
            .modal-content { width: 95%; }
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
                <li><a href="laporan.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
                <li><a href="galeri.php" class="active"><i class="fas fa-images"></i> Galeri</a></li>
                <li><a href="pengaturan.php"><i class="fas fa-cog"></i> Pengaturan</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h1>Galeri Gambar</h1>
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
            
            <?php if($upload_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $upload_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-images"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                    <div class="stat-label">Total Gambar</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['aktif']); ?></div>
                    <div class="stat-label">Aktif</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-folder"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['jumlah_kategori']); ?></div>
                    <div class="stat-label">Kategori</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-upload"></i></div>
                    <div class="stat-number">+ Upload</div>
                    <div class="stat-label">Tambah Baru</div>
                </div>
            </div>
            
            <!-- Upload Form -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Upload Gambar Baru</h3>
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="upload-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Judul Gambar *</label>
                            <input type="text" name="judul" class="form-control" placeholder="Masukkan judul gambar" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Kategori *</label>
                            <select name="kategori" class="form-control" required>
                                <option value="">Pilih Kategori</option>
                                <?php foreach($kategori_options as $kategori): ?>
                                    <option value="<?php echo $kategori; ?>"><?php echo $kategori; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="deskripsi" class="form-control" rows="3" placeholder="Masukkan deskripsi gambar"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Urutan Tampil</label>
                            <input type="number" name="urutan" class="form-control" value="0" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Pilih File Gambar *</label>
                        <input type="file" name="gambar" class="form-control file-input" accept="image/*" required>
                        <small style="color: #6c757d;">Format: JPG, PNG, GIF, WebP | Maksimal: 5MB</small>
                    </div>
                    
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-upload"></i> Upload Gambar
                    </button>
                </form>
            </div>
            
            <!-- Filter & Search -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Galeri Gambar</h3>
                </div>
                
                <div class="filter-container">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="search" placeholder="Cari gambar..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <select id="kategori-filter" class="filter-select">
                        <option value="">Semua Kategori</option>
                        <?php foreach($kategori_options as $kategori): ?>
                            <option value="<?php echo $kategori; ?>" <?php echo $kategori_filter == $kategori ? 'selected' : ''; ?>>
                                <?php echo $kategori; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="status-filter" class="filter-select">
                        <option value="">Semua Status</option>
                        <option value="aktif" <?php echo $status_filter === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="nonaktif" <?php echo $status_filter === 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                    </select>
                    <button type="button" id="reset-filter" class="btn btn-primary">Reset Filter</button>
                </div>
                
                <!-- Gallery Grid -->
                <div class="gallery-grid">
                    <?php if(empty($galeri)): ?>
                        <div class="empty-state">
                            <i class="fas fa-images"></i>
                            <p>Belum ada gambar di galeri</p>
                            <?php if(!empty($search) || !empty($kategori_filter) || !empty($status_filter)): ?>
                                <p>Coba ubah filter pencarian Anda</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach($galeri as $gambar): ?>
                        <div class="gallery-item">
                            <img src="../uploads/galeri/<?php echo htmlspecialchars($gambar['gambar']); ?>" 
                                 alt="<?php echo htmlspecialchars($gambar['judul']); ?>" 
                                 class="gallery-image"
                                 onclick="openModal('<?php echo htmlspecialchars($gambar['gambar']); ?>', '<?php echo htmlspecialchars($gambar['judul']); ?>')">
                            
                            <div class="gallery-info">
                                <div class="gallery-title"><?php echo htmlspecialchars($gambar['judul']); ?></div>
                                <?php if($gambar['deskripsi']): ?>
                                    <div class="gallery-desc"><?php echo htmlspecialchars($gambar['deskripsi']); ?></div>
                                <?php endif; ?>
                                
                                <div class="gallery-meta">
                                    <span class="gallery-category"><?php echo htmlspecialchars($gambar['kategori']); ?></span>
                                    <span class="gallery-status status-<?php echo $gambar['status']; ?>">
                                        <?php echo ucfirst($gambar['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="gallery-meta">
                                    <span>Urutan: <?php echo $gambar['urutan']; ?></span>
                                    <span><?php echo date('d M Y', strtotime($gambar['tanggal_upload'])); ?></span>
                                </div>
                                
                                <div class="gallery-actions">
                                    <button class="btn btn-primary btn-small" 
                                            onclick="openModal('<?php echo htmlspecialchars($gambar['gambar']); ?>', '<?php echo htmlspecialchars($gambar['judul']); ?>')">
                                        <i class="fas fa-eye"></i> Lihat
                                    </button>
                                    <button class="btn btn-warning btn-small" 
                                            onclick="openEditModal(<?php echo $gambar['id_galeri']; ?>, '<?php echo htmlspecialchars($gambar['judul']); ?>', '<?php echo htmlspecialchars($gambar['deskripsi']); ?>', '<?php echo $gambar['kategori']; ?>', <?php echo $gambar['urutan']; ?>, '<?php echo $gambar['status']; ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="id_galeri" value="<?php echo $gambar['id_galeri']; ?>">
                                        <input type="hidden" name="delete_image" value="1">
                                        <button type="submit" class="btn btn-danger btn-small" 
                                                onclick="return confirm('Hapus gambar ini? Tindakan ini tidak dapat dibatalkan!')">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div class="pagination">
                    <?php if($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&kategori=<?php echo urlencode($kategori_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
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
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&kategori=<?php echo urlencode($kategori_filter); ?>&status=<?php echo urlencode($status_filter); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&kategori=<?php echo urlencode($kategori_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal untuk preview gambar -->
    <div id="imageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Preview Gambar</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <img id="modalImage" src="" alt="" class="modal-image">
            </div>
        </div>
    </div>
    
    <!-- Modal untuk edit gambar -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Data Gambar</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editForm" method="POST">
                    <input type="hidden" name="id_galeri" id="edit_id">
                    <input type="hidden" name="update_image" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Judul Gambar</label>
                        <input type="text" name="judul" id="edit_judul" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="deskripsi" id="edit_deskripsi" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Kategori</label>
                            <select name="kategori" id="edit_kategori" class="form-control" required>
                                <?php foreach($kategori_options as $kategori): ?>
                                    <option value="<?php echo $kategori; ?>"><?php echo $kategori; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Urutan</label>
                            <input type="number" name="urutan" id="edit_urutan" class="form-control" min="0">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-control">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                    
                    <div class="form-actions" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" class="btn" onclick="closeEditModal()">Batal</button>
                        <button type="submit" class="btn btn-success">Simpan Perubahan</button>
                    </div>
                </form>
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
        
        document.getElementById('kategori-filter').addEventListener('change', applyFilters);
        document.getElementById('status-filter').addEventListener('change', applyFilters);
        document.getElementById('reset-filter').addEventListener('click', function() {
            window.location.href = 'galeri.php';
        });
        
        function applyFilters() {
            const search = document.getElementById('search').value;
            const kategori = document.getElementById('kategori-filter').value;
            const status = document.getElementById('status-filter').value;
            
            let url = 'galeri.php?';
            if (search) url += `search=${encodeURIComponent(search)}&`;
            if (kategori) url += `kategori=${encodeURIComponent(kategori)}&`;
            if (status) url += `status=${encodeURIComponent(status)}`;
            
            window.location.href = url;
        }
        
        // Modal functions
        function openModal(imageSrc, title) {
            document.getElementById('modalImage').src = '../uploads/galeri/' + imageSrc;
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('imageModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('imageModal').style.display = 'none';
        }
        
        function openEditModal(id, judul, deskripsi, kategori, urutan, status) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_judul').value = judul;
            document.getElementById('edit_deskripsi').value = deskripsi;
            document.getElementById('edit_kategori').value = kategori;
            document.getElementById('edit_urutan').value = urutan;
            document.getElementById('edit_status').value = status;
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target === document.getElementById('imageModal')) {
                closeModal();
            }
            if (e.target === document.getElementById('editModal')) {
                closeEditModal();
            }
        });
        
        // Close modal with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                closeEditModal();
            }
        });
    </script>
</body>
</html>
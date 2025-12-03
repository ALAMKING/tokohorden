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

// Ambil data pengaturan toko
try {
    $stmt = $pdo->query("SELECT * FROM pengaturan_toko WHERE id = 1");
    $pengaturan = $stmt->fetch();
    
    if (!$pengaturan) {
        // Buat data default jika tidak ada
        $pengaturan = [
            'id' => 1,
            'nama_toko' => 'Luxury Living',
            'deskripsi_singkat' => 'Toko horden premium dengan kualitas terbaik',
            'deskripsi_lengkap' => 'Luxury Living adalah toko horden premium yang menyediakan berbagai macam korden dengan kualitas terbaik.',
            'alamat' => 'Jl. Contoh No. 123, Jakarta',
            'telepon' => '+62 812-3456-7890',
            'email' => 'info@luxuryliving.com',
            'logo' => null,
            'favicon' => null,
            'meta_keywords' => 'korden, horden, luxury living, toko korden',
            'meta_description' => 'Toko korden premium dengan kualitas terbaik',
            'tema_warna' => 'cream-gold',
            'status_toko' => 'buka'
        ];
    }
} catch (Exception $e) {
    $pengaturan = [
        'id' => 1,
        'nama_toko' => 'Luxury Living',
        'deskripsi_singkat' => 'Toko horden premium dengan kualitas terbaik',
        'deskripsi_lengkap' => '',
        'alamat' => '',
        'telepon' => '',
        'email' => '',
        'logo' => null,
        'favicon' => null,
        'meta_keywords' => '',
        'meta_description' => '',
        'tema_warna' => 'cream-gold',
        'status_toko' => 'buka'
    ];
}

// Handle update pengaturan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_pengaturan'])) {
        $nama_toko = $_POST['nama_toko'] ?? '';
        $deskripsi_singkat = $_POST['deskripsi_singkat'] ?? '';
        $deskripsi_lengkap = $_POST['deskripsi_lengkap'] ?? '';
        $alamat = $_POST['alamat'] ?? '';
        $telepon = $_POST['telepon'] ?? '';
        $email = $_POST['email'] ?? '';
        $meta_keywords = $_POST['meta_keywords'] ?? '';
        $meta_description = $_POST['meta_description'] ?? '';
        $tema_warna = $_POST['tema_warna'] ?? 'cream-gold';
        $status_toko = $_POST['status_toko'] ?? 'buka';
        
        // Handle upload logo
        $logo = $pengaturan['logo'];
        if (!empty($_FILES['logo']['name'])) {
            $file = $_FILES['logo'];
            $filename = 'logo_' . time() . '_' . basename($file['name']);
            $upload_dir = '../uploads/';
            $target_file = $upload_dir . $filename;
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($file_extension, $allowed_types)) {
                if ($file['size'] <= 2 * 1024 * 1024) { // Max 2MB
                    if (move_uploaded_file($file['tmp_name'], $target_file)) {
                        // Hapus logo lama jika ada
                        if ($logo && file_exists('../uploads/' . $logo)) {
                            unlink('../uploads/' . $logo);
                        }
                        $logo = $filename;
                    }
                }
            }
        }
        
        // Handle upload favicon
        $favicon = $pengaturan['favicon'];
        if (!empty($_FILES['favicon']['name'])) {
            $file = $_FILES['favicon'];
            $filename = 'favicon_' . time() . '_' . basename($file['name']);
            $upload_dir = '../uploads/';
            $target_file = $upload_dir . $filename;
            
            $allowed_types = ['ico', 'png', 'jpg', 'jpeg'];
            $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($file_extension, $allowed_types)) {
                if ($file['size'] <= 1 * 1024 * 1024) { // Max 1MB
                    if (move_uploaded_file($file['tmp_name'], $target_file)) {
                        // Hapus favicon lama jika ada
                        if ($favicon && file_exists('../uploads/' . $favicon)) {
                            unlink('../uploads/' . $favicon);
                        }
                        $favicon = $filename;
                    }
                }
            }
        }
        
        try {
            // Cek apakah data sudah ada
            $check_stmt = $pdo->query("SELECT id FROM pengaturan_toko WHERE id = 1");
            $existing = $check_stmt->fetch();
            
            if ($existing) {
                // Update data
                $sql = "UPDATE pengaturan_toko SET 
                        nama_toko = :nama_toko,
                        deskripsi_singkat = :deskripsi_singkat,
                        deskripsi_lengkap = :deskripsi_lengkap,
                        alamat = :alamat,
                        telepon = :telepon,
                        email = :email,
                        logo = :logo,
                        favicon = :favicon,
                        meta_keywords = :meta_keywords,
                        meta_description = :meta_description,
                        tema_warna = :tema_warna,
                        status_toko = :status_toko,
                        updated_at = NOW()
                        WHERE id = 1";
            } else {
                // Insert data baru
                $sql = "INSERT INTO pengaturan_toko (
                        nama_toko, deskripsi_singkat, deskripsi_lengkap, alamat, telepon, email, 
                        logo, favicon, meta_keywords, meta_description, tema_warna, status_toko
                    ) VALUES (
                        :nama_toko, :deskripsi_singkat, :deskripsi_lengkap, :alamat, :telepon, :email,
                        :logo, :favicon, :meta_keywords, :meta_description, :tema_warna, :status_toko
                    )";
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nama_toko' => $nama_toko,
                ':deskripsi_singkat' => $deskripsi_singkat,
                ':deskripsi_lengkap' => $deskripsi_lengkap,
                ':alamat' => $alamat,
                ':telepon' => $telepon,
                ':email' => $email,
                ':logo' => $logo,
                ':favicon' => $favicon,
                ':meta_keywords' => $meta_keywords,
                ':meta_description' => $meta_description,
                ':tema_warna' => $tema_warna,
                ':status_toko' => $status_toko
            ]);
            
            $_SESSION['success_message'] = "Pengaturan berhasil diperbarui!";
            header("Location: pengaturan.php");
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Gagal memperbarui pengaturan: " . $e->getMessage();
            header("Location: pengaturan.php");
            exit;
        }
    }
    
    // Handle backup database
    if (isset($_POST['backup_database'])) {
        try {
            $backup_file = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backup_path = '../backups/' . $backup_file;
            
            if (!is_dir('../backups')) {
                mkdir('../backups', 0755, true);
            }
            
            // Simple backup using mysqldump (requires exec access)
            $command = "mysqldump --user=root --password= --host=127.0.0.1 toko_horden2 > " . $backup_path;
            exec($command, $output, $return_var);
            
            if ($return_var === 0) {
                // Simpan log backup
                $log_sql = "INSERT INTO backup_log (nama_file, ukuran, keterangan) VALUES (:nama_file, :ukuran, :keterangan)";
                $log_stmt = $pdo->prepare($log_sql);
                $log_stmt->execute([
                    ':nama_file' => $backup_file,
                    ':ukuran' => filesize($backup_path) ? round(filesize($backup_path) / 1024, 2) . ' KB' : '0 KB',
                    ':keterangan' => 'Backup otomatis sistem'
                ]);
                
                $_SESSION['success_message'] = "Backup database berhasil dibuat!";
            } else {
                $_SESSION['error_message'] = "Gagal membuat backup database.";
            }
            
            header("Location: pengaturan.php");
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error backup: " . $e->getMessage();
            header("Location: pengaturan.php");
            exit;
        }
    }
}

// Ambil data backup log
try {
    $backup_stmt = $pdo->query("SELECT * FROM backup_log ORDER BY tanggal DESC LIMIT 10");
    $backup_logs = $backup_stmt->fetchAll();
} catch (Exception $e) {
    $backup_logs = [];
}

// Ambil statistik sistem
try {
    $stats_sql = "
        SELECT 
            (SELECT COUNT(*) FROM produk) as total_produk,
            (SELECT COUNT(*) FROM pelanggan) as total_pelanggan,
            (SELECT COUNT(*) FROM pesanan WHERE DATE(tanggal_pesanan) = CURDATE()) as pesanan_hari_ini,
            (SELECT COUNT(*) FROM admin) as total_admin
    ";
    $stats_stmt = $pdo->query($stats_sql);
    $system_stats = $stats_stmt->fetch();
} catch (Exception $e) {
    $system_stats = [
        'total_produk' => 0,
        'total_pelanggan' => 0,
        'pesanan_hari_ini' => 0,
        'total_admin' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - Luxury Living</title>
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
        
        /* Form Styles */
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 500; color: var(--dark-brown); }
        .form-control { width: 100%; padding: 10px; border: 1px solid var(--cream); border-radius: 5px; background: white; }
        .form-control:focus { outline: none; border-color: var(--gold); }
        .form-text { color: #6c757d; font-size: 12px; margin-top: 5px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .file-input-wrapper { position: relative; }
        .file-input { padding: 8px; }
        .image-preview { margin-top: 10px; text-align: center; }
        .preview-image { max-width: 200px; max-height: 100px; border-radius: 5px; border: 1px solid var(--cream); }
        
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
        .btn-info { background: #17a2b8; color: white; }
        .btn-info:hover { background: #138496; }
        
        /* Tabs */
        .tabs { display: flex; border-bottom: 1px solid var(--cream); margin-bottom: 20px; }
        .tab { padding: 12px 20px; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.3s; }
        .tab.active { border-bottom-color: var(--gold); color: var(--gold); font-weight: 600; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        /* Table */
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--cream); }
        th { background: var(--cream); font-weight: 600; color: var(--dark-brown); }
        
        /* Alert Messages */
        .alert { padding: 12px 15px; border-radius: 5px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* Status Badge */
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-buka { background: #d4edda; color: #155724; }
        .status-tutup { background: #f8d7da; color: #721c24; }
        .status-maintenance { background: #fff3cd; color: #856404; }
        
        /* System Info */
        .system-info { background: var(--cream); padding: 15px; border-radius: var(--radius); margin-bottom: 20px; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .info-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--beige); }
        .info-label { font-weight: 500; }
        .info-value { color: var(--gold); font-weight: 600; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container { flex-direction: column; }
            .sidebar { width: 100%; }
            .form-row { grid-template-columns: 1fr; }
            .tabs { flex-wrap: wrap; }
            .tab { flex: 1; text-align: center; }
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
                <li><a href="galeri.php"><i class="fas fa-images"></i> Galeri</a></li>
                <li><a href="pengaturan.php" class="active"><i class="fas fa-cog"></i> Pengaturan</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h1>Pengaturan Sistem</h1>
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
            
            <!-- System Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-box"></i></div>
                    <div class="stat-number"><?php echo number_format($system_stats['total_produk']); ?></div>
                    <div class="stat-label">Total Produk</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?php echo number_format($system_stats['total_pelanggan']); ?></div>
                    <div class="stat-label">Total Pelanggan</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="stat-number"><?php echo number_format($system_stats['pesanan_hari_ini']); ?></div>
                    <div class="stat-label">Pesanan Hari Ini</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
                    <div class="stat-number"><?php echo number_format($system_stats['total_admin']); ?></div>
                    <div class="stat-label">Admin</div>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="tabs">
                <div class="tab active" data-tab="pengaturan-toko">Pengaturan Toko</div>
                <div class="tab" data-tab="backup-system">Backup System</div>
                <div class="tab" data-tab="info-system">Info System</div>
            </div>
            
            <!-- Tab Content: Pengaturan Toko -->
            <div class="tab-content active" id="pengaturan-toko">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Pengaturan Umum Toko</h3>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="update_pengaturan" value="1">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Nama Toko *</label>
                                <input type="text" name="nama_toko" class="form-control" value="<?php echo htmlspecialchars($pengaturan['nama_toko']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Status Toko</label>
                                <select name="status_toko" class="form-control">
                                    <option value="buka" <?php echo $pengaturan['status_toko'] === 'buka' ? 'selected' : ''; ?>>Buka</option>
                                    <option value="tutup" <?php echo $pengaturan['status_toko'] === 'tutup' ? 'selected' : ''; ?>>Tutup</option>
                                    <option value="maintenance" <?php echo $pengaturan['status_toko'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Deskripsi Singkat</label>
                            <textarea name="deskripsi_singkat" class="form-control" rows="2" placeholder="Deskripsi singkat toko"><?php echo htmlspecialchars($pengaturan['deskripsi_singkat']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Deskripsi Lengkap</label>
                            <textarea name="deskripsi_lengkap" class="form-control" rows="4" placeholder="Deskripsi lengkap toko"><?php echo htmlspecialchars($pengaturan['deskripsi_lengkap']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Alamat Lengkap</label>
                            <textarea name="alamat" class="form-control" rows="3" placeholder="Alamat lengkap toko"><?php echo htmlspecialchars($pengaturan['alamat']); ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Telepon *</label>
                                <input type="text" name="telepon" class="form-control" value="<?php echo htmlspecialchars($pengaturan['telepon']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($pengaturan['email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Logo Toko</label>
                                <input type="file" name="logo" class="form-control file-input" accept="image/*">
                                <div class="form-text">Format: JPG, PNG, GIF, WebP | Maksimal: 2MB</div>
                                <?php if($pengaturan['logo']): ?>
                                <div class="image-preview">
                                    <img src="../uploads/<?php echo htmlspecialchars($pengaturan['logo']); ?>" alt="Logo" class="preview-image">
                                    <div class="form-text">Logo saat ini</div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Favicon</label>
                                <input type="file" name="favicon" class="form-control file-input" accept=".ico,image/*">
                                <div class="form-text">Format: ICO, PNG, JPG | Maksimal: 1MB</div>
                                <?php if($pengaturan['favicon']): ?>
                                <div class="image-preview">
                                    <img src="../uploads/<?php echo htmlspecialchars($pengaturan['favicon']); ?>" alt="Favicon" class="preview-image">
                                    <div class="form-text">Favicon saat ini</div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Meta Keywords</label>
                                <textarea name="meta_keywords" class="form-control" rows="2" placeholder="Keyword untuk SEO, pisahkan dengan koma"><?php echo htmlspecialchars($pengaturan['meta_keywords']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Meta Description</label>
                                <textarea name="meta_description" class="form-control" rows="2" placeholder="Deskripsi untuk SEO"><?php echo htmlspecialchars($pengaturan['meta_description']); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Tema Warna</label>
                            <select name="tema_warna" class="form-control">
                                <option value="cream-gold" <?php echo $pengaturan['tema_warna'] === 'cream-gold' ? 'selected' : ''; ?>>Cream & Gold</option>
                                <option value="blue-white" <?php echo $pengaturan['tema_warna'] === 'blue-white' ? 'selected' : ''; ?>>Blue & White</option>
                                <option value="green-nature" <?php echo $pengaturan['tema_warna'] === 'green-nature' ? 'selected' : ''; ?>>Green & Nature</option>
                                <option value="dark-modern" <?php echo $pengaturan['tema_warna'] === 'dark-modern' ? 'selected' : ''; ?>>Dark & Modern</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Simpan Pengaturan
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Tab Content: Backup System -->
            <div class="tab-content" id="backup-system">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Backup Database</h3>
                    </div>
                    
                    <div class="system-info">
                        <h4 style="margin-bottom: 15px; color: var(--dark-brown);">Backup Manual</h4>
                        <p style="margin-bottom: 15px; color: var(--brown);">Backup database secara manual untuk mengamankan data toko Anda.</p>
                        
                        <form method="POST">
                            <input type="hidden" name="backup_database" value="1">
                            <button type="submit" class="btn btn-warning" onclick="return confirm('Buat backup database sekarang?')">
                                <i class="fas fa-database"></i> Buat Backup Sekarang
                            </button>
                        </form>
                    </div>
                    
                    <?php if(!empty($backup_logs)): ?>
                    <div style="margin-top: 20px;">
                        <h4 style="margin-bottom: 15px; color: var(--dark-brown);">Riwayat Backup</h4>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nama File</th>
                                        <th>Ukuran</th>
                                        <th>Keterangan</th>
                                        <th>Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($backup_logs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['nama_file']); ?></td>
                                        <td><?php echo htmlspecialchars($log['ukuran']); ?></td>
                                        <td><?php echo htmlspecialchars($log['keterangan']); ?></td>
                                        <td><?php echo date('d M Y H:i', strtotime($log['tanggal'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php else: ?>
                    <div style="text-align: center; padding: 20px; color: var(--brown);">
                        <i class="fas fa-database" style="font-size: 48px; color: var(--cream); margin-bottom: 10px;"></i>
                        <p>Belum ada riwayat backup</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Tab Content: Info System -->
            <div class="tab-content" id="info-system">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Informasi Sistem</h3>
                    </div>
                    
                    <div class="system-info">
                        <h4 style="margin-bottom: 15px; color: var(--dark-brown);">Status Sistem</h4>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">PHP Version</span>
                                <span class="info-value"><?php echo phpversion(); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Database</span>
                                <span class="info-value">MySQL</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Web Server</span>
                                <span class="info-value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status Toko</span>
                                <span class="status-badge status-<?php echo $pengaturan['status_toko']; ?>">
                                    <?php echo ucfirst($pengaturan['status_toko']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="system-info">
                        <h4 style="margin-bottom: 15px; color: var(--dark-brown);">Statistik Toko</h4>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Total Produk</span>
                                <span class="info-value"><?php echo number_format($system_stats['total_produk']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total Pelanggan</span>
                                <span class="info-value"><?php echo number_format($system_stats['total_pelanggan']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Pesanan Hari Ini</span>
                                <span class="info-value"><?php echo number_format($system_stats['pesanan_hari_ini']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total Admin</span>
                                <span class="info-value"><?php echo number_format($system_stats['total_admin']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="system-info">
                        <h4 style="margin-bottom: 15px; color: var(--dark-brown);">Pengaturan Saat Ini</h4>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Nama Toko</span>
                                <span class="info-value"><?php echo htmlspecialchars($pengaturan['nama_toko']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?php echo htmlspecialchars($pengaturan['email']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Telepon</span>
                                <span class="info-value"><?php echo htmlspecialchars($pengaturan['telepon']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Tema</span>
                                <span class="info-value"><?php echo ucfirst(str_replace('-', ' ', $pengaturan['tema_warna'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs and contents
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding content
                this.classList.add('active');
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });
        
        // Preview image before upload
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Create preview if doesn't exist
                        let previewContainer = input.parentNode.querySelector('.image-preview');
                        if (!previewContainer) {
                            previewContainer = document.createElement('div');
                            previewContainer.className = 'image-preview';
                            input.parentNode.appendChild(previewContainer);
                        }
                        
                        // Update preview
                        previewContainer.innerHTML = `
                            <img src="${e.target.result}" alt="Preview" class="preview-image">
                            <div class="form-text">Preview gambar baru</div>
                        `;
                    }
                    reader.readAsDataURL(file);
                }
            });
        });
        
        // Confirm before backup
        document.querySelector('form[action*="backup_database"]')?.addEventListener('submit', function(e) {
            if (!confirm('Buat backup database sekarang? Proses ini mungkin memerlukan beberapa saat.')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
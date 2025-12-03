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

// Ambil data statistik user untuk sidebar
try {
    // Total pesanan
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pesanan WHERE id_pelanggan = ?");
    $stmt->execute([$user_id]);
    $total_pesanan = $stmt->fetch()['total'];
    
    // Jumlah ulasan
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ulasan WHERE id_pelanggan = ?");
    $stmt->execute([$user_id]);
    $total_ulasan = $stmt->fetch()['total'];
    
} catch (Exception $e) {
    $total_pesanan = 0;
    $total_ulasan = 0;
    error_log("Error fetching user stats: " . $e->getMessage());
}

// Handle form update profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {
    $nama = $_POST['nama'];
    $email = $_POST['email'];
    $no_hp = $_POST['no_hp'];
    $alamat = $_POST['alamat'];
    $kota = $_POST['kota'];
    $kode_pos = $_POST['kode_pos'];
    
    try {
        // Validasi email unik
        $stmt = $pdo->prepare("SELECT id_pelanggan FROM pelanggan WHERE email = ? AND id_pelanggan != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $error_message = "Email sudah digunakan oleh akun lain.";
        } else {
            // Update data profil
            $stmt = $pdo->prepare("UPDATE pelanggan SET nama = ?, email = ?, no_hp = ?, alamat = ?, kota = ?, kode_pos = ? WHERE id_pelanggan = ?");
            $stmt->execute([$nama, $email, $no_hp, $alamat, $kota, $kode_pos, $user_id]);
            
            // Update session
            $_SESSION['user_nama'] = $nama;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_telepon'] = $no_hp;
            $_SESSION['user_alamat'] = $alamat;
            $_SESSION['user_kota'] = $kota;
            $_SESSION['user_kode_pos'] = $kode_pos;
            
            $success_message = "Profil berhasil diperbarui!";
            
            // Refresh user data
            $user = get_current_user_data();
            
            // Refresh statistik
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pesanan WHERE id_pelanggan = ?");
            $stmt->execute([$user_id]);
            $total_pesanan = $stmt->fetch()['total'];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ulasan WHERE id_pelanggan = ?");
            $stmt->execute([$user_id]);
            $total_ulasan = $stmt->fetch()['total'];
        }
    } catch (Exception $e) {
        $error_message = "Terjadi kesalahan saat memperbarui profil: " . $e->getMessage();
    }
}

// Handle form update password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    try {
        // Verifikasi password saat ini
        $stmt = $pdo->prepare("SELECT password FROM pelanggan WHERE id_pelanggan = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();
        
        if (!$user_data || !password_verify($current_password, $user_data['password'])) {
            $error_message = "Password saat ini salah.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "Password baru dan konfirmasi password tidak cocok.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "Password baru harus minimal 6 karakter.";
        } else {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE pelanggan SET password = ? WHERE id_pelanggan = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            $success_message = "Password berhasil diperbarui!";
        }
    } catch (Exception $e) {
        $error_message = "Terjadi kesalahan saat memperbarui password: " . $e->getMessage();
    }
}

// Ambil riwayat aktivitas terbaru
try {
    $riwayat_aktivitas = $pdo->prepare("
        (SELECT 
            'pesanan' as tipe,
            kode_pesanan as judul,
            CONCAT('Pesanan ', status_pesanan) as deskripsi,
            tanggal_pesanan as tanggal
        FROM pesanan 
        WHERE id_pelanggan = ?)
        
        UNION ALL
        
        (SELECT 
            'ulasan' as tipe,
            'Ulasan Produk' as judul,
            CONCAT('Memberikan rating ', rating, ' bintang') as deskripsi,
            tanggal_ulasan as tanggal
        FROM ulasan 
        WHERE id_pelanggan = ?)
        
        ORDER BY tanggal DESC 
        LIMIT 10
    ")->execute([$user_id, $user_id])->fetchAll();
} catch (Exception $e) {
    $riwayat_aktivitas = [];
    error_log("Error fetching activity history: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Luxury Living</title>
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
            text-align: center;
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
            max-width: 600px;
            margin: 0 auto;
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
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
        }
        
        @media (max-width: 968px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 30px;
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
            margin-bottom: 25px;
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
        
        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-brown);
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--beige);
            border-radius: var(--radius);
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(216,167,90,0.1);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Buttons */
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
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
        
        /* Profile Sidebar */
        .profile-sidebar {
            position: sticky;
            top: 100px;
        }
        
        .profile-card {
            text-align: center;
            padding: 30px 25px;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--gold) 0%, var(--dark-brown) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 2.5rem;
            margin: 0 auto 20px;
            border: 4px solid white;
            box-shadow: var(--shadow);
        }
        
        .profile-name {
            font-size: 1.4rem;
            color: var(--dark-brown);
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .profile-email {
            color: var(--brown);
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        
        .profile-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 25px 0;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px 10px;
            background: var(--light-cream);
            border-radius: var(--radius);
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
        
        /* Activity List */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            border: 1px solid var(--cream);
            border-radius: var(--radius);
            transition: all 0.3s ease;
        }
        
        .activity-item:hover {
            border-color: var(--gold);
            background: #fafafa;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            background: var(--cream);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold);
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            color: var(--dark-brown);
            margin-bottom: 5px;
            font-size: 0.95rem;
        }
        
        .activity-desc {
            color: var(--brown);
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        
        .activity-date {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 2px solid var(--cream);
            margin-bottom: 25px;
        }
        
        .tab {
            padding: 15px 25px;
            background: none;
            border: none;
            color: var(--brown);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }
        
        .tab.active {
            color: var(--gold);
            border-bottom-color: var(--gold);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Password Strength */
        .password-strength {
            height: 4px;
            background: var(--cream);
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .strength-weak { background: #e74c3c; width: 33%; }
        .strength-medium { background: #f39c12; width: 66%; }
        .strength-strong { background: #27ae60; width: 100%; }
        
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
            
            .card {
                padding: 20px;
            }
            
            .profile-stats {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                text-align: left;
                border-bottom: 1px solid var(--cream);
            }
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 15px;
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
            <a href="profil.php" class="nav-item active">
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

        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Profil Saya</h1>
            <p class="page-subtitle">Kelola informasi profil dan pengaturan akun Anda</p>
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

        <div class="content-grid">
            <!-- Main Content -->
            <div class="main-content">
                <!-- Tabs -->
                <div class="tabs">
                    <button class="tab active" data-tab="profil">Data Profil</button>
                    <button class="tab" data-tab="password">Ubah Password</button>
                    <button class="tab" data-tab="aktivitas">Riwayat Aktivitas</button>
                </div>

                <!-- Tab Content: Profil -->
                <div class="tab-content active" id="profil-tab">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-user-edit"></i>
                                Informasi Pribadi
                            </h3>
                        </div>
                        
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Nama Lengkap *</label>
                                    <input type="text" name="nama" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['nama']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Email *</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Nomor Telepon</label>
                                    <input type="tel" name="no_hp" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['telepon'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Kota</label>
                                    <input type="text" name="kota" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['kota'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label class="form-label">Alamat Lengkap</label>
                                    <textarea name="alamat" class="form-control" rows="3"><?php echo htmlspecialchars($user['alamat'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Kode Pos</label>
                                    <input type="text" name="kode_pos" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['kode_pos'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <button type="submit" name="update_profil" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Perubahan
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Tab Content: Password -->
                <div class="tab-content" id="password-tab">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-lock"></i>
                                Ubah Password
                            </h3>
                        </div>
                        
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Password Saat Ini *</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Password Baru *</label>
                                    <input type="password" name="new_password" class="form-control" id="newPassword" required>
                                    <div class="password-strength">
                                        <div class="password-strength-bar" id="passwordStrength"></div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Konfirmasi Password Baru *</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                            </div>
                            
                            <div style="background: var(--light-cream); padding: 15px; border-radius: var(--radius); margin-bottom: 20px;">
                                <h4 style="color: var(--dark-brown); margin-bottom: 10px; font-size: 0.9rem;">
                                    <i class="fas fa-info-circle"></i> Tips Password Aman:
                                </h4>
                                <ul style="color: var(--brown); font-size: 0.85rem; padding-left: 20px;">
                                    <li>Minimal 6 karakter</li>
                                    <li>Kombinasi huruf dan angka</li>
                                    <li>Gunakan karakter khusus jika memungkinkan</li>
                                </ul>
                            </div>
                            
                            <button type="submit" name="update_password" class="btn btn-primary">
                                <i class="fas fa-key"></i> Ubah Password
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Tab Content: Aktivitas -->
                <div class="tab-content" id="aktivitas-tab">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-history"></i>
                                Riwayat Aktivitas Terbaru
                            </h3>
                        </div>
                        
                        <?php if(empty($riwayat_aktivitas)): ?>
                            <div style="text-align: center; padding: 40px 20px; color: var(--brown);">
                                <i class="fas fa-history" style="font-size: 3rem; color: var(--cream); margin-bottom: 15px;"></i>
                                <h3 style="margin-bottom: 10px; color: var(--dark-brown);">Belum Ada Aktivitas</h3>
                                <p>Riwayat aktivitas Anda akan muncul di sini</p>
                            </div>
                        <?php else: ?>
                            <div class="activity-list">
                                <?php foreach($riwayat_aktivitas as $aktivitas): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <?php if($aktivitas['tipe'] === 'pesanan'): ?>
                                            <i class="fas fa-shopping-bag"></i>
                                        <?php else: ?>
                                            <i class="fas fa-star"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title"><?php echo htmlspecialchars($aktivitas['judul']); ?></div>
                                        <div class="activity-desc"><?php echo htmlspecialchars($aktivitas['deskripsi']); ?></div>
                                        <div class="activity-date"><?php echo date('d M Y H:i', strtotime($aktivitas['tanggal'])); ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="profile-sidebar">
                <!-- Profile Card -->
                <div class="card profile-card">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user['nama'], 0, 1)); ?>
                    </div>
                    <div class="profile-name"><?php echo htmlspecialchars($user['nama']); ?></div>
                    <div class="profile-email"><?php echo htmlspecialchars($user['email']); ?></div>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo number_format($total_pesanan); ?></div>
                            <div class="stat-label">Total Pesanan</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo number_format($total_ulasan); ?></div>
                            <div class="stat-label">Ulasan</div>
                        </div>
                    </div>
                    
                    <div style="font-size: 0.8rem; color: var(--brown); margin-bottom: 20px;">
                        <i class="fas fa-calendar"></i>
                        Member sejak <?php echo date('M Y'); ?>
                    </div>
                    
                    <a href="logout.php" class="btn btn-outline" style="width: 100%; justify-content: center;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-bolt"></i>
                            Aksi Cepat
                        </h3>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="pesanan.php" class="btn btn-outline" style="justify-content: flex-start;">
                            <i class="fas fa-shopping-cart"></i> Lihat Pesanan
                        </a>
                        <a href="ulasan.php" class="btn btn-outline" style="justify-content: flex-start;">
                            <i class="fas fa-star"></i> Ulasan Saya
                        </a>
                        <a href="../index.php" class="btn btn-outline" style="justify-content: flex-start;">
                            <i class="fas fa-store"></i> Lanjutkan Belanja
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));
                    
                    // Add active class to current tab and content
                    this.classList.add('active');
                    document.getElementById(`${tabId}-tab`).classList.add('active');
                });
            });
            
            // Password strength indicator
            const passwordInput = document.getElementById('newPassword');
            const strengthBar = document.getElementById('passwordStrength');
            
            if (passwordInput && strengthBar) {
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    let strength = 0;
                    
                    if (password.length >= 6) strength += 1;
                    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 1;
                    if (password.match(/\d/)) strength += 1;
                    if (password.match(/[^a-zA-Z\d]/)) strength += 1;
                    
                    strengthBar.className = 'password-strength-bar';
                    if (password.length === 0) {
                        strengthBar.style.width = '0%';
                    } else if (strength <= 1) {
                        strengthBar.classList.add('strength-weak');
                    } else if (strength <= 2) {
                        strengthBar.classList.add('strength-medium');
                    } else {
                        strengthBar.classList.add('strength-strong');
                    }
                });
            }
            
            // Form validation
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const requiredFields = this.querySelectorAll('[required]');
                    let valid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            valid = false;
                            field.style.borderColor = '#e74c3c';
                        } else {
                            field.style.borderColor = '';
                        }
                    });
                    
                    if (!valid) {
                        e.preventDefault();
                        alert('Harap lengkapi semua field yang wajib diisi!');
                    }
                });
            });
        });
    </script>
</body>
</html>
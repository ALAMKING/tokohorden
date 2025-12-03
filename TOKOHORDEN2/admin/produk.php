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

// Handle actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'hapus':
            // Handle hapus produk
            if (isset($_GET['id'])) {
                $id = $_GET['id'];
                try {
                    $stmt = $pdo->prepare("DELETE FROM produk WHERE id_produk = ?");
                    $stmt->execute([$id]);
                    $_SESSION['success'] = "Produk berhasil dihapus";
                    header('Location: produk.php');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['error'] = "Gagal menghapus produk: " . $e->getMessage();
                }
            }
            break;
    }
}

// Ambil data produk
try {
    $produk = $pdo->query("SELECT p.*, k.nama_kategori 
                          FROM produk p 
                          LEFT JOIN kategori k ON p.id_kategori = k.id_kategori 
                          ORDER BY p.created_at DESC")->fetchAll();
} catch (Exception $e) {
    $produk = [];
    $error = "Gagal memuat data produk: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Produk - Luxury Living</title>
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
            padding: 20px;
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
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-tersedia { background: #d4edda; color: #155724; }
        .status-habis { background: #f8d7da; color: #721c24; }
        .status-preorder { background: #fff3cd; color: #856404; }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-size: 14px;
        }
        
        .btn-primary {
            background: var(--gold);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--dark-brown);
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-edit, .btn-hapus, .btn-view {
            padding: 6px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
        }
        
        .btn-edit { background: #28a745; color: white; }
        .btn-edit:hover { background: #218838; }
        
        .btn-hapus { background: #dc3545; color: white; }
        .btn-hapus:hover { background: #c82333; }
        
        .btn-view { background: #17a2b8; color: white; }
        .btn-view:hover { background: #138496; }
        
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
                <li><a href="produk.php" class="active"><i class="fas fa-box"></i> Data Produk</a></li>
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
            <div class="header">
                <h1>Data Produk</h1>
                <a href="tambah_produk.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Tambah Produk
                </a>
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
            
            <div class="card">
                <?php if(empty($produk)): ?>
                    <div class="empty-state">
                        <i class="fas fa-box"></i>
                        <p>Belum ada data produk</p>
                        <a href="tambah_produk.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Tambah Produk Pertama
                        </a>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Produk</th>
                                <th>Kategori</th>
                                <th>Harga</th>
                                <th>Stok</th>
                                <th>Terjual</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($produk as $item): ?>
                            <tr>
                                <td><?php echo $item['kode_produk']; ?></td>
                                <td>
                                    <strong><?php echo $item['nama_produk']; ?></strong>
                                    <?php if($item['harga_diskon']): ?>
                                        <br><small style="color: #666;">Diskon: Rp <?php echo number_format($item['harga_diskon'], 0, ',', '.'); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $item['nama_kategori'] ?? '-'; ?></td>
                                <td>Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></td>
                                <td><?php echo $item['stok']; ?></td>
                                <td><?php echo $item['terjual']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($item['status']); ?>">
                                        <?php echo $item['status']; ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <a href="detail_produk.php?id=<?php echo $item['id_produk']; ?>" class="btn btn-view" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_produk.php?id=<?php echo $item['id_produk']; ?>" class="btn btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="produk.php?action=hapus&id=<?php echo $item['id_produk']; ?>" class="btn btn-hapus" title="Hapus" onclick="return confirm('Yakin hapus produk <?php echo $item['nama_produk']; ?>?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
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
        // Konfirmasi sebelum hapus
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('.btn-hapus');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Yakin ingin menghapus produk ini?')) {
                        e.preventDefault();
                    }
                });
            });
            
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
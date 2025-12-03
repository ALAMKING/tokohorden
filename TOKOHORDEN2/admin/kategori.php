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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['tambah_kategori'])) {
        // Tambah kategori baru
        $nama_kategori = $_POST['nama_kategori'];
        $deskripsi = $_POST['deskripsi'];
        $icon = $_POST['icon'];
        $urutan = $_POST['urutan'] ?: 0;
        
        try {
            $stmt = $pdo->prepare("INSERT INTO kategori (nama_kategori, deskripsi, icon, urutan, status) VALUES (?, ?, ?, ?, 'aktif')");
            $stmt->execute([$nama_kategori, $deskripsi, $icon, $urutan]);
            
            $_SESSION['success'] = "Kategori berhasil ditambahkan";
            header('Location: kategori.php');
            exit;
        } catch (Exception $e) {
            $error = "Gagal menambahkan kategori: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['edit_kategori'])) {
        // Edit kategori
        $id_kategori = $_POST['id_kategori'];
        $nama_kategori = $_POST['nama_kategori'];
        $deskripsi = $_POST['deskripsi'];
        $icon = $_POST['icon'];
        $urutan = $_POST['urutan'];
        $status = $_POST['status'];
        
        try {
            $stmt = $pdo->prepare("UPDATE kategori SET nama_kategori = ?, deskripsi = ?, icon = ?, urutan = ?, status = ? WHERE id_kategori = ?");
            $stmt->execute([$nama_kategori, $deskripsi, $icon, $urutan, $status, $id_kategori]);
            
            $_SESSION['success'] = "Kategori berhasil diperbarui";
            header('Location: kategori.php');
            exit;
        } catch (Exception $e) {
            $error = "Gagal memperbarui kategori: " . $e->getMessage();
        }
    }
}

// Handle hapus kategori
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    try {
        // Cek apakah kategori digunakan oleh produk
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM produk WHERE id_kategori = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result['total'] > 0) {
            $_SESSION['error'] = "Tidak dapat menghapus kategori karena masih digunakan oleh produk";
        } else {
            $stmt = $pdo->prepare("DELETE FROM kategori WHERE id_kategori = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "Kategori berhasil dihapus";
        }
        header('Location: kategori.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = "Gagal menghapus kategori: " . $e->getMessage();
        header('Location: kategori.php');
        exit;
    }
}

// Handle ubah status
if (isset($_GET['ubah_status'])) {
    $id = $_GET['ubah_status'];
    $status = $_GET['status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE kategori SET status = ? WHERE id_kategori = ?");
        $stmt->execute([$status, $id]);
        
        $_SESSION['success'] = "Status kategori berhasil diubah";
        header('Location: kategori.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = "Gagal mengubah status: " . $e->getMessage();
        header('Location: kategori.php');
        exit;
    }
}

// Ambil data kategori
try {
    $kategori = $pdo->query("SELECT k.*, 
                            (SELECT COUNT(*) FROM produk WHERE id_kategori = k.id_kategori) as jumlah_produk
                            FROM kategori k 
                            ORDER BY k.urutan ASC, k.nama_kategori ASC")->fetchAll();
} catch (Exception $e) {
    $kategori = [];
    $error = "Gagal memuat data kategori: " . $e->getMessage();
}

// Ambil data untuk edit (jika ada)
$edit_data = null;
if (isset($_GET['edit'])) {
    $id_edit = $_GET['edit'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM kategori WHERE id_kategori = ?");
        $stmt->execute([$id_edit]);
        $edit_data = $stmt->fetch();
    } catch (Exception $e) {
        $error = "Gagal memuat data edit: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Kategori - Luxury Living</title>
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
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .btn-primary {
            background: var(--gold);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--dark-brown);
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
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
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
        
        .status-aktif { background: #d4edda; color: #155724; }
        .status-nonaktif { background: #f8d7da; color: #721c24; }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--dark-brown);
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--beige);
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--gold);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
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
        
        .category-icon {
            font-size: 20px;
            color: var(--gold);
            margin-right: 10px;
        }
        
        .category-card {
            background: var(--light-cream);
            padding: 20px;
            border-radius: var(--radius);
            border-left: 4px solid var(--gold);
            margin-bottom: 15px;
        }
        
        .category-card h4 {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            color: var(--dark-brown);
        }
        
        .product-count {
            background: var(--gold);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: var(--radius);
            width: 90%;
            max-width: 500px;
            box-shadow: var(--shadow);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--cream);
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: var(--brown);
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
            
            .form-row {
                grid-template-columns: 1fr;
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
                <li><a href="produk.php"><i class="fas fa-box"></i> Data Produk</a></li>
                <li><a href="kategori.php" class="active"><i class="fas fa-tags"></i> Kategori</a></li>
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
                <h1>Data Kategori</h1>
                <button class="btn btn-primary" onclick="openModal('tambah')">
                    <i class="fas fa-plus"></i> Tambah Kategori
                </button>
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

            <!-- Statistik Kategori -->
            <div class="card">
                <h3 style="margin-bottom: 20px;">Statistik Kategori</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div style="text-align: center; padding: 20px; background: var(--light-cream); border-radius: var(--radius);">
                        <div style="font-size: 24px; font-weight: bold; color: var(--gold);"><?php echo count($kategori); ?></div>
                        <div>Total Kategori</div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: var(--light-cream); border-radius: var(--radius);">
                        <div style="font-size: 24px; font-weight: bold; color: var(--gold);">
                            <?php 
                            $aktif = array_filter($kategori, function($k) { return $k['status'] == 'aktif'; });
                            echo count($aktif); 
                            ?>
                        </div>
                        <div>Kategori Aktif</div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: var(--light-cream); border-radius: var(--radius);">
                        <div style="font-size: 24px; font-weight: bold; color: var(--gold);">
                            <?php 
                            $total_produk = array_sum(array_column($kategori, 'jumlah_produk'));
                            echo $total_produk; 
                            ?>
                        </div>
                        <div>Total Produk</div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <?php if(empty($kategori)): ?>
                    <div class="empty-state">
                        <i class="fas fa-tags"></i>
                        <p>Belum ada data kategori</p>
                        <button class="btn btn-primary" onclick="openModal('tambah')">
                            <i class="fas fa-plus"></i> Tambah Kategori Pertama
                        </button>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Icon</th>
                                <th>Nama Kategori</th>
                                <th>Deskripsi</th>
                                <th>Jumlah Produk</th>
                                <th>Urutan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($kategori as $item): ?>
                            <tr>
                                <td>
                                    <?php if($item['icon']): ?>
                                        <i class="<?php echo $item['icon']; ?> category-icon"></i>
                                    <?php else: ?>
                                        <i class="fas fa-tag category-icon"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo $item['nama_kategori']; ?></strong>
                                </td>
                                <td><?php echo $item['deskripsi'] ?: '-'; ?></td>
                                <td>
                                    <span class="product-count"><?php echo $item['jumlah_produk']; ?> produk</span>
                                </td>
                                <td><?php echo $item['urutan']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $item['status']; ?>">
                                        <?php echo $item['status']; ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <button class="btn btn-warning btn-sm" onclick="openModal('edit', <?php echo $item['id_kategori']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if($item['status'] == 'aktif'): ?>
                                        <a href="kategori.php?ubah_status=<?php echo $item['id_kategori']; ?>&status=nonaktif" class="btn btn-sm" style="background: #6c757d; color: white;" title="Nonaktifkan">
                                            <i class="fas fa-pause"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="kategori.php?ubah_status=<?php echo $item['id_kategori']; ?>&status=aktif" class="btn btn-success btn-sm" title="Aktifkan">
                                            <i class="fas fa-play"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="kategori.php?hapus=<?php echo $item['id_kategori']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin hapus kategori <?php echo $item['nama_kategori']; ?>?')" title="Hapus">
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

    <!-- Modal Tambah/Edit Kategori -->
    <div id="kategoriModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Tambah Kategori Baru</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST" action="" id="kategoriForm">
                <input type="hidden" name="id_kategori" id="id_kategori">
                <input type="hidden" name="edit_kategori" id="edit_kategori">
                
                <div class="form-group">
                    <label for="nama_kategori">Nama Kategori *</label>
                    <input type="text" id="nama_kategori" name="nama_kategori" required>
                </div>
                
                <div class="form-group">
                    <label for="deskripsi">Deskripsi</label>
                    <textarea id="deskripsi" name="deskripsi" placeholder="Deskripsi singkat kategori..."></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="icon">Icon FontAwesome</label>
                        <input type="text" id="icon" name="icon" placeholder="fas fa-tag">
                        <small style="color: #666;">Contoh: fas fa-tag, fas fa-home, fas fa-star</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="urutan">Urutan Tampil</label>
                        <input type="number" id="urutan" name="urutan" value="0" min="0">
                    </div>
                </div>
                
                <div class="form-group" id="statusField" style="display: none;">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 25px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i> Batal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('kategoriModal');
        const form = document.getElementById('kategoriForm');
        
        function openModal(action, id = null) {
            document.getElementById('modalTitle').textContent = action === 'tambah' ? 'Tambah Kategori Baru' : 'Edit Kategori';
            document.getElementById('edit_kategori').value = action === 'edit' ? '1' : '';
            document.getElementById('statusField').style.display = action === 'edit' ? 'block' : 'none';
            
            // Reset form
            form.reset();
            document.getElementById('id_kategori').value = '';
            
            // Jika edit, isi data
            if (action === 'edit' && id) {
                // Dalam implementasi real, Anda akan fetch data via AJAX
                // Untuk sekarang, redirect ke halaman dengan parameter edit
                window.location.href = 'kategori.php?edit=' + id;
            }
            
            modal.style.display = 'block';
        }
        
        function closeModal() {
            modal.style.display = 'none';
        }
        
        // Close modal ketika klik di luar
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
        
        // ESC key to close modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
        
        // Jika ada data edit dari PHP, buka modal
        <?php if($edit_data): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalTitle').textContent = 'Edit Kategori';
            document.getElementById('edit_kategori').value = '1';
            document.getElementById('statusField').style.display = 'block';
            document.getElementById('id_kategori').value = '<?php echo $edit_data['id_kategori']; ?>';
            document.getElementById('nama_kategori').value = '<?php echo addslashes($edit_data['nama_kategori']); ?>';
            document.getElementById('deskripsi').value = '<?php echo addslashes($edit_data['deskripsi'] ?? ''); ?>';
            document.getElementById('icon').value = '<?php echo addslashes($edit_data['icon'] ?? ''); ?>';
            document.getElementById('urutan').value = '<?php echo $edit_data['urutan']; ?>';
            document.getElementById('status').value = '<?php echo $edit_data['status']; ?>';
            modal.style.display = 'block';
        });
        <?php endif; ?>
    </script>
</body>
</html>
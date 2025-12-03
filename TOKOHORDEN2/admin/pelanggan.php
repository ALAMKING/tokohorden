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
$status_filter = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Query untuk mengambil data pelanggan dengan filter
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(nama LIKE :search OR email LIKE :search OR telepon LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = :status";
    $params[':status'] = $status_filter;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}

// Query untuk data pelanggan
try {
    $sql = "SELECT * FROM pelanggan $where_sql ORDER BY tanggal_daftar DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $pelanggan = $stmt->fetchAll();
    
    // Query untuk total pelanggan (untuk pagination)
    $count_sql = "SELECT COUNT(*) as total FROM pelanggan $where_sql";
    $count_stmt = $pdo->prepare($count_sql);
    
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    
    $count_stmt->execute();
    $total_pelanggan = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_pelanggan / $limit);
    
} catch (Exception $e) {
    $pelanggan = [];
    $total_pelanggan = 0;
    $total_pages = 1;
}

// Handle aksi pelanggan (edit/hapus)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $id_pelanggan = $_POST['id_pelanggan'] ?? '';
        
        if ($_POST['action'] === 'edit' && !empty($id_pelanggan)) {
            // Validasi input
            $nama = trim($_POST['nama'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telepon = trim($_POST['telepon'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');
            $status = $_POST['status'] ?? 'aktif';
            
            // Validasi data wajib
            if (empty($nama) || empty($email) || empty($telepon)) {
                $_SESSION['error_message'] = "Nama, email, dan telepon harus diisi";
                header("Location: pelanggan.php");
                exit;
            }
            
            // Validasi format email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error_message'] = "Format email tidak valid";
                header("Location: pelanggan.php");
                exit;
            }
            
            try {
                // Cek apakah email sudah digunakan oleh pelanggan lain
                $check_sql = "SELECT id_pelanggan FROM pelanggan WHERE email = :email AND id_pelanggan != :id";
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute([':email' => $email, ':id' => $id_pelanggan]);
                
                if ($check_stmt->fetch()) {
                    $_SESSION['error_message'] = "Email sudah digunakan oleh pelanggan lain";
                    header("Location: pelanggan.php");
                    exit;
                }
                
                // Update data pelanggan
                $update_sql = "UPDATE pelanggan SET 
                              nama = :nama, 
                              email = :email, 
                              telepon = :telepon, 
                              alamat = :alamat, 
                              status = :status 
                              WHERE id_pelanggan = :id";
                
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([
                    ':nama' => $nama,
                    ':email' => $email,
                    ':telepon' => $telepon,
                    ':alamat' => $alamat,
                    ':status' => $status,
                    ':id' => $id_pelanggan
                ]);
                
                // Cek apakah data berhasil diupdate
                if ($update_stmt->rowCount() > 0) {
                    $_SESSION['success_message'] = "Data pelanggan berhasil diperbarui";
                } else {
                    $_SESSION['error_message'] = "Tidak ada perubahan data atau pelanggan tidak ditemukan";
                }
                
                header("Location: pelanggan.php");
                exit;
                
            } catch (Exception $e) {
                $_SESSION['error_message'] = "Gagal memperbarui data pelanggan: " . $e->getMessage();
                header("Location: pelanggan.php");
                exit;
            }
            
        } elseif ($_POST['action'] === 'delete' && !empty($id_pelanggan)) {
            // Hapus pelanggan
            try {
                // Cek apakah pelanggan memiliki pesanan
                $check_order_sql = "SELECT COUNT(*) as total_pesanan FROM pesanan WHERE id_pelanggan = :id";
                $check_order_stmt = $pdo->prepare($check_order_sql);
                $check_order_stmt->execute([':id' => $id_pelanggan]);
                $total_pesanan = $check_order_stmt->fetch()['total_pesanan'];
                
                if ($total_pesanan > 0) {
                    $_SESSION['error_message'] = "Tidak dapat menghapus pelanggan yang memiliki pesanan";
                    header("Location: pelanggan.php");
                    exit;
                }
                
                $delete_sql = "DELETE FROM pelanggan WHERE id_pelanggan = :id";
                $delete_stmt = $pdo->prepare($delete_sql);
                $delete_stmt->execute([':id' => $id_pelanggan]);
                
                if ($delete_stmt->rowCount() > 0) {
                    $_SESSION['success_message'] = "Pelanggan berhasil dihapus";
                } else {
                    $_SESSION['error_message'] = "Pelanggan tidak ditemukan";
                }
                
                header("Location: pelanggan.php");
                exit;
                
            } catch (Exception $e) {
                $_SESSION['error_message'] = "Gagal menghapus pelanggan: " . $e->getMessage();
                header("Location: pelanggan.php");
                exit;
            }
        }
    }
}

// Fungsi helper untuk mendapatkan nilai array dengan default
function getValue($array, $key, $default = '') {
    return isset($array[$key]) ? $array[$key] : $default;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pelanggan - Luxury Living</title>
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
        
        /* Status Badge */
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-aktif { background: #d4edda; color: #155724; }
        .status-nonaktif { background: #f8d7da; color: #721c24; }
        
        /* Buttons */
        .btn { padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer; font-weight: 500; text-decoration: none; display: inline-block; text-align: center; transition: all 0.3s; }
        .btn-primary { background: var(--gold); color: white; }
        .btn-primary:hover { background: var(--dark-brown); }
        .btn-edit { background: #17a2b8; color: white; }
        .btn-edit:hover { background: #138496; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-delete:hover { background: #c82333; }
        .btn-small { padding: 5px 10px; font-size: 12px; }
        
        /* Pagination */
        .pagination { display: flex; justify-content: center; margin-top: 20px; gap: 5px; }
        .pagination a, .pagination span { padding: 8px 12px; border: 1px solid var(--cream); border-radius: 5px; text-decoration: none; color: var(--brown); }
        .pagination a:hover { background: var(--cream); }
        .pagination .active { background: var(--gold); color: white; border-color: var(--gold); }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-content { background: white; border-radius: var(--radius); width: 90%; max-width: 500px; padding: 20px; box-shadow: var(--shadow); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--cream); }
        .modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: var(--brown); }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 500; color: var(--dark-brown); }
        .form-control { width: 100%; padding: 10px; border: 1px solid var(--cream); border-radius: 5px; background: white; }
        .form-control:focus { outline: none; border-color: var(--gold); }
        .form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        
        /* Alert Messages */
        .alert { padding: 12px 15px; border-radius: 5px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* Empty State */
        .empty-state { text-align: center; padding: 40px 20px; color: var(--brown); }
        .empty-state i { font-size: 48px; color: var(--cream); margin-bottom: 10px; }
        
        /* Loading State */
        .loading { opacity: 0.6; pointer-events: none; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container { flex-direction: column; }
            .sidebar { width: 100%; }
            .filter-container { flex-direction: column; }
            .table-responsive { overflow-x: auto; }
            .modal-content { width: 95%; margin: 20px; }
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
                <li><a href="pelanggan.php" class="active"><i class="fas fa-users"></i> Data Pelanggan</a></li>
                <li><a href="ulasan.php"><i class="fas fa-star"></i> Ulasan</a></li>
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
                <h1>Data Pelanggan</h1>
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
            
            <!-- Filter & Search -->
            <div class="card">
                <div class="filter-container">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="search" placeholder="Cari pelanggan..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <select id="status-filter" class="filter-select">
                        <option value="">Semua Status</option>
                        <option value="aktif" <?php echo $status_filter === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="nonaktif" <?php echo $status_filter === 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                    </select>
                    <button type="button" id="reset-filter" class="btn btn-primary">Reset Filter</button>
                </div>
                
                <!-- Table -->
                <div class="table-responsive">
                    <?php if(empty($pelanggan)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>Tidak ada data pelanggan</p>
                            <?php if(!empty($search) || !empty($status_filter)): ?>
                                <p>Coba ubah filter pencarian Anda</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Email</th>
                                    <th>Telepon</th>
                                    <th>Alamat</th>
                                    <th>Status</th>
                                    <th>Tanggal Daftar</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($pelanggan as $p): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(getValue($p, 'nama')); ?></td>
                                    <td><?php echo htmlspecialchars(getValue($p, 'email')); ?></td>
                                    <td><?php echo htmlspecialchars(getValue($p, 'telepon')); ?></td>
                                    <td><?php echo !empty(getValue($p, 'alamat')) ? htmlspecialchars(getValue($p, 'alamat')) : '-'; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo getValue($p, 'status'); ?>">
                                            <?php echo ucfirst(getValue($p, 'status')); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime(getValue($p, 'tanggal_daftar'))); ?></td>
                                    <td>
                                        <button class="btn btn-edit btn-small edit-btn" 
                                                data-id="<?php echo getValue($p, 'id_pelanggan'); ?>" 
                                                data-nama="<?php echo htmlspecialchars(getValue($p, 'nama')); ?>" 
                                                data-email="<?php echo htmlspecialchars(getValue($p, 'email')); ?>" 
                                                data-telepon="<?php echo htmlspecialchars(getValue($p, 'telepon')); ?>" 
                                                data-alamat="<?php echo htmlspecialchars(getValue($p, 'alamat', '')); ?>" 
                                                data-status="<?php echo getValue($p, 'status'); ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-delete btn-small delete-btn" 
                                                data-id="<?php echo getValue($p, 'id_pelanggan'); ?>" 
                                                data-nama="<?php echo htmlspecialchars(getValue($p, 'nama')); ?>">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div class="pagination">
                    <?php if($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
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
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal Edit Pelanggan -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Pelanggan</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form id="editForm" method="POST">
                <input type="hidden" name="id_pelanggan" id="edit_id">
                <input type="hidden" name="action" value="edit">
                
                <div class="form-group">
                    <label class="form-label" for="edit_nama">Nama *</label>
                    <input type="text" class="form-control" id="edit_nama" name="nama" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="edit_email">Email *</label>
                    <input type="email" class="form-control" id="edit_email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="edit_telepon">Telepon *</label>
                    <input type="text" class="form-control" id="edit_telepon" name="telepon" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="edit_alamat">Alamat</label>
                    <textarea class="form-control" id="edit_alamat" name="alamat" rows="3" placeholder="Alamat lengkap pelanggan"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="edit_status">Status *</label>
                    <select class="form-control" id="edit_status" name="status" required>
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn modal-close">Batal</button>
                    <button type="submit" class="btn btn-primary" id="saveBtn">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Hapus Pelanggan -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Hapus Pelanggan</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form id="deleteForm" method="POST">
                <input type="hidden" name="id_pelanggan" id="delete_id">
                <input type="hidden" name="action" value="delete">
                
                <p>Apakah Anda yakin ingin menghapus pelanggan <strong id="delete_nama"></strong>?</p>
                <p style="color: #dc3545; font-size: 14px; margin-top: 10px;">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Tindakan ini tidak dapat dibatalkan!
                </p>
                
                <div class="form-actions">
                    <button type="button" class="btn modal-close">Batal</button>
                    <button type="submit" class="btn btn-delete">
                        <i class="fas fa-trash"></i> Hapus
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Filter & Search
        document.getElementById('search').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
        
        document.getElementById('status-filter').addEventListener('change', applyFilters);
        document.getElementById('reset-filter').addEventListener('click', function() {
            window.location.href = 'pelanggan.php';
        });
        
        function applyFilters() {
            const search = document.getElementById('search').value;
            const status = document.getElementById('status-filter').value;
            
            let url = 'pelanggan.php?';
            if (search) url += `search=${encodeURIComponent(search)}&`;
            if (status) url += `status=${encodeURIComponent(status)}`;
            
            window.location.href = url;
        }
        
        // Modal functionality
        const editModal = document.getElementById('editModal');
        const deleteModal = document.getElementById('deleteModal');
        const closeButtons = document.querySelectorAll('.modal-close');
        const saveBtn = document.getElementById('saveBtn');
        
        // Edit buttons
        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const nama = this.getAttribute('data-nama');
                const email = this.getAttribute('data-email');
                const telepon = this.getAttribute('data-telepon');
                const alamat = this.getAttribute('data-alamat');
                const status = this.getAttribute('data-status');
                
                document.getElementById('edit_id').value = id;
                document.getElementById('edit_nama').value = nama;
                document.getElementById('edit_email').value = email;
                document.getElementById('edit_telepon').value = telepon;
                document.getElementById('edit_alamat').value = alamat;
                document.getElementById('edit_status').value = status;
                
                editModal.style.display = 'flex';
            });
        });
        
        // Delete buttons
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const nama = this.getAttribute('data-nama');
                
                document.getElementById('delete_id').value = id;
                document.getElementById('delete_nama').textContent = nama;
                
                deleteModal.style.display = 'flex';
            });
        });
        
        // Close modals
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                editModal.style.display = 'none';
                deleteModal.style.display = 'none';
            });
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target === editModal) {
                editModal.style.display = 'none';
            }
            if (e.target === deleteModal) {
                deleteModal.style.display = 'none';
            }
        });
        
        // Form submission handling
        document.getElementById('editForm').addEventListener('submit', function(e) {
            // Validasi client-side
            const nama = document.getElementById('edit_nama').value.trim();
            const email = document.getElementById('edit_email').value.trim();
            const telepon = document.getElementById('edit_telepon').value.trim();
            
            if (!nama || !email || !telepon) {
                e.preventDefault();
                alert('Harap lengkapi semua field yang wajib diisi');
                return;
            }
            
            // Tampilkan loading state
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
            saveBtn.disabled = true;
            document.getElementById('editForm').classList.add('loading');
        });
        
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            // Konfirmasi tambahan sebelum menghapus
            if (!confirm('Yakin ingin menghapus pelanggan ini?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
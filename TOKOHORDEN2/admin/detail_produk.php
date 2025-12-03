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

// Ambil data produk berdasarkan ID
$id = $_GET['id'] ?? 0;
$produk = null;
$galeri = [];

try {
    $stmt = $pdo->prepare("SELECT p.*, k.nama_kategori 
                          FROM produk p 
                          LEFT JOIN kategori k ON p.id_kategori = k.id_kategori 
                          WHERE p.id_produk = ?");
    $stmt->execute([$id]);
    $produk = $stmt->fetch();
    
    // Ambil galeri produk
    $stmt_galeri = $pdo->prepare("SELECT * FROM produk_galeri WHERE id_produk = ? ORDER BY urutan");
    $stmt_galeri->execute([$id]);
    $galeri = $stmt_galeri->fetchAll();
    
} catch (Exception $e) {
    $error = "Gagal memuat data produk: " . $e->getMessage();
}

if (!$produk) {
    header('Location: produk.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Produk - Luxury Living</title>
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
        
        .card { background: white; border-radius: var(--radius); box-shadow: var(--shadow); padding: 30px; margin-bottom: 20px; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: var(--gold); color: white; }
        .btn-secondary { background: var(--cream); color: var(--brown); }
        .btn-danger { background: #dc3545; color: white; }
        
        .product-detail { display: grid; grid-template-columns: 1fr 2fr; gap: 30px; }
        
        .product-images { display: flex; flex-direction: column; gap: 15px; }
        .main-image { width: 100%; height: 400px; background: var(--cream); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .main-image img { width: 100%; height: 100%; object-fit: cover; }
        .thumbnail-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .thumbnail { height: 80px; background: var(--cream); border-radius: 5px; cursor: pointer; overflow: hidden; }
        .thumbnail img { width: 100%; height: 100%; object-fit: cover; }
        
        .product-info h2 { color: var(--dark-brown); margin-bottom: 10px; }
        .product-meta { display: flex; gap: 20px; margin-bottom: 20px; }
        .meta-item { display: flex; align-items: center; gap: 5px; color: var(--brown); }
        
        .price-section { background: var(--light-cream); padding: 20px; border-radius: var(--radius); margin: 20px 0; }
        .price { font-size: 24px; font-weight: bold; color: var(--gold); }
        .original-price { text-decoration: line-through; color: #888; margin-right: 10px; }
        .discount-badge { background: #dc3545; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px; }
        
        .specs-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 20px 0; }
        .spec-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--cream); }
        
        .status-badge { padding: 5px 15px; border-radius: 20px; font-size: 14px; font-weight: 600; }
        .status-tersedia { background: #d4edda; color: #155724; }
        .status-habis { background: #f8d7da; color: #721c24; }
        .status-preorder { background: #fff3cd; color: #856404; }
        
        .action-buttons { display: flex; gap: 10px; margin-top: 30px; }
        
        .alert { padding: 12px; border-radius: var(--radius); margin-bottom: 20px; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        @media (max-width: 768px) {
            .container { flex-direction: column; }
            .sidebar { width: 100%; }
            .product-detail { grid-template-columns: 1fr; }
            .specs-grid { grid-template-columns: 1fr; }
            .action-buttons { flex-direction: column; }
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
                <div>
                    <h1>Detail Produk</h1>
                    <p style="color: #888; margin-top: 5px;"><?php echo $produk['kode_produk']; ?></p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="produk.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                    </a>
                    <a href="edit_produk.php?id=<?php echo $produk['id_produk']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Produk
                    </a>
                </div>
            </div>

            <?php if(isset($error)): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="product-detail">
                    <!-- Product Images -->
                    <div class="product-images">
                        <div class="main-image">
                            <?php if($produk['foto_utama']): ?>
                                <img src="../uploads/<?php echo $produk['foto_utama']; ?>" alt="<?php echo $produk['nama_produk']; ?>" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjRjNFOEQ3Ii8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0jNkE0RjM3IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+Tm8gSW1hZ2U8L3RleHQ+PC9zdmc+'">
                            <?php else: ?>
                                <i class="fas fa-image" style="font-size: 48px; color: var(--gold);"></i>
                                <p>No Image</p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if(!empty($galeri)): ?>
                        <div class="thumbnail-grid">
                            <?php foreach($galeri as $gambar): ?>
                            <div class="thumbnail">
                                <img src="../uploads/<?php echo $gambar['gambar']; ?>" alt="Gallery Image" onerror="this.style.display='none'">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Product Info -->
                    <div class="product-info">
                        <h2><?php echo $produk['nama_produk']; ?></h2>
                        
                        <div class="product-meta">
                            <div class="meta-item">
                                <i class="fas fa-tag"></i>
                                <span><?php echo $produk['nama_kategori'] ?? 'Tidak ada kategori'; ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-chart-line"></i>
                                <span>Terjual: <?php echo number_format($produk['terjual']); ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="status-badge status-<?php echo strtolower($produk['status']); ?>">
                                    <?php echo $produk['status']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="price-section">
                            <?php if($produk['harga_diskon'] && $produk['harga_diskon'] < $produk['harga']): ?>
                                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                    <span class="original-price">Rp <?php echo number_format($produk['harga'], 0, ',', '.'); ?></span>
                                    <span class="price">Rp <?php echo number_format($produk['harga_diskon'], 0, ',', '.'); ?></span>
                                    <span class="discount-badge">
                                        <?php 
                                        $discount = (($produk['harga'] - $produk['harga_diskon']) / $produk['harga']) * 100;
                                        echo number_format($discount, 0) . '% OFF';
                                        ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <span class="price">Rp <?php echo number_format($produk['harga'], 0, ',', '.'); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <h3>Deskripsi Produk</h3>
                            <p style="line-height: 1.6; margin-top: 10px;">
                                <?php echo nl2br($produk['deskripsi_lengkap'] ?? $produk['deskripsi_singkat'] ?? 'Tidak ada deskripsi'); ?>
                            </p>
                        </div>
                        
                        <div class="specs-grid">
                            <div class="spec-item">
                                <span>Stok Tersedia</span>
                                <strong><?php echo number_format($produk['stok']); ?> unit</strong>
                            </div>
                            <div class="spec-item">
                                <span>Berat</span>
                                <strong><?php echo number_format($produk['berat'], 0); ?> gram</strong>
                            </div>
                            <div class="spec-item">
                                <span>Bahan</span>
                                <strong><?php echo $produk['bahan'] ?? '-'; ?></strong>
                            </div>
                            <div class="spec-item">
                                <span>Ukuran</span>
                                <strong><?php echo $produk['ukuran'] ?? '-'; ?></strong>
                            </div>
                            <div class="spec-item">
                                <span>Warna</span>
                                <strong><?php echo $produk['warna'] ?? '-'; ?></strong>
                            </div>
                            <div class="spec-item">
                                <span>Jenis Gantungan</span>
                                <strong><?php echo $produk['jenis_gantungan'] ?? '-'; ?></strong>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <a href="edit_produk.php?id=<?php echo $produk['id_produk']; ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit Produk
                            </a>
                            <a href="produk.php?action=hapus&id=<?php echo $produk['id_produk']; ?>" class="btn btn-danger" onclick="return confirm('Yakin hapus produk <?php echo addslashes($produk['nama_produk']); ?>?')">
                                <i class="fas fa-trash"></i> Hapus Produk
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistik Produk -->
            <div class="card">
                <h3 style="margin-bottom: 20px;">Statistik Produk</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div style="text-align: center; padding: 20px; background: var(--light-cream); border-radius: var(--radius);">
                        <div style="font-size: 24px; font-weight: bold; color: var(--gold);"><?php echo number_format($produk['terjual']); ?></div>
                        <div>Total Terjual</div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: var(--light-cream); border-radius: var(--radius);">
                        <div style="font-size: 24px; font-weight: bold; color: var(--gold);"><?php echo $produk['rating_rata'] ? number_format($produk['rating_rata'], 1) : '0.0'; ?></div>
                        <div>Rating Rata-rata</div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: var(--light-cream); border-radius: var(--radius);">
                        <div style="font-size: 24px; font-weight: bold; color: var(--gold);"><?php echo number_format($produk['jumlah_ulasan']); ?></div>
                        <div>Jumlah Ulasan</div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: var(--light-cream); border-radius: var(--radius);">
                        <div style="font-size: 24px; font-weight: bold; color: var(--gold);"><?php echo date('d M Y', strtotime($produk['created_at'])); ?></div>
                        <div>Tanggal Ditambahkan</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Thumbnail click handler
        document.addEventListener('DOMContentLoaded', function() {
            const thumbnails = document.querySelectorAll('.thumbnail');
            const mainImage = document.querySelector('.main-image img');
            
            thumbnails.forEach(thumb => {
                thumb.addEventListener('click', function() {
                    const img = this.querySelector('img');
                    if (img && mainImage) {
                        mainImage.src = img.src;
                    }
                });
            });
            
            // ESC key to go back
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    window.location.href = 'produk.php';
                }
            });
        });
    </script>
</body>
</html>
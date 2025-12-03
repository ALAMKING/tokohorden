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

// Ambil data kategori untuk dropdown
try {
    $kategori = $pdo->query("SELECT * FROM kategori WHERE status = 'aktif' ORDER BY nama_kategori")->fetchAll();
} catch (Exception $e) {
    $error = "Gagal memuat data kategori: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kode_produk = 'KRD-' . strtoupper(uniqid());
    $nama_produk = $_POST['nama_produk'];
    $id_kategori = $_POST['id_kategori'] ?: null;
    $deskripsi_singkat = $_POST['deskripsi_singkat'];
    $deskripsi_lengkap = $_POST['deskripsi_lengkap'];
    $harga = $_POST['harga'];
    $harga_diskon = $_POST['harga_diskon'] ?: null;
    $stok = $_POST['stok'];
    $berat = $_POST['berat'];
    $bahan = $_POST['bahan'];
    $ukuran = $_POST['ukuran'];
    $warna = $_POST['warna'];
    $jenis_gantungan = $_POST['jenis_gantungan'];
    $status = $_POST['status'];
    
    $foto_utama = null;
    
    // Handle upload gambar utama
    if (isset($_FILES['foto_utama']) && $_FILES['foto_utama']['error'] == 0) {
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $file_type = strtolower(pathinfo($_FILES['foto_utama']['name'], PATHINFO_EXTENSION));
        
        if (in_array($file_type, $allowed_types)) {
            $upload_dir = '../uploads/produk/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $filename = 'produk_' . time() . '_' . uniqid() . '.' . $file_type;
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['foto_utama']['tmp_name'], $target_file)) {
                $foto_utama = 'produk/' . $filename;
            } else {
                $error = "Gagal upload gambar utama";
            }
        } else {
            $error = "Tipe file tidak diizinkan. Hanya JPG, JPEG, PNG, GIF, WebP yang diperbolehkan";
        }
    }
    
    if (!isset($error)) {
        try {
            // Insert produk baru
            $stmt = $pdo->prepare("INSERT INTO produk (
                kode_produk, nama_produk, id_kategori, deskripsi_singkat, deskripsi_lengkap,
                harga, harga_diskon, stok, berat, bahan, ukuran, warna, jenis_gantungan,
                status, foto_utama, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            
            $stmt->execute([
                $kode_produk, $nama_produk, $id_kategori, $deskripsi_singkat, $deskripsi_lengkap,
                $harga, $harga_diskon, $stok, $berat, $bahan, $ukuran, $warna, $jenis_gantungan,
                $status, $foto_utama
            ]);
            
            $id_produk = $pdo->lastInsertId();
            
            $_SESSION['success'] = "Produk berhasil ditambahkan!";
            header('Location: detail_produk.php?id=' . $id_produk);
            exit;
            
        } catch (Exception $e) {
            $error = "Gagal menambahkan produk: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Produk - Luxury Living</title>
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
        .btn-success { background: #28a745; color: white; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: var(--dark-brown); }
        .form-group input, .form-group select, .form-group textarea { 
            width: 100%; padding: 12px; border: 1px solid var(--beige); border-radius: 5px; 
            font-size: 14px; transition: border-color 0.3s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { 
            outline: none; border-color: var(--gold); 
        }
        .form-group textarea { min-height: 100px; resize: vertical; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        .alert { padding: 12px; border-radius: var(--radius); margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .required::after { content: " *"; color: #dc3545; }
        
        .image-preview { width: 200px; height: 200px; background: var(--cream); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 10px; border: 2px dashed var(--gold); }
        .image-preview img { width: 100%; height: 100%; object-fit: cover; }
        .image-preview i { font-size: 48px; color: var(--gold); }
        
        .form-help { color: #666; font-size: 12px; margin-top: 5px; }
        
        @media (max-width: 768px) {
            .container { flex-direction: column; }
            .sidebar { width: 100%; }
            .form-row { grid-template-columns: 1fr; }
            .header { flex-direction: column; gap: 15px; align-items: flex-start; }
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
                    <h1>Tambah Produk Baru</h1>
                    <p style="color: #888; margin-top: 5px;">Isi form berikut untuk menambahkan produk baru</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="produk.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </div>

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if(isset($error)): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <form method="POST" action="" enctype="multipart/form-data">
                    <!-- Gambar Utama -->
                    <div class="form-group">
                        <label for="foto_utama">Gambar Utama</label>
                        <div class="image-preview" id="imagePreview">
                            <i class="fas fa-image"></i>
                        </div>
                        <input type="file" id="foto_utama" name="foto_utama" accept="image/*" onchange="previewImage(this)">
                        <div class="form-help">Format: JPG, PNG, GIF, WebP (Max: 2MB)</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="nama_produk" class="required">Nama Produk</label>
                            <input type="text" id="nama_produk" name="nama_produk" 
                                   required placeholder="Masukkan nama produk"
                                   value="<?php echo $_POST['nama_produk'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="id_kategori">Kategori</label>
                            <select id="id_kategori" name="id_kategori">
                                <option value="">Pilih Kategori</option>
                                <?php foreach($kategori as $kat): ?>
                                <option value="<?php echo $kat['id_kategori']; ?>" 
                                    <?php echo ($_POST['id_kategori'] ?? '') == $kat['id_kategori'] ? 'selected' : ''; ?>>
                                    <?php echo $kat['nama_kategori']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="deskripsi_singkat">Deskripsi Singkat</label>
                        <textarea id="deskripsi_singkat" name="deskripsi_singkat" 
                                  placeholder="Deskripsi singkat untuk tampilan produk... (max 200 karakter)"
                                  maxlength="200"><?php echo $_POST['deskripsi_singkat'] ?? ''; ?></textarea>
                        <div class="form-help">Maksimal 200 karakter</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="deskripsi_lengkap">Deskripsi Lengkap</label>
                        <textarea id="deskripsi_lengkap" name="deskripsi_lengkap" 
                                  placeholder="Deskripsi lengkap produk..."><?php echo $_POST['deskripsi_lengkap'] ?? ''; ?></textarea>
                        <div class="form-help">Jelaskan detail produk secara lengkap</div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="harga" class="required">Harga Normal</label>
                            <input type="number" id="harga" name="harga" 
                                   value="<?php echo $_POST['harga'] ?? ''; ?>" 
                                   min="0" required placeholder="0">
                            <div class="form-help">Harga normal produk</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="harga_diskon">Harga Diskon</label>
                            <input type="number" id="harga_diskon" name="harga_diskon" 
                                   value="<?php echo $_POST['harga_diskon'] ?? ''; ?>" 
                                   min="0" placeholder="Kosongkan jika tidak ada diskon">
                            <div class="form-help">Isi hanya jika ada diskon</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="stok" class="required">Stok</label>
                            <input type="number" id="stok" name="stok" 
                                   value="<?php echo $_POST['stok'] ?? '0'; ?>" 
                                   min="0" required placeholder="0">
                            <div class="form-help">Jumlah stok tersedia</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="berat" class="required">Berat (gram)</label>
                            <input type="number" id="berat" name="berat" 
                                   value="<?php echo $_POST['berat'] ?? '0'; ?>" 
                                   min="0" required placeholder="0">
                            <div class="form-help">Berat produk dalam gram</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bahan">Bahan</label>
                            <input type="text" id="bahan" name="bahan" 
                                   value="<?php echo $_POST['bahan'] ?? ''; ?>" 
                                   placeholder="Contoh: Katun, Polyester, dll">
                            <div class="form-help">Bahan utama produk</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="ukuran">Ukuran</label>
                            <input type="text" id="ukuran" name="ukuran" 
                                   value="<?php echo $_POST['ukuran'] ?? ''; ?>" 
                                   placeholder="Contoh: 200x150 cm">
                            <div class="form-help">Ukuran standar produk</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="warna">Warna Tersedia</label>
                            <input type="text" id="warna" name="warna" 
                                   value="<?php echo $_POST['warna'] ?? ''; ?>" 
                                   placeholder="Contoh: Cream, Beige, Brown">
                            <div class="form-help">Pisahkan dengan koma untuk multiple warna</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="jenis_gantungan">Jenis Gantungan</label>
                            <input type="text" id="jenis_gantungan" name="jenis_gantungan" 
                                   value="<?php echo $_POST['jenis_gantungan'] ?? ''; ?>" 
                                   placeholder="Contoh: Gantungan Ring, Hook, dll">
                            <div class="form-help">Jenis gantungan yang digunakan</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="status" class="required">Status Produk</label>
                        <select id="status" name="status" required>
                            <option value="Tersedia" <?php echo ($_POST['status'] ?? '') == 'Tersedia' ? 'selected' : ''; ?>>Tersedia</option>
                            <option value="Habis" <?php echo ($_POST['status'] ?? '') == 'Habis' ? 'selected' : ''; ?>>Habis</option>
                            <option value="Preorder" <?php echo ($_POST['status'] ?? '') == 'Preorder' ? 'selected' : ''; ?>>Preorder</option>
                        </select>
                        <div class="form-help">Status ketersediaan produk</div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 30px; flex-wrap: wrap;">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus"></i> Tambah Produk
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset Form
                        </button>
                        <a href="produk.php" class="btn" style="background: var(--beige); color: var(--brown);">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Info Panel -->
            <div class="card">
                <h3 style="margin-bottom: 15px;"><i class="fas fa-info-circle"></i> Informasi Tambahan</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                    <div style="padding: 15px; background: var(--light-cream); border-radius: var(--radius);">
                        <h4 style="color: var(--gold); margin-bottom: 5px;">Kode Produk</h4>
                        <p>Kode produk akan digenerate otomatis oleh sistem</p>
                    </div>
                    <div style="padding: 15px; background: var(--light-cream); border-radius: var(--radius);">
                        <h4 style="color: var(--gold); margin-bottom: 5px;">Gambar Produk</h4>
                        <p>Anda bisa menambah gambar lain di halaman detail produk setelah produk dibuat</p>
                    </div>
                    <div style="padding: 15px; background: var(--light-cream); border-radius: var(--radius);">
                        <h4 style="color: var(--gold); margin-bottom: 5px;">Statistik</h4>
                        <p>Data penjualan dan rating akan terupdate otomatis</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Image preview function
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const file = input.files[0];
            
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.innerHTML = '';
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.width = '100%';
                    img.style.height = '100%';
                    img.style.objectFit = 'cover';
                    preview.appendChild(img);
                }
                
                reader.readAsDataURL(file);
                
                // Check file size
                const fileSize = file.size / 1024 / 1024; // MB
                if (fileSize > 2) {
                    alert('File terlalu besar! Maksimal 2MB');
                    input.value = '';
                    preview.innerHTML = '<i class="fas fa-image"></i>';
                }
            } else {
                preview.innerHTML = '<i class="fas fa-image"></i>';
            }
        }

        // Auto calculate discount percentage
        document.addEventListener('DOMContentLoaded', function() {
            const hargaInput = document.getElementById('harga');
            const diskonInput = document.getElementById('harga_diskon');
            
            function calculateDiscount() {
                const harga = parseFloat(hargaInput.value) || 0;
                const diskon = parseFloat(diskonInput.value) || 0;
                
                if (diskon > 0 && harga > 0 && diskon < harga) {
                    const discountPercent = ((harga - diskon) / harga * 100).toFixed(1);
                    
                    const existingInfo = document.getElementById('discount-info');
                    if (existingInfo) {
                        existingInfo.remove();
                    }
                    
                    const info = document.createElement('div');
                    info.id = 'discount-info';
                    info.style.cssText = 'color: #dc3545; font-size: 12px; margin-top: 5px; font-weight: bold;';
                    info.textContent = '💡 Diskon: ' + discountPercent + '%';
                    diskonInput.parentNode.appendChild(info);
                } else {
                    const existingInfo = document.getElementById('discount-info');
                    if (existingInfo) {
                        existingInfo.remove();
                    }
                }
            }
            
            hargaInput.addEventListener('input', calculateDiscount);
            diskonInput.addEventListener('input', calculateDiscount);
            
            // Character counter for short description
            const shortDesc = document.getElementById('deskripsi_singkat');
            if (shortDesc) {
                const counter = document.createElement('div');
                counter.style.cssText = 'text-align: right; font-size: 12px; color: #666; margin-top: 5px;';
                shortDesc.parentNode.appendChild(counter);
                
                function updateCounter() {
                    const length = shortDesc.value.length;
                    counter.textContent = length + '/200 karakter';
                    if (length > 180) {
                        counter.style.color = '#dc3545';
                    } else {
                        counter.style.color = '#666';
                    }
                }
                
                shortDesc.addEventListener('input', updateCounter);
                updateCounter();
            }
            
            // Form validation
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const harga = parseFloat(document.getElementById('harga').value) || 0;
                const diskon = parseFloat(document.getElementById('harga_diskon').value) || 0;
                
                if (diskon > 0 && diskon >= harga) {
                    e.preventDefault();
                    alert('Harga diskon harus lebih kecil dari harga normal!');
                    document.getElementById('harga_diskon').focus();
                }
                
                if (harga <= 0) {
                    e.preventDefault();
                    alert('Harga harus lebih dari 0!');
                    document.getElementById('harga').focus();
                }
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
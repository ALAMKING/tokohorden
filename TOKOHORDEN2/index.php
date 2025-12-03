<?php
// Cek session status sebelum memulai session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Koneksi database
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=toko_korden;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

$page_title = "Nabil Hoden Luxury Living - Toko Korden Terpercaya";

// Helper functions
function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function generateStarRating($rating) {
    $stars = '';
    $fullStars = floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
    
    for ($i = 0; $i < $fullStars; $i++) {
        $stars .= '<i class="fas fa-star"></i>';
    }
    if ($halfStar) {
        $stars .= '<i class="fas fa-star-half-alt"></i>';
    }
    for ($i = 0; $i < $emptyStars; $i++) {
        $stars .= '<i class="far fa-star"></i>';
    }
    
    return $stars;
}

// Get cart count untuk logged in user
$cart_count = 0;
if (isset($_SESSION['user_logged_in'])) {
    try {
        $stmt_cart = $pdo->prepare("SELECT SUM(jumlah) as total FROM keranjang WHERE id_pelanggan = ?");
        $stmt_cart->execute([$_SESSION['user_id']]);
        $cart_count = $stmt_cart->fetch()['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error fetching cart count: " . $e->getMessage());
    }
}

// Get featured products
try {
    $stmt = $pdo->prepare("SELECT * FROM produk WHERE status = 'Tersedia' ORDER BY terjual DESC LIMIT 8");
    $stmt->execute();
    $featured_products = $stmt->fetchAll();
} catch (Exception $e) {
    $featured_products = [];
    error_log("Error fetching featured products: " . $e->getMessage());
}

// Get categories
try {
    $stmt = $pdo->prepare("SELECT * FROM kategori WHERE status = 'aktif' ORDER BY urutan LIMIT 6");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
    error_log("Error fetching categories: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS dari kode sebelumnya tetap sama */
        :root {
            --cream: #F3E8D7; --beige: #E7D3B8; --gold: #D8A75A; 
            --brown: #6A4F37; --dark-brown: #4a3828; --light-cream: #faf6f0;
            --radius: 10px; --shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: white; color: var(--brown); line-height: 1.6; }
        
        /* Header */
        .header { 
            background: white; 
            padding: 15px 0;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--brown);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .logo span { color: var(--gold); }
        
        .nav-links {
            display: flex;
            list-style: none;
            gap: 30px;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--brown);
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .nav-links a:hover {
            color: var(--gold);
        }
        
        .nav-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .nav-action-btn {
            position: relative;
            color: var(--brown);
            text-decoration: none;
            font-size: 1.2rem;
        }
        
        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--gold);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-login {
            background: var(--gold);
            color: white;
            padding: 8px 20px;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s ease;
        }
        
        .btn-login:hover {
            background: var(--dark-brown);
        }

        /* PERBAIKAN: Dropdown Login */
        .login-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 10px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            padding: 10px;
            width: 180px;
            z-index: 1000;
            display: none;
            border: 1px solid var(--cream);
        }
        
        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            color: var(--brown);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .dropdown-menu a:hover {
            background: var(--cream);
        }
        
        /* ... CSS lainnya tetap sama ... */
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="nav-container">
                <a href="index.php" class="logo">
                    <i class="fas fa-archway"></i>
                    UD Korden<span>Maju Jaya</span>
                </a>
                
                <ul class="nav-links">
                    <li><a href="index.php">Beranda</a></li>
                    <li><a href="produk.php">Produk</a></li>
                    <li><a href="kategori.php">Kategori</a></li>
                    <li><a href="tentang.php">Tentang</a></li>
                    <li><a href="kontak.php">Kontak</a></li>
                </ul>
                
                <div class="nav-actions">
                    <a href="keranjang.php" class="nav-action-btn">
                        <i class="fas fa-shopping-cart"></i>
                        <?php if($cart_count > 0): ?>
                            <span class="cart-count"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <!-- PERBAIKAN: Dropdown Login -->
                    <?php if(isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
                        <a href="admin/dashboard.php" class="btn-login">
                            <i class="fas fa-user-shield"></i> Admin Area
                        </a>
                    <?php elseif(isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true): ?>
                        <a href="user/dashboard.php" class="btn-login">
                            <i class="fas fa-user"></i> Area Member
                        </a>
                    <?php else: ?>
                        <div class="login-dropdown">
                            <a href="user/login.php" class="btn-login" style="display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-user"></i> Login
                                <i class="fas fa-chevron-down" style="font-size: 12px;"></i>
                            </a>
                            <div class="dropdown-menu">
                                <a href="user/login.php">
                                    <i class="fas fa-user" style="color: var(--gold);"></i> 
                                    Login Pelanggan
                                </a>
                                <a href="admin/login.php" style="background: var(--light-cream);">
                                    <i class="fas fa-user-shield" style="color: var(--gold);"></i> 
                                    Login Admin
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Kualitas Premium untuk Hunian Elegan Anda</h1>
                <p>Temukan koleksi korden eksklusif dengan bahan terbaik dan desain modern yang akan mentransformasi ruangan Anda</p>
                <div class="hero-buttons">
                    <a href="produk.php" class="btn btn-primary">
                        <i class="fas fa-shopping-bag"></i> Belanja Sekarang
                    </a>
                    <a href="kategori.php" class="btn btn-secondary">
                        <i class="fas fa-eye"></i> Lihat Koleksi
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Kategori Produk</h2>
                <p class="section-subtitle">Temukan korden perfect untuk setiap ruangan di rumah Anda</p>
            </div>
            
            <div class="category-grid">
                <?php foreach($categories as $cat): ?>
                <div class="category-card" onclick="window.location.href='produk.php?kategori=<?php echo $cat['id_kategori']; ?>'">
                    <div class="category-icon">
                        <i class="<?php echo $cat['icon']; ?>"></i>
                    </div>
                    <h3 class="category-name"><?php echo htmlspecialchars($cat['nama_kategori']); ?></h3>
                    <div class="category-count"><?php echo rand(5, 20); ?> Produk</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Featured Products -->
    <section class="section" style="background: var(--cream);">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Produk Terpopuler</h2>
                <p class="section-subtitle">Koleksi terbaik yang paling banyak dicari oleh pelanggan kami</p>
            </div>
            
            <div class="product-grid">
                <?php foreach($featured_products as $product): 
                    $harga_display = $product['harga_diskon'] ?: $product['harga'];
                    $diskon = $product['harga_diskon'] ? (($product['harga'] - $product['harga_diskon']) / $product['harga'] * 100) : 0;
                    $rating = $product['rating_rata'] ?: 0;
                ?>
                <div class="product-card">
                    <?php if($product['harga_diskon']): ?>
                    <div class="product-badge discount">DISKON</div>
                    <?php endif; ?>
                    
                    <div class="product-image">
                        <?php if(!empty($product['foto_utama'])): ?>
                            <img src="uploads/produk/<?php echo htmlspecialchars($product['foto_utama']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['nama_produk']); ?>"
                                 onclick="window.location.href='produk_detail.php?id=<?php echo $product['id_produk']; ?>'">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: var(--brown);">
                                <i class="fas fa-image" style="font-size: 3rem;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-info">
                        <div class="product-category">Korden</div>
                        <h3 class="product-name" onclick="window.location.href='produk_detail.php?id=<?php echo $product['id_produk']; ?>'">
                            <?php echo htmlspecialchars($product['nama_produk']); ?>
                        </h3>
                        <p class="product-description"><?php echo htmlspecialchars($product['deskripsi_singkat'] ?? 'Deskripsi produk tidak tersedia'); ?></p>
                        
                        <div class="product-price">
                            <span class="current-price"><?php echo formatCurrency($harga_display); ?></span>
                            <?php if($product['harga_diskon']): ?>
                            <span class="original-price"><?php echo formatCurrency($product['harga']); ?></span>
                            <span class="discount-percent">-<?php echo number_format($diskon, 0); ?>%</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-meta">
                            <div class="product-rating">
                                <div class="stars">
                                    <?php echo generateStarRating($rating); ?>
                                </div>
                                <span>(<?php echo $product['jumlah_ulasan'] ?: 0; ?>)</span>
                            </div>
                            <div class="product-sold"><?php echo $product['terjual']; ?> terjual</div>
                        </div>
                        
                        <div class="product-actions">
                            <button class="btn-cart" data-product-id="<?php echo $product['id_produk']; ?>">
                                <i class="fas fa-shopping-cart"></i> Keranjang
                            </button>
                            <button class="btn-wishlist" data-product-id="<?php echo $product['id_produk']; ?>">
                                <i class="far fa-heart"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <h3>UD Korden Maju Jaya</h3>
                    <p style="color: var(--cream); margin-bottom: 20px;">Toko korden terpercaya sejak 2013 dengan kualitas terbaik dan pelayanan memuaskan.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
                
                <div class="footer-col">
                    <h3>Menu Cepat</h3>
                    <ul class="footer-links">
                        <li><a href="index.php">Beranda</a></li>
                        <li><a href="produk.php">Produk</a></li>
                        <li><a href="kategori.php">Kategori</a></li>
                        <li><a href="tentang.php">Tentang Kami</a></li>
                        <li><a href="kontak.php">Kontak</a></li>
                    </ul>
                </div>
                
                <div class="footer-col">
                    <h3>Layanan Pelanggan</h3>
                    <ul class="footer-links">
                        <li><a href="bantuan.php">Bantuan</a></li>
                        <li><a href="syarat.php">Syarat & Ketentuan</a></li>
                        <li><a href="privasi.php">Kebijakan Privasi</a></li>
                    </ul>
                </div>
                
                <div class="footer-col">
                    <h3>Kontak Kami</h3>
                    <ul class="contact-info">
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Pasar Kawasan Desa Mbako, Demak</span>
                        </li>
                        <li>
                            <i class="fas fa-phone"></i>
                            <span>+62 812-3456-7890</span>
                        </li>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <span>info@kordenmajujaya.com</span>
                        </li>
                        <li>
                            <i class="fas fa-clock"></i>
                            <span>Senin - Minggu: 08:00 - 17:00</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2024 UD Korden Maju Jaya. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Dropdown functionality
        document.querySelector('.login-dropdown').addEventListener('click', function(e) {
            e.preventDefault();
            const menu = this.querySelector('.dropdown-menu');
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.login-dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.style.display = 'none';
                });
            }
        });

        // Add to cart functionality
        document.querySelectorAll('.btn-cart').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                alert('Produk berhasil ditambahkan ke keranjang!');
            });
        });

        // Add to wishlist functionality
        document.querySelectorAll('.btn-wishlist').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                this.innerHTML = '<i class="fas fa-heart"></i>';
                this.style.background = 'var(--gold)';
                this.style.color = 'white';
                alert('Produk berhasil ditambahkan ke wishlist!');
            });
        });
    </script>
</body>
</html>
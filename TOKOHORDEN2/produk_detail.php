<?php
// Cek session status sebelum memulai session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Koneksi database
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=toko_horden2;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

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

// Get product ID from URL
$product_id = $_GET['id'] ?? 0;

if (!$product_id) {
    header("Location: produk.php");
    exit;
}

// Get product details
try {
    $stmt = $pdo->prepare("
        SELECT p.*, k.nama_kategori,
               (SELECT COUNT(*) FROM ulasan u WHERE u.id_produk = p.id_produk) as total_review_count,
               (SELECT AVG(rating) FROM ulasan u WHERE u.id_produk = p.id_produk) as avg_rating_value
        FROM produk p
        LEFT JOIN kategori k ON p.id_kategori = k.id_kategori
        WHERE p.id_produk = ? AND p.status = 'Tersedia'
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header("Location: produk.php");
        exit;
    }
} catch (Exception $e) {
    die("Error mengambil data produk: " . $e->getMessage());
}

// Get product images
try {
    $stmt_images = $pdo->prepare("
        SELECT * FROM produk_gambar 
        WHERE id_produk = ? 
        ORDER BY urutan
    ");
    $stmt_images->execute([$product_id]);
    $product_images = $stmt_images->fetchAll();
} catch (Exception $e) {
    $product_images = [];
}

// Get product reviews
try {
    $stmt_reviews = $pdo->prepare("
        SELECT u.*, c.nama as customer_name
        FROM ulasan u
        LEFT JOIN customers c ON u.id_customer = c.id_customer
        WHERE u.id_produk = ?
        ORDER BY u.created_at DESC
        LIMIT 10
    ");
    $stmt_reviews->execute([$product_id]);
    $reviews = $stmt_reviews->fetchAll();
} catch (Exception $e) {
    $reviews = [];
}

// Get rating distribution
try {
    $stmt_ratings = $pdo->prepare("
        SELECT 
            rating,
            COUNT(*) as count
        FROM ulasan 
        WHERE id_produk = ?
        GROUP BY rating
        ORDER BY rating DESC
    ");
    $stmt_ratings->execute([$product_id]);
    $rating_distribution = $stmt_ratings->fetchAll();
    
    // Initialize rating counts
    $rating_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    foreach ($rating_distribution as $dist) {
        $rating_counts[$dist['rating']] = $dist['count'];
    }
} catch (Exception $e) {
    $rating_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
}

// Get related products
try {
    $stmt_related = $pdo->prepare("
        SELECT p.*, k.nama_kategori,
               (SELECT COUNT(*) FROM ulasan u WHERE u.id_produk = p.id_produk) as total_review_count,
               (SELECT AVG(rating) FROM ulasan u WHERE u.id_produk = p.id_produk) as avg_rating_value
        FROM produk p
        LEFT JOIN kategori k ON p.id_kategori = k.id_kategori
        WHERE p.id_kategori = ? AND p.id_produk != ? AND p.status = 'Tersedia'
        ORDER BY RAND()
        LIMIT 4
    ");
    $stmt_related->execute([$product['id_kategori'], $product_id]);
    $related_products = $stmt_related->fetchAll();
} catch (Exception $e) {
    $related_products = [];
}

// Calculate product values
$harga_display = $product['harga_diskon'] ?: $product['harga'];
$diskon = $product['harga_diskon'] ? (($product['harga'] - $product['harga_diskon']) / $product['harga'] * 100) : 0;
$rating = $product['avg_rating_value'] ?: 0;
$jumlah_ulasan = $product['total_review_count'] ?: 0;

// Set page title
$page_title = $product['nama_produk'] . " - Luxury Living";

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['user_logged_in'])) {
        $_SESSION['redirect_to'] = "produk_detail.php?id=" . $product_id;
        header("Location: user/login.php");
        exit;
    }
    
    $quantity = $_POST['quantity'] ?? 1;
    $warna = $_POST['warna'] ?? '';
    $ukuran = $_POST['ukuran'] ?? '';
    
    try {
        // Check if product already in cart
        $stmt = $pdo->prepare("
            SELECT * FROM keranjang 
            WHERE id_customer = ? AND id_produk = ? AND status = 'active'
        ");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
        $existing_item = $stmt->fetch();
        
        if ($existing_item) {
            // Update quantity
            $stmt = $pdo->prepare("
                UPDATE keranjang 
                SET jumlah = jumlah + ?, 
                    updated_at = NOW() 
                WHERE id_keranjang = ?
            ");
            $stmt->execute([$quantity, $existing_item['id_keranjang']]);
        } else {
            // Add new item
            $stmt = $pdo->prepare("
                INSERT INTO keranjang (id_customer, id_produk, jumlah, warna, ukuran, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], $product_id, $quantity, $warna, $ukuran]);
        }
        
        $_SESSION['success_message'] = "Produk berhasil ditambahkan ke keranjang!";
        header("Location: produk_detail.php?id=" . $product_id);
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Gagal menambahkan produk ke keranjang: " . $e->getMessage();
    }
}

// Handle add to wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_wishlist'])) {
    if (!isset($_SESSION['user_logged_in'])) {
        $_SESSION['redirect_to'] = "produk_detail.php?id=" . $product_id;
        header("Location: user/login.php");
        exit;
    }
    
    try {
        // Check if already in wishlist
        $stmt = $pdo->prepare("
            SELECT * FROM wishlist 
            WHERE id_customer = ? AND id_produk = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
        $existing_item = $stmt->fetch();
        
        if ($existing_item) {
            // Remove from wishlist
            $stmt = $pdo->prepare("DELETE FROM wishlist WHERE id_wishlist = ?");
            $stmt->execute([$existing_item['id_wishlist']]);
            $_SESSION['success_message'] = "Produk dihapus dari wishlist!";
        } else {
            // Add to wishlist
            $stmt = $pdo->prepare("
                INSERT INTO wishlist (id_customer, id_produk, created_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], $product_id]);
            $_SESSION['success_message'] = "Produk ditambahkan ke wishlist!";
        }
        
        header("Location: produk_detail.php?id=" . $product_id);
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Gagal mengupdate wishlist: " . $e->getMessage();
    }
}

// Check if product is in user's wishlist
$is_in_wishlist = false;
if (isset($_SESSION['user_logged_in'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM wishlist 
            WHERE id_customer = ? AND id_produk = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
        $is_in_wishlist = $stmt->fetch() ? true : false;
    } catch (Exception $e) {
        // Silent fail
    }
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
        /* CSS styles dari sebelumnya... */
        /* (CSS yang sama seperti di atas) */
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="nav-container">
                <a href="index.php" class="logo">
                    <i class="fas fa-archway"></i>
                    Luxury<span>Living</span>
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
                        <span class="cart-count">
                            <?php
                            if (isset($_SESSION['user_logged_in'])) {
                                try {
                                    $stmt = $pdo->prepare("SELECT SUM(jumlah) as total FROM keranjang WHERE id_customer = ? AND status = 'active'");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $cart_count = $stmt->fetch()['total'] ?? 0;
                                    echo $cart_count;
                                } catch (Exception $e) {
                                    echo "0";
                                }
                            } else {
                                echo "0";
                            }
                            ?>
                        </span>
                    </a>
                    <?php if(isset($_SESSION['user_logged_in'])): ?>
                        <a href="user/dashboard.php" class="btn-login">
                            <i class="fas fa-user"></i> Area Member
                        </a>
                    <?php else: ?>
                        <a href="user/login.php" class="btn-login">
                            <i class="fas fa-user"></i> Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Breadcrumb -->
    <section class="breadcrumb">
        <div class="container">
            <ul class="breadcrumb-links">
                <li><a href="index.php">Beranda</a></li>
                <li class="separator"><i class="fas fa-chevron-right"></i></li>
                <li><a href="produk.php">Produk</a></li>
                <li class="separator"><i class="fas fa-chevron-right"></i></li>
                <li><a href="produk.php?kategori=<?php echo $product['id_kategori']; ?>"><?php echo htmlspecialchars($product['nama_kategori']); ?></a></li>
                <li class="separator"><i class="fas fa-chevron-right"></i></li>
                <li><?php echo htmlspecialchars($product['nama_produk']); ?></li>
            </ul>
        </div>
    </section>

    <!-- Product Detail -->
    <section class="product-detail">
        <div class="container">
            <?php if(isset($_SESSION['success_message'])): ?>
                <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: var(--radius); margin-bottom: 20px; border: 1px solid #c3e6cb;">
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error_message'])): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: var(--radius); margin-bottom: 20px; border: 1px solid #f5c6cb;">
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            
            <div class="product-detail-grid">
                <!-- Product Gallery -->
                <div class="product-gallery">
                    <div class="main-image">
                        <?php if(!empty($product_images)): ?>
                            <img src="uploads/produk/<?php echo htmlspecialchars($product_images[0]['nama_file']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['nama_produk']); ?>" id="mainImage">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: var(--cream); color: var(--brown);">
                                <i class="fas fa-image" style="font-size: 4rem;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="thumbnail-list">
                        <?php foreach($product_images as $index => $image): ?>
                            <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" 
                                 data-image="uploads/produk/<?php echo htmlspecialchars($image['nama_file']); ?>">
                                <img src="uploads/produk/<?php echo htmlspecialchars($image['nama_file']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['nama_produk']); ?> - Tampilan <?php echo $index + 1; ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Product Info -->
                <div class="product-info">
                    <div class="product-category"><?php echo htmlspecialchars($product['nama_kategori']); ?></div>
                    <h1 class="product-title"><?php echo htmlspecialchars($product['nama_produk']); ?></h1>
                    
                    <div class="product-meta">
                        <div class="product-rating">
                            <div class="stars">
                                <?php echo generateStarRating($rating); ?>
                            </div>
                            <span class="product-reviews">(<?php echo $jumlah_ulasan; ?> ulasan)</span>
                        </div>
                        <div class="product-sold"><?php echo $product['terjual']; ?> terjual</div>
                    </div>
                    
                    <div class="product-price">
                        <span class="current-price"><?php echo formatCurrency($harga_display); ?></span>
                        <?php if($product['harga_diskon']): ?>
                            <span class="original-price"><?php echo formatCurrency($product['harga']); ?></span>
                            <span class="discount-percent">-<?php echo number_format($diskon, 0); ?>%</span>
                        <?php endif; ?>
                    </div>
                    
                    <p class="product-description"><?php echo htmlspecialchars($product['deskripsi_singkat']); ?></p>
                    
                    <div class="product-features">
                        <h3 class="features-title">Fitur Utama:</h3>
                        <ul class="features-list">
                            <li><i class="fas fa-check"></i> Bahan premium berkualitas tinggi</li>
                            <li><i class="fas fa-check"></i> Desain modern dan elegan</li>
                            <li><i class="fas fa-check"></i> Tahan lama dan mudah perawatan</li>
                            <li><i class="fas fa-check"></i> Tersedia dalam berbagai ukuran</li>
                        </ul>
                    </div>
                    
                    <form method="POST" action="">
                        <div class="product-options">
                            <div class="option-group">
                                <label class="option-label">Warna:</label>
                                <select name="warna" class="option-select" required>
                                    <option value="">Pilih Warna</option>
                                    <option value="Putih">Putih</option>
                                    <option value="Krem">Krem</option>
                                    <option value="Abu-abu">Abu-abu</option>
                                    <option value="Beige">Beige</option>
                                </select>
                            </div>
                            
                            <div class="option-group">
                                <label class="option-label">Ukuran:</label>
                                <select name="ukuran" class="option-select" required>
                                    <option value="">Pilih Ukuran</option>
                                    <option value="120x200">120cm x 200cm</option>
                                    <option value="150x200">150cm x 200cm</option>
                                    <option value="180x200">180cm x 200cm</option>
                                    <option value="200x200">200cm x 200cm</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="quantity-selector">
                            <span class="quantity-label">Jumlah:</span>
                            <div class="quantity-controls">
                                <button type="button" class="quantity-btn" onclick="decreaseQuantity()">-</button>
                                <input type="number" name="quantity" class="quantity-input" value="1" min="1" max="<?php echo $product['stok']; ?>" id="quantityInput">
                                <button type="button" class="quantity-btn" onclick="increaseQuantity()">+</button>
                            </div>
                        </div>
                        
                        <div class="stock-info">
                            <div class="stock-status <?php echo $product['stok'] > 10 ? 'stock-available' : ($product['stok'] > 0 ? 'stock-low' : 'stock-out'); ?>">
                                <?php echo $product['stok'] > 10 ? 'Stok Tersedia' : ($product['stok'] > 0 ? 'Stok Menipis' : 'Stok Habis'); ?>
                            </div>
                            <span><?php echo $product['stok']; ?> unit tersisa</span>
                        </div>
                        
                        <div class="product-actions">
                            <button type="submit" name="add_to_cart" class="btn-primary" <?php echo $product['stok'] == 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-shopping-cart"></i>
                                <?php echo $product['stok'] == 0 ? 'Stok Habis' : 'Tambah ke Keranjang'; ?>
                            </button>
                            
                            <button type="submit" name="add_to_wishlist" class="btn-secondary <?php echo $is_in_wishlist ? 'active' : ''; ?>">
                                <i class="<?php echo $is_in_wishlist ? 'fas' : 'far'; ?> fa-heart"></i>
                                Wishlist
                            </button>
                        </div>
                    </form>
                    
                    <div class="product-share">
                        <span class="share-label">Bagikan:</span>
                        <div class="share-buttons">
                            <a href="#" class="share-btn facebook">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="share-btn twitter">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="share-btn pinterest">
                                <i class="fab fa-pinterest"></i>
                            </a>
                            <a href="#" class="share-btn whatsapp">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Product Tabs -->
            <div class="product-tabs">
                <div class="tabs-header">
                    <button class="tab-btn active" data-tab="description">Deskripsi</button>
                    <button class="tab-btn" data-tab="specifications">Spesifikasi</button>
                    <button class="tab-btn" data-tab="reviews">Ulasan (<?php echo $jumlah_ulasan; ?>)</button>
                </div>
                
                <div class="tab-content active" id="description">
                    <div class="description-content">
                        <?php echo nl2br(htmlspecialchars($product['deskripsi_lengkap'])); ?>
                    </div>
                </div>
                
                <div class="tab-content" id="specifications">
                    <table class="specs-table">
                        <tr>
                            <td>Bahan</td>
                            <td>Polyester Premium</td>
                        </tr>
                        <tr>
                            <td>Warna Tersedia</td>
                            <td>Putih, Krem, Abu-abu, Beige</td>
                        </tr>
                        <tr>
                            <td>Ukuran Tersedia</td>
                            <td>120x200cm, 150x200cm, 180x200cm, 200x200cm</td>
                        </tr>
                        <tr>
                            <td>Perawatan</td>
                            <td>Dapat dicuci dengan mesin, suhu rendah</td>
                        </tr>
                        <tr>
                            <td>Garansi</td>
                            <td>1 tahun untuk jahitan</td>
                        </tr>
                    </table>
                </div>
                
                <div class="tab-content" id="reviews">
                    <?php if($jumlah_ulasan > 0): ?>
                    <div class="reviews-summary">
                        <div class="rating-overview">
                            <div class="average-rating"><?php echo number_format($rating, 1); ?></div>
                            <div class="rating-stars">
                                <?php echo generateStarRating($rating); ?>
                            </div>
                            <div class="rating-count"><?php echo $jumlah_ulasan; ?> ulasan</div>
                        </div>
                        
                        <div class="rating-bars">
                            <?php for($i = 5; $i >= 1; $i--): 
                                $percentage = $jumlah_ulasan > 0 ? ($rating_counts[$i] / $jumlah_ulasan) * 100 : 0;
                            ?>
                            <div class="rating-bar">
                                <span class="bar-label"><?php echo $i; ?> bintang</span>
                                <div class="bar-container">
                                    <div class="bar-fill" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                                <span class="bar-count"><?php echo $rating_counts[$i]; ?></span>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="review-list">
                        <?php foreach($reviews as $review): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <span class="reviewer-name"><?php echo htmlspecialchars($review['customer_name'] ?: 'Anonymous'); ?></span>
                                <span class="review-date"><?php echo date('d M Y', strtotime($review['created_at'])); ?></span>
                            </div>
                            <div class="review-rating">
                                <?php echo generateStarRating($review['rating']); ?>
                            </div>
                            <div class="review-text">
                                <?php echo htmlspecialchars($review['komentar']); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-comment-slash" style="font-size: 3rem; color: var(--cream); margin-bottom: 15px;"></i>
                        <h3>Belum Ada Ulasan</h3>
                        <p>Jadilah yang pertama memberikan ulasan untuk produk ini.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Related Products -->
    <?php if(!empty($related_products)): ?>
    <section class="related-products">
        <div class="container">
            <h2 class="section-title">Produk Terkait</h2>
            <div class="product-grid">
                <?php foreach($related_products as $related): 
                    $related_harga_display = $related['harga_diskon'] ?: $related['harga'];
                    $related_diskon = $related['harga_diskon'] ? (($related['harga'] - $related['harga_diskon']) / $related['harga'] * 100) : 0;
                    $related_rating = $related['avg_rating_value'] ?: 0;
                    $related_jumlah_ulasan = $related['total_review_count'] ?: 0;
                ?>
                <div class="product-card">
                    <?php if($related_diskon > 0): ?>
                    <div class="product-badge discount">-<?php echo number_format($related_diskon, 0); ?>%</div>
                    <?php endif; ?>
                    
                    <div class="product-image">
                        <?php if(!empty($related['foto_utama'])): ?>
                            <img src="uploads/produk/<?php echo htmlspecialchars($related['foto_utama']); ?>" 
                                 alt="<?php echo htmlspecialchars($related['nama_produk']); ?>">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: var(--cream); color: var(--brown);">
                                <i class="fas fa-image" style="font-size: 2rem;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-info">
                        <h3 class="product-name">
                            <a href="produk_detail.php?id=<?php echo $related['id_produk']; ?>">
                                <?php echo htmlspecialchars($related['nama_produk']); ?>
                            </a>
                        </h3>
                        
                        <div class="product-price">
                            <span class="current-price"><?php echo formatCurrency($related_harga_display); ?></span>
                            <?php if($related['harga_diskon']): ?>
                            <span class="original-price"><?php echo formatCurrency($related['harga']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-footer">
                            <a href="produk_detail.php?id=<?php echo $related['id_produk']; ?>" class="btn-cart">
                                <i class="fas fa-eye"></i> Lihat Detail
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <h3>Luxury Living</h3>
                    <p>Menyediakan korden berkualitas tinggi dengan desain elegan untuk menciptakan ruangan yang nyaman dan stylish.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-tiktok"></i></a>
                        <a href="#"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
                
                <div class="footer-col">
                    <h3>Kategori Produk</h3>
                    <ul class="footer-links">
                        <li><a href="produk.php?kategori=1">Korden Modern</a></li>
                        <li><a href="produk.php?kategori=2">Korden Klasik</a></li>
                        <li><a href="produk.php?kategori=3">Korden Minimalis</a></li>
                        <li><a href="produk.php?kategori=4">Korden Blackout</a></li>
                    </ul>
                </div>
                
                <div class="footer-col">
                    <h3>Link Cepat</h3>
                    <ul class="footer-links">
                        <li><a href="index.php">Beranda</a></li>
                        <li><a href="produk.php">Semua Produk</a></li>
                        <li><a href="kategori.php">Kategori</a></li>
                        <li><a href="tentang.php">Tentang Kami</a></li>
                        <li><a href="kontak.php">Kontak</a></li>
                    </ul>
                </div>
                
                <div class="footer-col">
                    <h3>Kontak Kami</h3>
                    <ul class="contact-info">
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Jl. Contoh Alamat No. 123, Jakarta</span>
                        </li>
                        <li>
                            <i class="fas fa-phone"></i>
                            <span>+62 812-3456-7890</span>
                        </li>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <span>info@luxuryliving.com</span>
                        </li>
                        <li>
                            <i class="fas fa-clock"></i>
                            <span>Senin - Sabtu: 08:00 - 17:00</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Luxury Living. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Thumbnail click handler
        document.querySelectorAll('.thumbnail').forEach(thumb => {
            thumb.addEventListener('click', function() {
                const mainImage = document.getElementById('mainImage');
                const imageUrl = this.getAttribute('data-image');
                
                // Update main image
                mainImage.src = imageUrl;
                
                // Update active thumbnail
                document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });
        
        // Tab functionality
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Update active tab button
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Update active tab content
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                document.getElementById(tabId).classList.add('active');
            });
        });
        
        // Quantity controls
        function increaseQuantity() {
            const input = document.getElementById('quantityInput');
            const max = parseInt(input.getAttribute('max'));
            if (parseInt(input.value) < max) {
                input.value = parseInt(input.value) + 1;
            }
        }
        
        function decreaseQuantity() {
            const input = document.getElementById('quantityInput');
            if (parseInt(input.value) > 1) {
                input.value = parseInt(input.value) - 1;
            }
        }
        
        // Share functionality
        document.querySelectorAll('.share-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const productUrl = window.location.href;
                const productTitle = document.querySelector('.product-title').textContent;
                
                if (this.classList.contains('facebook')) {
                    window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(productUrl)}`, '_blank');
                } else if (this.classList.contains('twitter')) {
                    window.open(`https://twitter.com/intent/tweet?text=${encodeURIComponent(productTitle)}&url=${encodeURIComponent(productUrl)}`, '_blank');
                } else if (this.classList.contains('pinterest')) {
                    window.open(`https://pinterest.com/pin/create/button/?url=${encodeURIComponent(productUrl)}&description=${encodeURIComponent(productTitle)}`, '_blank');
                } else if (this.classList.contains('whatsapp')) {
                    window.open(`https://wa.me/?text=${encodeURIComponent(productTitle + ' ' + productUrl)}`, '_blank');
                }
            });
        });
    </script>
</body>
</html>
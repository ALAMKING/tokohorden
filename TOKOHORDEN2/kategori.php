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

$page_title = "Kategori Produk - Luxury Living";

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

// Get kategori ID dari URL
$kategori_id = $_GET['id'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'terbaru';
$page = $_GET['page'] ?? 1;
$limit = 12; // Products per page
$offset = ($page - 1) * $limit;

// Get kategori details jika ada kategori ID
$kategori = null;
if ($kategori_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM kategori WHERE id_kategori = ? AND status = 'aktif'");
        $stmt->execute([$kategori_id]);
        $kategori = $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error fetching category: " . $e->getMessage());
    }
}

// Build query untuk produk
$query = "
    SELECT p.*, k.nama_kategori,
           (SELECT COUNT(*) FROM ulasan u WHERE u.id_produk = p.id_produk) as total_review_count,
           (SELECT AVG(rating) FROM ulasan u WHERE u.id_produk = p.id_produk) as avg_rating_value
    FROM produk p
    LEFT JOIN kategori k ON p.id_kategori = k.id_kategori
    WHERE p.status = 'Tersedia'
";

$params = [];
$count_params = [];

// Filter by kategori
if ($kategori_id) {
    $query .= " AND p.id_kategori = ?";
    $params[] = $kategori_id;
    $count_params = $params;
}

// Filter by search
if (!empty($search)) {
    $query .= " AND (p.nama_produk LIKE ? OR p.deskripsi_singkat LIKE ? OR p.deskripsi_lengkap LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $count_params = $params;
}

// Get total count untuk pagination
$count_query = "SELECT COUNT(*) as total FROM ($query) as count_table";
$stmt_count = $pdo->prepare($count_query);
$stmt_count->execute($count_params);
$total_products = $stmt_count->fetch()['total'];
$total_pages = ceil($total_products / $limit);

// Add sorting
switch ($sort) {
    case 'harga_terendah':
        $query .= " ORDER BY (CASE WHEN p.harga_diskon > 0 THEN p.harga_diskon ELSE p.harga END) ASC";
        break;
    case 'harga_tertinggi':
        $query .= " ORDER BY (CASE WHEN p.harga_diskon > 0 THEN p.harga_diskon ELSE p.harga END) DESC";
        break;
    case 'terlaris':
        $query .= " ORDER BY p.terjual DESC";
        break;
    case 'rating':
        $query .= " ORDER BY avg_rating_value DESC";
        break;
    case 'diskon':
        $query .= " ORDER BY (p.harga - COALESCE(p.harga_diskon, p.harga)) DESC";
        break;
    default: // terbaru
        $query .= " ORDER BY p.created_at DESC";
        break;
}

// Add pagination
$query .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

// Execute main query
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (Exception $e) {
    $products = [];
    error_log("Error fetching products: " . $e->getMessage());
}

// Get all categories untuk sidebar
try {
    $stmt_categories = $pdo->prepare("
        SELECT k.*, 
               (SELECT COUNT(*) FROM produk p WHERE p.id_kategori = k.id_kategori AND p.status = 'Tersedia') as total_produk
        FROM kategori k 
        WHERE k.status = 'aktif' 
        ORDER BY k.urutan, k.nama_kategori
    ");
    $stmt_categories->execute();
    $categories = $stmt_categories->fetchAll();
} catch (Exception $e) {
    $categories = [];
    error_log("Error fetching categories: " . $e->getMessage());
}

// Get cart count untuk logged in user
$cart_count = 0;
if (isset($_SESSION['user_logged_in'])) {
    try {
        $stmt_cart = $pdo->prepare("SELECT SUM(jumlah) as total FROM keranjang WHERE id_pelanggan = ? AND status = 'active'");
        $stmt_cart->execute([$_SESSION['user_id']]);
        $cart_count = $stmt_cart->fetch()['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error fetching cart count: " . $e->getMessage());
    }
}

// Helper function untuk pagination URLs
function buildPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return 'kategori.php?' . http_build_query($params);
}

// Set page title berdasarkan kategori atau search
if ($kategori) {
    $page_title = $kategori['nama_kategori'] . " - Luxury Living";
} elseif (!empty($search)) {
    $page_title = "Pencarian: " . htmlspecialchars($search) . " - Luxury Living";
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
        :root {
            --cream: #F3E8D7; --beige: #E7D3B8; --gold: #D8A75A; 
            --brown: #6A4F37; --dark-brown: #4a3828; --light-cream: #faf6f0;
            --radius: 10px; --shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
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
            position: relative;
        }
        
        .nav-links a:hover, .nav-links a.active {
            color: var(--gold);
        }
        
        .nav-links a.active::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--gold);
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
        
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--cream) 0%, var(--beige) 100%);
            padding: 60px 0;
            text-align: center;
        }
        
        .page-title {
            font-size: 2.5rem;
            color: var(--dark-brown);
            margin-bottom: 15px;
            font-weight: 700;
        }
        
        .page-subtitle {
            font-size: 1.1rem;
            color: var(--brown);
            max-width: 600px;
            margin: 0 auto;
        }
        
        .breadcrumb {
            padding: 20px 0;
            background: var(--light-cream);
        }
        
        .breadcrumb-links {
            display: flex;
            align-items: center;
            gap: 10px;
            list-style: none;
            font-size: 0.9rem;
        }
        
        .breadcrumb-links a {
            color: var(--brown);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .breadcrumb-links a:hover {
            color: var(--gold);
        }
        
        .breadcrumb-links .separator {
            color: var(--gold);
        }
        
        /* Main Content */
        .main-content {
            padding: 40px 0;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
        }
        
        @media (max-width: 968px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Categories Sidebar */
        .categories-sidebar {
            position: sticky;
            top: 100px;
            height: fit-content;
        }
        
        .categories-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .categories-title {
            font-size: 1.2rem;
            color: var(--dark-brown);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--cream);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .categories-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .category-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--brown);
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }
        
        .category-item:hover {
            background: var(--cream);
            color: var(--dark-brown);
        }
        
        .category-item.active {
            background: var(--gold);
            color: white;
            border-color: var(--gold);
        }
        
        .category-name {
            font-weight: 500;
        }
        
        .category-count {
            background: var(--cream);
            color: var(--brown);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .category-item.active .category-count {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        /* Products Header */
        .products-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .results-info {
            color: var(--brown);
            font-weight: 500;
        }
        
        .sort-options {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sort-select {
            padding: 8px 12px;
            border: 1px solid var(--beige);
            border-radius: var(--radius);
            background: white;
            color: var(--brown);
            cursor: pointer;
        }
        
        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        @media (max-width: 768px) {
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }
        
        @media (max-width: 480px) {
            .product-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .product-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .product-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            z-index: 2;
        }
        
        .product-badge.discount {
            background: var(--gold);
            color: white;
        }
        
        .product-badge.new {
            background: #28a745;
            color: white;
        }
        
        .product-badge.hot {
            background: #dc3545;
            color: white;
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            background: var(--cream);
            overflow: hidden;
            position: relative;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .product-actions {
            position: absolute;
            top: 15px;
            right: 15px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .product-card:hover .product-actions {
            opacity: 1;
        }
        
        .action-btn {
            width: 35px;
            height: 35px;
            background: white;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
            color: var(--brown);
        }
        
        .action-btn:hover {
            background: var(--gold);
            color: white;
            transform: scale(1.1);
        }
        
        .product-info {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .product-category {
            color: var(--brown);
            font-size: 0.8rem;
            margin-bottom: 5px;
        }
        
        .product-name {
            font-weight: 600;
            color: var(--dark-brown);
            margin-bottom: 8px;
            font-size: 1.1rem;
            line-height: 1.4;
        }
        
        .product-name a {
            color: inherit;
            text-decoration: none;
        }
        
        .product-name a:hover {
            color: var(--gold);
        }
        
        .product-description {
            color: var(--brown);
            font-size: 0.85rem;
            margin-bottom: 15px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex-grow: 1;
        }
        
        .product-price {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .current-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--gold);
        }
        
        .original-price {
            font-size: 0.9rem;
            color: #6c757d;
            text-decoration: line-through;
        }
        
        .discount-percent {
            background: #ffe6e6;
            color: #dc3545;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .product-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-size: 0.8rem;
        }
        
        .product-rating {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .stars {
            color: #ffc107;
        }
        
        .product-sold {
            color: var(--brown);
        }
        
        .product-stock {
            color: #28a745;
            font-size: 0.8rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .product-stock.low {
            color: #ffc107;
        }
        
        .product-stock.out {
            color: #dc3545;
        }
        
        .product-footer {
            display: flex;
            gap: 10px;
            margin-top: auto;
        }
        
        .btn-cart {
            flex: 1;
            background: var(--gold);
            color: white;
            border: none;
            padding: 10px;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            min-height: 42px;
        }
        
        .btn-cart:hover {
            background: var(--dark-brown);
        }
        
        .btn-cart:disabled {
            background: #6c757d;
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .btn-detail {
            width: 42px;
            background: var(--cream);
            color: var(--brown);
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
        }
        
        .btn-detail:hover {
            background: var(--gold);
            color: white;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 40px;
            flex-wrap: wrap;
        }
        
        .pagination-btn {
            padding: 8px 15px;
            border: 1px solid var(--beige);
            background: white;
            color: var(--brown);
            border-radius: var(--radius);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .pagination-btn:hover {
            background: var(--gold);
            color: white;
            border-color: var(--gold);
        }
        
        .pagination-btn.active {
            background: var(--gold);
            color: white;
            border-color: var(--gold);
        }
        
        .pagination-btn:disabled {
            background: var(--cream);
            color: var(--brown);
            cursor: not-allowed;
            opacity: 0.5;
        }
        
        .pagination-info {
            color: var(--brown);
            margin: 0 15px;
            font-weight: 500;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            grid-column: 1 / -1;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--cream);
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--dark-brown);
            font-size: 1.5rem;
        }
        
        .empty-state p {
            color: var(--brown);
            margin-bottom: 25px;
        }
        
        /* Category Description */
        .category-description {
            background: white;
            padding: 25px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            line-height: 1.7;
        }
        
        /* Footer */
        .footer {
            background: var(--dark-brown);
            color: var(--cream);
            padding: 50px 0 20px;
            margin-top: 60px;
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .footer-col h3 {
            color: white;
            margin-bottom: 20px;
            font-size: 1.3rem;
        }
        
        .footer-links {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 10px;
        }
        
        .footer-links a {
            color: var(--cream);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .footer-links a:hover {
            color: var(--gold);
        }
        
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .social-links a {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--cream);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .social-links a:hover {
            background: var(--gold);
            transform: translateY(-2px);
        }
        
        .contact-info {
            list-style: none;
        }
        
        .contact-info li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .contact-info i {
            color: var(--gold);
            margin-top: 3px;
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: var(--beige);
            font-size: 0.9rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-links {
                gap: 20px;
            }
            
            .page-header {
                padding: 40px 0;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .products-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .sort-options {
                justify-content: space-between;
            }
            
            .product-footer {
                flex-direction: column;
            }
            
            .btn-detail {
                width: 100%;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 0 15px;
            }
            
            .nav-links {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .pagination {
                flex-direction: column;
                align-items: stretch;
            }
            
            .pagination-btn {
                justify-content: center;
            }
        }
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
                    <li><a href="kategori.php" class="active">Kategori</a></li>
                    <li><a href="tentang.php">Tentang</a></li>
                    <li><a href="kontak.php">Kontak</a></li>
                </ul>
                
                <div class="nav-actions">
                    <a href="keranjang.php" class="nav-action-btn">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $cart_count; ?></span>
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

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <?php if($kategori): ?>
                <h1 class="page-title"><?php echo htmlspecialchars($kategori['nama_kategori']); ?></h1>
                <p class="page-subtitle"><?php echo htmlspecialchars($kategori['deskripsi'] ?? 'Temukan koleksi terbaik dari kategori ini'); ?></p>
            <?php elseif(!empty($search)): ?>
                <h1 class="page-title">Pencarian: "<?php echo htmlspecialchars($search); ?>"</h1>
                <p class="page-subtitle">Hasil pencarian untuk produk yang Anda cari</p>
            <?php else: ?>
                <h1 class="page-title">Semua Kategori</h1>
                <p class="page-subtitle">Jelajahi berbagai kategori produk korden berkualitas kami</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Breadcrumb -->
    <section class="breadcrumb">
        <div class="container">
            <ul class="breadcrumb-links">
                <li><a href="index.php">Beranda</a></li>
                <li class="separator"><i class="fas fa-chevron-right"></i></li>
                <li><a href="kategori.php">Kategori</a></li>
                <?php if($kategori): ?>
                    <li class="separator"><i class="fas fa-chevron-right"></i></li>
                    <li><?php echo htmlspecialchars($kategori['nama_kategori']); ?></li>
                <?php elseif(!empty($search)): ?>
                    <li class="separator"><i class="fas fa-chevron-right"></i></li>
                    <li>Pencarian: "<?php echo htmlspecialchars($search); ?>"</li>
                <?php endif; ?>
            </ul>
        </div>
    </section>

    <!-- Main Content -->
    <section class="main-content">
        <div class="container">
            <div class="content-grid">
                <!-- Categories Sidebar -->
                <aside class="categories-sidebar">
                    <div class="categories-card">
                        <h3 class="categories-title">
                            <i class="fas fa-tags"></i>
                            Semua Kategori
                        </h3>
                        <div class="categories-list">
                            <a href="kategori.php" class="category-item <?php echo empty($kategori_id) ? 'active' : ''; ?>">
                                <span class="category-name">Semua Produk</span>
                                <span class="category-count">
                                    <?php
                                    try {
                                        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM produk WHERE status = 'Tersedia'");
                                        $stmt->execute();
                                        $total_all = $stmt->fetch()['total'];
                                        echo $total_all;
                                    } catch (Exception $e) {
                                        echo "0";
                                    }
                                    ?>
                                </span>
                            </a>
                            <?php foreach($categories as $cat): ?>
                            <a href="kategori.php?id=<?php echo $cat['id_kategori']; ?>" 
                               class="category-item <?php echo $kategori_id == $cat['id_kategori'] ? 'active' : ''; ?>">
                                <span class="category-name"><?php echo htmlspecialchars($cat['nama_kategori']); ?></span>
                                <span class="category-count"><?php echo $cat['total_produk']; ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Search Box -->
                    <div class="categories-card">
                        <h3 class="categories-title">
                            <i class="fas fa-search"></i>
                            Cari Produk
                        </h3>
                        <form method="GET" action="kategori.php">
                            <?php if($kategori_id): ?>
                                <input type="hidden" name="id" value="<?php echo $kategori_id; ?>">
                            <?php endif; ?>
                            <div style="display: flex; gap: 10px;">
                                <input type="text" name="search" class="quantity-input" 
                                       placeholder="Cari produk..." 
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       style="flex: 1;">
                                <button type="submit" class="btn-detail" style="min-height: auto;">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </aside>

                <!-- Products Section -->
                <main class="products-main">
                    <?php if($kategori && !empty($kategori['deskripsi_lengkap'])): ?>
                    <div class="category-description">
                        <?php echo nl2br(htmlspecialchars($kategori['deskripsi_lengkap'])); ?>
                    </div>
                    <?php endif; ?>

                    <div class="products-header">
                        <div class="results-info">
                            Menampilkan <?php echo $total_products; ?> produk
                            <?php if(!empty($search)): ?>
                                untuk "<strong><?php echo htmlspecialchars($search); ?></strong>"
                            <?php endif; ?>
                        </div>
                        
                        <div class="sort-options">
                            <label for="sort" style="color: var(--brown);">Urutkan:</label>
                            <select name="sort" id="sort" class="sort-select" onchange="updateSort(this.value)">
                                <option value="terbaru" <?php echo $sort === 'terbaru' ? 'selected' : ''; ?>>Terbaru</option>
                                <option value="harga_terendah" <?php echo $sort === 'harga_terendah' ? 'selected' : ''; ?>>Harga Terendah</option>
                                <option value="harga_tertinggi" <?php echo $sort === 'harga_tertinggi' ? 'selected' : ''; ?>>Harga Tertinggi</option>
                                <option value="terlaris" <?php echo $sort === 'terlaris' ? 'selected' : ''; ?>>Terlaris</option>
                                <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Rating Tertinggi</option>
                                <option value="diskon" <?php echo $sort === 'diskon' ? 'selected' : ''; ?>>Diskon Terbesar</option>
                            </select>
                        </div>
                    </div>

                    <?php if(empty($products)): ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>Produk Tidak Ditemukan</h3>
                        <p>Maaf, tidak ada produk yang sesuai dengan kriteria pencarian Anda.</p>
                        <a href="kategori.php" class="btn-cart" style="display: inline-flex; width: auto; padding: 10px 20px;">
                            <i class="fas fa-refresh"></i> Lihat Semua Produk
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="product-grid">
                        <?php foreach($products as $product): 
                            $harga_display = $product['harga_diskon'] ?: $product['harga'];
                            $diskon = $product['harga_diskon'] ? (($product['harga'] - $product['harga_diskon']) / $product['harga'] * 100) : 0;
                            $rating = $product['avg_rating_value'] ?: 0;
                            $jumlah_ulasan = $product['total_review_count'] ?: 0;
                            $stock_class = $product['stok'] > 10 ? '' : ($product['stok'] > 0 ? 'low' : 'out');
                            $stock_text = $product['stok'] > 10 ? 'Stok Tersedia' : ($product['stok'] > 0 ? 'Stok Menipis' : 'Stok Habis');
                        ?>
                        <div class="product-card">
                            <?php if($diskon > 0): ?>
                            <div class="product-badge discount">-<?php echo number_format($diskon, 0); ?>%</div>
                            <?php elseif($product['terjual'] > 100): ?>
                            <div class="product-badge hot">Hot</div>
                            <?php endif; ?>
                            
                            <div class="product-image">
                                <?php if(!empty($product['foto_utama'])): ?>
                                    <img src="uploads/produk/<?php echo htmlspecialchars($product['foto_utama']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['nama_produk']); ?>">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: var(--brown);">
                                        <i class="fas fa-image" style="font-size: 3rem;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="product-actions">
                                    <button class="action-btn wishlist" data-product-id="<?php echo $product['id_produk']; ?>">
                                        <i class="far fa-heart"></i>
                                    </button>
                                    <button class="action-btn quick-view" data-product-id="<?php echo $product['id_produk']; ?>">
                                        <i class="far fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="product-info">
                                <div class="product-category"><?php echo htmlspecialchars($product['nama_kategori'] ?? 'Korden'); ?></div>
                                <h3 class="product-name">
                                    <a href="produk_detail.php?id=<?php echo $product['id_produk']; ?>">
                                        <?php echo htmlspecialchars($product['nama_produk']); ?>
                                    </a>
                                </h3>
                                <p class="product-description"><?php echo htmlspecialchars($product['deskripsi_singkat'] ?? 'Deskripsi tidak tersedia'); ?></p>
                                
                                <div class="product-price">
                                    <span class="current-price"><?php echo formatCurrency($harga_display); ?></span>
                                    <?php if($product['harga_diskon']): ?>
                                    <span class="original-price"><?php echo formatCurrency($product['harga']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-meta">
                                    <div class="product-rating">
                                        <div class="stars">
                                            <?php echo generateStarRating($rating); ?>
                                        </div>
                                        <span>(<?php echo $jumlah_ulasan; ?>)</span>
                                    </div>
                                    <div class="product-sold"><?php echo $product['terjual']; ?> terjual</div>
                                </div>
                                
                                <div class="product-stock <?php echo $stock_class; ?>">
                                    <i class="fas fa-box"></i> <?php echo $stock_text; ?>
                                </div>
                                
                                <div class="product-footer">
                                    <button class="btn-cart" 
                                            data-product-id="<?php echo $product['id_produk']; ?>"
                                            <?php echo $product['stok'] == 0 ? 'disabled' : ''; ?>>
                                        <i class="fas fa-shopping-cart"></i>
                                        <?php echo $product['stok'] == 0 ? 'Stok Habis' : 'Tambah Keranjang'; ?>
                                    </button>
                                    <a href="produk_detail.php?id=<?php echo $product['id_produk']; ?>" 
                                       class="btn-detail" title="Lihat Detail">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if($page > 1): ?>
                            <a href="<?php echo buildPaginationUrl(1); ?>" class="pagination-btn">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="<?php echo buildPaginationUrl($page - 1); ?>" class="pagination-btn">
                                <i class="fas fa-angle-left"></i> Sebelumnya
                            </a>
                        <?php else: ?>
                            <span class="pagination-btn" disabled>
                                <i class="fas fa-angle-double-left"></i>
                            </span>
                            <span class="pagination-btn" disabled>
                                <i class="fas fa-angle-left"></i> Sebelumnya
                            </span>
                        <?php endif; ?>

                        <span class="pagination-info">
                            Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?>
                        </span>

                        <?php if($page < $total_pages): ?>
                            <a href="<?php echo buildPaginationUrl($page + 1); ?>" class="pagination-btn">
                                Selanjutnya <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="<?php echo buildPaginationUrl($total_pages); ?>" class="pagination-btn">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="pagination-btn" disabled>
                                Selanjutnya <i class="fas fa-angle-right"></i>
                            </span>
                            <span class="pagination-btn" disabled>
                                <i class="fas fa-angle-double-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </main>
            </div>
        </div>
    </section>

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
                        <?php foreach(array_slice($categories, 0, 5) as $cat): ?>
                        <li><a href="kategori.php?id=<?php echo $cat['id_kategori']; ?>"><?php echo htmlspecialchars($cat['nama_kategori']); ?></a></li>
                        <?php endforeach; ?>
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
        // Update sort function
        function updateSort(sortValue) {
            const url = new URL(window.location.href);
            url.searchParams.set('sort', sortValue);
            url.searchParams.set('page', 1); // Reset to first page when sorting
            window.location.href = url.toString();
        }

        // Add to cart functionality
        document.querySelectorAll('.btn-cart').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                const isDisabled = this.disabled;
                
                if (isDisabled) return;
                
                // Check if user is logged in
                const isLoggedIn = <?php echo isset($_SESSION['user_logged_in']) ? 'true' : 'false'; ?>;
                
                if (!isLoggedIn) {
                    window.location.href = 'user/login.php';
                    return;
                }
                
                // Add loading state
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menambah...';
                this.disabled = true;
                
                // Simulate API call
                setTimeout(() => {
                    // Update cart count
                    const cartCount = document.querySelector('.cart-count');
                    const currentCount = parseInt(cartCount.textContent);
                    cartCount.textContent = currentCount + 1;
                    
                    // Show success message
                    showNotification('Produk berhasil ditambahkan ke keranjang!', 'success');
                    
                    // Reset button
                    this.innerHTML = originalText;
                    this.disabled = false;
                }, 1000);
            });
        });

        // Wishlist functionality
        document.querySelectorAll('.action-btn.wishlist').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                const icon = this.querySelector('i');
                
                // Check if user is logged in
                const isLoggedIn = <?php echo isset($_SESSION['user_logged_in']) ? 'true' : 'false'; ?>;
                
                if (!isLoggedIn) {
                    window.location.href = 'user/login.php';
                    return;
                }
                
                // Toggle wishlist state
                const isActive = this.classList.contains('active');
                
                if (isActive) {
                    // Remove from wishlist
                    this.classList.remove('active');
                    icon.className = 'far fa-heart';
                    showNotification('Produk dihapus dari wishlist', 'info');
                } else {
                    // Add to wishlist
                    this.classList.add('active');
                    icon.className = 'fas fa-heart';
                    showNotification('Produk ditambahkan ke wishlist', 'success');
                }
            });
        });

        // Quick view functionality
        document.querySelectorAll('.action-btn.quick-view').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                window.location.href = `produk_detail.php?id=${productId}`;
            });
        });

        // Notification function
        function showNotification(message, type = 'info') {
            // Remove existing notification
            const existingNotification = document.querySelector('.notification');
            if (existingNotification) {
                existingNotification.remove();
            }
            
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas fa-${getNotificationIcon(type)}"></i>
                    <span>${message}</span>
                </div>
                <button class="notification-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            // Add styles for notification
            if (!document.querySelector('#notification-styles')) {
                const style = document.createElement('style');
                style.id = 'notification-styles';
                style.textContent = `
                    .notification {
                        position: fixed;
                        top: 100px;
                        right: 20px;
                        background: white;
                        padding: 15px 20px;
                        border-radius: var(--radius);
                        box-shadow: var(--shadow);
                        display: flex;
                        align-items: center;
                        gap: 15px;
                        z-index: 10000;
                        max-width: 400px;
                        border-left: 4px solid var(--gold);
                        animation: slideInRight 0.3s ease;
                    }
                    .notification-success {
                        border-left-color: #28a745;
                    }
                    .notification-error {
                        border-left-color: #dc3545;
                    }
                    .notification-info {
                        border-left-color: var(--gold);
                    }
                    .notification-content {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        flex: 1;
                    }
                    .notification-content i {
                        font-size: 1.2rem;
                    }
                    .notification-success .notification-content i {
                        color: #28a745;
                    }
                    .notification-error .notification-content i {
                        color: #dc3545;
                    }
                    .notification-info .notification-content i {
                        color: var(--gold);
                    }
                    .notification-close {
                        background: none;
                        border: none;
                        color: var(--brown);
                        cursor: pointer;
                        padding: 5px;
                        border-radius: 4px;
                        transition: background 0.3s ease;
                    }
                    .notification-close:hover {
                        background: var(--cream);
                    }
                    @keyframes slideInRight {
                        from {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                        to {
                            transform: translateX(0);
                            opacity: 1;
                        }
                    }
                `;
                document.head.appendChild(style);
            }
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }

        function getNotificationIcon(type) {
            switch (type) {
                case 'success': return 'check-circle';
                case 'error': return 'exclamation-circle';
                case 'info': return 'info-circle';
                default: return 'info-circle';
            }
        }

        // Initialize with URL parameters
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            // Set sort select
            const sortParam = urlParams.get('sort');
            if (sortParam) {
                document.querySelector('#sort').value = sortParam;
            }
        });
    </script>
</body>
</html>
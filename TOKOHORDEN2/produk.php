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

$page_title = "Koleksi Produk - Luxury Living";

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

// Get parameters
$search = $_GET['search'] ?? '';
$kategori = $_GET['kategori'] ?? '';
$sort = $_GET['sort'] ?? 'terbaru';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$page = $_GET['page'] ?? 1;
$limit = 12; // Products per page
$offset = ($page - 1) * $limit;

// Build query
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

// Filter by search
if (!empty($search)) {
    $query .= " AND (p.nama_produk LIKE ? OR p.deskripsi_singkat LIKE ? OR p.deskripsi_lengkap LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $count_params = $params;
}

// Filter by category
if (!empty($kategori)) {
    $query .= " AND p.id_kategori = ?";
    $params[] = $kategori;
    $count_params = $params;
}

// Filter by price range
if (!empty($min_price) && is_numeric($min_price)) {
    $query .= " AND (p.harga_diskon > 0 AND p.harga_diskon >= ? OR p.harga >= ?)";
    $params[] = $min_price;
    $params[] = $min_price;
    $count_params = $params;
}

if (!empty($max_price) && is_numeric($max_price)) {
    $query .= " AND (p.harga_diskon > 0 AND p.harga_diskon <= ? OR p.harga <= ?)";
    $params[] = $max_price;
    $params[] = $max_price;
    $count_params = $params;
}

// Get total count for pagination
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

// Get categories for filter
try {
    $stmt_categories = $pdo->prepare("SELECT * FROM kategori WHERE status = 'aktif' ORDER BY urutan");
    $stmt_categories->execute();
    $categories = $stmt_categories->fetchAll();
} catch (Exception $e) {
    $categories = [];
    error_log("Error fetching categories: " . $e->getMessage());
}

// Get price range for filter
try {
    $stmt_price = $pdo->prepare("
        SELECT 
            MIN(COALESCE(harga_diskon, harga)) as min_price,
            MAX(COALESCE(harga_diskon, harga)) as max_price
        FROM produk 
        WHERE status = 'Tersedia'
    ");
    $stmt_price->execute();
    $price_range = $stmt_price->fetch();
} catch (Exception $e) {
    $price_range = ['min_price' => 0, 'max_price' => 1000000];
    error_log("Error fetching price range: " . $e->getMessage());
}

// Get cart count for logged in user
$cart_count = 0;
if (isset($_SESSION['user_logged_in'])) {
    try {
        $stmt_cart = $pdo->prepare("SELECT SUM(jumlah) as total FROM keranjang WHERE id_customer = ? AND status = 'active'");
        $stmt_cart->execute([$_SESSION['user_id']]);
        $cart_count = $stmt_cart->fetch()['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error fetching cart count: " . $e->getMessage());
    }
}

// Helper function for pagination URLs
function buildPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return 'produk.php?' . http_build_query($params);
}

// Handle add to cart via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    if (!isset($_SESSION['user_logged_in'])) {
        echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
        exit;
    }
    
    $product_id = $_POST['product_id'] ?? 0;
    
    if (!$product_id) {
        echo json_encode(['success' => false, 'message' => 'Produk tidak valid']);
        exit;
    }
    
    try {
        // Check if product exists and is available
        $stmt = $pdo->prepare("SELECT * FROM produk WHERE id_produk = ? AND status = 'Tersedia'");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan']);
            exit;
        }
        
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
                SET jumlah = jumlah + 1, 
                    updated_at = NOW() 
                WHERE id_keranjang = ?
            ");
            $stmt->execute([$existing_item['id_keranjang']]);
        } else {
            // Add new item
            $stmt = $pdo->prepare("
                INSERT INTO keranjang (id_customer, id_produk, jumlah, created_at)
                VALUES (?, ?, 1, NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], $product_id]);
        }
        
        // Get updated cart count
        $stmt_cart = $pdo->prepare("SELECT SUM(jumlah) as total FROM keranjang WHERE id_customer = ? AND status = 'active'");
        $stmt_cart->execute([$_SESSION['user_id']]);
        $new_cart_count = $stmt_cart->fetch()['total'] ?? 0;
        
        echo json_encode([
            'success' => true, 
            'message' => 'Produk berhasil ditambahkan ke keranjang',
            'cart_count' => $new_cart_count
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan produk ke keranjang: ' . $e->getMessage()]);
    }
    exit;
}

// Handle wishlist toggle via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_wishlist') {
    if (!isset($_SESSION['user_logged_in'])) {
        echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
        exit;
    }
    
    $product_id = $_POST['product_id'] ?? 0;
    
    if (!$product_id) {
        echo json_encode(['success' => false, 'message' => 'Produk tidak valid']);
        exit;
    }
    
    try {
        // Check if product exists
        $stmt = $pdo->prepare("SELECT * FROM produk WHERE id_produk = ? AND status = 'Tersedia'");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan']);
            exit;
        }
        
        // Check if already in wishlist
        $stmt = $pdo->prepare("SELECT * FROM wishlist WHERE id_customer = ? AND id_produk = ?");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
        $existing_item = $stmt->fetch();
        
        if ($existing_item) {
            // Remove from wishlist
            $stmt = $pdo->prepare("DELETE FROM wishlist WHERE id_wishlist = ?");
            $stmt->execute([$existing_item['id_wishlist']]);
            echo json_encode(['success' => true, 'is_in_wishlist' => false, 'message' => 'Produk dihapus dari wishlist']);
        } else {
            // Add to wishlist
            $stmt = $pdo->prepare("INSERT INTO wishlist (id_customer, id_produk, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$_SESSION['user_id'], $product_id]);
            echo json_encode(['success' => true, 'is_in_wishlist' => true, 'message' => 'Produk ditambahkan ke wishlist']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupdate wishlist: ' . $e->getMessage()]);
    }
    exit;
}

// Check wishlist status for products
$wishlist_status = [];
if (isset($_SESSION['user_logged_in'])) {
    try {
        $stmt_wishlist = $pdo->prepare("
            SELECT id_produk FROM wishlist 
            WHERE id_customer = ?
        ");
        $stmt_wishlist->execute([$_SESSION['user_id']]);
        $user_wishlist = $stmt_wishlist->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($user_wishlist as $product_id) {
            $wishlist_status[$product_id] = true;
        }
    } catch (Exception $e) {
        error_log("Error fetching wishlist: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koleksi Produk - Luxury Living</title>
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
        
        /* Filter Sidebar */
        .filter-sidebar {
            position: sticky;
            top: 100px;
            height: fit-content;
        }
        
        .filter-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .filter-title {
            font-size: 1.2rem;
            color: var(--dark-brown);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--cream);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-group {
            margin-bottom: 25px;
        }
        
        .filter-group:last-child {
            margin-bottom: 0;
        }
        
        .filter-label {
            display: block;
            margin-bottom: 10px;
            color: var(--dark-brown);
            font-weight: 500;
        }
        
        .filter-options {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .filter-option input {
            margin: 0;
        }
        
        .price-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .price-input {
            padding: 8px 12px;
            border: 1px solid var(--beige);
            border-radius: var(--radius);
            font-size: 14px;
        }
        
        .btn-apply {
            width: 100%;
            background: var(--gold);
            color: white;
            border: none;
            padding: 10px;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 500;
            margin-top: 15px;
            transition: background 0.3s ease;
        }
        
        .btn-apply:hover {
            background: var(--dark-brown);
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
        
        .action-btn.wishlist.active {
            background: var(--gold);
            color: white;
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
        
        /* PERBAIKAN: Product Footer dengan tombol yang lebih rapi */
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
            
            .price-inputs {
                grid-template-columns: 1fr;
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
                    <li><a href="produk.php" class="active">Produk</a></li>
                    <li><a href="kategori.php">Kategori</a></li>
                    <li><a href="tentang.php">Tentang</a></li>
                    <li><a href="kontak.php">Kontak</a></li>
                </ul>
                
                <div class="nav-actions">
                    <a href="keranjang.php" class="nav-action-btn">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count">3</span>
                    </a>
                    <a href="user/login.php" class="btn-login">
                        <i class="fas fa-user"></i> Login
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">Koleksi Produk</h1>
            <p class="page-subtitle">Temukan korden perfect untuk setiap ruangan di rumah Anda dengan kualitas premium</p>
        </div>
    </section>

    <!-- Main Content -->
    <section class="main-content">
        <div class="container">
            <div class="content-grid">
                <!-- Filter Sidebar -->
                <aside class="filter-sidebar">
                    <form method="GET" id="filterForm">
                        <div class="filter-card">
                            <h3 class="filter-title">
                                <i class="fas fa-search"></i>
                                Pencarian
                            </h3>
                            <div class="filter-group">
                                <input type="text" name="search" class="price-input" 
                                       placeholder="Cari produk..." 
                                       value="">
                            </div>
                        </div>

                        <div class="filter-card">
                            <h3 class="filter-title">
                                <i class="fas fa-tags"></i>
                                Kategori
                            </h3>
                            <div class="filter-group">
                                <div class="filter-options">
                                    <label class="filter-option">
                                        <input type="radio" name="kategori" value="" checked>
                                        <span>Semua Kategori</span>
                                    </label>
                                    <label class="filter-option">
                                        <input type="radio" name="kategori" value="1">
                                        <span>Korden Modern</span>
                                    </label>
                                    <label class="filter-option">
                                        <input type="radio" name="kategori" value="2">
                                        <span>Korden Klasik</span>
                                    </label>
                                    <label class="filter-option">
                                        <input type="radio" name="kategori" value="3">
                                        <span>Korden Minimalis</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="filter-card">
                            <h3 class="filter-title">
                                <i class="fas fa-filter"></i>
                                Filter Harga
                            </h3>
                            <div class="filter-group">
                                <div class="price-inputs">
                                    <input type="number" name="min_price" class="price-input" 
                                           placeholder="Min" 
                                           value="">
                                    <input type="number" name="max_price" class="price-input" 
                                           placeholder="Max" 
                                           value="">
                                </div>
                                <div style="font-size: 0.8rem; color: var(--brown); margin-top: 5px;">
                                    Range: Rp 150.000 - Rp 2.500.000
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn-apply">
                            <i class="fas fa-check"></i> Terapkan Filter
                        </button>
                    </form>
                </aside>

                <!-- Products Section -->
                <main class="products-main">
                    <div class="products-header">
                        <div class="results-info">
                            Menampilkan 12 produk
                        </div>
                        
                        <div class="sort-options">
                            <label for="sort" style="color: var(--brown);">Urutkan:</label>
                            <select name="sort" id="sort" class="sort-select" onchange="updateSort(this.value)">
                                <option value="terbaru" selected>Terbaru</option>
                                <option value="harga_terendah">Harga Terendah</option>
                                <option value="harga_tertinggi">Harga Tertinggi</option>
                                <option value="terlaris">Terlaris</option>
                                <option value="rating">Rating Tertinggi</option>
                                <option value="diskon">Diskon Terbesar</option>
                            </select>
                        </div>
                    </div>

                    <div class="product-grid">
                        <!-- Product 1 -->
                        <div class="product-card">
                            <div class="product-badge discount">-20%</div>
                            
                            <div class="product-image">
                                <img src="https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=500&q=80" 
                                     alt="Korden Modern Elegant">
                                
                                <div class="product-actions">
                                    <button class="action-btn wishlist" data-product-id="1">
                                        <i class="far fa-heart"></i>
                                    </button>
                                    <button class="action-btn quick-view" data-product-id="1">
                                        <i class="far fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="product-info">
                                <div class="product-category">Korden Modern</div>
                                <h3 class="product-name">
                                    <a href="produk_detail.php?id=1">
                                        Korden Modern Elegant
                                    </a>
                                </h3>
                                <p class="product-description">Korden dengan bahan premium dan desain modern yang elegan untuk ruang tamu Anda.</p>
                                
                                <div class="product-price">
                                    <span class="current-price">Rp 450.000</span>
                                    <span class="original-price">Rp 560.000</span>
                                </div>
                                
                                <div class="product-meta">
                                    <div class="product-rating">
                                        <div class="stars">
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star-half-alt"></i>
                                        </div>
                                        <span>(24)</span>
                                    </div>
                                    <div class="product-sold">128 terjual</div>
                                </div>
                                
                                <div class="product-stock">
                                    <i class="fas fa-box"></i> Stok Tersedia
                                </div>
                                
                                <div class="product-footer">
                                    <button class="btn-cart" data-product-id="1">
                                        <i class="fas fa-shopping-cart"></i>
                                        Tambah Keranjang
                                    </button>
                                    <a href="produk_detail.php?id=1" 
                                       class="btn-detail" title="Lihat Detail">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Product 2 -->
                        <div class="product-card">
                            <div class="product-badge hot">Hot</div>
                            
                            <div class="product-image">
                                <img src="https://images.unsplash.com/photo-1558618047-3c8c76ca7d13?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=500&q=80" 
                                     alt="Korden Minimalis Grey">
                                
                                <div class="product-actions">
                                    <button class="action-btn wishlist" data-product-id="2">
                                        <i class="far fa-heart"></i>
                                    </button>
                                    <button class="action-btn quick-view" data-product-id="2">
                                        <i class="far fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="product-info">
                                <div class="product-category">Korden Minimalis</div>
                                <h3 class="product-name">
                                    <a href="produk_detail.php?id=2">
                                        Korden Minimalis Grey
                                    </a>
                                </h3>
                                <p class="product-description">Korden minimalis dengan warna abu-abu yang cocok untuk ruangan dengan konsep modern.</p>
                                
                                <div class="product-price">
                                    <span class="current-price">Rp 380.000</span>
                                </div>
                                
                                <div class="product-meta">
                                    <div class="product-rating">
                                        <div class="stars">
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="far fa-star"></i>
                                        </div>
                                        <span>(18)</span>
                                    </div>
                                    <div class="product-sold">95 terjual</div>
                                </div>
                                
                                <div class="product-stock low">
                                    <i class="fas fa-box"></i> Stok Menipis
                                </div>
                                
                                <div class="product-footer">
                                    <button class="btn-cart" data-product-id="2">
                                        <i class="fas fa-shopping-cart"></i>
                                        Tambah Keranjang
                                    </button>
                                    <a href="produk_detail.php?id=2" 
                                       class="btn-detail" title="Lihat Detail">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Product 3 -->
                        <div class="product-card">
                            <div class="product-badge new">Baru</div>
                            
                            <div class="product-image">
                                <img src="https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=500&q=80" 
                                     alt="Korden Klasik Royal">
                                
                                <div class="product-actions">
                                    <button class="action-btn wishlist" data-product-id="3">
                                        <i class="far fa-heart"></i>
                                    </button>
                                    <button class="action-btn quick-view" data-product-id="3">
                                        <i class="far fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="product-info">
                                <div class="product-category">Korden Klasik</div>
                                <h3 class="product-name">
                                    <a href="produk_detail.php?id=3">
                                        Korden Klasik Royal
                                    </a>
                                </h3>
                                <p class="product-description">Korden dengan desain klasik dan bahan mewah, memberikan kesan elegan pada ruangan.</p>
                                
                                <div class="product-price">
                                    <span class="current-price">Rp 620.000</span>
                                    <span class="original-price">Rp 750.000</span>
                                </div>
                                
                                <div class="product-meta">
                                    <div class="product-rating">
                                        <div class="stars">
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                        </div>
                                        <span>(32)</span>
                                    </div>
                                    <div class="product-sold">210 terjual</div>
                                </div>
                                
                                <div class="product-stock">
                                    <i class="fas fa-box"></i> Stok Tersedia
                                </div>
                                
                                <div class="product-footer">
                                    <button class="btn-cart" data-product-id="3">
                                        <i class="fas fa-shopping-cart"></i>
                                        Tambah Keranjang
                                    </button>
                                    <a href="produk_detail.php?id=3" 
                                       class="btn-detail" title="Lihat Detail">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Product 4 -->
                        <div class="product-card">
                            <div class="product-image">
                                <img src="https://images.unsplash.com/photo-1558618047-3c8c76ca7d13?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=500&q=80" 
                                     alt="Korden Blackout Premium">
                                
                                <div class="product-actions">
                                    <button class="action-btn wishlist" data-product-id="4">
                                        <i class="far fa-heart"></i>
                                    </button>
                                    <button class="action-btn quick-view" data-product-id="4">
                                        <i class="far fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="product-info">
                                <div class="product-category">Korden Blackout</div>
                                <h3 class="product-name">
                                    <a href="produk_detail.php?id=4">
                                        Korden Blackout Premium
                                    </a>
                                </h3>
                                <p class="product-description">Korden dengan teknologi blackout untuk mencegah cahaya masuk, cocok untuk kamar tidur.</p>
                                
                                <div class="product-price">
                                    <span class="current-price">Rp 520.000</span>
                                </div>
                                
                                <div class="product-meta">
                                    <div class="product-rating">
                                        <div class="stars">
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star-half-alt"></i>
                                        </div>
                                        <span>(27)</span>
                                    </div>
                                    <div class="product-sold">156 terjual</div>
                                </div>
                                
                                <div class="product-stock out">
                                    <i class="fas fa-box"></i> Stok Habis
                                </div>
                                
                                <div class="product-footer">
                                    <button class="btn-cart" data-product-id="4" disabled>
                                        <i class="fas fa-shopping-cart"></i>
                                        Stok Habis
                                    </button>
                                    <a href="produk_detail.php?id=4" 
                                       class="btn-detail" title="Lihat Detail">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div class="pagination">
                        <a href="#" class="pagination-btn">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="#" class="pagination-btn">
                            <i class="fas fa-angle-left"></i> Sebelumnya
                        </a>

                        <span class="pagination-info">
                            Halaman 1 dari 5
                        </span>

                        <a href="#" class="pagination-btn">
                            Selanjutnya <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="#" class="pagination-btn">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </div>
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
                        <li><a href="produk.php?kategori=1">Korden Modern</a></li>
                        <li><a href="produk.php?kategori=2">Korden Klasik</a></li>
                        <li><a href="produk.php?kategori=3">Korden Minimalis</a></li>
                        <li><a href="produk.php?kategori=4">Korden Blackout</a></li>
                        <li><a href="produk.php?kategori=5">Korden Anak</a></li>
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
                <p>&copy; 2025 Luxury Living. All rights reserved.</p>
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
                
                // Show quick view modal (in a real implementation)
                showQuickViewModal(productId);
            });
        });

        // Price range validation
        document.querySelector('form#filterForm').addEventListener('submit', function(e) {
            const minPrice = document.querySelector('input[name="min_price"]').value;
            const maxPrice = document.querySelector('input[name="max_price"]').value;
            
            if (minPrice && maxPrice && parseInt(minPrice) > parseInt(maxPrice)) {
                e.preventDefault();
                showNotification('Harga minimum tidak boleh lebih besar dari harga maksimum', 'error');
            }
        });

        // Filter form auto-submit for radio buttons
        document.querySelectorAll('.filter-option input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
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
            const style = document.createElement('style');
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
            
            if (!document.querySelector('#notification-styles')) {
                style.id = 'notification-styles';
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

        // Quick view modal function
        function showQuickViewModal(productId) {
            // In a real implementation, this would fetch product data via AJAX
            // For demo purposes, we'll redirect to product detail page
            window.location.href = `produk_detail.php?id=${productId}`;
        }

        // Product card hover effects
        document.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = '0 10px 25px rgba(0,0,0,0.15)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'var(--shadow)';
            });
        });

        // Initialize filter form with URL parameters
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            // Set search input value
            const searchParam = urlParams.get('search');
            if (searchParam) {
                document.querySelector('input[name="search"]').value = searchParam;
            }
            
            // Set category radio
            const categoryParam = urlParams.get('kategori');
            if (categoryParam) {
                const categoryRadio = document.querySelector(`input[name="kategori"][value="${categoryParam}"]`);
                if (categoryRadio) {
                    categoryRadio.checked = true;
                }
            }
            
            // Set price inputs
            const minPriceParam = urlParams.get('min_price');
            const maxPriceParam = urlParams.get('max_price');
            if (minPriceParam) {
                document.querySelector('input[name="min_price"]').value = minPriceParam;
            }
            if (maxPriceParam) {
                document.querySelector('input[name="max_price"]').value = maxPriceParam;
            }
            
            // Set sort select
            const sortParam = urlParams.get('sort');
            if (sortParam) {
                document.querySelector('#sort').value = sortParam;
            }
        });

        // Responsive menu for mobile
        function initMobileMenu() {
            const navContainer = document.querySelector('.nav-container');
            const navLinks = document.querySelector('.nav-links');
            
            // Create mobile menu button
            const mobileMenuBtn = document.createElement('button');
            mobileMenuBtn.className = 'mobile-menu-btn';
            mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            mobileMenuBtn.style.display = 'none';
            
            // Add styles for mobile menu
            const mobileStyles = document.createElement('style');
            mobileStyles.textContent = `
                @media (max-width: 768px) {
                    .mobile-menu-btn {
                        display: block !important;
                        background: none;
                        border: none;
                        font-size: 1.5rem;
                        color: var(--brown);
                        cursor: pointer;
                        padding: 5px;
                    }
                    
                    .nav-links {
                        position: absolute;
                        top: 100%;
                        left: 0;
                        width: 100%;
                        background: white;
                        flex-direction: column;
                        padding: 20px;
                        box-shadow: var(--shadow);
                        display: none;
                    }
                    
                    .nav-links.active {
                        display: flex;
                    }
                    
                    .nav-container {
                        position: relative;
                    }
                }
            `;
            
            document.head.appendChild(mobileStyles);
            navContainer.appendChild(mobileMenuBtn);
            
            // Toggle mobile menu
            mobileMenuBtn.addEventListener('click', function() {
                navLinks.classList.toggle('active');
            });
            
            // Close mobile menu when clicking outside
            document.addEventListener('click', function(e) {
                if (!navContainer.contains(e.target) && navLinks.classList.contains('active')) {
                    navLinks.classList.remove('active');
                }
            });
        }

        // Initialize mobile menu
        if (window.innerWidth <= 768) {
            initMobileMenu();
        }

        // Re-initialize mobile menu on resize
        window.addEventListener('resize', function() {
            if (window.innerWidth <= 768) {
                if (!document.querySelector('.mobile-menu-btn')) {
                    initMobileMenu();
                }
            } else {
                const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
                if (mobileMenuBtn) {
                    mobileMenuBtn.remove();
                }
                const navLinks = document.querySelector('.nav-links');
                if (navLinks) {
                    navLinks.style.display = 'flex';
                }
            }
        });

        // Lazy loading for images
        function initLazyLoading() {
            const images = document.querySelectorAll('.product-image img');
            
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });
            
            images.forEach(img => {
                if (img.complete) return;
                img.dataset.src = img.src;
                img.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjRjNFN0Q3Ii8+PC9zdmc+';
                img.classList.add('lazy');
                imageObserver.observe(img);
            });
        }

        // Initialize lazy loading
        initLazyLoading();

    </script>
</body>
</html>

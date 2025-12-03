<?php
session_start();

// Koneksi database langsung
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=toko_horden2;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Jika user belum login, redirect ke login page
if (!isset($_SESSION['user_logged_in'])) {
    $_SESSION['redirect_to'] = 'keranjang.php';
    header('Location: user/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_cart'])) {
        foreach ($_POST['quantities'] as $cart_id => $quantity) {
            if ($quantity <= 0) {
                // Remove item if quantity is 0
                $stmt = $pdo->prepare("DELETE FROM keranjang WHERE id_keranjang = ? AND id_pelanggan = ?");
                $stmt->execute([$cart_id, $user_id]);
            } else {
                // Update quantity
                $stmt = $pdo->prepare("UPDATE keranjang SET jumlah = ? WHERE id_keranjang = ? AND id_pelanggan = ?");
                $stmt->execute([$quantity, $cart_id, $user_id]);
            }
        }
        $_SESSION['success_message'] = "Keranjang berhasil diperbarui!";
        header("Location: keranjang.php");
        exit;
    }
}

if (isset($_GET['remove'])) {
    $stmt = $pdo->prepare("DELETE FROM keranjang WHERE id_keranjang = ? AND id_pelanggan = ?");
    $stmt->execute([$_GET['remove'], $user_id]);
    $_SESSION['success_message'] = "Produk berhasil dihapus dari keranjang!";
    header("Location: keranjang.php");
    exit;
}

// Get cart items - PERBAIKAN: Gunakan nama kolom yang benar
$stmt = $pdo->prepare("
    SELECT k.*, p.nama_produk, p.harga, p.harga_diskon, p.foto_utama, p.stok 
    FROM keranjang k 
    JOIN produk p ON k.id_produk = p.id_produk 
    WHERE k.id_pelanggan = ? AND p.status = 'Tersedia'
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();

// Calculate totals
$subtotal = 0;
$total_items = 0;
foreach ($cart_items as $item) {
    $price = $item['harga_diskon'] ?: $item['harga'];
    $subtotal += $price * $item['jumlah'];
    $total_items += $item['jumlah'];
}

$shipping = 15000; // Flat rate shipping
// Free shipping for orders above 500,000
if ($subtotal >= 500000) {
    $shipping = 0;
}
$total = $subtotal + $shipping;

// Helper function
function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja - Luxury Living</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --cream: #F3E8D7; --beige: #E7D3B8; --gold: #D8A75A; 
            --brown: #6A4F37; --dark-brown: #4a3828; --light-cream: #faf6f0;
            --radius: 10px; --shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
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
        
        .cart-page {
            padding: 120px 0 50px;
        }
        
        .page-title {
            font-size: 32px;
            color: var(--dark-brown);
            text-align: center;
            margin-bottom: 40px;
        }
        
        .cart-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .cart-items {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .cart-header {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 15px;
            padding: 20px;
            background: var(--cream);
            font-weight: 600;
            color: var(--dark-brown);
        }
        
        .cart-item {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 15px;
            padding: 20px;
            border-bottom: 1px solid var(--cream);
            align-items: center;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .item-info {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .item-image {
            width: 80px;
            height: 80px;
            background: var(--cream);
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .item-details h3 {
            color: var(--dark-brown);
            margin-bottom: 5px;
            font-size: 1.1rem;
        }
        
        .item-details p {
            color: var(--brown);
            font-size: 0.9rem;
        }
        
        .item-price {
            color: var(--dark-brown);
            font-weight: 600;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quantity-btn {
            width: 35px;
            height: 35px;
            background: var(--cream);
            border: none;
            border-radius: 5px;
            color: var(--brown);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .quantity-btn:hover {
            background: var(--gold);
            color: white;
        }
        
        .quantity-input {
            width: 60px;
            text-align: center;
            padding: 8px;
            border: 1px solid var(--beige);
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .item-total {
            font-weight: 600;
            color: var(--dark-brown);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .item-remove {
            background: none;
            border: none;
            color: #e74c3c;
            cursor: pointer;
            padding: 8px;
            border-radius: 5px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .item-remove:hover {
            background: #ffe6e6;
        }
        
        .cart-summary {
            background: white;
            padding: 25px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            height: fit-content;
            position: sticky;
            top: 100px;
        }
        
        .summary-title {
            font-size: 20px;
            color: var(--dark-brown);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--cream);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            color: var(--brown);
        }
        
        .summary-total {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark-brown);
            border-top: 2px solid var(--cream);
            padding-top: 15px;
            margin-top: 15px;
        }
        
        .total-price {
            color: var(--gold);
        }
        
        .btn-checkout {
            width: 100%;
            background: var(--gold);
            color: white;
            padding: 15px;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }
        
        .btn-checkout:hover {
            background: var(--dark-brown);
            transform: translateY(-2px);
        }
        
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            color: var(--brown);
        }
        
        .empty-cart i {
            font-size: 64px;
            color: var(--cream);
            margin-bottom: 20px;
        }
        
        .cart-actions {
            display: flex;
            justify-content: space-between;
            padding: 20px;
            background: var(--light-cream);
        }
        
        .btn-continue {
            background: var(--cream);
            color: var(--brown);
            padding: 12px 25px;
            border: none;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-continue:hover {
            background: var(--beige);
        }
        
        .btn-update {
            background: var(--gold);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-update:hover {
            background: var(--dark-brown);
        }

        .free-shipping {
            color: #28a745;
            font-weight: 600;
        }

        .alert {
            padding: 15px 20px;
            border-radius: var(--radius);
            margin-bottom: 25px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }

        .stock-warning {
            color: #e74c3c;
            font-size: 0.8rem;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .cart-container {
                grid-template-columns: 1fr;
            }
            
            .cart-header {
                display: none;
            }
            
            .cart-item {
                grid-template-columns: 1fr;
                gap: 15px;
                text-align: center;
            }
            
            .item-info {
                flex-direction: column;
                text-align: center;
            }
            
            .item-total {
                justify-content: center;
                gap: 15px;
            }
            
            .cart-actions {
                flex-direction: column;
                gap: 15px;
            }
            
            .btn-continue, .btn-update {
                width: 100%;
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
                    <li><a href="kategori.php">Kategori</a></li>
                    <li><a href="tentang.php">Tentang</a></li>
                    <li><a href="kontak.php">Kontak</a></li>
                </ul>
                
                <div class="nav-actions">
                    <a href="keranjang.php" class="nav-action-btn">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $total_items; ?></span>
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

    <!-- Cart Page -->
    <section class="cart-page">
        <div class="container">
            <h1 class="page-title">Keranjang Belanja</h1>
            
            <?php if(isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if(empty($cart_items)): ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h2>Keranjang Anda kosong</h2>
                <p style="margin-bottom: 30px;">Mulai berbelanja dan temukan produk menarik untuk hunian Anda</p>
                <a href="produk.php" class="btn-checkout" style="display: inline-flex; width: auto; padding: 12px 30px;">
                    <i class="fas fa-shopping-bag"></i> Mulai Belanja
                </a>
            </div>
            <?php else: ?>
            <form method="POST">
                <div class="cart-container">
                    <!-- Cart Items -->
                    <div class="cart-items">
                        <div class="cart-header">
                            <div>Produk</div>
                            <div>Harga</div>
                            <div>Jumlah</div>
                            <div>Subtotal</div>
                        </div>
                        
                        <?php foreach($cart_items as $item): ?>
                        <?php
                        $price = $item['harga_diskon'] ?: $item['harga'];
                        $item_total = $price * $item['jumlah'];
                        ?>
                        <div class="cart-item">
                            <div class="item-info">
                                <div class="item-image">
                                    <?php if(!empty($item['foto_utama'])): ?>
                                        <img src="uploads/produk/<?php echo $item['foto_utama']; ?>" 
                                             alt="<?php echo $item['nama_produk']; ?>">
                                    <?php else: ?>
                                        <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: var(--brown);">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="item-details">
                                    <h3><?php echo htmlspecialchars($item['nama_produk']); ?></h3>
                                    <p>Stok: <?php echo $item['stok']; ?> unit</p>
                                    <?php if($item['jumlah'] > $item['stok']): ?>
                                        <div class="stock-warning">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            Jumlah melebihi stok tersedia
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="item-price">
                                <?php echo formatCurrency($price); ?>
                            </div>
                            
                            <div class="quantity-controls">
                                <button type="button" class="quantity-btn" onclick="updateQuantity(<?php echo $item['id_keranjang']; ?>, -1)">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" name="quantities[<?php echo $item['id_keranjang']; ?>]" 
                                       value="<?php echo $item['jumlah']; ?>" min="1" max="<?php echo $item['stok']; ?>" 
                                       class="quantity-input" id="quantity_<?php echo $item['id_keranjang']; ?>">
                                <button type="button" class="quantity-btn" onclick="updateQuantity(<?php echo $item['id_keranjang']; ?>, 1)">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            
                            <div class="item-total">
                                <span><?php echo formatCurrency($item_total); ?></span>
                                <a href="keranjang.php?remove=<?php echo $item['id_keranjang']; ?>" class="item-remove" onclick="return confirm('Hapus produk dari keranjang?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="cart-actions">
                            <a href="produk.php" class="btn-continue">
                                <i class="fas fa-arrow-left"></i> Lanjutkan Belanja
                            </a>
                            <button type="submit" name="update_cart" class="btn-update">
                                <i class="fas fa-sync-alt"></i> Perbarui Keranjang
                            </button>
                        </div>
                    </div>
                    
                    <!-- Cart Summary -->
                    <div class="cart-summary">
                        <h3 class="summary-title">Ringkasan Belanja</h3>
                        
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span><?php echo formatCurrency($subtotal); ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Pengiriman</span>
                            <span>
                                <?php if($shipping == 0): ?>
                                    <span class="free-shipping">Gratis</span>
                                <?php else: ?>
                                    <?php echo formatCurrency($shipping); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <?php if($shipping == 0): ?>
                        <div class="summary-row free-shipping">
                            <span>✓ Gratis ongkir untuk order > Rp 500.000</span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="summary-row">
                            <span>Estimasi Waktu</span>
                            <span>2-3 hari</span>
                        </div>
                        
                        <div class="summary-row summary-total">
                            <span>Total</span>
                            <span class="total-price"><?php echo formatCurrency($total); ?></span>
                        </div>
                        
                        <a href="checkout.php" class="btn-checkout">
                            <i class="fas fa-lock"></i> Lanjut ke Checkout
                        </a>
                        
                        <div style="margin-top: 20px; padding: 15px; background: var(--light-cream); border-radius: var(--radius);">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                <i class="fas fa-shield-alt" style="color: var(--gold);"></i>
                                <span style="font-weight: 600;">Pembayaran Aman</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-truck" style="color: var(--gold);"></i>
                                <span>Gratis Ongkir untuk order > Rp 500.000</span>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </section>

    <script>
        function updateQuantity(cartId, change) {
            const input = document.getElementById('quantity_' + cartId);
            let newValue = parseInt(input.value) + change;
            const maxStock = parseInt(input.max);
            
            if (newValue < 1) newValue = 1;
            if (newValue > maxStock) {
                newValue = maxStock;
                alert('Stok tidak mencukupi! Stok tersedia: ' + maxStock);
            }
            
            input.value = newValue;
        }
        
        // Auto submit form when quantity changes
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
</body>
</html>
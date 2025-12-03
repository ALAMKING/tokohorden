<?php
session_start();
require_once 'config/database.php';

// Redirect jika belum login
if (!isset($_SESSION['user_logged_in'])) {
    $_SESSION['redirect_to'] = 'checkout.php';
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Ambil data user
$user_stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE id_pelanggan = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

// Handle direct buy now
if (isset($_GET['product_id']) && isset($_GET['quantity'])) {
    $product_id = $_GET['product_id'];
    $quantity = $_GET['quantity'];
    
    // Clear cart first
    $pdo->prepare("DELETE FROM keranjang WHERE id_pelanggan = ?")->execute([$user_id]);
    
    // Add single product to cart
    $pdo->prepare("INSERT INTO keranjang (id_pelanggan, id_produk, jumlah) VALUES (?, ?, ?)")
        ->execute([$user_id, $product_id, $quantity]);
}

// Get cart items
$cart_items = $pdo->prepare("SELECT k.*, p.nama_produk, p.harga, p.harga_diskon, p.foto_utama, p.stok, p.berat 
                            FROM keranjang k 
                            JOIN produk p ON k.id_produk = p.id_produk 
                            WHERE k.id_pelanggan = ? AND p.status = 'Tersedia'")
                 ->execute([$user_id])
                 ->fetchAll();

// Redirect jika keranjang kosong
if (empty($cart_items)) {
    header('Location: keranjang.php');
    exit;
}

// Calculate totals
$subtotal = 0;
$total_weight = 0;
foreach ($cart_items as $item) {
    $price = $item['harga_diskon'] ?: $item['harga'];
    $subtotal += $price * $item['jumlah'];
    $total_weight += $item['berat'] * $item['jumlah'];
}

// Shipping options
$shipping_options = [
    ['code' => 'jne_reg', 'name' => 'JNE Reguler', 'cost' => 15000, 'eta' => '2-3 hari'],
    ['code' => 'jne_oke', 'name' => 'JNE OKE', 'cost' => 12000, 'eta' => '3-5 hari'],
    ['code' => 'jne_yes', 'name' => 'JNE YES', 'cost' => 25000, 'eta' => '1-2 hari'],
    ['code' => 'tiki_reg', 'name' => 'TIKI Reguler', 'cost' => 18000, 'eta' => '2-4 hari'],
    ['code' => 'pos_reg', 'name' => 'POS Indonesia', 'cost' => 10000, 'eta' => '4-7 hari']
];

// Payment methods
$payment_methods = [
    ['code' => 'transfer_bca', 'name' => 'Transfer BCA', 'icon' => 'fas fa-university'],
    ['code' => 'transfer_mandiri', 'name' => 'Transfer Mandiri', 'icon' => 'fas fa-university'],
    ['code' => 'transfer_bri', 'name' => 'Transfer BRI', 'icon' => 'fas fa-university'],
    ['code' => 'gopay', 'name' => 'GoPay', 'icon' => 'fas fa-mobile-alt'],
    ['code' => 'ovo', 'name' => 'OVO', 'icon' => 'fas fa-mobile-alt'],
    ['code' => 'dana', 'name' => 'DANA', 'icon' => 'fas fa-wallet'],
    ['code' => 'cod', 'name' => 'Cash on Delivery', 'icon' => 'fas fa-money-bill-wave']
];

// Default values
$shipping_method = $_POST['shipping_method'] ?? 'jne_reg';
$payment_method = $_POST['payment_method'] ?? 'transfer_bca';
$shipping_cost = 0;

// Find selected shipping cost
foreach ($shipping_options as $option) {
    if ($option['code'] === $shipping_method) {
        $shipping_cost = $option['cost'];
        break;
    }
}

$total = $subtotal + $shipping_cost;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $nama_penerima = $_POST['nama_penerima'];
    $no_hp_penerima = $_POST['no_hp_penerima'];
    $alamat_pengiriman = $_POST['alamat_pengiriman'];
    $kota_pengiriman = $_POST['kota_pengiriman'];
    $kode_pos_pengiriman = $_POST['kode_pos_pengiriman'];
    $catatan = $_POST['catatan'] ?? '';
    
    // Generate order code
    $order_code = 'ORD-' . date('Ymd') . '-' . strtoupper(uniqid());
    
    try {
        $pdo->beginTransaction();
        
        // Insert order
        $order_stmt = $pdo->prepare("INSERT INTO pesanan (kode_pesanan, id_pelanggan, total_harga, metode_pembayaran, 
                                  nama_penerima, no_hp_penerima, alamat_pengiriman, kota_pengiriman, kode_pos_pengiriman, 
                                  catatan, ongkir, kurir) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $order_stmt->execute([
            $order_code, $user_id, $total, $payment_method,
            $nama_penerima, $no_hp_penerima, $alamat_pengiriman, $kota_pengiriman, $kode_pos_pengiriman,
            $catatan, $shipping_cost, $shipping_method
        ]);
        
        $order_id = $pdo->lastInsertId();
        
        // Insert order items and update product stock
        foreach ($cart_items as $item) {
            $price = $item['harga_diskon'] ?: $item['harga'];
            $subtotal_item = $price * $item['jumlah'];
            
            $detail_stmt = $pdo->prepare("INSERT INTO detail_pesanan (id_pesanan, id_produk, jumlah, harga_satuan, subtotal) 
                                        VALUES (?, ?, ?, ?, ?)");
            $detail_stmt->execute([$order_id, $item['id_produk'], $item['jumlah'], $price, $subtotal_item]);
            
            // Update product stock
            $update_stmt = $pdo->prepare("UPDATE produk SET stok = stok - ?, terjual = terjual + ? WHERE id_produk = ?");
            $update_stmt->execute([$item['jumlah'], $item['jumlah'], $item['id_produk']]);
        }
        
        // Clear cart
        $pdo->prepare("DELETE FROM keranjang WHERE id_pelanggan = ?")->execute([$user_id]);
        
        // Add notification
        $notif_stmt = $pdo->prepare("INSERT INTO notifikasi (tipe, judul, pesan, target, id_target, link) 
                                   VALUES ('Pesanan Baru', 'Pesanan Baru', ?, 'admin', ?, ?)");
        $notif_stmt->execute(["Pesanan baru $order_code dari {$user['nama']}", $order_id, "admin/pesanan.php?action=detail&id=$order_id"]);
        
        $pdo->commit();
        
        // Redirect to order confirmation
        $_SESSION['order_success'] = $order_code;
        header('Location: order-confirmation.php?order_id=' . $order_id);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Terjadi kesalahan saat memproses pesanan. Silakan coba lagi.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Luxury Living</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .checkout-page {
            padding: 120px 0 50px;
        }
        
        .page-title {
            font-size: 32px;
            color: var(--dark-brown);
            text-align: center;
            margin-bottom: 40px;
        }
        
        .checkout-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
        }
        
        .checkout-steps {
            display: flex;
            justify-content: center;
            margin-bottom: 40px;
            background: white;
            padding: 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        
        .step {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 30px;
            position: relative;
        }
        
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            right: -20px;
            top: 50%;
            width: 40px;
            height: 2px;
            background: var(--cream);
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            background: var(--cream);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--brown);
        }
        
        .step.active .step-number {
            background: var(--gold);
            color: white;
        }
        
        .step-text {
            font-weight: 500;
            color: var(--brown);
        }
        
        .step.active .step-text {
            color: var(--gold);
            font-weight: 600;
        }
        
        .checkout-form {
            background: white;
            padding: 30px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        
        .form-section {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 1px solid var(--cream);
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .section-title {
            font-size: 20px;
            color: var(--dark-brown);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: var(--gold);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-brown);
            font-weight: 500;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--beige);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(216,167,90,0.1);
        }
        
        .shipping-options, .payment-options {
            display: grid;
            gap: 15px;
        }
        
        .shipping-option, .payment-option {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border: 2px solid var(--cream);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .shipping-option:hover, .payment-option:hover {
            border-color: var(--gold);
        }
        
        .shipping-option.selected, .payment-option.selected {
            border-color: var(--gold);
            background: rgba(216,167,90,0.05);
        }
        
        .option-radio {
            display: none;
        }
        
        .option-content {
            flex: 1;
        }
        
        .option-name {
            font-weight: 600;
            color: var(--dark-brown);
            margin-bottom: 5px;
        }
        
        .option-details {
            color: var(--brown);
            font-size: 14px;
        }
        
        .option-price {
            font-weight: 600;
            color: var(--gold);
        }
        
        .order-summary {
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
            border-bottom: 1px solid var(--cream);
        }
        
        .order-items {
            margin-bottom: 20px;
        }
        
        .order-item {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--cream);
        }
        
        .order-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .item-image {
            width: 60px;
            height: 60px;
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
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            font-weight: 600;
            color: var(--dark-brown);
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .item-meta {
            color: var(--brown);
            font-size: 12px;
        }
        
        .item-price {
            font-weight: 600;
            color: var(--dark-brown);
            font-size: 14px;
        }
        
        .summary-totals {
            border-top: 1px solid var(--cream);
            padding-top: 20px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            color: var(--brown);
        }
        
        .total-row.final {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-brown);
            border-top: 1px solid var(--cream);
            padding-top: 15px;
            margin-top: 15px;
        }
        
        .btn-place-order {
            width: 100%;
            background: var(--gold);
            color: white;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }
        
        .btn-place-order:hover {
            background: var(--dark-brown);
        }
        
        .security-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
            padding: 15px;
            background: var(--light-cream);
            border-radius: 8px;
            color: var(--brown);
            font-size: 14px;
        }
        
        .error-message {
            background: #ffe6e6;
            color: #d63031;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #d63031;
        }

        @media (max-width: 768px) {
            .checkout-container {
                grid-template-columns: 1fr;
            }
            
            .checkout-steps {
                flex-direction: column;
                gap: 20px;
            }
            
            .step:not(:last-child)::after {
                display: none;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
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
                        <span class="cart-count"><?php echo count($cart_items); ?></span>
                    </a>
                    <?php if(isset($_SESSION['user_logged_in'])): ?>
                        <a href="profile.php" class="btn-login">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['user_nama']; ?>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn-login">
                            <i class="fas fa-user"></i> Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Checkout Page -->
    <section class="checkout-page">
        <div class="container">
            <h1 class="page-title">Checkout</h1>
            
            <!-- Checkout Steps -->
            <div class="checkout-steps">
                <div class="step active">
                    <div class="step-number">1</div>
                    <div class="step-text">Informasi Pengiriman</div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-text">Metode Pembayaran</div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-text">Konfirmasi Pesanan</div>
                </div>
            </div>
            
            <?php if(isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" id="checkoutForm">
                <div class="checkout-container">
                    <!-- Checkout Form -->
                    <div class="checkout-form">
                        <!-- Shipping Information -->
                        <div class="form-section">
                            <h2 class="section-title">
                                <i class="fas fa-truck"></i>
                                Informasi Pengiriman
                            </h2>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Nama Penerima *</label>
                                    <input type="text" name="nama_penerima" class="form-input" 
                                           value="<?php echo htmlspecialchars($user['nama']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">No. HP Penerima *</label>
                                    <input type="tel" name="no_hp_penerima" class="form-input" 
                                           value="<?php echo htmlspecialchars($user['no_hp']); ?>" required>
                                </div>
                                
                                <div class="form-group full-width">
                                    <label class="form-label">Alamat Lengkap *</label>
                                    <textarea name="alamat_pengiriman" class="form-textarea" rows="3" required><?php echo htmlspecialchars($user['alamat']); ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Kota *</label>
                                    <input type="text" name="kota_pengiriman" class="form-input" 
                                           value="<?php echo htmlspecialchars($user['kota']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Kode Pos *</label>
                                    <input type="text" name="kode_pos_pengiriman" class="form-input" 
                                           value="<?php echo htmlspecialchars($user['kode_pos']); ?>" required>
                                </div>
                                
                                <div class="form-group full-width">
                                    <label class="form-label">Catatan (Opsional)</label>
                                    <textarea name="catatan" class="form-textarea" rows="2" placeholder="Contoh: Tinggal di rumah warna biru..."><?php echo htmlspecialchars($_POST['catatan'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Shipping Method -->
                        <div class="form-section">
                            <h2 class="section-title">
                                <i class="fas fa-shipping-fast"></i>
                                Metode Pengiriman
                            </h2>
                            
                            <div class="shipping-options">
                                <?php foreach($shipping_options as $option): ?>
                                <label class="shipping-option <?php echo $shipping_method === $option['code'] ? 'selected' : ''; ?>">
                                    <input type="radio" name="shipping_method" value="<?php echo $option['code']; ?>" 
                                           class="option-radio" <?php echo $shipping_method === $option['code'] ? 'checked' : ''; ?> 
                                           onchange="updateShipping('<?php echo $option['code']; ?>', <?php echo $option['cost']; ?>)">
                                    <div class="option-content">
                                        <div class="option-name"><?php echo $option['name']; ?></div>
                                        <div class="option-details">Estimasi: <?php echo $option['eta']; ?> • Berat: <?php echo number_format($total_weight/1000, 1); ?> kg</div>
                                    </div>
                                    <div class="option-price">Rp <?php echo number_format($option['cost'], 0, ',', '.'); ?></div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Payment Method -->
                        <div class="form-section">
                            <h2 class="section-title">
                                <i class="fas fa-credit-card"></i>
                                Metode Pembayaran
                            </h2>
                            
                            <div class="payment-options">
                                <?php foreach($payment_methods as $method): ?>
                                <label class="payment-option <?php echo $payment_method === $method['code'] ? 'selected' : ''; ?>">
                                    <input type="radio" name="payment_method" value="<?php echo $method['code']; ?>" 
                                           class="option-radio" <?php echo $payment_method === $method['code'] ? 'checked' : ''; ?>>
                                    <i class="<?php echo $method['icon']; ?>" style="color: var(--gold);"></i>
                                    <div class="option-content">
                                        <div class="option-name"><?php echo $method['name']; ?></div>
                                        <?php if($method['code'] === 'cod'): ?>
                                        <div class="option-details">Bayar ketika pesanan diterima</div>
                                        <?php endif; ?>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="order-summary">
                        <h3 class="summary-title">Ringkasan Pesanan</h3>
                        
                        <div class="order-items">
                            <?php foreach($cart_items as $item): ?>
                            <?php $price = $item['harga_diskon'] ?: $item['harga']; ?>
                            <div class="order-item">
                                <div class="item-image">
                                    <img src="assets/images/produk/<?php echo $item['foto_utama'] ?: 'default.jpg'; ?>" 
                                         alt="<?php echo $item['nama_produk']; ?>">
                                </div>
                                <div class="item-details">
                                    <div class="item-name"><?php echo $item['nama_produk']; ?></div>
                                    <div class="item-meta">Jumlah: <?php echo $item['jumlah']; ?> x Rp <?php echo number_format($price, 0, ',', '.'); ?></div>
                                </div>
                                <div class="item-price">
                                    Rp <?php echo number_format($price * $item['jumlah'], 0, ',', '.'); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="summary-totals">
                            <div class="total-row">
                                <span>Subtotal</span>
                                <span id="subtotal">Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></span>
                            </div>
                            
                            <div class="total-row">
                                <span>Ongkos Kirim</span>
                                <span id="shipping-cost">Rp <?php echo number_format($shipping_cost, 0, ',', '.'); ?></span>
                            </div>
                            
                            <div class="total-row final">
                                <span>Total</span>
                                <span id="total-amount">Rp <?php echo number_format($total, 0, ',', '.'); ?></span>
                            </div>
                        </div>
                        
                        <button type="submit" name="place_order" class="btn-place-order">
                            <i class="fas fa-lock"></i> Buat Pesanan
                        </button>
                        
                        <div class="security-badge">
                            <i class="fas fa-shield-alt"></i>
                            <span>Transaksi Anda aman dan terenkripsi</span>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <h3>Luxury Living</h3>
                    <p style="color: var(--cream); margin-bottom: 20px;">Toko horden premium dengan kualitas terbaik dan pelayanan terpercaya untuk hunian elegan Anda.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
                
                <div class="footer-col">
                    <h3>Quick Links</h3>
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
                        <li><a href="pengembalian.php">Pengembalian</a></li>
                        <li><a href="syarat.php">Syarat & Ketentuan</a></li>
                        <li><a href="privasi.php">Kebijakan Privasi</a></li>
                    </ul>
                </div>
                
                <div class="footer-col">
                    <h3>Kontak Kami</h3>
                    <ul class="contact-info">
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Jl. Contoh No. 123, Jakarta</span>
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
                            <span>Senin - Minggu: 09:00 - 21:00</span>
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
        // Update shipping cost
        function updateShipping(method, cost) {
            const subtotal = <?php echo $subtotal; ?>;
            const shippingCostElement = document.getElementById('shipping-cost');
            const totalElement = document.getElementById('total-amount');
            
            const total = subtotal + cost;
            
            shippingCostElement.textContent = 'Rp ' + cost.toLocaleString('id-ID');
            totalElement.textContent = 'Rp ' + total.toLocaleString('id-ID');
            
            // Update selected state
            document.querySelectorAll('.shipping-option').forEach(option => {
                option.classList.remove('selected');
            });
            event.target.closest('.shipping-option').classList.add('selected');
        }
        
        // Payment method selection
        document.querySelectorAll('.payment-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.payment-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                this.classList.add('selected');
            });
        });
        
        // Form validation
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let valid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    valid = false;
                    field.style.borderColor = '#e74c3c';
                } else {
                    field.style.borderColor = '';
                }
            });
            
            if (!valid) {
                e.preventDefault();
                alert('Harap lengkapi semua field yang wajib diisi!');
            }
        });
        
        // Auto format phone number
        const phoneInput = document.querySelector('input[name="no_hp_penerima"]');
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.startsWith('0')) {
                value = '62' + value.substring(1);
            }
            e.target.value = value;
        });
    </script>
</body>
</html>
<?php
session_start();
require_once 'config/database.php';

// Redirect jika tidak ada order success
if (!isset($_SESSION['order_success'])) {
    header('Location: index.php');
    exit;
}

$order_id = $_GET['order_id'] ?? 0;

// Get order details
$order_stmt = $pdo->prepare("SELECT p.*, pl.nama as nama_pelanggan, pl.email 
                           FROM pesanan p 
                           JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan 
                           WHERE p.id_pesanan = ? AND p.kode_pesanan = ?");
$order_stmt->execute([$order_id, $_SESSION['order_success']]);
$order = $order_stmt->fetch();

if (!$order) {
    header('Location: index.php');
    exit;
}

// Get order items
$items_stmt = $pdo->prepare("SELECT d.*, pr.nama_produk, pr.foto_utama 
                           FROM detail_pesanan d 
                           JOIN produk pr ON d.id_produk = pr.id_produk 
                           WHERE d.id_pesanan = ?");
$items_stmt->execute([$order_id]);
$order_items = $items_stmt->fetchAll();

// Clear the success session
unset($_SESSION['order_success']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Berhasil - Luxury Living</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .confirmation-page {
            padding: 120px 0 50px;
            background: linear-gradient(135deg, var(--light-cream), var(--cream));
            min-height: 100vh;
        }
        
        .confirmation-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .confirmation-header {
            background: linear-gradient(135deg, var(--gold), #e6b567);
            padding: 40px;
            text-align: center;
            color: white;
        }
        
        .success-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .confirmation-title {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .order-number {
            font-size: 18px;
            opacity: 0.9;
        }
        
        .confirmation-body {
            padding: 40px;
        }
        
        .info-section {
            margin-bottom: 30px;
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
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .info-item {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--dark-brown);
            margin-bottom: 5px;
        }
        
        .info-value {
            color: var(--brown);
        }
        
        .order-items {
            margin-bottom: 30px;
        }
        
        .order-item {
            display: flex;
            gap: 15px;
            padding: 15px;
            border: 1px solid var(--cream);
            border-radius: 8px;
            margin-bottom: 10px;
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
        }
        
        .item-meta {
            color: var(--brown);
            font-size: 14px;
        }
        
        .item-price {
            font-weight: 600;
            color: var(--dark-brown);
        }
        
        .next-steps {
            background: var(--light-cream);
            padding: 25px;
            border-radius: var(--radius);
            margin-bottom: 30px;
        }
        
        .steps-list {
            list-style: none;
        }
        
        .steps-list li {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--cream);
        }
        
        .steps-list li:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            background: var(--gold);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .confirmation-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--gold);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--dark-brown);
        }
        
        .btn-secondary {
            background: var(--cream);
            color: var(--brown);
        }
        
        .btn-secondary:hover {
            background: var(--beige);
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .confirmation-actions {
                flex-direction: column;
            }
            
            .btn {
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
                
                <div class="nav-actions">
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

    <!-- Confirmation Page -->
    <section class="confirmation-page">
        <div class="container">
            <div class="confirmation-container">
                <!-- Header -->
                <div class="confirmation-header">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h1 class="confirmation-title">Pesanan Berhasil!</h1>
                    <div class="order-number">No. Pesanan: <?php echo $order['kode_pesanan']; ?></div>
                </div>
                
                <!-- Body -->
                <div class="confirmation-body">
                    <!-- Order Summary -->
                    <div class="info-section">
                        <h2 class="section-title">
                            <i class="fas fa-receipt"></i>
                            Ringkasan Pesanan
                        </h2>
                        
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Tanggal Pesanan</div>
                                <div class="info-value"><?php echo date('d F Y H:i', strtotime($order['tanggal_pesanan'])); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Status Pesanan</div>
                                <div class="info-value">
                                    <span style="color: var(--gold); font-weight: 600;"><?php echo $order['status_pesanan']; ?></span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Total Pembayaran</div>
                                <div class="info-value" style="font-size: 18px; font-weight: 600; color: var(--gold);">
                                    Rp <?php echo number_format($order['total_harga'], 0, ',', '.'); ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Metode Pembayaran</div>
                                <div class="info-value"><?php echo ucwords(str_replace('_', ' ', $order['metode_pembayaran'])); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Shipping Information -->
                    <div class="info-section">
                        <h2 class="section-title">
                            <i class="fas fa-truck"></i>
                            Informasi Pengiriman
                        </h2>
                        
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Nama Penerima</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['nama_penerima']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">No. HP Penerima</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['no_hp_penerima']); ?></div>
                            </div>
                            <div class="info-item full-width">
                                <div class="info-label">Alamat Pengiriman</div>
                                <div class="info-value"><?php echo nl2br(htmlspecialchars($order['alamat_pengiriman'])); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Kota</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['kota_pengiriman']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Kode Pos</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['kode_pos_pengiriman']); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Items -->
                    <div class="info-section">
                        <h2 class="section-title">
                            <i class="fas fa-box"></i>
                            Item Pesanan
                        </h2>
                        
                        <div class="order-items">
                            <?php foreach($order_items as $item): ?>
                            <div class="order-item">
                                <div class="item-image">
                                    <img src="assets/images/produk/<?php echo $item['foto_utama'] ?: 'default.jpg'; ?>" 
                                         alt="<?php echo $item['nama_produk']; ?>">
                                </div>
                                <div class="item-details">
                                    <div class="item-name"><?php echo $item['nama_produk']; ?></div>
                                    <div class="item-meta">Jumlah: <?php echo $item['jumlah']; ?> x Rp <?php echo number_format($item['harga_satuan'], 0, ',', '.'); ?></div>
                                </div>
                                <div class="item-price">
                                    Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Next Steps -->
                    <div class="next-steps">
                        <h3 class="section-title" style="margin-bottom: 20px;">
                            <i class="fas fa-list-ol"></i>
                            Langkah Selanjutnya
                        </h3>
                        
                        <ul class="steps-list">
                            <li>
                                <div class="step-number">1</div>
                                <div>
                                    <div style="font-weight: 600; color: var(--dark-brown);">Konfirmasi Pembayaran</div>
                                    <div style="color: var(--brown); font-size: 14px;">
                                        <?php if($order['metode_pembayaran'] !== 'cod'): ?>
                                        Lakukan pembayaran dan upload bukti transfer di halaman pesanan Anda
                                        <?php else: ?>
                                        Siapkan pembayaran tunai ketika kurir mengantar pesanan
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </li>
                            <li>
                                <div class="step-number">2</div>
                                <div>
                                    <div style="font-weight: 600; color: var(--dark-brown);">Pesanan Diproses</div>
                                    <div style="color: var(--brown); font-size: 14px;">Kami akan memproses dan mengemas pesanan Anda</div>
                                </div>
                            </li>
                            <li>
                                <div class="step-number">3</div>
                                <div>
                                    <div style="font-weight: 600; color: var(--dark-brown);">Pesanan Dikirim</div>
                                    <div style="color: var(--brown); font-size: 14px;">Pesanan akan dikirim ke alamat Anda</div>
                                </div>
                            </li>
                            <li>
                                <div class="step-number">4</div>
                                <div>
                                    <div style="font-weight: 600; color: var(--dark-brown);">Pesanan Selesai</div>
                                    <div style="color: var(--brown); font-size: 14px;">Konfirmasi penerimaan dan berikan ulasan</div>
                                </div>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Actions -->
                    <div class="confirmation-actions">
                        <a href="profile.php?tab=orders" class="btn btn-primary">
                            <i class="fas fa-clipboard-list"></i> Lihat Pesanan Saya
                        </a>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-home"></i> Kembali ke Beranda
                        </a>
                        <a href="produk.php" class="btn btn-secondary">
                            <i class="fas fa-shopping-bag"></i> Lanjutkan Belanja
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <!-- ... footer content ... -->
    </footer>

    <script>
        // Print order confirmation
        function printConfirmation() {
            window.print();
        }
        
        // Auto redirect after 30 seconds
        setTimeout(() => {
            window.location.href = 'profile.php?tab=orders';
        }, 30000);
    </script>
</body>
</html>
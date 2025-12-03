<?php
session_start();
require_once 'config/database.php';

// Redirect jika belum login
if (!isset($_SESSION['user_logged_in'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'] ?? 0;

// Get order details
$order_stmt = $pdo->prepare("SELECT p.*, pl.nama as nama_pelanggan, pl.email 
                           FROM pesanan p 
                           JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan 
                           WHERE p.id_pesanan = ? AND p.id_pelanggan = ?");
$order_stmt->execute([$order_id, $user_id]);
$order = $order_stmt->fetch();

if (!$order) {
    header('Location: profile.php?tab=orders');
    exit;
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $payment_method = $order['metode_pembayaran'];
    $amount = $order['total_harga'];
    
    try {
        $pdo->beginTransaction();
        
        // Insert payment record
        $payment_stmt = $pdo->prepare("INSERT INTO pembayaran (id_pesanan, metode_pembayaran, jumlah_bayar, status) 
                                     VALUES (?, ?, ?, 'Menunggu')");
        $payment_stmt->execute([$order_id, $payment_method, $amount]);
        
        // Handle file upload for transfer proof
        if ($payment_method !== 'cod' && isset($_FILES['bukti_transfer'])) {
            $bukti_transfer = $_FILES['bukti_transfer'];
            
            if ($bukti_transfer['error'] === UPLOAD_ERR_OK) {
                $file_extension = pathinfo($bukti_transfer['name'], PATHINFO_EXTENSION);
                $file_name = 'payment_' . $order['kode_pesanan'] . '_' . time() . '.' . $file_extension;
                $upload_path = 'assets/images/payments/' . $file_name;
                
                if (move_uploaded_file($bukti_transfer['tmp_name'], $upload_path)) {
                    $update_stmt = $pdo->prepare("UPDATE pembayaran SET bukti_transfer = ? WHERE id_pesanan = ?");
                    $update_stmt->execute([$file_name, $order_id]);
                }
            }
        }
        
        // Update order status
        $update_order_stmt = $pdo->prepare("UPDATE pesanan SET status_pesanan = 'Menunggu Konfirmasi' WHERE id_pesanan = ?");
        $update_order_stmt->execute([$order_id]);
        
        // Add notification for admin
        $notif_stmt = $pdo->prepare("INSERT INTO notifikasi (tipe, judul, pesan, target, id_target, link) 
                                   VALUES ('Pembayaran', 'Pembayaran Baru', ?, 'admin', ?, ?)");
        $notif_stmt->execute(["Pembayaran untuk pesanan {$order['kode_pesanan']} menunggu konfirmasi", $order_id, "admin/pembayaran.php"]);
        
        $pdo->commit();
        
        $_SESSION['payment_success'] = true;
        header('Location: payment-success.php?order_id=' . $order_id);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Terjadi kesalahan saat memproses pembayaran. Silakan coba lagi.";
    }
}

// Get payment instructions based on method
$payment_instructions = [
    'transfer_bca' => [
        'bank' => 'BCA',
        'account_number' => '1234567890',
        'account_name' => 'PT Luxury Living Indonesia',
        'instructions' => 'Transfer ke rekening BCA di atas. Gunakan kode pesanan sebagai keterangan transfer.'
    ],
    'transfer_mandiri' => [
        'bank' => 'Mandiri',
        'account_number' => '0987654321',
        'account_name' => 'PT Luxury Living Indonesia',
        'instructions' => 'Transfer ke rekening Mandiri di atas. Gunakan kode pesanan sebagai keterangan transfer.'
    ],
    'transfer_bri' => [
        'bank' => 'BRI',
        'account_number' => '1122334455',
        'account_name' => 'PT Luxury Living Indonesia',
        'instructions' => 'Transfer ke rekening BRI di atas. Gunakan kode pesanan sebagai keterangan transfer.'
    ],
    'gopay' => [
        'instructions' => 'Bayar menggunakan GoPay. Scan QR code berikut atau gunakan nomor telepon: 081234567890'
    ],
    'ovo' => [
        'instructions' => 'Bayar menggunakan OVO. Gunakan nomor telepon: 081234567890'
    ],
    'dana' => [
        'instructions' => 'Bayar menggunakan DANA. Gunakan nomor telepon: 081234567890'
    ],
    'cod' => [
        'instructions' => 'Bayar tunai ketika pesanan diterima. Pastikan menyiapkan uang pas.'
    ]
];

$current_instruction = $payment_instructions[$order['metode_pembayaran']] ?? [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - Luxury Living</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .payment-page {
            padding: 120px 0 50px;
            background: var(--light-cream);
            min-height: 100vh;
        }
        
        .payment-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .page-title {
            font-size: 32px;
            color: var(--dark-brown);
            text-align: center;
            margin-bottom: 40px;
        }
        
        .payment-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .payment-header {
            background: linear-gradient(135deg, var(--gold), #e6b567);
            padding: 25px;
            color: white;
            text-align: center;
        }
        
        .order-number {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .payment-amount {
            font-size: 28px;
            font-weight: 700;
        }
        
        .payment-body {
            padding: 30px;
        }
        
        .payment-method {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding: 20px;
            background: var(--light-cream);
            border-radius: var(--radius);
        }
        
        .method-icon {
            width: 50px;
            height: 50px;
            background: var(--cream);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold);
            font-size: 20px;
        }
        
        .method-info {
            flex: 1;
        }
        
        .method-name {
            font-weight: 600;
            color: var(--dark-brown);
            margin-bottom: 5px;
        }
        
        .method-description {
            color: var(--brown);
            font-size: 14px;
        }
        
        .payment-instructions {
            background: var(--light-cream);
            padding: 25px;
            border-radius: var(--radius);
            margin-bottom: 25px;
        }
        
        .instructions-title {
            font-weight: 600;
            color: var(--dark-brown);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .bank-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            padding: 15px;
            background: white;
            border-radius: 8px;
            border: 1px solid var(--cream);
        }
        
        .info-label {
            font-size: 12px;
            color: var(--brown);
            margin-bottom: 5px;
        }
        
        .info-value {
            font-weight: 600;
            color: var(--dark-brown);
        }
        
        .upload-section {
            margin-bottom: 25px;
        }
        
        .upload-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--dark-brown);
        }
        
        .upload-area {
            border: 2px dashed var(--beige);
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .upload-area:hover {
            border-color: var(--gold);
        }
        
        .upload-area i {
            font-size: 48px;
            color: var(--cream);
            margin-bottom: 15px;
        }
        
        .upload-text {
            color: var(--brown);
            margin-bottom: 10px;
        }
        
        .upload-hint {
            font-size: 12px;
            color: var(--brown);
        }
        
        .file-input {
            display: none;
        }
        
        .file-preview {
            margin-top: 15px;
            text-align: center;
        }
        
        .file-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
        }
        
        .btn-process {
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
        }
        
        .btn-process:hover {
            background: var(--dark-brown);
        }
        
        .countdown-timer {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            background: #fff3cd;
            border-radius: 8px;
            color: #856404;
        }
        
        .timer-text {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .timer-display {
            font-size: 18px;
            font-weight: 700;
            color: var(--gold);
        }
        
        .order-summary {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 25px;
        }
        
        .summary-title {
            font-size: 20px;
            color: var(--dark-brown);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--cream);
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            color: var(--brown);
        }
        
        .summary-total {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-brown);
            border-top: 1px solid var(--cream);
            padding-top: 15px;
            margin-top: 15px;
        }

        @media (max-width: 768px) {
            .bank-info {
                grid-template-columns: 1fr;
            }
            
            .payment-method {
                flex-direction: column;
                text-align: center;
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
                    <a href="profile.php" class="btn-login">
                        <i class="fas fa-user"></i> <?php echo $_SESSION['user_nama']; ?>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Payment Page -->
    <section class="payment-page">
        <div class="container">
            <h1 class="page-title">Pembayaran</h1>
            
            <?php if(isset($error_message)): ?>
            <div class="error-message" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #dc3545;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
            <?php endif; ?>
            
            <div class="payment-container">
                <!-- Payment Card -->
                <div class="payment-card">
                    <div class="payment-header">
                        <div class="order-number"><?php echo $order['kode_pesanan']; ?></div>
                        <div class="payment-amount">Rp <?php echo number_format($order['total_harga'], 0, ',', '.'); ?></div>
                    </div>
                    
                    <div class="payment-body">
                        <!-- Payment Method -->
                        <div class="payment-method">
                            <div class="method-icon">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="method-info">
                                <div class="method-name">
                                    <?php echo ucwords(str_replace('_', ' ', $order['metode_pembayaran'])); ?>
                                </div>
                                <div class="method-description">
                                    <?php echo $order['metode_pembayaran'] === 'cod' ? 'Bayar ketika pesanan diterima' : 'Transfer pembayaran'; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Payment Instructions -->
                        <div class="payment-instructions">
                            <h3 class="instructions-title">
                                <i class="fas fa-info-circle"></i>
                                Instruksi Pembayaran
                            </h3>
                            
                            <?php if(isset($current_instruction['bank'])): ?>
                            <div class="bank-info">
                                <div class="info-item">
                                    <div class="info-label">Nama Bank</div>
                                    <div class="info-value"><?php echo $current_instruction['bank']; ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Nomor Rekening</div>
                                    <div class="info-value"><?php echo $current_instruction['account_number']; ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Atas Nama</div>
                                    <div class="info-value"><?php echo $current_instruction['account_name']; ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Jumlah Transfer</div>
                                    <div class="info-value">Rp <?php echo number_format($order['total_harga'], 0, ',', '.'); ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div style="color: var(--brown); line-height: 1.6;">
                                <?php echo $current_instruction['instructions']; ?>
                            </div>
                            
                            <?php if($order['metode_pembayaran'] === 'cod'): ?>
                            <div style="margin-top: 15px; padding: 15px; background: #d4edda; border-radius: 5px; color: #155724;">
                                <i class="fas fa-info-circle"></i> Pastikan Anda akan berada di alamat pengiriman ketika kurir datang.
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Upload Proof (for non-COD) -->
                        <?php if($order['metode_pembayaran'] !== 'cod'): ?>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="upload-section">
                                <label class="upload-label">Upload Bukti Transfer</label>
                                <div class="upload-area" onclick="document.getElementById('bukti_transfer').click()">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <div class="upload-text">Klik untuk upload bukti transfer</div>
                                    <div class="upload-hint">Format: JPG, PNG, PDF (Maks. 2MB)</div>
                                </div>
                                <input type="file" id="bukti_transfer" name="bukti_transfer" class="file-input" 
                                       accept=".jpg,.jpeg,.png,.pdf" onchange="previewFile()">
                                
                                <div class="file-preview" id="filePreview"></div>
                            </div>
                            
                            <button type="submit" name="process_payment" class="btn-process">
                                <i class="fas fa-paper-plane"></i> Konfirmasi Pembayaran
                            </button>
                        </form>
                        <?php else: ?>
                        <form method="POST">
                            <button type="submit" name="process_payment" class="btn-process">
                                <i class="fas fa-check-circle"></i> Konfirmasi Pesanan COD
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <!-- Countdown Timer -->
                        <?php if($order['metode_pembayaran'] !== 'cod'): ?>
                        <div class="countdown-timer">
                            <div class="timer-text">Selesaikan pembayaran dalam:</div>
                            <div class="timer-display" id="countdown">23:59:59</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="order-summary">
                    <h3 class="summary-title">Ringkasan Pesanan</h3>
                    
                    <div class="summary-item">
                        <span>Subtotal</span>
                        <span>Rp <?php echo number_format($order['total_harga'] - $order['ongkir'], 0, ',', '.'); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span>Ongkos Kirim</span>
                        <span>Rp <?php echo number_format($order['ongkir'], 0, ',', '.'); ?></span>
                    </div>
                    
                    <div class="summary-item summary-total">
                        <span>Total</span>
                        <span>Rp <?php echo number_format($order['total_harga'], 0, ',', '.'); ?></span>
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
        // File preview
        function previewFile() {
            const fileInput = document.getElementById('bukti_transfer');
            const filePreview = document.getElementById('filePreview');
            const file = fileInput.files[0];
            
            if (file) {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        filePreview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                    };
                    reader.readAsDataURL(file);
                } else {
                    filePreview.innerHTML = `<div style="padding: 20px; background: var(--cream); border-radius: 8px;">
                        <i class="fas fa-file" style="font-size: 48px; color: var(--gold); margin-bottom: 10px;"></i>
                        <div>${file.name}</div>
                    </div>`;
                }
            }
        }
        
        // Countdown timer
        <?php if($order['metode_pembayaran'] !== 'cod'): ?>
        function startCountdown() {
            const countdownElement = document.getElementById('countdown');
            let timeLeft = 24 * 60 * 60; // 24 hours in seconds
            
            function updateCountdown() {
                const hours = Math.floor(timeLeft / 3600);
                const minutes = Math.floor((timeLeft % 3600) / 60);
                const seconds = timeLeft % 60;
                
                countdownElement.textContent = 
                    `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                if (timeLeft > 0) {
                    timeLeft--;
                    setTimeout(updateCountdown, 1000);
                } else {
                    countdownElement.textContent = "Waktu habis";
                    countdownElement.style.color = "#e74c3c";
                }
            }
            
            updateCountdown();
        }
        
        startCountdown();
        <?php endif; ?>
        
        // Form validation
        document.querySelector('form')?.addEventListener('submit', function(e) {
            <?php if($order['metode_pembayaran'] !== 'cod'): ?>
            const fileInput = document.getElementById('bukti_transfer');
            if (!fileInput.files.length) {
                e.preventDefault();
                alert('Harap upload bukti transfer terlebih dahulu.');
                return;
            }
            
            const file = fileInput.files[0];
            const maxSize = 2 * 1024 * 1024; // 2MB
            if (file.size > maxSize) {
                e.preventDefault();
                alert('Ukuran file terlalu besar. Maksimal 2MB.');
                return;
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>
<?php
require_once 'check_auth.php';

// Ambil data user
$user_data = get_current_user_data();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - Luxury Living</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --cream: #F3E8D7; --beige: #E7D3B8; --gold: #D8A75A; 
            --brown: #6A4F37; --dark-brown: #4a3828; --light-cream: #faf6f0;
            --radius: 10px; --shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: var(--light-cream); color: var(--brown); padding: 20px; }
        
        .container { 
            max-width: 1000px; 
            margin: 0 auto; 
            background: white; 
            padding: 30px; 
            border-radius: var(--radius); 
            box-shadow: var(--shadow); 
        }
        
        .header { 
            display: flex; 
            justify-content: between; 
            align-items: center; 
            margin-bottom: 30px; 
            padding-bottom: 20px; 
            border-bottom: 1px solid var(--cream); 
        }
        
        h1 { color: var(--dark-brown); }
        
        .back-btn { 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            padding: 10px 20px; 
            background: var(--gold); 
            color: white; 
            text-decoration: none; 
            border-radius: 5px; 
            margin-bottom: 20px; 
            transition: background 0.3s;
        }
        .back-btn:hover { background: var(--dark-brown); }
        
        .user-info {
            background: var(--cream);
            padding: 15px;
            border-radius: var(--radius);
            margin-bottom: 20px;
        }
        
        .pesanan-list {
            display: grid;
            gap: 20px;
        }
        
        .pesanan-card {
            border: 1px solid var(--cream);
            border-radius: var(--radius);
            padding: 20px;
            background: white;
        }
        
        .pesanan-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--cream);
        }
        
        .pesanan-id {
            font-weight: bold;
            color: var(--dark-brown);
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-menunggu { background: #fff3cd; color: #856404; }
        .status-diproses { background: #cce7ff; color: #004085; }
        .status-dikirim { background: #d4edda; color: #155724; }
        .status-selesai { background: #e2e3e5; color: #383d41; }
        
        .pesanan-detail {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .detail-item {
            margin-bottom: 8px;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--dark-brown);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--brown);
        }
        
        .empty-state i {
            font-size: 48px;
            color: var(--cream);
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .pesanan-detail {
                grid-template-columns: 1fr;
            }
            
            .pesanan-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
        </a>
        
        <div class="header">
            <h1>Pesanan Saya</h1>
            <div class="user-info">
                <strong>Pelanggan:</strong> <?php echo htmlspecialchars($user_data['nama']); ?> | 
                <strong>Email:</strong> <?php echo htmlspecialchars($user_data['email']); ?>
            </div>
        </div>
        
        <div class="pesanan-list">
            <!-- Contoh data pesanan -->
            <div class="pesanan-card">
                <div class="pesanan-header">
                    <div class="pesanan-id">#ORD-2024-001</div>
                    <span class="status-badge status-diproses">Diproses</span>
                </div>
                <div class="pesanan-detail">
                    <div>
                        <div class="detail-item">
                            <span class="detail-label">Tanggal Pesan:</span> 15 Nov 2024
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Produk:</span> Horden Classic Gold
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Jumlah:</span> 2 set
                        </div>
                    </div>
                    <div>
                        <div class="detail-item">
                            <span class="detail-label">Total Harga:</span> Rp 1.500.000
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Estimasi Selesai:</span> 25 Nov 2024
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Pembayaran:</span> Lunas
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="pesanan-card">
                <div class="pesanan-header">
                    <div class="pesanan-id">#ORD-2024-002</div>
                    <span class="status-badge status-selesai">Selesai</span>
                </div>
                <div class="pesanan-detail">
                    <div>
                        <div class="detail-item">
                            <span class="detail-label">Tanggal Pesan:</span> 10 Nov 2024
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Produk:</span> Horden Modern Silver
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Jumlah:</span> 1 set
                        </div>
                    </div>
                    <div>
                        <div class="detail-item">
                            <span class="detail-label">Total Harga:</span> Rp 850.000
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Tanggal Selesai:</span> 18 Nov 2024
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Pembayaran:</span> Lunas
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Jika tidak ada pesanan -->
            <!--
            <div class="empty-state">
                <i class="fas fa-shopping-cart"></i>
                <h3>Belum Ada Pesanan</h3>
                <p>Anda belum melakukan pemesanan apapun.</p>
                <a href="produk.php" class="back-btn" style="margin-top: 15px;">
                    <i class="fas fa-shopping-bag"></i> Belanja Sekarang
                </a>
            </div>
            -->
        </div>
    </div>
</body>
</html>
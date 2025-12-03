<?php
// switch_login.php - Halaman untuk memilih login type
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Login - Luxury Living</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --cream: #F3E8D7; --beige: #E7D3B8; --gold: #D8A75A; 
            --brown: #6A4F37; --dark-brown: #4a3828; --light-cream: #faf6f0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { 
            background: linear-gradient(135deg, var(--light-cream) 0%, var(--beige) 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center; 
            padding: 20px;
        }
        .container { 
            max-width: 500px; width: 100%; text-align: center; 
        }
        .logo { 
            margin-bottom: 40px; 
        }
        .logo h1 { 
            font-size: 2.5rem; color: var(--brown); font-weight: 700; 
        }
        .logo span { color: var(--gold); }
        .logo p { color: var(--brown); opacity: 0.8; }
        .login-options { 
            display: grid; gap: 20px; 
        }
        .login-card {
            background: white; padding: 30px; border-radius: 15px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-decoration: none;
            color: inherit; transition: all 0.3s ease; border: 2px solid transparent;
        }
        .login-card:hover {
            transform: translateY(-5px); border-color: var(--gold);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .card-icon {
            width: 80px; height: 80px; background: var(--cream); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px; color: var(--gold); font-size: 2rem;
        }
        .card-title { 
            font-size: 1.3rem; font-weight: 600; color: var(--dark-brown);
            margin-bottom: 10px;
        }
        .card-desc {
            color: var(--brown); font-size: 0.9rem; line-height: 1.5;
        }
        .admin-card .card-icon { background: #fff3cd; color: #856404; }
        .user-card .card-icon { background: #d1ecf1; color: #0c5460; }
        .back-home {
            margin-top: 30px;
        }
        .back-home a {
            color: var(--brown); text-decoration: none; display: inline-flex;
            align-items: center; gap: 8px; font-weight: 500;
        }
        .back-home a:hover { color: var(--gold); }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>Luxury<span>Living</span></h1>
            <p>Pilih Jenis Login</p>
        </div>
        
        <div class="login-options">
            <a href="admin/login.php" class="login-card admin-card">
                <div class="card-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="card-title">Login sebagai Admin</div>
                <div class="card-desc">
                    Akses panel administrator untuk mengelola produk, pesanan, dan laporan
                </div>
            </a>
            
            <a href="user/login.php" class="login-card user-card">
                <div class="card-icon">
                    <i class="fas fa-user"></i>
                </div>
                <div class="card-title">Login sebagai Pelanggan</div>
                <div class="card-desc">
                    Akses area member untuk berbelanja, melihat pesanan, dan mengelola profil
                </div>
            </a>
        </div>
        
        <div class="back-home">
            <a href="index.php">
                <i class="fas fa-arrow-left"></i> Kembali ke Beranda
            </a>
        </div>
    </div>
</body>
</html>
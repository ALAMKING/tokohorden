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

$page_title = "Tentang Kami - UD Korden Maju Jaya";

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
            transition: all 0.3s ease;
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
            transition: color 0.3s ease;
        }
        
        .nav-action-btn:hover {
            color: var(--gold);
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
            font-weight: 600;
        }
        
        .btn-login {
            background: var(--gold);
            color: white;
            padding: 8px 20px;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            background: var(--dark-brown);
            transform: translateY(-2px);
        }
        
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--cream) 0%, var(--beige) 100%);
            padding: 80px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="%23ffffff" opacity="0.1"><polygon points="1000,100 1000,0 0,100"/></svg>');
            background-size: cover;
        }
        
        .page-title {
            font-size: 3rem;
            color: var(--dark-brown);
            margin-bottom: 20px;
            font-weight: 700;
            position: relative;
        }
        
        .page-subtitle {
            font-size: 1.2rem;
            color: var(--brown);
            max-width: 600px;
            margin: 0 auto;
            position: relative;
        }
        
        /* Breadcrumb */
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
            padding: 80px 0;
        }
        
        /* Hero Section */
        .hero-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            margin-bottom: 80px;
        }
        
        .hero-content h1 {
            font-size: 2.5rem;
            color: var(--dark-brown);
            margin-bottom: 20px;
            line-height: 1.3;
        }
        
        .hero-content .highlight {
            color: var(--gold);
        }
        
        .hero-content p {
            font-size: 1.1rem;
            color: var(--brown);
            margin-bottom: 30px;
            line-height: 1.8;
        }
        
        .hero-image {
            position: relative;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .hero-image img {
            width: 100%;
            height: 400px;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .hero-image:hover img {
            transform: scale(1.05);
        }
        
        .hero-image::before {
            content: '';
            position: absolute;
            top: -20px;
            left: -20px;
            right: 20px;
            bottom: 20px;
            background: var(--gold);
            border-radius: var(--radius);
            z-index: -1;
            opacity: 0.1;
        }
        
        /* Stats Section */
        .stats-section {
            background: var(--light-cream);
            padding: 80px 0;
            margin: 80px 0;
            border-radius: var(--radius);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
        }
        
        .stat-item {
            text-align: center;
            padding: 30px;
        }
        
        .stat-icon {
            width: 80px;
            height: 80px;
            background: var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 2rem;
            transition: transform 0.3s ease;
        }
        
        .stat-item:hover .stat-icon {
            transform: scale(1.1);
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            color: var(--gold);
            margin-bottom: 10px;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 1.1rem;
            color: var(--dark-brown);
            font-weight: 600;
        }
        
        /* Values Section */
        .values-section {
            margin-bottom: 80px;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }
        
        .section-title {
            font-size: 2.5rem;
            color: var(--dark-brown);
            margin-bottom: 15px;
            font-weight: 700;
        }
        
        .section-subtitle {
            font-size: 1.1rem;
            color: var(--brown);
            max-width: 600px;
            margin: 0 auto;
        }
        
        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .value-card {
            background: white;
            padding: 40px 30px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .value-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(216, 167, 90, 0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .value-card:hover::before {
            left: 100%;
        }
        
        .value-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border-color: var(--gold);
        }
        
        .value-icon {
            width: 70px;
            height: 70px;
            background: var(--cream);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            color: var(--gold);
            font-size: 1.8rem;
            transition: all 0.3s ease;
        }
        
        .value-card:hover .value-icon {
            background: var(--gold);
            color: white;
            transform: rotateY(180deg);
        }
        
        .value-title {
            font-size: 1.5rem;
            color: var(--dark-brown);
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .value-description {
            color: var(--brown);
            line-height: 1.7;
        }
        
        /* Team Section */
        .team-section {
            margin-bottom: 80px;
        }
        
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }
        
        .team-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .team-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .team-image {
            width: 100%;
            height: 250px;
            background: var(--cream);
            overflow: hidden;
            position: relative;
        }
        
        .team-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(transparent 70%, rgba(0,0,0,0.3));
            z-index: 1;
        }
        
        .team-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .team-card:hover .team-image img {
            transform: scale(1.1);
        }
        
        .team-info {
            padding: 30px 20px;
        }
        
        .team-name {
            font-size: 1.3rem;
            color: var(--dark-brown);
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .team-role {
            color: var(--gold);
            font-weight: 500;
            margin-bottom: 15px;
        }
        
        .team-description {
            color: var(--brown);
            font-size: 0.95rem;
            line-height: 1.6;
        }
        
        /* Mission Vision Section */
        .mission-vision {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            margin-bottom: 80px;
        }
        
        .mission-card, .vision-card {
            background: white;
            padding: 50px 40px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .mission-card:hover, .vision-card:hover {
            transform: translateY(-5px);
        }
        
        .mission-card::before, .vision-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
        }
        
        .mission-card {
            border-left: 5px solid var(--gold);
        }
        
        .vision-card {
            border-left: 5px solid var(--dark-brown);
        }
        
        .mission-card::before {
            background: var(--gold);
        }
        
        .vision-card::before {
            background: var(--dark-brown);
        }
        
        .card-title {
            font-size: 1.8rem;
            color: var(--dark-brown);
            margin-bottom: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .card-title i {
            color: var(--gold);
        }
        
        .card-content {
            color: var(--brown);
            line-height: 1.8;
            font-size: 1.05rem;
        }
        
        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, var(--gold) 0%, var(--dark-brown) 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
            border-radius: var(--radius);
            margin-bottom: 80px;
            position: relative;
            overflow: hidden;
        }
        
        .cta-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="%23ffffff" opacity="0.05"><polygon points="0,0 1000,100 0,100"/></svg>');
            background-size: cover;
        }
        
        .cta-title {
            font-size: 2.5rem;
            margin-bottom: 20px;
            font-weight: 700;
            position: relative;
        }
        
        .cta-description {
            font-size: 1.1rem;
            margin-bottom: 30px;
            opacity: 0.9;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            position: relative;
        }
        
        .btn {
            padding: 15px 35px;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            font-size: 1.1rem;
            position: relative;
        }
        
        .btn-primary {
            background: white;
            color: var(--dark-brown);
        }
        
        .btn-primary:hover {
            background: var(--cream);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .btn-outline {
            background: transparent;
            color: white;
            border: 2px solid white;
        }
        
        .btn-outline:hover {
            background: white;
            color: var(--dark-brown);
            transform: translateY(-2px);
        }
        
        /* Timeline Section */
        .timeline-section {
            margin-bottom: 80px;
        }
        
        .timeline {
            position: relative;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 50%;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--gold);
            transform: translateX(-50%);
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 50px;
            width: 50%;
            padding: 0 40px;
        }
        
        .timeline-item:nth-child(odd) {
            left: 0;
        }
        
        .timeline-item:nth-child(even) {
            left: 50%;
        }
        
        .timeline-content {
            background: white;
            padding: 30px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            position: relative;
            transition: transform 0.3s ease;
        }
        
        .timeline-content:hover {
            transform: translateY(-5px);
        }
        
        .timeline-item:nth-child(odd) .timeline-content::before {
            content: '';
            position: absolute;
            right: -10px;
            top: 50%;
            transform: translateY(-50%);
            border-left: 10px solid white;
            border-top: 10px solid transparent;
            border-bottom: 10px solid transparent;
        }
        
        .timeline-item:nth-child(even) .timeline-content::before {
            content: '';
            position: absolute;
            left: -10px;
            top: 50%;
            transform: translateY(-50%);
            border-right: 10px solid white;
            border-top: 10px solid transparent;
            border-bottom: 10px solid transparent;
        }
        
        .timeline-year {
            background: var(--gold);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        .timeline-title {
            font-size: 1.3rem;
            color: var(--dark-brown);
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .timeline-description {
            color: var(--brown);
            line-height: 1.6;
        }
        
        /* Footer */
        .footer {
            background: var(--dark-brown);
            color: var(--cream);
            padding: 50px 0 20px;
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
            position: relative;
            padding-bottom: 10px;
        }
        
        .footer-col h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 2px;
            background: var(--gold);
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
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .footer-links a:hover {
            color: var(--gold);
            transform: translateX(5px);
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
            min-width: 20px;
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: var(--beige);
            font-size: 0.9rem;
        }
        
        /* Responsive */
        @media (max-width: 968px) {
            .hero-section {
                grid-template-columns: 1fr;
                gap: 40px;
            }
            
            .mission-vision {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .timeline::before {
                left: 30px;
            }
            
            .timeline-item {
                width: 100%;
                padding-left: 80px;
                padding-right: 0;
            }
            
            .timeline-item:nth-child(even) {
                left: 0;
            }
            
            .timeline-item:nth-child(odd) .timeline-content::before,
            .timeline-item:nth-child(even) .timeline-content::before {
                left: -10px;
                right: auto;
                border-right: 10px solid white;
                border-left: none;
            }
        }
        
        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-links {
                gap: 20px;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .page-header {
                padding: 60px 0;
            }
            
            .page-title {
                font-size: 2.2rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
            }
            
            .stat-number {
                font-size: 2.5rem;
            }
            
            .cta-title {
                font-size: 2rem;
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
            
            .hero-content h1 {
                font-size: 2rem;
            }
            
            .mission-card, .vision-card {
                padding: 30px 20px;
            }
            
            .btn {
                padding: 12px 25px;
                font-size: 1rem;
            }
            
            .timeline-item {
                padding-left: 60px;
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
                    <li><a href="tentang.php" class="active">Tentang</a></li>
                    <li><a href="kontak.php">Kontak</a></li>
                </ul>
                
                <div class="nav-actions">
                    <a href="keranjang.php" class="nav-action-btn">
                        <i class="fas fa-shopping-cart"></i>
                        <?php if($cart_count > 0): ?>
                            <span class="cart-count"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
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
            <h1 class="page-title">Tentang Luxury Living Nabila Horden</h1>
            <p class="page-subtitle">Melayani kebutuhan korden berkualitas sejak 2013 dengan pengalaman dan keahlian yang terpercaya</p>
        </div>
    </section>

    <!-- Breadcrumb -->
    <section class="breadcrumb">
        <div class="container">
            <ul class="breadcrumb-links">
                <li><a href="index.php">Beranda</a></li>
                <li class="separator"><i class="fas fa-chevron-right"></i></li>
                <li>Tentang Kami</li>
            </ul>
        </div>
    </section>

    <!-- Main Content -->
    <section class="main-content">
        <div class="container">
            <!-- Hero Section -->
            <div class="hero-section">
                <div class="hero-content">
                    <h1>Dari <span class="highlight">Keliling</span> ke <span class="highlight">Toko Tetap</span> dengan Kualitas Terjamin</h1>
                    <p>
                        Toko Horden ini memulai perjalanan sejak tahun 2013 dengan sistem keliling melayani 
                        pelanggan secara langsung. Berawal dari memanfaatkan potensi lokal di Desa Jodhapur yang 
                        banyak pengusaha korden, kami berkembang menjadi toko tetap yang melayani berbagai 
                        kebutuhan korden masyarakat.
                    </p>
                    <p>
                        Dengan pengalaman lebih dari 10 tahun, kami memahami bahwa korden bukan sekadar 
                        pelengkap dekorasi, tetapi investasi untuk kenyamanan dan privasi rumah Anda. 
                        Kami berkomitmen memberikan produk terbaik dengan harga terjangkau.
                    </p>
                </div>
                <div class="hero-image">
                    <img src="https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" 
                         alt="Toko UD Korden Maju Jaya">
                </div>
            </div>

            <!-- Stats Section -->
            <section class="stats-section">
                <div class="container">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="stat-number">11+</div>
                            <div class="stat-label">Tahun Pengalaman</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-smile"></i>
                            </div>
                            <div class="stat-number">2.000+</div>
                            <div class="stat-label">Pelanggan Puas</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-tags"></i>
                            </div>
                            <div class="stat-number">100+</div>
                            <div class="stat-label">Model Tersedia</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="stat-number">10+</div>
                            <div class="stat-label">Kota Terlayani</div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Mission & Vision Section -->
            <section class="mission-vision">
                <div class="mission-card">
                    <h2 class="card-title">
                        <i class="fas fa-bullseye"></i>
                        Misi Kami
                    </h2>
                    <div class="card-content">
                        <p>Menyediakan produk korden berkualitas dengan harga terjangkau untuk semua kalangan 
                           masyarakat. Kami berkomitmen memberikan pelayanan terbaik mulai dari konsultasi, 
                           pemasangan, hingga purna jual.</p>
                        <p style="margin-top: 15px;">Dengan memanfaatkan potensi lokal dan jaringan supplier 
                           terpercaya, kami memastikan setiap produk yang kami hasilkan memenuhi standar 
                           kualitas dan kepuasan pelanggan.</p>
                    </div>
                </div>
                <div class="vision-card">
                    <h2 class="card-title">
                        <i class="fas fa-eye"></i>
                        Visi Kami
                    </h2>
                    <div class="card-content">
                        <p>Menjadi usaha korden terpercaya di Demak dan sekitarnya yang dikenal karena 
                           kualitas produk, pelayanan ramah, dan harga kompetitif. Kami bercita-cita 
                           untuk terus berkembang dengan mengikuti tren terkini sambil mempertahankan 
                           nilai-nilai kekeluargaan.</p>
                        <p style="margin-top: 15px;">Melalui inovasi dan adaptasi teknologi, kami bertekad 
                           memperluas jangkauan layanan hingga ke seluruh Indonesia dengan tetap menjaga 
                           kualitas dan kepercayaan pelanggan.</p>
                    </div>
                </div>
            </section>

            <!-- Values Section -->
            <section class="values-section">
                <div class="section-header">
                    <h2 class="section-title">Nilai-Nilai Kami</h2>
                    <p class="section-subtitle">Prinsip yang menjadi pedoman dalam setiap layanan kami</p>
                </div>
                <div class="values-grid">
                    <div class="value-card">
                        <div class="value-icon">
                            <i class="fas fa-award"></i>
                        </div>
                        <h3 class="value-title">Kualitas Terjamin</h3>
                        <p class="value-description">
                            Setiap produk menggunakan bahan pilihan dari supplier terpercaya dengan proses 
                            jahit yang rapi dan detail.
                        </p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <h3 class="value-title">Harga Terjangkau</h3>
                        <p class="value-description">
                            Menyediakan produk berkualitas dengan harga kompetitif yang sesuai dengan 
                            daya beli masyarakat.
                        </p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="value-title">Pelayanan Ramah</h3>
                        <p class="value-description">
                            Melayani dengan hati dan memperlakukan setiap pelanggan seperti keluarga 
                            sendiri.
                        </p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3 class="value-title">Terpercaya</h3>
                        <p class="value-description">
                            Membangun hubungan jangka panjang dengan pelanggan melalui kejujuran dan 
                            transparansi dalam berbisnis.
                        </p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <h3 class="value-title">Tepat Waktu</h3>
                        <p class="value-description">
                            Menyelesaikan pesanan sesuai deadline yang disepakati dengan menjaga 
                            kualitas hasil kerja.
                        </p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon">
                            <i class="fas fa-hands-helping"></i>
                        </div>
                        <h3 class="value-title">Gotong Royong</h3>
                        <p class="value-description">
                            Bekerja sama dengan tenaga jahit dan pemasang lokal untuk memberdayakan 
                            potensi masyarakat sekitar.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Timeline Section -->
            <section class="timeline-section">
                <div class="section-header">
                    <h2 class="section-title">Perjalanan Kami</h2>
                    <p class="section-subtitle">Cerita perkembangan UD Korden Maju Jaya dari masa ke masa</p>
                </div>
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <div class="timeline-year">2013</div>
                            <h3 class="timeline-title">Awal Berdiri</h3>
                            <p class="timeline-description">
                                Memulai usaha dengan sistem keliling, melayani pelanggan secara langsung 
                                di sekitar Demak dan Kudus. Memanfaatkan potensi lokal di Desa Jeleper.
                            </p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <div class="timeline-year">2017</div>
                            <h3 class="timeline-title">Toko Pertama</h3>
                            <p class="timeline-description">
                                Membuka toko fisik pertama di Pecangaan, menandai transformasi dari 
                                usaha keliling menjadi toko tetap dengan layanan lebih lengkap.
                            </p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <div class="timeline-year">2019</div>
                            <h3 class="timeline-title">Ekspansi Lokasi</h3>
                            <p class="timeline-description">
                                Pindah ke lokasi strategis di Belahan dan kemudian Mijen, memperluas 
                                jangkauan pelayanan dengan tim yang lebih profesional.
                            </p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <div class="timeline-year">2021</div>
                            <h3 class="timeline-title">Go Digital</h3>
                            <p class="timeline-description">
                                Memulai pemasaran digital melalui Facebook dan Instagram, menjangkau 
                                pelanggan hingga luar kota seperti Jepara, Semarang, dan Kalimantan.
                            </p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <div class="timeline-year">2023</div>
                            <h3 class="timeline-title">Toko Tetap</h3>
                            <p class="timeline-description">
                                Menetap di Pasar Kawasan Desa Bakong dengan sistem operasional yang 
                                lebih matang dan jaringan supplier yang semakin luas.
                            </p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <div class="timeline-year">2025</div>
                            <h3 class="timeline-title">Website Resmi</h3>
                            <p class="timeline-description">
                                Meluncurkan website resmi untuk memudahkan pelanggan berbelanja 
                                online dengan pengalaman yang lebih modern dan terpercaya.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Team Section -->
            <section class="team-section">
                <div class="section-header">
                    <h2 class="section-title">Tim Kami</h2>
                    <p class="section-subtitle">Orang-orang berdedikasi di balik layanan UD Korden Maju Jaya</p>
                </div>
                <div class="team-grid">
                    <div class="team-card">
                        <div class="team-image">
                            <img src="https://images.unsplash.com/photo-1560250097-0b93528c311a?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=500&q=80" 
                                 alt="Pemilik Usaha">
                        </div>
                        <div class="team-info">
                            <h3 class="team-name">Bapak Pemilik</h3>
                            <p class="team-role">Pemilik & Marketing</p>
                            <p class="team-description">
                                Memimpin UD Korden Maju Jaya sejak 2013 dengan visi menghadirkan 
                                produk korden berkualitas untuk masyarakat Demak dan sekitarnya.
                            </p>
                        </div>
                    </div>
                    <div class="team-card">
                        <div class="team-image">
                            <img src="https://images.unsplash.com/photo-1586297135537-94bc9ba060aa?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=500&q=80" 
                                 alt="Tenaga Jahit">
                        </div>
                        <div class="team-info">
                            <h3 class="team-name">Tim Jahit</h3>
                            <p class="team-role">Ahli Jahit</p>
                            <p class="team-description">
                                Tenaga profesional yang berpengalaman dalam menjahit berbagai model 
                                korden dengan hasil rapi dan presisi sesuai permintaan pelanggan.
                            </p>
                        </div>
                    </div>
                    <div class="team-card">
                        <div class="team-image">
                            <img src="https://images.unsplash.com/photo-1580489944761-15a19d654956?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=500&q=80" 
                                 alt="Tim Pemasangan">
                        </div>
                        <div class="team-info">
                            <h3 class="team-name">Tim Pemasang</h3>
                            <p class="team-role">Ahli Pemasangan</p>
                            <p class="team-description">
                                Spesialis dalam pemasangan korden yang cepat, rapi, dan aman. 
                                Memastikan setiap produk terpasang dengan sempurna di rumah pelanggan.
                            </p>
                        </div>
                    </div>
                    <div class="team-card">
                        <div class="team-image">
                            <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=500&q=80" 
                                 alt="Supplier">
                        </div>
                        <div class="team-info">
                            <h3 class="team-name">Jaringan Supplier</h3>
                            <p class="team-role">Partner Material</p>
                            <p class="team-description">
                                Menyediakan bahan-bahan korden berkualitas dari Semarang dan 
                                kota lainnya dengan harga kompetitif dan kualitas terjamin.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA Section -->
            <section class="cta-section">
                <h2 class="cta-title">Butuh Korden Berkualitas untuk Rumah Anda?</h2>
                <p class="cta-description">
                    Dapatkan konsultasi gratis dan penawaran spesial untuk korden impian Anda. 
                    Melayani pemesanan custom dengan harga terjangkau dan kualitas terjamin.
                </p>
                <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
                    <a href="produk.php" class="btn btn-primary">
                        <i class="fas fa-shopping-bag"></i>
                        Lihat Koleksi
                    </a>
                    <a href="https://wa.me/6281234567890" class="btn btn-outline">
                        <i class="fab fa-whatsapp"></i>
                        Konsultasi Gratis
                    </a>
                </div>
            </section>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <h3>UD Korden Maju Jaya</h3>
                    <p style="margin-bottom: 20px; color: var(--cream);">
                        Melayani kebutuhan korden berkualitas sejak 2013 dengan pengalaman 
                        dan keahlian yang terpercaya untuk rumah dan kantor Anda.
                    </p>
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
                    <h3>Layanan Kami</h3>
                    <ul class="footer-links">
                        <li><a href="#">Konsultasi Desain</a></li>
                        <li><a href="#">Pemasangan Profesional</a></li>
                        <li><a href="#">Korden Custom</a></li>
                        <li><a href="#">Perawatan Korden</a></li>
                        <li><a href="#">Service & Perbaikan</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h3>Kontak Kami</h3>
                    <ul class="contact-info">
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Pasar Kawasan Desa Bakong, Demak, Jawa Tengah</span>
                        </li>
                        <li>
                            <i class="fas fa-phone-alt"></i>
                            <span>(0291) 123-4567</span>
                        </li>
                        <li>
                            <i class="fab fa-whatsapp"></i>
                            <span>+62 812-3456-7890</span>
                        </li>
                        <li>
                            <i class="fas fa-clock"></i>
                            <span>Senin - Minggu: 08.00 - 17.00</span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 Luxury Living. All rights reserved. | UMKM Demak Berdaya</p>
            </div>
        </div>
    </footer>

    <script>
        // Animasi untuk statistik
        function animateStats() {
            const statNumbers = document.querySelectorAll('.stat-number');
            const statsSection = document.querySelector('.stats-section');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        statNumbers.forEach(stat => {
                            const target = parseInt(stat.textContent);
                            let current = 0;
                            const increment = target / 50;
                            const timer = setInterval(() => {
                                current += increment;
                                if (current >= target) {
                                    stat.textContent = target + '+';
                                    clearInterval(timer);
                                } else {
                                    stat.textContent = Math.floor(current) + '+';
                                }
                            }, 50);
                        });
                        observer.unobserve(statsSection);
                    }
                });
            });
            
            observer.observe(statsSection);
        }
        
        // Inisialisasi animasi ketika halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            animateStats();
            
            // Smooth scroll untuk internal links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
            
            // Animasi fade in untuk cards
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);
            
            // Terapkan animasi untuk value cards dan team cards
            document.querySelectorAll('.value-card, .team-card, .timeline-content, .mission-card, .vision-card').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(el);
            });
        });
        
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const header = document.querySelector('.header');
            if (window.scrollY > 100) {
                header.style.background = 'rgba(255, 255, 255, 0.95)';
                header.style.backdropFilter = 'blur(10px)';
            } else {
                header.style.background = 'white';
                header.style.backdropFilter = 'none';
            }
        });
    </script>
</body>
</html>
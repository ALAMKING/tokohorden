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

$page_title = "Kontak - Luxury Living";

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

// Process contact form
$form_success = false;
$form_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = $_POST['nama'] ?? '';
    $email = $_POST['email'] ?? '';
    $telepon = $_POST['telepon'] ?? '';
    $subjek = $_POST['subjek'] ?? '';
    $pesan = $_POST['pesan'] ?? '';
    
    // Validasi
    if (empty($nama) || empty($email) || empty($subjek) || empty($pesan)) {
        $form_error = "Harap lengkapi semua field yang wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $form_error = "Format email tidak valid.";
    } else {
        try {
            // Simpan ke database
            $stmt = $pdo->prepare("INSERT INTO pesan_kontak (nama, email, telepon, subjek, pesan, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$nama, $email, $telepon, $subjek, $pesan]);
            
            $form_success = true;
            
            // Reset form values
            $nama = $email = $telepon = $subjek = $pesan = '';
            
        } catch (Exception $e) {
            $form_error = "Terjadi kesalahan saat mengirim pesan. Silakan coba lagi.";
            error_log("Error saving contact message: " . $e->getMessage());
        }
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
        
        /* Contact Grid */
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            margin-bottom: 80px;
        }
        
        /* Contact Info */
        .contact-info {
            background: var(--light-cream);
            padding: 50px 40px;
            border-radius: var(--radius);
            height: fit-content;
        }
        
        .contact-info h2 {
            color: var(--dark-brown);
            margin-bottom: 30px;
            font-size: 2rem;
            font-weight: 700;
        }
        
        .contact-info p {
            color: var(--brown);
            margin-bottom: 40px;
            font-size: 1.1rem;
            line-height: 1.8;
        }
        
        .contact-details {
            display: flex;
            flex-direction: column;
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .contact-icon {
            width: 50px;
            height: 50px;
            background: var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .contact-text h4 {
            color: var(--dark-brown);
            margin-bottom: 5px;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .contact-text p {
            color: var(--brown);
            margin: 0;
            font-size: 1rem;
        }
        
        .social-section h3 {
            color: var(--dark-brown);
            margin-bottom: 20px;
            font-size: 1.3rem;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
        }
        
        .social-links a {
            width: 45px;
            height: 45px;
            background: var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 1.1rem;
        }
        
        .social-links a:hover {
            background: var(--dark-brown);
            transform: translateY(-3px);
        }
        
        /* Contact Form */
        .contact-form-container {
            background: white;
            padding: 50px 40px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        
        .contact-form-container h2 {
            color: var(--dark-brown);
            margin-bottom: 30px;
            font-size: 2rem;
            font-weight: 700;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-brown);
            font-weight: 500;
        }
        
        .form-label .required {
            color: #e74c3c;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--cream);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(216, 167, 90, 0.1);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }
        
        .btn-submit {
            background: var(--gold);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: var(--radius);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-submit:hover {
            background: var(--dark-brown);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: var(--radius);
            margin-bottom: 25px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Map Section */
        .map-section {
            margin-bottom: 80px;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 50px;
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
        
        .map-container {
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            height: 400px;
            background: var(--light-cream);
            position: relative;
        }
        
        .map-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--cream) 0%, var(--beige) 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--dark-brown);
        }
        
        .map-placeholder i {
            font-size: 3rem;
            margin-bottom: 20px;
            color: var(--gold);
        }
        
        .map-placeholder h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        
        /* FAQ Section */
        .faq-section {
            margin-bottom: 80px;
        }
        
        .faq-grid {
            display: grid;
            gap: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .faq-item {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .faq-item:hover {
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .faq-question {
            padding: 25px 30px;
            background: var(--light-cream);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s ease;
        }
        
        .faq-question:hover {
            background: var(--cream);
        }
        
        .faq-question h3 {
            color: var(--dark-brown);
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }
        
        .faq-toggle {
            color: var(--gold);
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }
        
        .faq-answer {
            padding: 0 30px;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
            background: white;
        }
        
        .faq-item.active .faq-answer {
            padding: 25px 30px;
            max-height: 500px;
        }
        
        .faq-item.active .faq-toggle {
            transform: rotate(180deg);
        }
        
        .faq-answer p {
            color: var(--brown);
            line-height: 1.7;
            margin: 0;
        }
        
        /* Store Hours */
        .hours-section {
            background: var(--light-cream);
            padding: 60px 0;
            border-radius: var(--radius);
            margin-bottom: 80px;
        }
        
        .hours-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .hours-card {
            background: white;
            padding: 40px 30px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .hours-card:hover {
            transform: translateY(-5px);
        }
        
        .hours-icon {
            width: 70px;
            height: 70px;
            background: var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            margin: 0 auto 25px;
        }
        
        .hours-card h3 {
            color: var(--dark-brown);
            margin-bottom: 20px;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .hours-list {
            list-style: none;
        }
        
        .hours-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--cream);
        }
        
        .hours-list li:last-child {
            border-bottom: none;
        }
        
        .day {
            color: var(--dark-brown);
            font-weight: 500;
        }
        
        .time {
            color: var(--gold);
            font-weight: 600;
        }
        
        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, var(--gold) 0%, var(--dark-brown) 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
            border-radius: var(--radius);
        }
        
        .cta-title {
            font-size: 2.5rem;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .cta-description {
            font-size: 1.1rem;
            margin-bottom: 30px;
            opacity: 0.9;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
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
        
        /* Footer */
        .footer {
            background: var(--dark-brown);
            color: var(--cream);
            padding: 50px 0 20px;
            margin-top: 80px;
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
        
        .social-links-footer {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .social-links-footer a {
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
        
        .social-links-footer a:hover {
            background: var(--gold);
            transform: translateY(-2px);
        }
        
        .contact-info-footer {
            list-style: none;
        }
        
        .contact-info-footer li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .contact-info-footer i {
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
            .contact-grid {
                grid-template-columns: 1fr;
                gap: 40px;
            }
            
            .hours-grid {
                grid-template-columns: 1fr;
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
            
            .contact-info, .contact-form-container {
                padding: 30px 25px;
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
            
            .contact-info, .contact-form-container {
                padding: 25px 20px;
            }
            
            .btn {
                padding: 12px 25px;
                font-size: 1rem;
            }
            
            .faq-question {
                padding: 20px 25px;
            }
            
            .faq-question h3 {
                font-size: 1.1rem;
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
                    <li><a href="kontak.php" class="active">Kontak</a></li>
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
            <h1 class="page-title">Hubungi Kami</h1>
            <p class="page-subtitle">Kami siap membantu Anda menemukan solusi korden terbaik untuk hunian impian</p>
        </div>
    </section>

    <!-- Breadcrumb -->
    <section class="breadcrumb">
        <div class="container">
            <ul class="breadcrumb-links">
                <li><a href="index.php">Beranda</a></li>
                <li class="separator"><i class="fas fa-chevron-right"></i></li>
                <li>Kontak</li>
            </ul>
        </div>
    </section>

    <!-- Main Content -->
    <section class="main-content">
        <div class="container">
            <!-- Contact Grid -->
            <div class="contact-grid">
                <!-- Contact Info -->
                <div class="contact-info">
                    <h2>Informasi Kontak</h2>
                    <p>
                        Tim customer service kami siap membantu Anda dengan senang hati. 
                        Jangan ragu untuk menghubungi kami melalui berbagai channel yang tersedia.
                    </p>
                    
                    <div class="contact-details">
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="contact-text">
                                <h4>Alamat Showroom</h4>
                                <p>Jl. Kemang Raya No. 12<br>Jakarta Selatan 12730</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-phone-alt"></i>
                            </div>
                            <div class="contact-text">
                                <h4>Telepon</h4>
                                <p>(021) 1234-5678<br>0812-3456-7890</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="contact-text">
                                <h4>Email</h4>
                                <p>info@luxuryliving.co.id<br>cs@luxuryliving.co.id</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="contact-text">
                                <h4>Jam Operasional</h4>
                                <p>Senin - Sabtu: 09.00 - 17.00<br>Minggu: Tutup</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="social-section">
                        <h3>Ikuti Kami</h3>
                        <div class="social-links">
                            <a href="#"><i class="fab fa-facebook-f"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                            <a href="#"><i class="fab fa-pinterest"></i></a>
                            <a href="#"><i class="fab fa-youtube"></i></a>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Form -->
                <div class="contact-form-container">
                    <h2>Kirim Pesan</h2>
                    
                    <?php if($form_success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Terima kasih! Pesan Anda telah berhasil dikirim. Tim kami akan segera merespons.
                        </div>
                    <?php elseif($form_error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $form_error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">
                                Nama Lengkap <span class="required">*</span>
                            </label>
                            <input type="text" name="nama" class="form-control" 
                                   value="<?php echo htmlspecialchars($nama ?? ''); ?>" 
                                   required placeholder="Masukkan nama lengkap Anda">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                Email <span class="required">*</span>
                            </label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                                   required placeholder="nama@email.com">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Nomor Telepon</label>
                            <input type="tel" name="telepon" class="form-control" 
                                   value="<?php echo htmlspecialchars($telepon ?? ''); ?>" 
                                   placeholder="0812-3456-7890">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                Subjek <span class="required">*</span>
                            </label>
                            <select name="subjek" class="form-control" required>
                                <option value="">Pilih subjek pesan</option>
                                <option value="Konsultasi Produk" <?php echo ($subjek ?? '') === 'Konsultasi Produk' ? 'selected' : ''; ?>>Konsultasi Produk</option>
                                <option value="Pemasangan" <?php echo ($subjek ?? '') === 'Pemasangan' ? 'selected' : ''; ?>>Pemasangan</option>
                                <option value="Garansi" <?php echo ($subjek ?? '') === 'Garansi' ? 'selected' : ''; ?>>Garansi</option>
                                <option value="Keluhan" <?php echo ($subjek ?? '') === 'Keluhan' ? 'selected' : ''; ?>>Keluhan</option>
                                <option value="Lainnya" <?php echo ($subjek ?? '') === 'Lainnya' ? 'selected' : ''; ?>>Lainnya</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                Pesan <span class="required">*</span>
                            </label>
                            <textarea name="pesan" class="form-control" 
                                      required placeholder="Tulis pesan Anda di sini..."><?php echo htmlspecialchars($pesan ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i>
                            Kirim Pesan
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Map Section -->
            <section class="map-section">
                <div class="section-header">
                    <h2 class="section-title">Lokasi Kami</h2>
                    <p class="section-subtitle">Kunjungi showroom kami untuk pengalaman berbelanja yang lebih personal</p>
                </div>
                
                <div class="map-container">
                    <div class="map-placeholder">
                        <i class="fas fa-map-marked-alt"></i>
                        <h3>Peta Lokasi Showroom</h3>
                        <p>Jl. Kemang Raya No. 12, Jakarta Selatan</p>
                        <small style="margin-top: 10px; opacity: 0.8;">Integrasi Google Maps dapat ditambahkan di sini</small>
                    </div>
                </div>
            </section>
            
            <!-- Store Hours -->
            <section class="hours-section">
                <div class="container">
                    <div class="hours-grid">
                        <div class="hours-card">
                            <div class="hours-icon">
                                <i class="fas fa-store"></i>
                            </div>
                            <h3>Jam Operasional Showroom</h3>
                            <ul class="hours-list">
                                <li><span class="day">Senin - Jumat</span><span class="time">09.00 - 17.00</span></li>
                                <li><span class="day">Sabtu</span><span class="time">09.00 - 15.00</span></li>
                                <li><span class="day">Minggu</span><span class="time">Tutup</span></li>
                            </ul>
                        </div>
                        
                        <div class="hours-card">
                            <div class="hours-icon">
                                <i class="fas fa-headset"></i>
                            </div>
                            <h3>Layanan Customer Service</h3>
                            <ul class="hours-list">
                                <li><span class="day">Senin - Jumat</span><span class="time">08.00 - 18.00</span></li>
                                <li><span class="day">Sabtu</span><span class="time">08.00 - 16.00</span></li>
                                <li><span class="day">Minggu</span><span class="time">10.00 - 14.00</span></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- FAQ Section -->
            <section class="faq-section">
                <div class="section-header">
                    <h2 class="section-title">Pertanyaan Umum</h2>
                    <p class="section-subtitle">Temukan jawaban untuk pertanyaan yang sering diajukan</p>
                </div>
                
                <div class="faq-grid">
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>Bagaimana cara mengukur jendela untuk korden?</h3>
                            <span class="faq-toggle"><i class="fas fa-chevron-down"></i></span>
                        </div>
                        <div class="faq-answer">
                            <p>Untuk mengukur jendela, gunakan pita pengukur dan ukur lebar serta tinggi jendela. Tambahkan 15-20 cm pada lebar untuk memberikan kesan lebih penuh, dan untuk tinggi, sesuaikan dengan preferensi Anda (panjang lantai, hingga ambang jendela, atau di antara keduanya).</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>Apakah tersedia jasa pemasangan?</h3>
                            <span class="faq-toggle"><i class="fas fa-chevron-down"></i></span>
                        </div>
                        <div class="faq-answer">
                            <p>Ya, kami menyediakan jasa pemasangan profesional untuk area Jakarta dan sekitarnya. Biaya pemasangan bervariasi tergantung kompleksitas dan jumlah jendela. Tim pemasangan kami akan datang ke lokasi Anda sesuai janji temu yang telah disepakati.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>Berapa lama waktu pengiriman?</h3>
                            <span class="faq-toggle"><i class="fas fa-chevron-down"></i></span>
                        </div>
                        <div class="faq-answer">
                            <p>Untuk produk ready stock, pengiriman dilakukan dalam 1-3 hari kerja. Untuk produk custom, waktu produksi bervariasi antara 7-14 hari kerja tergantung kompleksitas desain. Kami akan memberikan update yang jelas mengenai progres pesanan Anda.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>Apakah produk korden bisa dicuci?</h3>
                            <span class="faq-toggle"><i class="fas fa-chevron-down"></i></span>
                        </div>
                        <div class="faq-answer">
                            <p>Ya, sebagian besar produk korden kami dapat dicuci. Namun, metode pencucian tergantung pada bahan korden. Beberapa dapat dicuci mesin dengan air dingin, sementara yang lain memerlukan dry cleaning. Petunjuk perawatan spesifik akan diberikan saat pembelian.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>Bagaimana cara perawatan korden yang benar?</h3>
                            <span class="faq-toggle"><i class="fas fa-chevron-down"></i></span>
                        </div>
                        <div class="faq-answer">
                            <p>Perawatan rutin meliputi vacuuming dengan alat pelapis lembut untuk menghilangkan debu, menghindari paparan sinar matahari langsung yang berlebihan, dan membersihkan noda segera dengan metode yang sesuai. Untuk pencucian mendalam, ikuti petunjuk perawatan yang diberikan.</p>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- CTA Section -->
            <section class="cta-section">
                <h2 class="cta-title">Butuh Bantuan Cepat?</h2>
                <p class="cta-description">
                    Hubungi customer service kami sekarang untuk konsultasi gratis dan penawaran terbaik
                </p>
                <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
                    <a href="https://wa.me/6281234567890" class="btn btn-primary" target="_blank">
                        <i class="fab fa-whatsapp"></i>
                        Chat WhatsApp
                    </a>
                    <a href="tel:02112345678" class="btn btn-outline">
                        <i class="fas fa-phone-alt"></i>
                        Telepon Sekarang
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
                    <h3>Luxury Living</h3>
                    <p style="margin-bottom: 20px; color: var(--cream);">
                        Menghadirkan keanggunan dan kenyamanan melalui koleksi korden premium untuk hunian impian Anda sejak 2010.
                    </p>
                    <div class="social-links-footer">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-pinterest"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
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
                    <h3>Layanan</h3>
                    <ul class="footer-links">
                        <li><a href="#">Konsultasi Desain</a></li>
                        <li><a href="#">Pemasangan Profesional</a></li>
                        <li><a href="#">Custom Desain</a></li>
                        <li><a href="#">Perawatan Produk</a></li>
                        <li><a href="#">Garansi Produk</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h3>Kontak Kami</h3>
                    <ul class="contact-info-footer">
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Jl. Kemang Raya No. 12, Jakarta Selatan 12730</span>
                        </li>
                        <li>
                            <i class="fas fa-phone-alt"></i>
                            <span>(021) 1234-5678</span>
                        </li>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <span>info@luxuryliving.co.id</span>
                        </li>
                        <li>
                            <i class="fas fa-clock"></i>
                            <span>Senin - Sabtu: 09.00 - 17.00</span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 Luxury Living. All rights reserved. | Designed with <i class="fas fa-heart" style="color: var(--gold);"></i> for Your Comfort</p>
            </div>
        </div>
    </footer>

    <script>
        // FAQ Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const faqItems = document.querySelectorAll('.faq-item');
            
            faqItems.forEach(item => {
                const question = item.querySelector('.faq-question');
                
                question.addEventListener('click', () => {
                    // Close all other items
                    faqItems.forEach(otherItem => {
                        if (otherItem !== item) {
                            otherItem.classList.remove('active');
                        }
                    });
                    
                    // Toggle current item
                    item.classList.toggle('active');
                });
            });
            
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
            
            // Terapkan animasi untuk berbagai elemen
            document.querySelectorAll('.contact-item, .hours-card, .faq-item').forEach(el => {
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
        
        // Form validation enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const requiredFields = form.querySelectorAll('[required]');
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
                        alert('Harap lengkapi semua field yang wajib diisi.');
                    }
                });
            }
        });
    </script>
</body>
</html>
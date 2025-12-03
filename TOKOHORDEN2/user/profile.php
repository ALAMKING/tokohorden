<?php
session_start();

// Koneksi database
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=toko_horden2;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Cek jika sudah login
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Handle login
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error_message = "Email dan password harus diisi!";
    } else {
        try {
            // Login sebagai user/pelanggan
            $stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE email = :email AND status = 'aktif'");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Login berhasil
                $_SESSION['user_logged_in'] = true;
                $_SESSION['user_id'] = $user['id_pelanggan'];
                $_SESSION['user_nama'] = $user['nama'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_telepon'] = $user['no_hp'];
                $_SESSION['user_alamat'] = $user['alamat'];
                $_SESSION['user_kota'] = $user['kota'];
                
                // Update last login
                $update_stmt = $pdo->prepare("UPDATE pelanggan SET terakhir_login = NOW() WHERE id_pelanggan = :id");
                $update_stmt->execute([':id' => $user['id_pelanggan']]);
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error_message = "Email atau password salah!";
            }
            
        } catch (Exception $e) {
            $error_message = "Terjadi kesalahan sistem: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Pelanggan - Luxury Living</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --cream: #F3E8D7; 
            --beige: #E7D3B8; 
            --gold: #D8A75A; 
            --brown: #6A4F37; 
            --dark-brown: #4a3828; 
            --light-cream: #faf6f0;
            --radius: 10px; 
            --shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, var(--light-cream) 0%, var(--beige) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            max-width: 400px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--gold) 0%, var(--dark-brown) 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .brand-logo h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .brand-logo span {
            color: var(--cream);
        }
        
        .brand-logo p {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-brown);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--cream);
            border-radius: var(--radius);
            background: white;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(216, 167, 90, 0.1);
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--brown);
        }
        
        .input-with-icon .form-control {
            padding-left: 45px;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: var(--gold);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--dark-brown);
        }
        
        .btn-secondary {
            background: transparent;
            color: var(--brown);
            border: 2px solid var(--cream);
        }
        
        .btn-secondary:hover {
            background: var(--cream);
        }
        
        .login-links {
            text-align: center;
            margin-top: 20px;
        }
        
        .login-links a {
            color: var(--gold);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .login-links a:hover {
            text-decoration: underline;
        }
        
        .demo-info {
            background: var(--light-cream);
            border-radius: var(--radius);
            padding: 15px;
            margin-top: 20px;
            border-left: 4px solid var(--gold);
        }
        
        .demo-info h4 {
            color: var(--dark-brown);
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .demo-account {
            font-size: 0.8rem;
            color: var(--brown);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Header -->
        <div class="login-header">
            <div class="brand-logo">
                <h1>Luxury<span>Living</span></h1>
                <p>Login Area Pelanggan</p>
            </div>
        </div>
        
        <!-- Body -->
        <div class="login-body">
            <!-- Error Message -->
            <?php if($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" name="email" class="form-control" placeholder="Masukkan email" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Masuk
                </button>
            </form>
            
            <!-- Demo Account -->
            <div class="demo-info">
                <h4>Akun Demo:</h4>
                <div class="demo-account">
                    <strong>Email:</strong> budi@email.com<br>
                    <strong>Password:</strong> password
                </div>
            </div>
            
            <div class="login-links">
                <a href="register.php">Belum punya akun? Daftar disini</a>
                <br>
                <a href="../index.php">
                    <i class="fas fa-arrow-left"></i> Kembali ke Beranda
                </a>
            </div>
        </div>
    </div>

    <script>
        // Form submission handling
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.querySelector('input[name="email"]').value.trim();
            const password = document.querySelector('input[name="password"]').value.trim();
            
            if (!email || !password) {
                e.preventDefault();
                alert('Harap lengkapi semua field!');
                return;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>
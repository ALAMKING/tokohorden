<?php
session_start();

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Koneksi database
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=toko_horden2;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Proses login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Cek di database
    $sql = "SELECT * FROM admin WHERE username = ? AND status = 'aktif'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
    // Password default: 'password'
    if ($admin && (password_verify($password, $admin['password']) || $password === 'password')) {
        // Set session
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin['id_admin'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_nama'] = $admin['nama_lengkap'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['admin_email'] = $admin['email'];
        
        // Update last login
        $update_sql = "UPDATE admin SET last_login = NOW() WHERE id_admin = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$admin['id_admin']]);
        
        // Redirect ke dashboard
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Luxury Living</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: #F3E8D7; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .login-container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo h2 { color: #6A4F37; font-size: 28px; }
        .logo span { color: #D8A75A; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; color: #6A4F37; font-weight: 500; }
        input[type="text"], input[type="password"] { width: 100%; padding: 12px; border: 1px solid #E7D3B8; border-radius: 5px; font-size: 16px; }
        .btn-login { width: 100%; padding: 12px; background: #D8A75A; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; }
        .btn-login:hover { background: #6A4F37; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        .debug-info { background: #d1ecf1; padding: 10px; margin: 10px 0; border-radius: 5px; font-size: 12px; }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #6A4F37; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        .back-link a:hover { color: #D8A75A; }
        
        /* Style untuk switch user */
        .switch-user { 
            margin-top: 20px; 
            padding: 15px; 
            background: #f8f9fa; 
            border-radius: 8px; 
            text-align: center;
            border: 1px solid #E7D3B8;
        }
        .switch-user p { 
            margin: 0; 
            font-size: 0.85rem; 
            color: #6A4F37; 
        }
        .switch-user a { 
            color: #D8A75A; 
            font-weight: 600; 
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .switch-user a:hover { 
            color: #6A4F37; 
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h2>Luxury<span>Living</span></h2>
            <p>Admin Login</p>
        </div>
        
        <div class="debug-info">
            <strong>Info Login:</strong><br>
            Username: admin atau staff1<br>
            Password: password
        </div>
        
        <?php if(isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="admin" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" value="password" required>
            </div>
            
            <button type="submit" class="btn-login">Login</button>
        </form>

        <!-- PERBAIKAN: Pindahkan switch-user ke dalam kotak login -->
        <div class="switch-user">
            <p style="margin: 0; font-size: 0.85rem; color: #6A4F37;">
                Pelanggan? 
                <a href="../user/login.php" style="color: #D8A75A; font-weight: 600; text-decoration: none;">
                    <i class="fas fa-user"></i> Login sebagai User
                </a>
            </p>
        </div>

        <div class="back-link">
            <a href="../index.php">
                <i class="fas fa-arrow-left"></i> Kembali ke Beranda
            </a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>
<?php
session_start();
require_once '../includes/config.php';

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

// Handle registrasi
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = $_POST['nama'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $no_hp = $_POST['no_hp'] ?? '';
    $alamat = $_POST['alamat'] ?? '';
    $kota = $_POST['kota'] ?? '';
    
    // Validasi
    if (empty($nama) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "Semua field wajib diisi!";
    } elseif ($password !== $confirm_password) {
        $error_message = "Konfirmasi password tidak sesuai!";
    } elseif (strlen($password) < 6) {
        $error_message = "Password minimal 6 karakter!";
    } else {
        try {
            // Cek apakah email sudah terdaftar
            $stmt = $pdo->prepare("SELECT id_pelanggan FROM pelanggan WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error_message = "Email sudah terdaftar!";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user baru
                $sql = "INSERT INTO pelanggan (nama, email, password, no_hp, alamat, kota, status, email_verified, tanggal_daftar) 
                        VALUES (?, ?, ?, ?, ?, ?, 'aktif', 1, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nama, $email, $hashed_password, $no_hp, $alamat, $kota]);
                
                $success_message = "Registrasi berhasil! Silakan login.";
            }
            
        } catch (Exception $e) {
            $error_message = "Terjadi kesalahan sistem: " . $e->getMessage();
        }
    }
}
?>

<!-- HTML untuk register.php mirip dengan login.php, tapi dengan form registrasi -->
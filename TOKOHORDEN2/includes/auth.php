<?php
// auth.php - Fungsi autentikasi

/**
 * Cek login admin
 */
function check_admin_auth() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Cek role admin
 */
function check_admin_role($required_role) {
    if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== $required_role) {
        header('Location: unauthorized.php');
        exit;
    }
}

/**
 * Login admin
 */
function admin_login($pdo, $username, $password) {
    $sql = "SELECT * FROM admin WHERE username = ? AND status = 'aktif'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
    if ($admin && password_verify($password, $admin['password'])) {
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
        
        return true;
    }
    
    return false;
}

/**
 * Logout admin
 */
function admin_logout() {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
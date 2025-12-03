<?php
// Cek jika session belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple debug - HAPUS INI DI PRODUCTION
// error_log("Debug Auth - User logged in: " . ($_SESSION['user_logged_in'] ?? 'false'));

// Cek authentication
function check_user_auth() {
    if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Get current user data
 */
function get_current_user_data() {
    if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
        return [
            'id' => $_SESSION['user_id'],
            'nama' => $_SESSION['user_nama'],
            'email' => $_SESSION['user_email'],
            'telepon' => $_SESSION['user_telepon'],
            'alamat' => $_SESSION['user_alamat'],
            'kota' => $_SESSION['user_kota']
        ];
    }
    return null;
}

/**
 * Logout user
 */
function user_logout() {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
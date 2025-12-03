<?php
// functions.php - Kumpulan fungsi helper untuk sistem

/**
 * Format angka ke Rupiah
 */
function format_rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

/**
 * Format tanggal Indonesia
 */
function format_tanggal($tanggal) {
    $bulan = array(
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    
    $timestamp = strtotime($tanggal);
    return date('d', $timestamp) . ' ' . $bulan[(date('n', $timestamp) - 1)] . ' ' . date('Y', $timestamp);
}

/**
 * Generate kode unik untuk pesanan
 */
function generate_kode_pesanan() {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Generate kode unik untuk produk
 */
function generate_kode_produk() {
    return 'KRD-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Validasi email
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validasi nomor HP Indonesia
 */
function validate_phone($phone) {
    return preg_match('/^08[1-9][0-9]{7,10}$/', $phone);
}

/**
 * Upload file dengan validasi
 */
function upload_file($file, $target_dir, $allowed_types = ['jpg', 'jpeg', 'png', 'gif']) {
    $filename = uniqid() . '_' . basename($file["name"]);
    $target_file = $target_dir . $filename;
    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Validasi tipe file
    if (!in_array($file_type, $allowed_types)) {
        return ['error' => 'Tipe file tidak diizinkan'];
    }
    
    // Validasi ukuran file (max 2MB)
    if ($file["size"] > 2000000) {
        return ['error' => 'Ukuran file terlalu besar (max 2MB)'];
    }
    
    // Upload file
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ['success' => true, 'filename' => $filename];
    } else {
        return ['error' => 'Gagal mengupload file'];
    }
}

/**
 * Hapus file
 */
function delete_file($file_path) {
    if (file_exists($file_path) && is_file($file_path)) {
        return unlink($file_path);
    }
    return false;
}

/**
 * Redirect dengan pesan flash
 */
function redirect_with_message($url, $type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
    header("Location: $url");
    exit;
}

/**
 * Tampilkan pesan flash
 */
function display_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $alert_class = '';
        
        switch ($message['type']) {
            case 'success':
                $alert_class = 'alert-success';
                break;
            case 'error':
                $alert_class = 'alert-danger';
                break;
            case 'warning':
                $alert_class = 'alert-warning';
                break;
            case 'info':
                $alert_class = 'alert-info';
                break;
        }
        
        echo '<div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">
                ' . $message['message'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        
        unset($_SESSION['flash_message']);
    }
}

/**
 * Cek apakah user sudah login
 */
function check_admin_login() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Log activity
 */
function log_activity($pdo, $admin_id, $action, $description) {
    $sql = "INSERT INTO activity_log (admin_id, action, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $admin_id,
        $action,
        $description,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
}

/**
 * Get status badge class
 */
function get_status_badge($status) {
    $status_map = [
        'Menunggu Pembayaran' => 'status-menunggu',
        'Diproses' => 'status-diproses',
        'Dikirim' => 'status-dikirim',
        'Selesai' => 'status-selesai',
        'Dibatalkan' => 'status-dibatalkan',
        'Menunggu' => 'status-menunggu',
        'Lunas' => 'status-selesai',
        'Gagal' => 'status-dibatalkan',
        'Disetujui' => 'status-selesai',
        'Ditolak' => 'status-dibatalkan'
    ];
    
    return $status_map[$status] ?? 'status-menunggu';
}

/**
 * Sanitize input data
 */
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Get pagination data
 */
function get_pagination($pdo, $table, $where = '', $params = [], $per_page = 10) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $per_page;
    
    // Total records
    $sql_count = "SELECT COUNT(*) as total FROM $table $where";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetch()['total'];
    
    // Total pages
    $total_pages = ceil($total_records / $per_page);
    
    return [
        'page' => $page,
        'per_page' => $per_page,
        'offset' => $offset,
        'total_records' => $total_records,
        'total_pages' => $total_pages
    ];
}
?>
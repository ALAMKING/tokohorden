<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'add':
        $product_id = $_POST['product_id'] ?? 0;
        $quantity = $_POST['quantity'] ?? 1;
        
        // Validate product
        $product_stmt = $pdo->prepare("SELECT * FROM produk WHERE id_produk = ? AND status = 'Tersedia'");
        $product_stmt->execute([$product_id]);
        $product = $product_stmt->fetch();
        
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Produk tidak tersedia']);
            exit;
        }
        
        // Check stock
        if ($product['stok'] < $quantity) {
            echo json_encode(['success' => false, 'message' => 'Stok tidak mencukupi']);
            exit;
        }
        
        // Check if already in cart
        $cart_stmt = $pdo->prepare("SELECT * FROM keranjang WHERE id_pelanggan = ? AND id_produk = ?");
        $cart_stmt->execute([$user_id, $product_id]);
        $existing_item = $cart_stmt->fetch();
        
        if ($existing_item) {
            $new_quantity = $existing_item['jumlah'] + $quantity;
            if ($new_quantity > $product['stok']) {
                echo json_encode(['success' => false, 'message' => 'Stok tidak mencukupi']);
                exit;
            }
            
            $update_stmt = $pdo->prepare("UPDATE keranjang SET jumlah = ? WHERE id_keranjang = ?");
            $update_stmt->execute([$new_quantity, $existing_item['id_keranjang']]);
        } else {
            $insert_stmt = $pdo->prepare("INSERT INTO keranjang (id_pelanggan, id_produk, jumlah) VALUES (?, ?, ?)");
            $insert_stmt->execute([$user_id, $product_id, $quantity]);
        }
        
        // Get updated cart count
        $count_stmt = $pdo->prepare("SELECT SUM(jumlah) as total FROM keranjang WHERE id_pelanggan = ?");
        $count_stmt->execute([$user_id]);
        $cart_count = $count_stmt->fetch()['total'] ?? 0;
        
        echo json_encode(['success' => true, 'cart_count' => $cart_count]);
        break;
        
    case 'update':
        $cart_id = $_POST['cart_id'] ?? 0;
        $quantity = $_POST['quantity'] ?? 1;
        
        // Validate cart item
        $cart_stmt = $pdo->prepare("SELECT k.*, p.stok FROM keranjang k 
                                  JOIN produk p ON k.id_produk = p.id_produk 
                                  WHERE k.id_keranjang = ? AND k.id_pelanggan = ?");
        $cart_stmt->execute([$cart_id, $user_id]);
        $cart_item = $cart_stmt->fetch();
        
        if (!$cart_item) {
            echo json_encode(['success' => false, 'message' => 'Item tidak ditemukan']);
            exit;
        }
        
        if ($quantity > $cart_item['stok']) {
            echo json_encode(['success' => false, 'message' => 'Stok tidak mencukupi']);
            exit;
        }
        
        if ($quantity <= 0) {
            // Remove item
            $delete_stmt = $pdo->prepare("DELETE FROM keranjang WHERE id_keranjang = ?");
            $delete_stmt->execute([$cart_id]);
        } else {
            // Update quantity
            $update_stmt = $pdo->prepare("UPDATE keranjang SET jumlah = ? WHERE id_keranjang = ?");
            $update_stmt->execute([$quantity, $cart_id]);
        }
        
        // Get updated totals
        $totals_stmt = $pdo->prepare("SELECT 
            SUM(k.jumlah) as cart_count,
            SUM(k.jumlah * COALESCE(p.harga_diskon, p.harga)) as subtotal
            FROM keranjang k 
            JOIN produk p ON k.id_produk = p.id_produk 
            WHERE k.id_pelanggan = ?");
        $totals_stmt->execute([$user_id]);
        $totals = $totals_stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'cart_count' => $totals['cart_count'] ?? 0,
            'subtotal' => $totals['subtotal'] ?? 0
        ]);
        break;
        
    case 'remove':
        $cart_id = $_POST['cart_id'] ?? 0;
        
        $delete_stmt = $pdo->prepare("DELETE FROM keranjang WHERE id_keranjang = ? AND id_pelanggan = ?");
        $delete_stmt->execute([$cart_id, $user_id]);
        
        // Get updated cart count
        $count_stmt = $pdo->prepare("SELECT SUM(jumlah) as total FROM keranjang WHERE id_pelanggan = ?");
        $count_stmt->execute([$user_id]);
        $cart_count = $count_stmt->fetch()['total'] ?? 0;
        
        echo json_encode(['success' => true, 'cart_count' => $cart_count]);
        break;
        
    case 'get_count':
        $count_stmt = $pdo->prepare("SELECT SUM(jumlah) as total FROM keranjang WHERE id_pelanggan = ?");
        $count_stmt->execute([$user_id]);
        $cart_count = $count_stmt->fetch()['total'] ?? 0;
        
        echo json_encode(['success' => true, 'cart_count' => $cart_count]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
}
?>
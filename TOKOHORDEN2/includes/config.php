<?php
// database.php - Konfigurasi database

class Database {
    private $host = '127.0.0.1';
    private $db_name = 'toko_horden2';
    private $username = 'root';
    private $password = '';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8", 
                $this->username, 
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            echo "Koneksi database gagal: " . $e->getMessage();
        }
        return $this->conn;
    }
}

// Buat koneksi global
$database = new Database();
$pdo = $database->getConnection();
?>
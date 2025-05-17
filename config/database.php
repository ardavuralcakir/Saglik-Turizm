<?php
class Database {
    private $host = "localhost";
    private $db_name = "health_tourism";
    private $username = "root";
    private $password = "";
    public $conn;

    // Veritabanı bağlantısını al
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            // Hata mesajını JSON formatında döndür
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => "Database connection error: " . $exception->getMessage()
            ]));
        }

        return $this->conn;
    }
}
?>
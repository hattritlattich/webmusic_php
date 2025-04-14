<?php
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }

        // Đặt charset UTF-8 để hỗ trợ tiếng Việt và ký tự đặc biệt
        $this->conn->set_charset("utf8mb4");
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    // Đóng kết nối (có thể không cần thiết do kết nối tự động đóng khi script kết thúc)
    public function closeConnection() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>

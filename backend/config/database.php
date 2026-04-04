<?php
/**
 * Konfiguracja połączenia z bazą danych MySQL
 */

class Database {
    private $host = "localhost";
    private $db_name = "crypto_tracker_db";
    private $username = "root";
    private $password = "";
    private $charset = "utf8mb4";
    public $conn;

    /**
     * Nawiązuje połączenie z bazą danych
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch(PDOException $e) {
            echo "Connection Error: " . $e->getMessage();
        }

        return $this->conn;
    }
}

<?php
class Database
{
    private $host = 'localhost';
    private $dbname = 'boarding_house_db';
    private $username = 'root';
    private $password = '';
    public $conn;

    public function __construct()
    {
        $configFile = __DIR__ . '/db_config.php';
        if (is_file($configFile)) {
            require $configFile;

            if (!isset($servername, $username, $password, $dbname)) {
                throw new RuntimeException('Database config must define $servername, $username, $password, and $dbname.');
            }

            $this->host = $servername;
            $this->username = $username;
            $this->password = $password;
            $this->dbname = $dbname;
        }
    }

    public function connect()
    {
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            return $this->conn;
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(500);
            exit('Database connection failed. Check the database credentials in connection.php or db_config.php.');
        }
    }
}

$database = new Database();
$pdo = $database->connect();

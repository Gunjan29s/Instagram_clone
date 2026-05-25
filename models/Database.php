<?php
class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        date_default_timezone_set('Asia/Kolkata');
        $this->loadLocalEnv();

        $servername = getenv('DB_HOST') ?: 'localhost';
        $username   = getenv('DB_USER') ?: 'root';
        $password   = getenv('DB_PASS') ?: '';
        $dbname     = getenv('DB_NAME') ?: 'instagram_clone';

        try {
            $this->conn = new PDO(
                "mysql:host=$servername;dbname=$dbname;charset=utf8mb4",
                $username,
                $password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->exec("SET time_zone = '+05:30'");
        } catch (PDOException $e) {
            error_log("Database Connection Failed: " . $e->getMessage());
            http_response_code(500);
            die("Database connection failed. Please try again later.");
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->conn;
    }

    private function loadLocalEnv(): void {
        $envPath = __DIR__ . '/../.env';
        if (!is_file($envPath) || !is_readable($envPath)) {
            $envPath = dirname(__DIR__, 2) . '/insta_out.env';
        }

        if (!is_file($envPath) || !is_readable($envPath)) {
            return;
        }

        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $value = trim($value, "\"'");
            if ($key !== '' && getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
    }
}
?>

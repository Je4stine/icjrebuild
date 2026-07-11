<?php
class Database {
    private static $instance = null;
    private $connection;
    
    private $host = DB_HOST;
    private $port = DB_PORT;
    private $database = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    
    private function __construct() {
        try {
            $this->validateConfig();

            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset=utf8mb4";
            $this->connection = new PDO($dsn, $this->username, $this->password);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            $message = APP_ENV === 'development'
                ? "Database connection failed: " . $e->getMessage()
                : "Database connection failed. Check server database configuration.";
            throw new Exception($message);
        }
    }

    private function validateConfig() {
        $missing = [];

        foreach ([
            'DB_HOST' => $this->host,
            'DB_PORT' => $this->port,
            'DB_NAME' => $this->database,
            'DB_USER' => $this->username,
        ] as $key => $value) {
            if ($value === null || $value === '') {
                $missing[] = $key;
            }
        }

        if ($this->password === null || (APP_ENV === 'production' && $this->password === '')) {
            $missing[] = 'DB_PASS';
        }

        if (!empty($missing)) {
            throw new Exception('Missing database configuration: ' . implode(', ', $missing));
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Database query failed: " . $e->getMessage());
        }
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
}

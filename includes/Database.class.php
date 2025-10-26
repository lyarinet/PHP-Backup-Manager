<?php
/**
 * Database Class
 * SQLite wrapper with prepared statements and migration system
 */

class Database {
    private $pdo;
    private $dbPath;
    
    public function __construct($dbPath = null) {
        $this->dbPath = $this->getDatabasePath($dbPath);
        $this->connect();
        $this->runMigrations();
    }
    
    /**
     * Get database path with dynamic detection
     * @param string|null $dbPath Custom database path
     * @return string Database file path
     */
    private function getDatabasePath($dbPath = null) {
        // If custom path provided, use it
        if ($dbPath !== null) {
            return $dbPath;
        }
        
        // Try to get path from constant if defined
        if (defined('DB_PATH')) {
            return DB_PATH;
        }
        
        // Auto-detect the database path
        $possiblePaths = [
            // Current directory
            __DIR__ . '/../backups.db',
            // Parent directory
            dirname(__DIR__) . '/backups.db',
            // Application root
            (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/backups.db',
            // Relative to current working directory
            getcwd() . '/backups.db',
            // Relative to script directory
            dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__) . '/backups.db'
        ];
        
        // Check each possible path
        foreach ($possiblePaths as $path) {
            $realPath = realpath(dirname($path)) . '/' . basename($path);
            
            // If file exists, use it
            if (file_exists($realPath)) {
                return $realPath;
            }
            
            // If directory is writable, create the database file there
            if (is_writable(dirname($realPath))) {
                return $realPath;
            }
        }
        
        // Fallback to current directory
        $fallbackPath = __DIR__ . '/../backups.db';
        
        // Ensure directory exists and is writable
        $dbDir = dirname($fallbackPath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }
        
        return $fallbackPath;
    }
    
    private function connect() {
        try {
            $this->pdo = new PDO("sqlite:" . $this->dbPath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => DB_TIMEOUT
            ]);
            
            // Set file permissions
            chmod($this->dbPath, DB_FILE_PERMISSIONS);
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    private function runMigrations() {
        $migrations = [
            'CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                email VARCHAR(100),
                role VARCHAR(20) DEFAULT "user",
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )',
            
            'CREATE TABLE IF NOT EXISTS backup_configs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                backup_type VARCHAR(20) NOT NULL,
                config_data TEXT NOT NULL,
                enabled BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )',
            
            'CREATE TABLE IF NOT EXISTS backup_schedules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                config_id INTEGER NOT NULL,
                cron_expression VARCHAR(100) NOT NULL,
                last_run DATETIME,
                next_run DATETIME,
                enabled BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (config_id) REFERENCES backup_configs(id) ON DELETE CASCADE
            )',
            
            'CREATE TABLE IF NOT EXISTS backup_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                config_id INTEGER NOT NULL,
                start_time DATETIME NOT NULL,
                end_time DATETIME,
                status VARCHAR(20) DEFAULT "running",
                size_bytes INTEGER DEFAULT 0,
                file_count INTEGER DEFAULT 0,
                error_log TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (config_id) REFERENCES backup_configs(id) ON DELETE CASCADE
            )',
            
            'CREATE TABLE IF NOT EXISTS backup_files (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                history_id INTEGER NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_size INTEGER DEFAULT 0,
                backup_type VARCHAR(20) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (history_id) REFERENCES backup_history(id) ON DELETE CASCADE
            )',
            
            'CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )',
            
            'CREATE TABLE IF NOT EXISTS activity_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                action VARCHAR(100) NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                details TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )',
            
            'CREATE TABLE IF NOT EXISTS cloud_providers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                type VARCHAR(20) NOT NULL,
                host VARCHAR(255),
                port INTEGER,
                username VARCHAR(100),
                password TEXT,
                ssl BOOLEAN DEFAULT 0,
                passive BOOLEAN DEFAULT 1,
                remote_path VARCHAR(500),
                endpoint VARCHAR(255),
                region VARCHAR(50),
                bucket VARCHAR(100),
                access_key VARCHAR(255),
                secret_key TEXT,
                storage_class VARCHAR(50),
                enabled BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )',
            
            'CREATE TABLE IF NOT EXISTS cloud_uploads (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                history_id INTEGER NOT NULL,
                provider_id INTEGER NOT NULL,
                remote_path VARCHAR(500) NOT NULL,
                upload_status VARCHAR(20) DEFAULT "pending",
                upload_start DATETIME,
                upload_end DATETIME,
                error_message TEXT,
                file_size INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (history_id) REFERENCES backup_history(id) ON DELETE CASCADE,
                FOREIGN KEY (provider_id) REFERENCES cloud_providers(id) ON DELETE CASCADE
            )'
        ];
        
        foreach ($migrations as $sql) {
            $this->pdo->exec($sql);
        }
        
        // Create default admin user if no users exist
        $this->createDefaultAdmin();
        
        // Set default settings
        $this->setDefaultSettings();
    }
    
    private function createDefaultAdmin() {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("INSERT INTO users (username, password_hash, email, role) VALUES (?, ?, ?, ?)");
            $stmt->execute(['admin', $adminPassword, 'admin@localhost', 'admin']);
        }
    }
    
    private function setDefaultSettings() {
        $defaultSettings = [
            'backup_directory' => BACKUP_DIR,
            'retention_days' => DEFAULT_RETENTION_DAYS,
            'max_backup_size' => MAX_BACKUP_SIZE,
            'compression_level' => COMPRESSION_LEVEL,
            'email_notifications' => '0',
            'email_smtp_host' => '',
            'email_smtp_port' => '587',
            'email_smtp_user' => '',
            'email_smtp_pass' => '',
            'email_from' => '',
            'email_to' => ''
        ];
        
        foreach ($defaultSettings as $key => $value) {
            $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->execute([$key, $value]);
        }
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query failed: " . $e->getMessage());
            throw new Exception("Database query failed");
        }
    }
    
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        
        return $this->pdo->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $setClause = [];
        foreach (array_keys($data) as $key) {
            $setClause[] = "{$key} = :{$key}";
        }
        $setClause = implode(', ', $setClause);
        
        // Convert positional where params to named params
        $whereNamed = $where;
        $namedWhereParams = [];
        
        // Ensure $where is a string before using strpos
        if (!is_string($where)) {
            throw new Exception('WHERE clause must be a string');
        }
        
        $paramIndex = 0;
        while (strpos($whereNamed, '?') !== false) {
            $paramName = 'where_param_' . $paramIndex;
            $whereNamed = preg_replace('/\?/', ':' . $paramName, $whereNamed, 1);
            $namedWhereParams[$paramName] = $whereParams[$paramIndex];
            $paramIndex++;
        }
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$whereNamed}";
        $params = array_merge($data, $namedWhereParams);
        
        // Debug logging
        $logFile = APP_ROOT . '/debug_update.log';
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "DATABASE - update() called\n", FILE_APPEND);
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "SQL: $sql\n", FILE_APPEND);
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Params: " . print_r($params, true) . "\n", FILE_APPEND);
        
        $stmt = $this->query($sql, $params);
        $rowCount = $stmt->rowCount();
        
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Rows affected: $rowCount\n", FILE_APPEND);
        
        return $rowCount;
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    public function getLastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    public function logActivity($userId, $action, $details = '') {
        $this->insert('activity_logs', [
            'user_id' => $userId,
            'action' => $action,
            'details' => $details
        ]);
    }
    
    public function getSetting($key, $default = null) {
        $result = $this->fetch("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
        return $result ? $result['setting_value'] : $default;
    }
    
    public function setSetting($key, $value) {
        $this->query("INSERT OR REPLACE INTO settings (setting_key, setting_value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)", [$key, $value]);
    }
    
    /**
     * Get the current database path
     * @return string Database file path
     */
    public function getCurrentDatabasePath() {
        return $this->dbPath;
    }
    
    /**
     * Check if database file exists and is accessible
     * @return bool True if database is accessible
     */
    public function isDatabaseAccessible() {
        return file_exists($this->dbPath) && is_readable($this->dbPath) && is_writable(dirname($this->dbPath));
    }
}

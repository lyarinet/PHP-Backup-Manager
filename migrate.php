<?php
/**
 * Migration Script
 * Import existing bash script configuration and maintain compatibility
 */

define('BACKUP_MANAGER', true);
require_once 'config.php';

$db = new Database();
$auth = new Auth($db);
$backupManager = new BackupManager($db);

// Check if migration has already been run
$migrationRun = $db->getSetting('migration_completed', '0');
if ($migrationRun === '1') {
    echo "Migration has already been completed.\n";
    exit(0);
}

echo "Starting migration from bash script configuration...\n";

try {
    // Import bash script configuration
    importBashConfig();
    
    // Import existing backup files
    importExistingBackups();
    
    // Set migration as completed
    $db->setSetting('migration_completed', '1');
    $db->setSetting('migration_date', date('Y-m-d H:i:s'));
    
    echo "Migration completed successfully!\n";
    echo "You can now access the web interface at: " . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) : 'your-domain.com') . "\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Import configuration from bash script config file
 */
function importBashConfig() {
    global $db, $backupManager;
    
    $bashConfigFile = $_SERVER['HOME'] . '/.backup_manager.conf';
    
    if (!file_exists($bashConfigFile)) {
        echo "No existing bash configuration found at: {$bashConfigFile}\n";
        return;
    }
    
    echo "Importing configuration from: {$bashConfigFile}\n";
    
    // Parse bash config file
    $config = parseBashConfig($bashConfigFile);
    
    if (empty($config)) {
        echo "No valid configuration found in bash config file.\n";
        return;
    }
    
    // Create backup configurations based on bash config
    $configId = null;
    
    // Files backup configuration
    if (!empty($config['FILES_TO_BACKUP'])) {
        $filesConfig = [
            'files' => $config['FILES_TO_BACKUP']
        ];
        
        $configId = $backupManager->createConfig(
            'Imported Files Backup',
            'files',
            $filesConfig,
            1 // Admin user ID
        );
        
        echo "✓ Created files backup configuration\n";
    }
    
    // MySQL backup configuration
    if (!empty($config['MYSQL_USER']) && !empty($config['MYSQL_PASSWORD'])) {
        $mysqlConfig = [
            'host' => $config['MYSQL_HOST'] ?? 'localhost',
            'username' => $config['MYSQL_USER'],
            'password' => $config['MYSQL_PASSWORD'],
            'databases' => getMySQLDatabases($config)
        ];
        
        if (!empty($mysqlConfig['databases'])) {
            $configId = $backupManager->createConfig(
                'Imported MySQL Backup',
                'mysql',
                $mysqlConfig,
                1 // Admin user ID
            );
            
            echo "✓ Created MySQL backup configuration\n";
        }
    }
    
    // PostgreSQL backup configuration
    if (!empty($config['POSTGRES_USER']) && !empty($config['POSTGRES_PASSWORD'])) {
        $postgresConfig = [
            'host' => $config['POSTGRES_HOST'] ?? 'localhost',
            'username' => $config['POSTGRES_USER'],
            'password' => $config['POSTGRES_PASSWORD'],
            'databases' => getPostgreSQLDatabases($config)
        ];
        
        if (!empty($postgresConfig['databases'])) {
            $configId = $backupManager->createConfig(
                'Imported PostgreSQL Backup',
                'postgresql',
                $postgresConfig,
                1 // Admin user ID
            );
            
            echo "✓ Created PostgreSQL backup configuration\n";
        }
    }
    
    // Import settings
    if (!empty($config['BACKUP_DIR'])) {
        $db->setSetting('backup_directory', $config['BACKUP_DIR']);
    }
    
    if (!empty($config['RETENTION_DAYS'])) {
        $db->setSetting('retention_days', $config['RETENTION_DAYS']);
    }
    
    echo "✓ Imported settings\n";
}

/**
 * Parse bash configuration file
 */
function parseBashConfig($filePath) {
    $config = [];
    $content = file_get_contents($filePath);
    
    // Parse bash-style configuration
    $lines = explode("\n", $content);
    $currentArray = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        // Handle array definitions
        if (preg_match('/^(\w+)=\($/', $line, $matches)) {
            $currentArray = $matches[1];
            $config[$currentArray] = [];
            continue;
        }
        
        // Handle array end
        if ($line === ')' && $currentArray) {
            $currentArray = null;
            continue;
        }
        
        // Handle array items
        if ($currentArray && preg_match('/^"([^"]+)"$/', $line, $matches)) {
            $config[$currentArray][] = $matches[1];
            continue;
        }
        
        // Handle simple key=value pairs
        if (preg_match('/^(\w+)="?([^"]*)"?$/', $line, $matches)) {
            $key = $matches[1];
            $value = $matches[2];
            
            // Remove quotes if present
            $value = trim($value, '"');
            
            $config[$key] = $value;
        }
    }
    
    return $config;
}

/**
 * Get MySQL databases from configuration
 */
function getMySQLDatabases($config) {
    // Try to get databases from MySQL server
    if (!empty($config['MYSQL_USER']) && !empty($config['MYSQL_PASSWORD'])) {
        $host = $config['MYSQL_HOST'] ?? 'localhost';
        $username = $config['MYSQL_USER'];
        $password = $config['MYSQL_PASSWORD'];
        
        $command = "mysql -h " . escapeshellarg($host) . 
                  " -u " . escapeshellarg($username) . 
                  " -p" . escapeshellarg($password) . 
                  " -e 'SHOW DATABASES;' 2>/dev/null";
        
        $output = shell_exec($command);
        
        if ($output) {
            $databases = [];
            $lines = explode("\n", $output);
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && 
                    !in_array($line, ['Database', 'information_schema', 'performance_schema', 'mysql', 'sys'])) {
                    $databases[] = $line;
                }
            }
            
            return $databases;
        }
    }
    
    return [];
}

/**
 * Get PostgreSQL databases from configuration
 */
function getPostgreSQLDatabases($config) {
    // Try to get databases from PostgreSQL server
    if (!empty($config['POSTGRES_USER']) && !empty($config['POSTGRES_PASSWORD'])) {
        $host = $config['POSTGRES_HOST'] ?? 'localhost';
        $username = $config['POSTGRES_USER'];
        $password = $config['POSTGRES_PASSWORD'];
        
        // Set password environment variable
        putenv("PGPASSWORD=" . $password);
        
        $command = "psql -h " . escapeshellarg($host) . 
                  " -U " . escapeshellarg($username) . 
                  " -l -t 2>/dev/null";
        
        $output = shell_exec($command);
        
        // Clear password environment variable
        putenv("PGPASSWORD=");
        
        if ($output) {
            $databases = [];
            $lines = explode("\n", $output);
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && strpos($line, '|') !== false) {
                    $parts = explode('|', $line);
                    $dbName = trim($parts[0]);
                    
                    if (!empty($dbName) && 
                        !in_array($dbName, ['template0', 'template1', 'postgres'])) {
                        $databases[] = $dbName;
                    }
                }
            }
            
            return $databases;
        }
    }
    
    return [];
}

/**
 * Import existing backup files
 */
function importExistingBackups() {
    global $db;
    
    $backupDir = $db->getSetting('backup_directory', '/opt/backups');
    
    if (!is_dir($backupDir)) {
        echo "Backup directory not found: {$backupDir}\n";
        return;
    }
    
    echo "Scanning for existing backup files in: {$backupDir}\n";
    
    $importedCount = 0;
    
    // Scan for backup files
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($backupDir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && preg_match('/\.(tar\.gz|sql\.gz)$/', $file->getFilename())) {
            $filePath = $file->getPathname();
            $relativePath = str_replace($backupDir . '/', '', $filePath);
            
            // Determine backup type
            $backupType = 'files';
            if (strpos($filePath, '/mysql/') !== false) {
                $backupType = 'mysql';
            } elseif (strpos($filePath, '/postgresql/') !== false) {
                $backupType = 'postgresql';
            }
            
            // Create a mock backup history entry
            $historyId = $db->insert('backup_history', [
                'config_id' => 1, // Default to first config
                'start_time' => date('Y-m-d H:i:s', $file->getMTime()),
                'end_time' => date('Y-m-d H:i:s', $file->getMTime()),
                'status' => 'completed',
                'size_bytes' => $file->getSize(),
                'file_count' => 1
            ]);
            
            // Record the backup file
            $db->insert('backup_files', [
                'history_id' => $historyId,
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'backup_type' => $backupType
            ]);
            
            $importedCount++;
        }
    }
    
    echo "✓ Imported {$importedCount} existing backup files\n";
}

/**
 * Create default admin user if not exists
 */
function createDefaultAdmin() {
    global $db;
    
    $adminExists = $db->fetch("SELECT id FROM users WHERE username = 'admin'");
    
    if (!$adminExists) {
        $db->insert('users', [
            'username' => 'admin',
            'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
            'email' => 'admin@localhost',
            'role' => 'admin'
        ]);
        
        echo "✓ Created default admin user (username: admin, password: admin123)\n";
    }
}

// Run migration
if (php_sapi_name() === 'cli') {
    // Command line execution
    importBashConfig();
    importExistingBackups();
    createDefaultAdmin();
    
    $db->setSetting('migration_completed', '1');
    $db->setSetting('migration_date', date('Y-m-d H:i:s'));
    
    echo "Migration completed successfully!\n";
} else {
    // Web execution
    echo "<!DOCTYPE html>\n";
    echo "<html><head><title>Migration</title></head><body>\n";
    echo "<h1>Backup Manager Migration</h1>\n";
    echo "<pre>\n";
    
    importBashConfig();
    importExistingBackups();
    createDefaultAdmin();
    
    $db->setSetting('migration_completed', '1');
    $db->setSetting('migration_date', date('Y-m-d H:i:s'));
    
    echo "Migration completed successfully!\n";
    echo "<a href='index.php'>Go to Login Page</a>\n";
    echo "</pre></body></html>\n";
}

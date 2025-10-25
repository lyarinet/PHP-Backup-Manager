<?php
/**
 * Backup Manager Class
 * Core backup logic ported from bash script to PHP
 */

class BackupManager {
    private $db;
    private $backupDir;
    private $retentionDays;
    private $logFile;
    
    public function __construct($database) {
        $this->db = $database;
        $this->backupDir = $this->db->getSetting('backup_directory', BACKUP_DIR);
        $this->retentionDays = (int)$this->db->getSetting('retention_days', DEFAULT_RETENTION_DAYS);
        $this->logFile = LOG_DIR . '/backup_manager.log';
        
        // Ensure backup directory exists
        $this->ensureBackupDirectory();
    }
    
    private function ensureBackupDirectory() {
        if (!is_dir($this->backupDir)) {
            if (!mkdir($this->backupDir, 0755, true)) {
                throw new Exception("Failed to create backup directory: {$this->backupDir}");
            }
        }
    }
    
    /**
     * Execute backup based on configuration
     */
    public function executeBackup($configId, $userId = null, $historyId = null) {
        $config = $this->db->fetch("SELECT * FROM backup_configs WHERE id = ?", [$configId]);
        if (!$config) {
            throw new Exception("Backup configuration not found");
        }
        
        $configData = json_decode($config['config_data'], true);
        $configData = $this->decryptConfigData($configData);
        $backupType = $config['backup_type'];
        
        // Create backup history record only if not provided
        if ($historyId === null) {
            $historyId = $this->db->insert('backup_history', [
                'config_id' => $configId,
                'start_time' => date('Y-m-d H:i:s'),
                'status' => 'running',
                'progress' => 0,
                'current_step' => 'Initializing backup...'
            ]);
        }
        
        try {
            $result = [];
            
            switch ($backupType) {
                case 'files':
                    $result = $this->backupFiles($configData, $historyId);
                    break;
                case 'mysql':
                    $result = $this->backupMySQL($configData, $historyId);
                    break;
                case 'postgresql':
                    $result = $this->backupPostgreSQL($configData, $historyId);
                    break;
                default:
                    throw new Exception("Unknown backup type: {$backupType}");
            }
            
            // Update backup history
            $this->db->update('backup_history', [
                'end_time' => date('Y-m-d H:i:s'),
                'status' => 'completed',
                'size_bytes' => $result['total_size'],
                'file_count' => $result['file_count']
            ], 'id = ?', [$historyId]);
            
            // Log activity
            if ($userId) {
                $this->db->logActivity($userId, 'backup_completed', "Backup completed: {$config['name']}");
            }
            
            // Trigger cloud upload if enabled
            $this->handleCloudUpload($historyId);
            
            return $result;
            
        } catch (Exception $e) {
            // Update backup history with error
            $this->db->update('backup_history', [
                'end_time' => date('Y-m-d H:i:s'),
                'status' => 'failed',
                'error_log' => $e->getMessage()
            ], 'id = ?', [$historyId]);
            
            // Log activity
            if ($userId) {
                $this->db->logActivity($userId, 'backup_failed', "Backup failed: {$config['name']} - " . $e->getMessage());
            }
            
            throw $e;
        }
    }
    
    /**
     * Backup files and directories
     */
    private function backupFiles($config, $historyId) {
        $filesToBackup = $config['files'] ?? [];
        $backupPath = $this->backupDir . '/' . date('Y-m-d_H-i-s') . '/files';
        
        if (!mkdir($backupPath, 0755, true)) {
            throw new Exception("Failed to create backup directory: {$backupPath}");
        }
        
        $totalSize = 0;
        $fileCount = 0;
        $successCount = 0;
        $failCount = 0;
        
        foreach ($filesToBackup as $filePath) {
            // Trim whitespace and control characters
            $filePath = trim($filePath);
            
            if (!file_exists($filePath)) {
                $this->log("WARNING: Path does not exist: {$filePath}");
                $failCount++;
                continue;
            }
            
            if (!isPathSafe($filePath)) {
                $this->log("WARNING: Unsafe path: {$filePath}");
                $failCount++;
                continue;
            }
            
            $baseName = basename($filePath);
            $backupFile = $backupPath . '/' . $baseName . '_' . date('Y-m-d_H-i-s') . '.tar.gz';
            
            $this->log("Backing up: {$filePath}");
            
            // Create tar.gz archive
            $command = "tar -czf " . escapeshellarg($backupFile) . " " . escapeshellarg($filePath) . " 2>&1";
            $output = shell_exec($command);
            
            if (file_exists($backupFile) && filesize($backupFile) > 0) {
                $fileSize = filesize($backupFile);
                $totalSize += $fileSize;
                $fileCount++;
                $successCount++;
                
                // Record backup file
                $this->db->insert('backup_files', [
                    'history_id' => $historyId,
                    'file_path' => $backupFile,
                    'file_size' => $fileSize,
                    'backup_type' => 'files'
                ]);
                
                $this->log("Successfully backed up: {$filePath} -> {$backupFile}");
            } else {
                $failCount++;
                $this->log("Failed to backup: {$filePath}");
            }
        }
        
        $this->log("File backup completed: {$successCount} successful, {$failCount} failed");
        
        return [
            'total_size' => $totalSize,
            'file_count' => $fileCount,
            'success_count' => $successCount,
            'fail_count' => $failCount
        ];
    }
    
    /**
     * Backup MySQL databases
     */
    private function backupMySQL($config, $historyId) {
        $host = $config['host'] ?? 'localhost';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $databases = $config['databases'] ?? [];
        
        if (empty($username) || empty($databases)) {
            throw new Exception("MySQL configuration incomplete");
        }
        
        $backupPath = $this->backupDir . '/' . date('Y-m-d_H-i-s') . '/mysql';
        
        if (!mkdir($backupPath, 0755, true)) {
            throw new Exception("Failed to create MySQL backup directory: {$backupPath}");
        }
        
        $totalSize = 0;
        $fileCount = 0;
        $successCount = 0;
        $failCount = 0;
        $totalDatabases = count($databases);
        
        // Update progress: Testing connection
        $this->updateProgress($historyId, 10, 'Testing MySQL connection...');
        
        // Test MySQL connection
        $testCommand = "mysql -h " . escapeshellarg($host) . 
                      " -u " . escapeshellarg($username) . 
                      " -p" . escapeshellarg($password) . 
                      " --skip-ssl -e 'SHOW DATABASES;' 2>&1";
        
        $testOutput = shell_exec($testCommand);
        if (strpos($testOutput, 'ERROR') !== false) {
            throw new Exception("Cannot connect to MySQL server: " . $testOutput);
        }
        
        // Update progress: Connection successful
        $this->updateProgress($historyId, 20, 'Connection successful. Starting database backups...');
        
        foreach ($databases as $index => $database) {
            $progress = 20 + (($index + 1) / $totalDatabases) * 70; // 20-90% progress
            $this->updateProgress($historyId, $progress, "Backing up database: {$database}");
            $backupFile = $backupPath . '/' . $database . '_' . date('Y-m-d_H-i-s') . '.sql.gz';
            
            $this->log("Backing up MySQL database: {$database}");
            
            $command = "mysqldump -h " . escapeshellarg($host) . 
                      " -u " . escapeshellarg($username) . 
                      " -p" . escapeshellarg($password) . 
                      " --skip-ssl --single-transaction --routines --triggers --databases --add-drop-database " . 
                      escapeshellarg($database) . 
                      " 2>&1 | gzip > " . escapeshellarg($backupFile);
            
            $output = shell_exec($command);
            
            if (file_exists($backupFile) && filesize($backupFile) > 0) {
                $fileSize = filesize($backupFile);
                $totalSize += $fileSize;
                $fileCount++;
                $successCount++;
                
                // Record backup file
                $this->db->insert('backup_files', [
                    'history_id' => $historyId,
                    'file_path' => $backupFile,
                    'file_size' => $fileSize,
                    'backup_type' => 'mysql'
                ]);
                
                $this->log("Successfully backed up MySQL: {$database}");
            } else {
                $failCount++;
                $this->log("Failed to backup MySQL: {$database}");
            }
        }
        
        $this->log("MySQL backup completed: {$successCount} successful, {$failCount} failed");
        
        // Update progress: Completed
        $this->updateProgress($historyId, 100, 'MySQL backup completed successfully');
        
        return [
            'total_size' => $totalSize,
            'file_count' => $fileCount,
            'success_count' => $successCount,
            'fail_count' => $failCount
        ];
    }
    
    /**
     * Backup PostgreSQL databases
     */
    private function backupPostgreSQL($config, $historyId) {
        $host = $config['host'] ?? 'localhost';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $databases = $config['databases'] ?? [];
        
        if (empty($username) || empty($databases)) {
            throw new Exception("PostgreSQL configuration incomplete");
        }
        
        $backupPath = $this->backupDir . '/' . date('Y-m-d_H-i-s') . '/postgresql';
        
        if (!mkdir($backupPath, 0755, true)) {
            throw new Exception("Failed to create PostgreSQL backup directory: {$backupPath}");
        }
        
        $totalSize = 0;
        $fileCount = 0;
        $successCount = 0;
        $failCount = 0;
        
        // Set password environment variable
        putenv("PGPASSWORD=" . $password);
        
        // Test PostgreSQL connection
        $testCommand = "psql -h " . escapeshellarg($host) . 
                      " -U " . escapeshellarg($username) . 
                      " -l 2>&1";
        
        $testOutput = shell_exec($testCommand);
        if (strpos($testOutput, 'FATAL') !== false || strpos($testOutput, 'ERROR') !== false) {
            putenv("PGPASSWORD=");
            throw new Exception("Cannot connect to PostgreSQL server: " . $testOutput);
        }
        
        foreach ($databases as $database) {
            $backupFile = $backupPath . '/' . $database . '_' . date('Y-m-d_H-i-s') . '.sql.gz';
            
            $this->log("Backing up PostgreSQL database: {$database}");
            
            $command = "pg_dump -h " . escapeshellarg($host) . 
                      " -U " . escapeshellarg($username) . 
                      " " . escapeshellarg($database) . 
                      " 2>&1 | gzip > " . escapeshellarg($backupFile);
            
            $output = shell_exec($command);
            
            if (file_exists($backupFile) && filesize($backupFile) > 0) {
                $fileSize = filesize($backupFile);
                $totalSize += $fileSize;
                $fileCount++;
                $successCount++;
                
                // Record backup file
                $this->db->insert('backup_files', [
                    'history_id' => $historyId,
                    'file_path' => $backupFile,
                    'file_size' => $fileSize,
                    'backup_type' => 'postgresql'
                ]);
                
                $this->log("Successfully backed up PostgreSQL: {$database}");
            } else {
                $failCount++;
                $this->log("Failed to backup PostgreSQL: {$database}");
            }
        }
        
        // Clear password environment variable
        putenv("PGPASSWORD=");
        
        $this->log("PostgreSQL backup completed: {$successCount} successful, {$failCount} failed");
        
        return [
            'total_size' => $totalSize,
            'file_count' => $fileCount,
            'success_count' => $successCount,
            'fail_count' => $failCount
        ];
    }
    
    /**
     * Clean old backups based on retention policy
     */
    public function cleanOldBackups() {
        $this->log("Cleaning backups older than {$this->retentionDays} days");
        
        $deletedCount = cleanOldFiles($this->backupDir, $this->retentionDays);
        
        $this->log("Cleanup completed. Removed {$deletedCount} old backup files");
        
        return $deletedCount;
    }
    
    /**
     * Get backup statistics
     */
    public function getBackupStats() {
        return getBackupStats($this->db);
    }
    
    /**
     * Get recent backup history
     */
    public function getRecentBackups($limit = 10) {
        return getRecentBackups($this->db, $limit);
    }
    
    /**
     * Create backup configuration
     */
    public function createConfig($name, $backupType, $configData, $userId) {
        // Validate configuration data
        $this->validateConfig($backupType, $configData);
        
        // Encrypt sensitive data
        $encryptedData = $this->encryptConfigData($configData);
        
        $configId = $this->db->insert('backup_configs', [
            'name' => $name,
            'backup_type' => $backupType,
            'config_data' => json_encode($encryptedData),
            'enabled' => 1
        ]);
        
        $this->db->logActivity($userId, 'create_config', "Created backup config: {$name}");
        
        return $configId;
    }
    
    /**
     * Update backup configuration
     */
    public function updateConfig($configId, $name, $configData, $userId, $backupType = null) {
        // Debug logging
        $logFile = APP_ROOT . '/debug_update.log';
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "BACKUPMANAGER - updateConfig called\n", FILE_APPEND);
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Input data: " . print_r($configData, true) . "\n", FILE_APPEND);
        
        $config = $this->db->fetch("SELECT * FROM backup_configs WHERE id = ?", [$configId]);
        if (!$config) {
            throw new Exception("Configuration not found");
        }
        
        // Use provided backup type or existing one
        $typeToUse = $backupType ?: $config['backup_type'];
        
        // Validate configuration data
        $this->validateConfig($typeToUse, $configData);
        
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Before encryption: " . print_r($configData, true) . "\n", FILE_APPEND);
        
        // Encrypt sensitive data
        $encryptedData = $this->encryptConfigData($configData);
        
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "After encryption: " . print_r($encryptedData, true) . "\n", FILE_APPEND);
        
        $updateData = [
            'name' => $name,
            'config_data' => json_encode($encryptedData)
        ];
        
        // Update backup type if provided
        if ($backupType) {
            $updateData['backup_type'] = $backupType;
        }
        
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Update data to DB: " . print_r($updateData, true) . "\n", FILE_APPEND);
        
        $this->db->update('backup_configs', $updateData, 'id = ?', [$configId]);
        
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "DB update executed\n", FILE_APPEND);
        
        $this->db->logActivity($userId, 'update_config', "Updated backup config: {$name}");
        
        return true;
    }
    
    /**
     * Delete backup configuration
     */
    public function deleteConfig($configId, $userId) {
        $config = $this->db->fetch("SELECT name FROM backup_configs WHERE id = ?", [$configId]);
        if (!$config) {
            throw new Exception("Configuration not found");
        }
        
        $this->db->delete('backup_configs', 'id = ?', [$configId]);
        
        $this->db->logActivity($userId, 'delete_config', "Deleted backup config: {$config['name']}");
        
        return true;
    }
    
    /**
     * Get all backup configurations
     */
    public function getConfigs() {
        $configs = $this->db->fetchAll("SELECT * FROM backup_configs ORDER BY created_at DESC");
        
        foreach ($configs as &$config) {
            $configData = json_decode($config['config_data'], true);
            $config['config_data'] = $this->decryptConfigData($configData);
        }
        
        return $configs;
    }
    
    /**
     * Validate configuration data
     */
    private function validateConfig($backupType, $configData) {
        switch ($backupType) {
            case 'files':
                if (empty($configData['files']) || !is_array($configData['files'])) {
                    throw new Exception("Files configuration must include files array");
                }
                break;
            case 'mysql':
                if (empty($configData['username']) || empty($configData['databases'])) {
                    throw new Exception("MySQL configuration must include username and databases");
                }
                break;
            case 'postgresql':
                if (empty($configData['username']) || empty($configData['databases'])) {
                    throw new Exception("PostgreSQL configuration must include username and databases");
                }
                break;
            default:
                throw new Exception("Unknown backup type: {$backupType}");
        }
    }
    
    /**
     * Encrypt sensitive configuration data
     */
    private function encryptConfigData($configData) {
        $sensitiveFields = ['password', 'username'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($configData[$field])) {
                $configData[$field] = encryptData($configData[$field]);
            }
        }
        
        return $configData;
    }
    
    /**
     * Decrypt sensitive configuration data
     */
    private function decryptConfigData($configData) {
        $sensitiveFields = ['password', 'username'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($configData[$field])) {
                $configData[$field] = decryptData($configData[$field]);
            }
        }
        
        return $configData;
    }
    
    /**
     * Log message
     */
    /**
     * Start backup and return history ID
     */
    public function startBackup($configId, $userId = null) {
        $config = $this->db->fetch("SELECT * FROM backup_configs WHERE id = ?", [$configId]);
        if (!$config) {
            throw new Exception("Backup configuration not found");
        }
        
        // Create backup history record
        $historyId = $this->db->insert('backup_history', [
            'config_id' => $configId,
            'start_time' => date('Y-m-d H:i:s'),
            'status' => 'running',
            'progress' => 0,
            'current_step' => 'Initializing backup...'
        ]);
        
        return $historyId;
    }

    /**
     * Update backup progress
     */
    private function updateProgress($historyId, $progress, $step) {
        $this->db->update('backup_history', [
            'progress' => $progress,
            'current_step' => $step
        ], 'id = ?', [$historyId]);
    }

    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        // Write to log file
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // Also log to PHP error log
        error_log($message);
    }
    
    /**
     * Upload backup to cloud storage
     */
    public function uploadToCloud($historyId, $providerId = null) {
        try {
            // Get backup history
            $history = $this->db->fetch("SELECT * FROM backup_history WHERE id = ?", [$historyId]);
            if (!$history) {
                throw new Exception("Backup history not found");
            }
            
            // Get backup configuration
            $config = $this->db->fetch("SELECT * FROM backup_configs WHERE id = ?", [$history['config_id']]);
            if (!$config) {
                throw new Exception("Backup configuration not found");
            }
            
            // Check if cloud upload is enabled for this config
            $configData = json_decode($config['config_data'], true);
            $configData = $this->decryptConfigData($configData);
            
            if (!isset($configData['cloud_enabled']) || !$configData['cloud_enabled']) {
                $this->log("Cloud upload disabled for configuration: {$config['name']}", 'INFO');
                return false;
            }
            
            // Get cloud provider
            if ($providerId === null) {
                $providerId = $configData['cloud_provider_id'] ?? null;
            }
            
            if (!$providerId) {
                throw new Exception("No cloud provider configured");
            }
            
            $provider = $this->db->fetch("SELECT * FROM cloud_providers WHERE id = ? AND enabled = 1", [$providerId]);
            if (!$provider) {
                throw new Exception("Cloud provider not found or disabled");
            }
            
            // Get backup file path
            $backupFile = $this->getBackupFilePath($history);
            if (!file_exists($backupFile)) {
                throw new Exception("Backup file not found: {$backupFile}");
            }
            
            // Create cloud upload record
            $uploadId = $this->db->insert('cloud_uploads', [
                'history_id' => $historyId,
                'provider_id' => $providerId,
                'remote_path' => $this->generateRemotePath($config, $history),
                'upload_status' => 'pending',
                'upload_start' => date('Y-m-d H:i:s'),
                'file_size' => filesize($backupFile)
            ]);
            
            // Initialize cloud storage
            $storage = $this->getCloudStorage($provider);
            if (!$storage) {
                throw new Exception("Failed to initialize cloud storage");
            }
            
            // Connect to cloud storage
            if (!$storage->connect()) {
                throw new Exception("Failed to connect to cloud storage");
            }
            
            // Update upload status to uploading
            $this->db->update('cloud_uploads', ['upload_status' => 'uploading'], ['id' => $uploadId]);
            
            // Upload file
            $remotePath = $this->generateRemotePath($config, $history);
            $result = $storage->upload($backupFile, $remotePath);
            
            if ($result) {
                // Update upload status to completed
                $this->db->update('cloud_uploads', [
                    'upload_status' => 'completed',
                    'upload_end' => date('Y-m-d H:i:s')
                ], ['id' => $uploadId]);
                
                $this->log("Successfully uploaded backup to cloud: {$remotePath}", 'INFO');
                return true;
            } else {
                throw new Exception("Cloud upload failed");
            }
            
        } catch (Exception $e) {
            // Update upload status to failed
            if (isset($uploadId)) {
                $this->db->update('cloud_uploads', [
                    'upload_status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'upload_end' => date('Y-m-d H:i:s')
                ], ['id' => $uploadId]);
            }
            
            $this->log("Cloud upload failed: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Get cloud storage instance
     */
    private function getCloudStorage($provider) {
        $type = $provider['type'];
        
        switch ($type) {
            case 'ftp':
                require_once __DIR__ . '/storage/FTPStorage.class.php';
                return new FTPStorage($provider, $this->db);
                
            case 's3':
                require_once __DIR__ . '/storage/S3Storage.class.php';
                return new S3Storage($provider, $this->db);
                
            case 'google_drive':
                require_once __DIR__ . '/storage/GoogleDriveStorage.class.php';
                return new GoogleDriveStorage($provider, $this->db);
                
            default:
                throw new Exception("Unsupported cloud storage type: {$type}");
        }
    }
    
    /**
     * Generate remote path for cloud upload
     */
    private function generateRemotePath($config, $history) {
        $timestamp = date('Y-m-d_H-i-s', strtotime($history['start_time']));
        $configName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $config['name']);
        $backupType = $config['backup_type'];
        
        return "/backups/{$configName}/{$backupType}_{$timestamp}.tar.gz";
    }
    
    /**
     * Get backup file path from history
     */
    private function getBackupFilePath($history) {
        // This would need to be implemented based on how backup files are stored
        // For now, we'll assume files are in the backup directory with a naming pattern
        $timestamp = date('Y-m-d_H-i-s', strtotime($history['start_time']));
        return $this->backupDir . "/backup_{$history['id']}_{$timestamp}.tar.gz";
    }
    
    /**
     * Auto-upload to cloud if enabled
     */
    public function handleCloudUpload($historyId) {
        try {
            // Check if auto-upload is enabled globally
            $autoUpload = $this->db->getSetting('auto_cloud_upload', false);
            if (!$autoUpload) {
                return false;
            }
            
            // Get backup configuration
            $history = $this->db->fetch("SELECT * FROM backup_history WHERE id = ?", [$historyId]);
            if (!$history) {
                return false;
            }
            
            $config = $this->db->fetch("SELECT * FROM backup_configs WHERE id = ?", [$history['config_id']]);
            if (!$config) {
                return false;
            }
            
            $configData = json_decode($config['config_data'], true);
            $configData = $this->decryptConfigData($configData);
            
            // Check if cloud upload is enabled for this specific config
            if (isset($configData['cloud_enabled']) && $configData['cloud_enabled']) {
                return $this->uploadToCloud($historyId);
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->log("Auto cloud upload failed: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
}

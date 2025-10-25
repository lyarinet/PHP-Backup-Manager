<?php
/**
 * Cloud Storage Base Class
 * Abstract base class for cloud storage providers
 */

abstract class CloudStorage {
    protected $config;
    protected $db;
    protected $logger;
    
    public function __construct($config, $db = null) {
        $this->config = $config;
        $this->db = $db;
        $this->logger = new CloudStorageLogger($db);
    }
    
    /**
     * Connect to cloud storage provider
     * @return bool Success status
     */
    abstract public function connect();
    
    /**
     * Upload file to cloud storage
     * @param string $localPath Local file path
     * @param string $remotePath Remote file path
     * @param callable $progressCallback Optional progress callback
     * @return bool Success status
     */
    abstract public function upload($localPath, $remotePath, $progressCallback = null);
    
    /**
     * Download file from cloud storage
     * @param string $remotePath Remote file path
     * @param string $localPath Local file path
     * @return bool Success status
     */
    abstract public function download($remotePath, $localPath);
    
    /**
     * Delete file from cloud storage
     * @param string $remotePath Remote file path
     * @return bool Success status
     */
    abstract public function delete($remotePath);
    
    /**
     * List files in cloud storage
     * @param string $prefix Optional path prefix
     * @return array List of files
     */
    abstract public function listFiles($prefix = '');
    
    /**
     * Test connection to cloud storage
     * @return array Test result with success status and message
     */
    abstract public function testConnection();
    
    /**
     * Get storage provider name
     * @return string Provider name
     */
    abstract public function getProviderName();
    
    /**
     * Validate configuration
     * @return array Validation result
     */
    public function validateConfig() {
        $errors = [];
        
        if (empty($this->config['name'])) {
            $errors[] = 'Provider name is required';
        }
        
        if (empty($this->config['enabled'])) {
            $errors[] = 'Provider must be enabled';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Log cloud storage activity
     * @param string $action Action performed
     * @param string $message Log message
     * @param array $data Additional data
     */
    protected function logActivity($action, $message, $data = []) {
        if ($this->logger) {
            $this->logger->log($action, $message, $data);
        }
    }
    
    /**
     * Format file size for display
     * @param int $bytes File size in bytes
     * @return string Formatted size
     */
    protected function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Generate unique remote path
     * @param string $filename Original filename
     * @return string Unique remote path
     */
    protected function generateRemotePath($filename) {
        $timestamp = date('Y-m-d_H-i-s');
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        
        return "backups/{$timestamp}_{$basename}.{$extension}";
    }
}

/**
 * Cloud Storage Logger
 * Handles logging of cloud storage activities
 */
class CloudStorageLogger {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Log cloud storage activity
     * @param string $action Action performed
     * @param string $message Log message
     * @param array $data Additional data
     */
    public function log($action, $message, $data = []) {
        try {
            if ($this->db) {
                $this->db->insert('activity_logs', [
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'action' => "cloud_{$action}",
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'created_at' => date('Y-m-d H:i:s'),
                    'details' => json_encode([
                        'message' => $message,
                        'data' => $data
                    ])
                ]);
            }
            
            // Also log to file
            error_log("Cloud Storage [{$action}]: {$message}");
        } catch (Exception $e) {
            error_log("Failed to log cloud storage activity: " . $e->getMessage());
        }
    }
}
?>

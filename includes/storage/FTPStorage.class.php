<?php
/**
 * FTP Storage Implementation
 * Handles FTP and FTPS connections for cloud storage
 */

require_once __DIR__ . '/../CloudStorage.class.php';

class FTPStorage extends CloudStorage {
    private $connection;
    
    public function connect() {
        try {
            $host = $this->config['host'];
            $port = $this->config['port'] ?? 21;
            $username = $this->config['username'];
            $password = $this->config['password'];
            $ssl = $this->config['ssl'] ?? false;
            $passive = $this->config['passive'] ?? true;
            
            if ($ssl) {
                // Use FTPS (FTP over SSL)
                $this->connection = ftp_ssl_connect($host, $port, 30);
            } else {
                // Use regular FTP
                $this->connection = ftp_connect($host, $port, 30);
            }
            
            if (!$this->connection) {
                throw new Exception("Failed to connect to FTP server: {$host}:{$port}");
            }
            
            // Login
            if (!ftp_login($this->connection, $username, $password)) {
                throw new Exception("Failed to login to FTP server");
            }
            
            // Set passive mode
            if ($passive) {
                ftp_pasv($this->connection, true);
            }
            
            $this->logActivity('connect', "Connected to FTP server: {$host}");
            return true;
            
        } catch (Exception $e) {
            $this->logActivity('connect_error', "Failed to connect to FTP: " . $e->getMessage());
            return false;
        }
    }
    
    public function upload($localPath, $remotePath, $progressCallback = null) {
        try {
            if (!$this->connection) {
                if (!$this->connect()) {
                    return false;
                }
            }
            
            if (!file_exists($localPath)) {
                throw new Exception("Local file does not exist: {$localPath}");
            }
            
            $fileSize = filesize($localPath);
            $this->logActivity('upload_start', "Starting upload: {$localPath} -> {$remotePath}", [
                'file_size' => $fileSize
            ]);
            
            // Create remote directory if it doesn't exist
            $remoteDir = dirname($remotePath);
            if ($remoteDir !== '.') {
                $this->createRemoteDirectory($remoteDir);
            }
            
            // Upload file
            $result = ftp_put($this->connection, $remotePath, $localPath, FTP_BINARY);
            
            if ($result) {
                $this->logActivity('upload_success', "File uploaded successfully: {$remotePath}");
                return true;
            } else {
                throw new Exception("Failed to upload file to FTP server");
            }
            
        } catch (Exception $e) {
            $this->logActivity('upload_error', "Upload failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function download($remotePath, $localPath) {
        try {
            if (!$this->connection) {
                if (!$this->connect()) {
                    return false;
                }
            }
            
            $this->logActivity('download_start', "Starting download: {$remotePath} -> {$localPath}");
            
            // Create local directory if it doesn't exist
            $localDir = dirname($localPath);
            if (!is_dir($localDir)) {
                mkdir($localDir, 0755, true);
            }
            
            // Download file
            $result = ftp_get($this->connection, $localPath, $remotePath, FTP_BINARY);
            
            if ($result) {
                $this->logActivity('download_success', "File downloaded successfully: {$localPath}");
                return true;
            } else {
                throw new Exception("Failed to download file from FTP server");
            }
            
        } catch (Exception $e) {
            $this->logActivity('download_error', "Download failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function delete($remotePath) {
        try {
            if (!$this->connection) {
                if (!$this->connect()) {
                    return false;
                }
            }
            
            $this->logActivity('delete_start', "Deleting file: {$remotePath}");
            
            $result = ftp_delete($this->connection, $remotePath);
            
            if ($result) {
                $this->logActivity('delete_success', "File deleted successfully: {$remotePath}");
                return true;
            } else {
                throw new Exception("Failed to delete file from FTP server");
            }
            
        } catch (Exception $e) {
            $this->logActivity('delete_error', "Delete failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function listFiles($prefix = '') {
        try {
            if (!$this->connection) {
                if (!$this->connect()) {
                    return [];
                }
            }
            
            $files = [];
            $remotePath = $prefix ?: $this->config['remote_path'] ?? '/';
            
            $fileList = ftp_nlist($this->connection, $remotePath);
            
            if ($fileList) {
                foreach ($fileList as $file) {
                    $files[] = [
                        'name' => basename($file),
                        'path' => $file,
                        'size' => ftp_size($this->connection, $file),
                        'modified' => ftp_mdtm($this->connection, $file)
                    ];
                }
            }
            
            return $files;
            
        } catch (Exception $e) {
            $this->logActivity('list_error', "Failed to list files: " . $e->getMessage());
            return [];
        }
    }
    
    public function testConnection() {
        try {
            if ($this->connect()) {
                // Test by listing root directory
                $files = ftp_nlist($this->connection, '/');
                ftp_close($this->connection);
                
                return [
                    'success' => true,
                    'message' => 'FTP connection successful',
                    'files_count' => count($files)
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to connect to FTP server'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'FTP connection test failed: ' . $e->getMessage()
            ];
        }
    }
    
    public function getProviderName() {
        return 'FTP';
    }
    
    /**
     * Create remote directory recursively
     * @param string $path Directory path
     */
    private function createRemoteDirectory($path) {
        $parts = explode('/', trim($path, '/'));
        $currentPath = '';
        
        foreach ($parts as $part) {
            if (empty($part)) continue;
            
            $currentPath .= '/' . $part;
            
            // Check if directory exists
            $files = ftp_nlist($this->connection, $currentPath);
            if (!$files) {
                // Directory doesn't exist, create it
                ftp_mkdir($this->connection, $currentPath);
            }
        }
    }
    
    /**
     * Close FTP connection
     */
    public function __destruct() {
        if ($this->connection) {
            ftp_close($this->connection);
        }
    }
}
?>

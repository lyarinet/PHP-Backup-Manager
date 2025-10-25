<?php
/**
 * Google Drive Storage Implementation
 * Handles Google Drive API integration for cloud storage
 */

require_once __DIR__ . '/../CloudStorage.class.php';

class GoogleDriveStorage extends CloudStorage {
    private $client;
    private $service;
    private $accessToken;
    
    public function __construct($config, $db = null) {
        parent::__construct($config, $db);
        
        // Parse access token if it's a JSON string
        $accessToken = $config['access_token'] ?? null;
        if (is_string($accessToken) && !empty($accessToken)) {
            $decoded = json_decode($accessToken, true);
            if ($decoded && isset($decoded['access_token'])) {
                // Extract just the access_token from the JSON response
                $this->accessToken = [
                    'access_token' => $decoded['access_token'],
                    'expires_in' => $decoded['expires_in'] ?? 3600,
                    'token_type' => $decoded['token_type'] ?? 'Bearer',
                    'created' => time()
                ];
            } elseif ($decoded && isset($decoded['web'])) {
                // This is OAuth configuration, not an access token
                $this->accessToken = null;
            } else {
                $this->accessToken = $accessToken;
            }
        } else {
            $this->accessToken = $accessToken;
        }
        
        // Include Composer autoloader if available
        if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
            require_once __DIR__ . '/../../vendor/autoload.php';
        }
        
        // Check if Google API client is available
        if (!class_exists('Google_Client')) {
            throw new Exception("Google API client not available. Please install google/apiclient package.");
        }
    }
    
    public function connect() {
        try {
            // Check if Google API client is available
            if (!class_exists('Google_Client')) {
                throw new Exception("Google API client not available. Please install google/apiclient package.");
            }
            
            $this->client = new Google_Client();
            $this->client->setClientId($this->config['client_id']);
            $this->client->setClientSecret($this->config['client_secret']);
            $this->client->setRedirectUri($this->config['redirect_uri'] ?? 'urn:ietf:wg:oauth:2.0:oob');
            $this->client->setScopes([
                'https://www.googleapis.com/auth/drive.file',
                'https://www.googleapis.com/auth/drive.metadata.readonly'
            ]);
            
            // Set access token if available
            if ($this->accessToken) {
                $this->client->setAccessToken($this->accessToken);
                
                // Refresh token if needed
                if ($this->client->isAccessTokenExpired()) {
                    $refreshToken = $this->config['refresh_token'] ?? null;
                    if ($refreshToken) {
                        $this->client->setRefreshToken($refreshToken);
                        $newToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
                        $this->updateAccessToken($newToken);
                    } else {
                        throw new Exception("Access token expired and no refresh token available. Please re-authenticate.");
                    }
                }
            } else {
                throw new Exception("No access token provided. Please click the 'Authenticate' button to complete Google Drive OAuth authentication.");
            }
            
            // Create Drive service
            $this->service = new Google_Service_Drive($this->client);
            
            $this->logActivity('connect', "Connected to Google Drive");
            return true;
            
        } catch (Exception $e) {
            $this->logActivity('connect_error', "Failed to connect to Google Drive: " . $e->getMessage());
            return false;
        }
    }
    
    public function upload($localPath, $remotePath, $progressCallback = null) {
        try {
            if (!$this->service) {
                if (!$this->connect()) {
                    return false;
                }
            }
            
            if (!file_exists($localPath)) {
                throw new Exception("Local file does not exist: {$localPath}");
            }
            
            $fileSize = filesize($localPath);
            $this->logActivity('upload_start', "Starting Google Drive upload: {$localPath} -> {$remotePath}", [
                'file_size' => $fileSize
            ]);
            
            // Create file metadata
            $fileMetadata = new Google_Service_Drive_DriveFile([
                'name' => basename($remotePath),
                'parents' => [$this->getFolderId($remotePath)]
            ]);
            
            // Upload file
            $result = $this->service->files->create(
                $fileMetadata,
                [
                    'data' => file_get_contents($localPath),
                    'mimeType' => $this->getMimeType($localPath),
                    'uploadType' => 'multipart',
                    'fields' => 'id,name,size,webViewLink'
                ]
            );
            
            if ($result) {
                $this->logActivity('upload_success', "File uploaded to Google Drive: {$result->getName()}", [
                    'file_id' => $result->getId(),
                    'web_link' => $result->getWebViewLink()
                ]);
                return true;
            } else {
                throw new Exception("Failed to upload file to Google Drive");
            }
            
        } catch (Exception $e) {
            $this->logActivity('upload_error', "Google Drive upload failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function download($remotePath, $localPath) {
        try {
            if (!$this->service) {
                if (!$this->connect()) {
                    return false;
                }
            }
            
            $this->logActivity('download_start', "Starting Google Drive download: {$remotePath} -> {$localPath}");
            
            // Find file by name
            $fileId = $this->findFileByName(basename($remotePath));
            if (!$fileId) {
                throw new Exception("File not found in Google Drive: {$remotePath}");
            }
            
            // Create local directory if it doesn't exist
            $localDir = dirname($localPath);
            if (!is_dir($localDir)) {
                mkdir($localDir, 0755, true);
            }
            
            // Download file
            $content = $this->service->files->get($fileId, ['alt' => 'media']);
            file_put_contents($localPath, $content->getBody());
            
            $this->logActivity('download_success', "File downloaded from Google Drive: {$localPath}");
            return true;
            
        } catch (Exception $e) {
            $this->logActivity('download_error', "Google Drive download failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function delete($remotePath) {
        try {
            if (!$this->service) {
                if (!$this->connect()) {
                    return false;
                }
            }
            
            $this->logActivity('delete_start', "Deleting file from Google Drive: {$remotePath}");
            
            // Find file by name
            $fileId = $this->findFileByName(basename($remotePath));
            if (!$fileId) {
                throw new Exception("File not found in Google Drive: {$remotePath}");
            }
            
            $result = $this->service->files->delete($fileId);
            
            if ($result === null) { // Google Drive returns null on successful deletion
                $this->logActivity('delete_success', "File deleted from Google Drive: {$remotePath}");
                return true;
            } else {
                throw new Exception("Failed to delete file from Google Drive");
            }
            
        } catch (Exception $e) {
            $this->logActivity('delete_error', "Google Drive delete failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function listFiles($prefix = '') {
        try {
            if (!$this->service) {
                if (!$this->connect()) {
                    return [];
                }
            }
            
            $files = [];
            $query = "trashed = false";
            
            if ($prefix) {
                $query .= " and name contains '{$prefix}'";
            }
            
            $results = $this->service->files->listFiles([
                'q' => $query,
                'fields' => 'files(id,name,size,modifiedTime,webViewLink)'
            ]);
            
            foreach ($results->getFiles() as $file) {
                $files[] = [
                    'name' => $file->getName(),
                    'path' => $file->getName(),
                    'size' => $file->getSize() ?? 0,
                    'modified' => strtotime($file->getModifiedTime()),
                    'web_link' => $file->getWebViewLink(),
                    'file_id' => $file->getId()
                ];
            }
            
            return $files;
            
        } catch (Exception $e) {
            $this->logActivity('list_error', "Failed to list Google Drive files: " . $e->getMessage());
            return [];
        }
    }
    
    public function testConnection() {
        try {
            if ($this->connect()) {
                // Test by getting user info
                $about = $this->service->about->get(['fields' => 'user,storageQuota']);
                $user = $about->getUser();
                $quota = $about->getStorageQuota();
                
                // Handle storage quota information safely
                $storageUsed = 0;
                $storageTotal = 0;
                
                if ($quota) {
                    // Try different methods to get storage information
                    if (method_exists($quota, 'getUsed')) {
                        $storageUsed = $quota->getUsed() ?? 0;
                    } elseif (method_exists($quota, 'getUsage')) {
                        $storageUsed = $quota->getUsage() ?? 0;
                    }
                    
                    if (method_exists($quota, 'getLimit')) {
                        $storageTotal = $quota->getLimit() ?? 0;
                    } elseif (method_exists($quota, 'getMax')) {
                        $storageTotal = $quota->getMax() ?? 0;
                    }
                }
                
                return [
                    'success' => true,
                    'message' => 'Google Drive connection successful',
                    'user_email' => $user->getEmailAddress(),
                    'storage_used' => $storageUsed,
                    'storage_total' => $storageTotal
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to connect to Google Drive'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Google Drive connection test failed: ' . $e->getMessage()
            ];
        }
    }
    
    public function getProviderName() {
        return 'Google Drive';
    }
    
    /**
     * Get authentication URL for OAuth flow
     * @return string Authentication URL
     */
    public function getAuthUrl() {
        try {
            // Initialize client if not already done
            if (!$this->client) {
                $this->client = new Google_Client();
                $this->client->setClientId($this->config['client_id']);
                $this->client->setClientSecret($this->config['client_secret']);
                $this->client->setRedirectUri($this->config['redirect_uri'] ?? 'urn:ietf:wg:oauth:2.0:oob');
                $this->client->setScopes([
                    'https://www.googleapis.com/auth/drive.file',
                    'https://www.googleapis.com/auth/drive.metadata.readonly'
                ]);
            }
            
            return $this->client->createAuthUrl();
        } catch (Exception $e) {
            throw new Exception("Failed to create auth URL: " . $e->getMessage());
        }
    }
    
    /**
     * Exchange authorization code for access token
     * @param string $authCode Authorization code from OAuth callback
     * @return array Token data
     */
    public function exchangeCodeForToken($authCode) {
        try {
            // Initialize client if not already done
            if (!$this->client) {
                $this->client = new Google_Client();
                $this->client->setClientId($this->config['client_id']);
                $this->client->setClientSecret($this->config['client_secret']);
                $this->client->setRedirectUri($this->config['redirect_uri'] ?? 'urn:ietf:wg:oauth:2.0:oob');
                $this->client->setScopes([
                    'https://www.googleapis.com/auth/drive.file',
                    'https://www.googleapis.com/auth/drive.metadata.readonly'
                ]);
            }
            
            $token = $this->client->fetchAccessTokenWithAuthCode($authCode);
            $this->updateAccessToken($token);
            
            return $token;
        } catch (Exception $e) {
            throw new Exception("Failed to exchange authorization code: " . $e->getMessage());
        }
    }
    
    /**
     * Update access token in database
     * @param array $token Token data
     */
    private function updateAccessToken($token) {
        if ($this->db && isset($this->config['id'])) {
            $this->db->query(
                "UPDATE cloud_providers SET access_token = ?, refresh_token = ? WHERE id = ?",
                [
                    json_encode($token),
                    $token['refresh_token'] ?? null,
                    $this->config['id']
                ]
            );
        }
    }
    
    /**
     * Get folder ID for a given path
     * @param string $path File path
     * @return string Folder ID
     */
    private function getFolderId($path) {
        $folderPath = dirname($path);
        if ($folderPath === '.' || $folderPath === '/') {
            return 'root';
        }
        
        // For now, use root folder
        // In a full implementation, you'd create folder structure
        return 'root';
    }
    
    /**
     * Find file by name in Google Drive
     * @param string $filename File name
     * @return string|null File ID
     */
    private function findFileByName($filename) {
        $results = $this->service->files->listFiles([
            'q' => "name = '{$filename}' and trashed = false",
            'fields' => 'files(id)'
        ]);
        
        $files = $results->getFiles();
        return !empty($files) ? $files[0]->getId() : null;
    }
    
    /**
     * Get MIME type for file
     * @param string $filePath File path
     * @return string MIME type
     */
    private function getMimeType($filePath) {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'zip' => 'application/zip',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            'sql' => 'application/sql',
            'txt' => 'text/plain',
            'log' => 'text/plain'
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
}
?>

<?php
/**
 * S3-Compatible Storage Implementation
 * Handles AWS S3, MinIO, DigitalOcean Spaces, Wasabi, etc.
 */

require_once __DIR__ . '/../CloudStorage.class.php';

class S3Storage extends CloudStorage {
    private $s3Client;
    
    public function connect() {
        try {
            // Check if AWS SDK is available
            if (!class_exists('Aws\S3\S3Client')) {
                // Fallback to simple HTTP client for S3-compatible services
                return $this->connectWithHttpClient();
            }
            
            $config = [
                'version' => 'latest',
                'region' => $this->config['region'] ?? 'us-east-1',
                'credentials' => [
                    'key' => $this->config['access_key'],
                    'secret' => $this->config['secret_key']
                ]
            ];
            
            // Custom endpoint for S3-compatible services
            if (!empty($this->config['endpoint'])) {
                $config['endpoint'] = $this->config['endpoint'];
                $config['use_path_style_endpoint'] = true;
            }
            
            $this->s3Client = new Aws\S3\S3Client($config);
            
            // Test connection by listing buckets
            $this->s3Client->listBuckets();
            
            $this->logActivity('connect', "Connected to S3-compatible storage");
            return true;
            
        } catch (Exception $e) {
            $this->logActivity('connect_error', "Failed to connect to S3: " . $e->getMessage());
            return false;
        }
    }
    
    public function upload($localPath, $remotePath, $progressCallback = null) {
        try {
            if (!$this->s3Client) {
                if (!$this->connect()) {
                    return false;
                }
            }
            
            if (!file_exists($localPath)) {
                throw new Exception("Local file does not exist: {$localPath}");
            }
            
            $bucket = $this->config['bucket'];
            $fileSize = filesize($localPath);
            
            $this->logActivity('upload_start', "Starting S3 upload: {$localPath} -> {$remotePath}", [
                'bucket' => $bucket,
                'file_size' => $fileSize
            ]);
            
            $params = [
                'Bucket' => $bucket,
                'Key' => $remotePath,
                'SourceFile' => $localPath,
                'ACL' => 'private'
            ];
            
            // Add storage class if specified
            if (!empty($this->config['storage_class'])) {
                $params['StorageClass'] = $this->config['storage_class'];
            }
            
            $result = $this->s3Client->putObject($params);
            
            if ($result) {
                $this->logActivity('upload_success', "File uploaded to S3: {$remotePath}");
                return true;
            } else {
                throw new Exception("Failed to upload file to S3");
            }
            
        } catch (Exception $e) {
            $this->logActivity('upload_error', "S3 upload failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function download($remotePath, $localPath) {
        try {
            if (!$this->s3Client) {
                if (!$this->connect()) {
                    return false;
                }
            }
            
            $bucket = $this->config['bucket'];
            
            $this->logActivity('download_start', "Starting S3 download: {$remotePath} -> {$localPath}");
            
            // Create local directory if it doesn't exist
            $localDir = dirname($localPath);
            if (!is_dir($localDir)) {
                mkdir($localDir, 0755, true);
            }
            
            $result = $this->s3Client->getObject([
                'Bucket' => $bucket,
                'Key' => $remotePath,
                'SaveAs' => $localPath
            ]);
            
            if ($result) {
                $this->logActivity('download_success', "File downloaded from S3: {$localPath}");
                return true;
            } else {
                throw new Exception("Failed to download file from S3");
            }
            
        } catch (Exception $e) {
            $this->logActivity('download_error', "S3 download failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function delete($remotePath) {
        try {
            if (!$this->s3Client) {
                if (!$this->connect()) {
                    return false;
                }
            }
            
            $bucket = $this->config['bucket'];
            
            $this->logActivity('delete_start', "Deleting S3 object: {$remotePath}");
            
            $result = $this->s3Client->deleteObject([
                'Bucket' => $bucket,
                'Key' => $remotePath
            ]);
            
            if ($result) {
                $this->logActivity('delete_success', "S3 object deleted: {$remotePath}");
                return true;
            } else {
                throw new Exception("Failed to delete object from S3");
            }
            
        } catch (Exception $e) {
            $this->logActivity('delete_error', "S3 delete failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function listFiles($prefix = '') {
        try {
            if (!$this->s3Client) {
                if (!$this->connect()) {
                    return [];
                }
            }
            
            $bucket = $this->config['bucket'];
            $files = [];
            
            $params = [
                'Bucket' => $bucket
            ];
            
            if ($prefix) {
                $params['Prefix'] = $prefix;
            }
            
            $result = $this->s3Client->listObjectsV2($params);
            
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $files[] = [
                        'name' => basename($object['Key']),
                        'path' => $object['Key'],
                        'size' => $object['Size'],
                        'modified' => strtotime($object['LastModified']),
                        'etag' => $object['ETag']
                    ];
                }
            }
            
            return $files;
            
        } catch (Exception $e) {
            $this->logActivity('list_error', "Failed to list S3 objects: " . $e->getMessage());
            return [];
        }
    }
    
    public function testConnection() {
        try {
            if ($this->connect()) {
                $bucket = $this->config['bucket'];
                
                // Test by listing objects in bucket
                $result = $this->s3Client->listObjectsV2([
                    'Bucket' => $bucket,
                    'MaxKeys' => 1
                ]);
                
                return [
                    'success' => true,
                    'message' => 'S3 connection successful',
                    'bucket' => $bucket,
                    'objects_count' => $result['KeyCount'] ?? 0
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to connect to S3'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'S3 connection test failed: ' . $e->getMessage()
            ];
        }
    }
    
    public function getProviderName() {
        return 'S3-Compatible';
    }
    
    /**
     * Connect using simple HTTP client (fallback)
     */
    private function connectWithHttpClient() {
        // This would implement a simple HTTP client for S3-compatible services
        // For now, we'll just return false and require AWS SDK
        throw new Exception("AWS SDK not available. Please install aws/aws-sdk-php package.");
    }
    
    /**
     * Generate presigned URL for download
     * @param string $remotePath Remote file path
     * @param int $expiration Expiration time in seconds
     * @return string Presigned URL
     */
    public function getPresignedUrl($remotePath, $expiration = 3600) {
        try {
            if (!$this->s3Client) {
                if (!$this->connect()) {
                    return false;
                }
            }
            
            $bucket = $this->config['bucket'];
            
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $bucket,
                'Key' => $remotePath
            ]);
            
            $request = $this->s3Client->createPresignedRequest($cmd, "+{$expiration} seconds");
            
            return (string) $request->getUri();
            
        } catch (Exception $e) {
            $this->logActivity('presigned_url_error', "Failed to generate presigned URL: " . $e->getMessage());
            return false;
        }
    }
}
?>

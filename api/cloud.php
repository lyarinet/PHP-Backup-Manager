<?php
/**
 * Cloud Storage API
 * Handles cloud upload, download, and management operations
 */

define('BACKUP_MANAGER', true);
require_once '../config.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$db = new Database();
$backupManager = new BackupManager($db);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'upload':
            handleUpload();
            break;
            
        case 'download':
            handleDownload();
            break;
            
        case 'delete':
            handleDelete();
            break;
            
        case 'status':
            handleStatus();
            break;
            
        case 'list':
            handleList();
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleUpload() {
    global $backupManager, $db;
    
    $historyId = $_POST['history_id'] ?? null;
    $providerId = $_POST['provider_id'] ?? null;
    
    if (!$historyId) {
        throw new Exception('History ID is required');
    }
    
    $result = $backupManager->uploadToCloud($historyId, $providerId);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Upload started successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Upload failed'
        ]);
    }
}

function handleDownload() {
    global $db;
    
    $uploadId = $_GET['upload_id'] ?? null;
    
    if (!$uploadId) {
        throw new Exception('Upload ID is required');
    }
    
    // Get upload details
    $upload = $db->fetch("
        SELECT cu.*, cp.*, bh.*, bc.name as config_name 
        FROM cloud_uploads cu
        JOIN cloud_providers cp ON cu.provider_id = cp.id
        JOIN backup_history bh ON cu.history_id = bh.id
        JOIN backup_configs bc ON bh.config_id = bc.id
        WHERE cu.id = ?
    ", [$uploadId]);
    
    if (!$upload) {
        throw new Exception('Upload not found');
    }
    
    if ($upload['upload_status'] !== 'completed') {
        throw new Exception('Upload not completed');
    }
    
    // Initialize cloud storage
    $storage = getCloudStorage($upload);
    if (!$storage->connect()) {
        throw new Exception('Failed to connect to cloud storage');
    }
    
    // Download file
    $tempFile = tempnam(sys_get_temp_dir(), 'backup_download_');
    $result = $storage->download($upload['remote_path'], $tempFile);
    
    if ($result) {
        // Send file to browser
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($upload['remote_path']) . '"');
        header('Content-Length: ' . filesize($tempFile));
        readfile($tempFile);
        unlink($tempFile);
        exit;
    } else {
        throw new Exception('Download failed');
    }
}

function handleDelete() {
    global $db;
    
    $uploadId = $_POST['upload_id'] ?? null;
    
    if (!$uploadId) {
        throw new Exception('Upload ID is required');
    }
    
    // Get upload details
    $upload = $db->fetch("
        SELECT cu.*, cp.* 
        FROM cloud_uploads cu
        JOIN cloud_providers cp ON cu.provider_id = cp.id
        WHERE cu.id = ?
    ", [$uploadId]);
    
    if (!$upload) {
        throw new Exception('Upload not found');
    }
    
    // Initialize cloud storage
    $storage = getCloudStorage($upload);
    if (!$storage->connect()) {
        throw new Exception('Failed to connect to cloud storage');
    }
    
    // Delete file from cloud
    $result = $storage->delete($upload['remote_path']);
    
    if ($result) {
        // Update upload status
        $db->update('cloud_uploads', [
            'upload_status' => 'deleted',
            'upload_end' => date('Y-m-d H:i:s')
        ], ['id' => $uploadId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'File deleted successfully'
        ]);
    } else {
        throw new Exception('Delete failed');
    }
}

function handleStatus() {
    global $db;
    
    $historyId = $_GET['history_id'] ?? null;
    
    if (!$historyId) {
        throw new Exception('History ID is required');
    }
    
    // Get cloud upload status
    $uploads = $db->fetchAll("
        SELECT cu.*, cp.name as provider_name, cp.type as provider_type
        FROM cloud_uploads cu
        JOIN cloud_providers cp ON cu.provider_id = cp.id
        WHERE cu.history_id = ?
        ORDER BY cu.created_at DESC
    ", [$historyId]);
    
    echo json_encode([
        'success' => true,
        'uploads' => $uploads
    ]);
}

function handleList() {
    global $db;
    
    $providerId = $_GET['provider_id'] ?? null;
    $limit = $_GET['limit'] ?? 50;
    $offset = $_GET['offset'] ?? 0;
    
    $whereClause = '';
    $params = [];
    
    if ($providerId) {
        $whereClause = 'WHERE cu.provider_id = ?';
        $params[] = $providerId;
    }
    
    // Get cloud uploads
    $uploads = $db->fetchAll("
        SELECT cu.*, cp.name as provider_name, cp.type as provider_type,
               bc.name as config_name, bh.start_time, bh.status as backup_status
        FROM cloud_uploads cu
        JOIN cloud_providers cp ON cu.provider_id = cp.id
        JOIN backup_history bh ON cu.history_id = bh.id
        JOIN backup_configs bc ON bh.config_id = bc.id
        {$whereClause}
        ORDER BY cu.created_at DESC
        LIMIT ? OFFSET ?
    ", array_merge($params, [$limit, $offset]));
    
    echo json_encode([
        'success' => true,
        'uploads' => $uploads
    ]);
}

function getCloudStorage($provider) {
    $type = $provider['type'];
    
    switch ($type) {
        case 'ftp':
            require_once __DIR__ . '/../includes/storage/FTPStorage.class.php';
            return new FTPStorage($provider, $GLOBALS['db']);
            
        case 's3':
            require_once __DIR__ . '/../includes/storage/S3Storage.class.php';
            return new S3Storage($provider, $GLOBALS['db']);
            
        case 'google_drive':
            require_once __DIR__ . '/../includes/storage/GoogleDriveStorage.class.php';
            return new GoogleDriveStorage($provider, $GLOBALS['db']);
            
        default:
            throw new Exception("Unsupported cloud storage type: {$type}");
    }
}
?>

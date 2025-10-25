<?php
/**
 * Backup API Endpoints
 * REST API for backup operations
 */

// Start output buffering to prevent any HTML output
ob_start();

// Disable error display to prevent HTML errors
ini_set('display_errors', 0);
ini_set('log_errors', 1);

define('BACKUP_MANAGER', true);
require_once dirname(__DIR__) . '/config.php';

$db = new Database();
$auth = new Auth($db);
$backupManager = new BackupManager($db);

// Require authentication
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

$user = $auth->getCurrentUser();

// Set JSON header
header('Content-Type: application/json');

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];

// Parse JSON input for POST requests
$input = null;
if ($method === 'POST' && $_SERVER['CONTENT_TYPE'] === 'application/json') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
} else {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
}

// Handle different actions
try {
    switch ($action) {
        case 'start':
            handleStartBackup();
            break;
        case 'status':
            handleGetStatus();
            break;
        case 'list':
            handleListBackups();
            break;
        case 'delete':
            handleDeleteBackup();
            break;
        case 'download':
            handleDownloadBackup();
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    error_log('Backup API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

function handleStartBackup() {
    global $backupManager, $user, $auth, $input, $db;
    
    // Debug logging
    $logFile = '/var/www/html/backup_package/logs/api_debug.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "API START BACKUP CALLED\n", FILE_APPEND);
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Input: " . print_r($input, true) . "\n", FILE_APPEND);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }
    
    $configId = $input['config_id'] ?? null;
    
    if (!$configId) {
        throw new Exception('Configuration ID required');
    }
    
    // Log the config ID being executed
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Executing backup for config ID: {$configId}\n", FILE_APPEND);
    
    // Validate configuration exists
    $config = $backupManager->getConfigs();
    $configExists = false;
    foreach ($config as $c) {
        if ($c['id'] == $configId) {
            $configExists = true;
            break;
        }
    }
    
    if (!$configExists) {
        throw new Exception('Configuration not found');
    }
    
    // Start backup and get history ID
    $historyId = $backupManager->startBackup($configId, $user['id']);
    
    // For now, run backup synchronously to test progress tracking
    // TODO: Make this truly asynchronous later
    try {
        $backupManager->executeBackup($configId, $user['id'], $historyId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Backup completed successfully',
            'history_id' => $historyId
        ]);
    } catch (Exception $e) {
        // Update backup status to failed
        $db->update('backup_history', [
            'status' => 'failed',
            'end_time' => date('Y-m-d H:i:s'),
            'error_log' => $e->getMessage()
        ], 'id = ?', [$historyId]);
        
        // Debug logging
        error_log("Backup API - Backup failed: " . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'message' => 'Backup failed: ' . $e->getMessage(),
            'history_id' => $historyId
        ]);
    }
}

function handleGetStatus() {
    global $db, $backupManager;
    
    $historyId = $_GET['history_id'] ?? null;
    
    if (!$historyId) {
        throw new Exception('History ID required');
    }
    
    $backup = $db->fetch("
        SELECT 
            bh.*,
            bc.name as config_name,
            bc.backup_type
        FROM backup_history bh
        JOIN backup_configs bc ON bh.config_id = bc.id
        WHERE bh.id = ?
    ", [$historyId]);
    
    if (!$backup) {
        throw new Exception('Backup not found');
    }
    
    echo json_encode([
        'success' => true,
        'backup' => $backup
    ]);
}

function handleListBackups() {
    global $backupManager, $db;
    
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = (int)($_GET['offset'] ?? 0);
    
    $backups = $backupManager->getRecentBackups($limit);
    
    echo json_encode([
        'success' => true,
        'backups' => $backups,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

function handleDeleteBackup() {
    global $db, $user, $backupManager;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        throw new Exception('Method not allowed');
    }
    
    $historyId = $_GET['history_id'] ?? null;
    
    if (!$historyId) {
        throw new Exception('History ID required');
    }
    
    // Get backup info
    $backup = $db->fetch("SELECT * FROM backup_history WHERE id = ?", [$historyId]);
    if (!$backup) {
        throw new Exception('Backup not found');
    }
    
    // Get backup files
    $files = $db->fetchAll("SELECT * FROM backup_files WHERE history_id = ?", [$historyId]);
    
    // Delete physical files
    foreach ($files as $file) {
        if (file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }
    }
    
    // Delete database records
    $db->delete('backup_files', 'history_id = ?', [$historyId]);
    $db->delete('backup_history', 'id = ?', [$historyId]);
    
    // Log activity
    $db->logActivity($user['id'], 'delete_backup', "Deleted backup ID: {$historyId}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Backup deleted successfully'
    ]);
}

function handleDownloadBackup() {
    global $db, $user;
    
    $fileId = $_GET['file_id'] ?? null;
    
    if (!$fileId) {
        throw new Exception('File ID required');
    }
    
    $file = $db->fetch("SELECT * FROM backup_files WHERE id = ?", [$fileId]);
    
    if (!$file || !file_exists($file['file_path'])) {
        throw new Exception('File not found');
    }
    
    // Set download headers
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($file['file_path']) . '"');
    header('Content-Length: ' . filesize($file['file_path']));
    
    // Output file
    readfile($file['file_path']);
    exit;
}

// Flush output buffer
ob_end_flush();

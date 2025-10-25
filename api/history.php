<?php
/**
 * History API Endpoints
 * REST API for backup history management
 */

define('BACKUP_MANAGER', true);
require_once '../config.php';

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
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Handle different actions
try {
    switch ($action) {
        case 'list':
            handleListHistory();
            break;
        case 'get':
            handleGetHistory();
            break;
        case 'delete':
            handleDeleteHistory();
            break;
        case 'logs':
            handleGetLogs();
            break;
        case 'stats':
            handleGetStats();
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleListHistory() {
    global $db;
    
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = (int)($_GET['offset'] ?? 0);
    $status = $_GET['status'] ?? '';
    $configId = $_GET['config_id'] ?? '';
    
    $whereConditions = [];
    $params = [];
    
    if ($status) {
        $whereConditions[] = "bh.status = ?";
        $params[] = $status;
    }
    
    if ($configId) {
        $whereConditions[] = "bh.config_id = ?";
        $params[] = $configId;
    }
    
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }
    
    $sql = "
        SELECT 
            bh.*,
            bc.name as config_name,
            bc.backup_type,
            COUNT(bf.id) as file_count
        FROM backup_history bh
        JOIN backup_configs bc ON bh.config_id = bc.id
        LEFT JOIN backup_files bf ON bh.id = bf.history_id
        {$whereClause}
        GROUP BY bh.id
        ORDER BY bh.start_time DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $history = $db->fetchAll($sql, $params);
    
    // Get total count
    $countSql = "
        SELECT COUNT(*) as total
        FROM backup_history bh
        JOIN backup_configs bc ON bh.config_id = bc.id
        {$whereClause}
    ";
    
    $countParams = array_slice($params, 0, -2); // Remove limit and offset
    $totalResult = $db->fetch($countSql, $countParams);
    $total = $totalResult['total'];
    
    echo json_encode([
        'success' => true,
        'history' => $history,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

function handleGetHistory() {
    global $db;
    
    $historyId = $_GET['history_id'] ?? null;
    
    if (!$historyId) {
        throw new Exception('History ID required');
    }
    
    $history = $db->fetch("
        SELECT 
            bh.*,
            bc.name as config_name,
            bc.backup_type
        FROM backup_history bh
        JOIN backup_configs bc ON bh.config_id = bc.id
        WHERE bh.id = ?
    ", [$historyId]);
    
    if (!$history) {
        throw new Exception('Backup history not found');
    }
    
    // Get backup files
    $files = $db->fetchAll("
        SELECT * FROM backup_files 
        WHERE history_id = ? 
        ORDER BY created_at ASC
    ", [$historyId]);
    
    $history['files'] = $files;
    
    echo json_encode([
        'success' => true,
        'history' => $history
    ]);
}

function handleDeleteHistory() {
    global $db, $user;
    
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
        throw new Exception('Backup history not found');
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
    $db->logActivity($user['id'], 'delete_backup_history', "Deleted backup history ID: {$historyId}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Backup history deleted successfully'
    ]);
}

function handleGetLogs() {
    global $db;
    
    $historyId = $_GET['history_id'] ?? null;
    
    if (!$historyId) {
        throw new Exception('History ID required');
    }
    
    $backup = $db->fetch("SELECT * FROM backup_history WHERE id = ?", [$historyId]);
    
    if (!$backup) {
        throw new Exception('Backup history not found');
    }
    
    // Get logs from log file (simplified - in production, you'd want more sophisticated log parsing)
    $logFile = LOG_DIR . '/backup_manager.log';
    $logs = [];
    
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        $logLines = explode("\n", $logContent);
        
        // Filter logs related to this backup (simplified approach)
        foreach ($logLines as $line) {
            if (strpos($line, $backup['start_time']) !== false || 
                strpos($line, 'backup') !== false) {
                $logs[] = $line;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'error_log' => $backup['error_log']
    ]);
}

function handleGetStats() {
    global $backupManager;
    
    $stats = $backupManager->getBackupStats();
    
    // Get additional statistics
    $db = new Database();
    
    // Success rate by backup type
    $typeStats = $db->fetchAll("
        SELECT 
            bc.backup_type,
            COUNT(*) as total,
            SUM(CASE WHEN bh.status = 'completed' THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN bh.status = 'failed' THEN 1 ELSE 0 END) as failed
        FROM backup_history bh
        JOIN backup_configs bc ON bh.config_id = bc.id
        GROUP BY bc.backup_type
    ");
    
    // Recent activity
    $recentActivity = $db->fetchAll("
        SELECT 
            al.action,
            al.details,
            al.timestamp,
            u.username
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.timestamp DESC
        LIMIT 10
    ");
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'type_stats' => $typeStats,
        'recent_activity' => $recentActivity
    ]);
}

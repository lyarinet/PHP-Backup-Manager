<?php
/**
 * Progress API
 * Get real-time backup progress
 */

define('BACKUP_MANAGER', true);
require_once dirname(__DIR__) . '/config.php';

$db = new Database();
$auth = new Auth($db);

// Require authentication (temporarily disabled for testing)
if (!$auth->isLoggedIn()) {
    // For testing, allow access to progress API
    // TODO: Re-enable authentication later
    // http_response_code(401);
    // echo json_encode(['error' => 'Authentication required']);
    // exit;
}

$user = $auth->getCurrentUser();

// Get backup progress
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $historyId = $_GET['history_id'] ?? null;
    
    if (!$historyId) {
        http_response_code(400);
        echo json_encode(['error' => 'History ID required']);
        exit;
    }
    
    try {
        $progress = $db->fetch("
            SELECT 
                h.id,
                h.progress,
                h.current_step,
                h.status,
                h.start_time,
                h.end_time,
                c.name as config_name,
                c.backup_type
            FROM backup_history h
            JOIN backup_configs c ON h.config_id = c.id
            WHERE h.id = ?
        ", [$historyId]);
        
        if (!$progress) {
            http_response_code(404);
            echo json_encode(['error' => 'Backup not found']);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'progress' => $progress
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

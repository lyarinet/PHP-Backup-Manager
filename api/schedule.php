<?php
/**
 * Schedule API Endpoints
 * REST API for backup schedule management
 */

define('BACKUP_MANAGER', true);
require_once dirname(__DIR__) . '/config.php';

$db = new Database();
$auth = new Auth($db);

// Require authentication
$auth->requireLogin();

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
            handleListSchedules();
            break;
        case 'create':
            handleCreateSchedule();
            break;
        case 'update':
            handleUpdateSchedule();
            break;
        case 'delete':
            handleDeleteSchedule();
            break;
        case 'toggle':
            handleToggleSchedule();
            break;
        case 'test':
            handleTestCronExpression();
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

function handleListSchedules() {
    global $db;
    
    $schedules = $db->fetchAll("
        SELECT 
            bs.*,
            bc.name as config_name,
            bc.backup_type
        FROM backup_schedules bs
        JOIN backup_configs bc ON bs.config_id = bc.id
        ORDER BY bs.created_at DESC
    ");
    
    echo json_encode([
        'success' => true,
        'schedules' => $schedules
    ]);
}

function handleCreateSchedule() {
    global $db, $user;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $configId = $input['config_id'] ?? null;
    $cronExpression = $input['cron_expression'] ?? '';
    $enabled = $input['enabled'] ?? true;
    
    if (!$configId || empty($cronExpression)) {
        throw new Exception('Configuration ID and cron expression are required');
    }
    
    // Validate cron expression
    if (!isValidCronExpression($cronExpression)) {
        throw new Exception('Invalid cron expression');
    }
    
    // Check if configuration exists
    $config = $db->fetch("SELECT id FROM backup_configs WHERE id = ?", [$configId]);
    if (!$config) {
        throw new Exception('Configuration not found');
    }
    
    // Calculate next run time
    $nextRun = calculateNextRun($cronExpression);
    
    $scheduleId = $db->insert('backup_schedules', [
        'config_id' => $configId,
        'cron_expression' => $cronExpression,
        'next_run' => $nextRun,
        'enabled' => $enabled ? 1 : 0
    ]);
    
    $db->logActivity($user['id'], 'create_schedule', "Created schedule for config ID: {$configId}");
    
    echo json_encode([
        'success' => true,
        'schedule_id' => $scheduleId,
        'message' => 'Schedule created successfully'
    ]);
}

function handleUpdateSchedule() {
    global $db, $user;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        throw new Exception('Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $scheduleId = $input['schedule_id'] ?? null;
    $cronExpression = $input['cron_expression'] ?? '';
    $enabled = $input['enabled'] ?? true;
    
    if (!$scheduleId || empty($cronExpression)) {
        throw new Exception('Schedule ID and cron expression are required');
    }
    
    // Validate cron expression
    if (!isValidCronExpression($cronExpression)) {
        throw new Exception('Invalid cron expression');
    }
    
    // Check if schedule exists
    $schedule = $db->fetch("SELECT id FROM backup_schedules WHERE id = ?", [$scheduleId]);
    if (!$schedule) {
        throw new Exception('Schedule not found');
    }
    
    // Calculate next run time
    $nextRun = calculateNextRun($cronExpression);
    
    $db->update('backup_schedules', [
        'cron_expression' => $cronExpression,
        'next_run' => $nextRun,
        'enabled' => $enabled ? 1 : 0
    ], 'id = ?', [$scheduleId]);
    
    $db->logActivity($user['id'], 'update_schedule', "Updated schedule ID: {$scheduleId}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Schedule updated successfully'
    ]);
}

function handleDeleteSchedule() {
    global $db, $user;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        throw new Exception('Method not allowed');
    }
    
    $scheduleId = $_GET['schedule_id'] ?? null;
    
    if (!$scheduleId) {
        throw new Exception('Schedule ID required');
    }
    
    $db->delete('backup_schedules', 'id = ?', [$scheduleId]);
    
    $db->logActivity($user['id'], 'delete_schedule', "Deleted schedule ID: {$scheduleId}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Schedule deleted successfully'
    ]);
}

function handleToggleSchedule() {
    global $db, $user;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $scheduleId = $input['schedule_id'] ?? null;
    $enabled = $input['enabled'] ?? true;
    
    if (!$scheduleId) {
        throw new Exception('Schedule ID required');
    }
    
    $db->update('backup_schedules', [
        'enabled' => $enabled ? 1 : 0
    ], 'id = ?', [$scheduleId]);
    
    $db->logActivity($user['id'], 'toggle_schedule', "Toggled schedule ID: {$scheduleId} to " . ($enabled ? 'enabled' : 'disabled'));
    
    echo json_encode([
        'success' => true,
        'message' => 'Schedule status updated successfully'
    ]);
}

function handleTestCronExpression() {
    $cronExpression = $_GET['expression'] ?? '';
    
    if (empty($cronExpression)) {
        throw new Exception('Cron expression required');
    }
    
    if (!isValidCronExpression($cronExpression)) {
        throw new Exception('Invalid cron expression');
    }
    
    // Calculate next few run times
    $nextRuns = [];
    $now = new DateTime();
    
    for ($i = 0; $i < 5; $i++) {
        $nextRun = calculateNextRun($cronExpression, $now);
        $nextRuns[] = $nextRun->format('Y-m-d H:i:s');
        $now = clone $nextRun;
        $now->add(new DateInterval('PT1M')); // Add 1 minute to find next occurrence
    }
    
    echo json_encode([
        'success' => true,
        'next_runs' => $nextRuns
    ]);
}

/**
 * Calculate next run time for a cron expression
 */
function calculateNextRun($cronExpression, $fromTime = null) {
    if (!$fromTime) {
        $fromTime = new DateTime();
    }
    
    $nextRun = clone $fromTime;
    
    // Simple implementation: check next 24 hours
    for ($i = 0; $i < 24 * 60; $i++) { // Check every minute for 24 hours
        $nextRun->add(new DateInterval('PT1M'));
        if (isCronTime($cronExpression, $nextRun)) {
            return $nextRun;
        }
    }
    
    // Fallback: next day at same time
    $nextRun = clone $fromTime;
    $nextRun->add(new DateInterval('P1D'));
    return $nextRun;
}

/**
 * Simple cron expression parser
 */
function isCronTime($cronExpression, $dateTime) {
    $parts = explode(' ', trim($cronExpression));
    if (count($parts) !== 5) {
        return false;
    }
    
    $minute = (int)$dateTime->format('i');
    $hour = (int)$dateTime->format('H');
    $day = (int)$dateTime->format('j');
    $month = (int)$dateTime->format('n');
    $dayOfWeek = (int)$dateTime->format('w'); // 0 = Sunday
    
    return (
        matchesCronField($parts[0], $minute) &&
        matchesCronField($parts[1], $hour) &&
        matchesCronField($parts[2], $day) &&
        matchesCronField($parts[3], $month) &&
        matchesCronField($parts[4], $dayOfWeek)
    );
}

/**
 * Check if a value matches a cron field
 */
function matchesCronField($field, $value) {
    if ($field === '*') {
        return true;
    }
    
    // Handle comma-separated values
    if (strpos($field, ',') !== false) {
        $values = explode(',', $field);
        foreach ($values as $v) {
            if (matchesCronField(trim($v), $value)) {
                return true;
            }
        }
        return false;
    }
    
    // Handle ranges
    if (strpos($field, '-') !== false) {
        list($start, $end) = explode('-', $field, 2);
        return $value >= (int)$start && $value <= (int)$end;
    }
    
    // Handle step values
    if (strpos($field, '/') !== false) {
        list($range, $step) = explode('/', $field, 2);
        if ($range === '*') {
            return $value % (int)$step === 0;
        }
    }
    
    // Exact match
    return (int)$field === $value;
}

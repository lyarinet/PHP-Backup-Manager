<?php
/**
 * Configuration API Endpoints
 * REST API for backup configuration management
 */

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
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Handle different actions
try {
    switch ($action) {
        case 'list':
            handleListConfigs();
            break;
        case 'create':
            handleCreateConfig();
            break;
        case 'update':
            handleUpdateConfig();
            break;
        case 'delete':
            handleDeleteConfig();
            break;
        case 'get':
            handleGetConfig();
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

function handleListConfigs() {
    global $backupManager;
    
    $configs = $backupManager->getConfigs();
    
    echo json_encode([
        'success' => true,
        'configs' => $configs
    ]);
}

function handleCreateConfig() {
    global $backupManager, $user;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name = $input['name'] ?? '';
    $backupType = $input['backup_type'] ?? '';
    $configData = $input['config_data'] ?? [];
    
    if (empty($name) || empty($backupType) || empty($configData)) {
        throw new Exception('Name, backup type, and configuration data are required');
    }
    
    // Validate backup type
    if (!in_array($backupType, ['files', 'mysql', 'postgresql'])) {
        throw new Exception('Invalid backup type');
    }
    
    // Validate configuration data based on type
    validateConfigData($backupType, $configData);
    
    $configId = $backupManager->createConfig($name, $backupType, $configData, $user['id']);
    
    echo json_encode([
        'success' => true,
        'config_id' => $configId,
        'message' => 'Configuration created successfully'
    ]);
}

function handleUpdateConfig() {
    global $backupManager, $user;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }
    
    // Handle both JSON input and form data
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['CONTENT_TYPE'] !== 'application/json') {
        // Form data from POST request
        $input = $_POST;
    } else {
        // JSON input from PUT request or JSON POST
        $input = json_decode(file_get_contents('php://input'), true);
    }
    
    $configId = $input['config_id'] ?? null;
    $name = $input['name'] ?? '';
    $backupType = $input['backup_type'] ?? '';
    
    // Parse configuration data based on backup type
    $configData = [];
    if ($backupType === 'files') {
        $configData = [
            'files' => !empty($input['files']) ? explode("\n", $input['files']) : []
        ];
    } elseif ($backupType === 'mysql') {
        $configData = [
            'host' => $input['mysql_host'] ?? '',
            'username' => $input['mysql_username'] ?? '',
            'password' => $input['mysql_password'] ?? '',
            'databases' => !empty($input['mysql_databases']) ? explode("\n", $input['mysql_databases']) : []
        ];
    } elseif ($backupType === 'postgresql') {
        $configData = [
            'host' => $input['postgres_host'] ?? '',
            'username' => $input['postgres_username'] ?? '',
            'password' => $input['postgres_password'] ?? '',
            'databases' => !empty($input['postgres_databases']) ? explode("\n", $input['postgres_databases']) : []
        ];
    }
    
    if (!$configId || empty($name) || empty($configData)) {
        throw new Exception('Configuration ID, name, and configuration data are required');
    }
    
    // Get existing config to validate
    $existingConfig = $db->fetch("SELECT backup_type FROM backup_configs WHERE id = ?", [$configId]);
    if (!$existingConfig) {
        throw new Exception('Configuration not found');
    }
    
    // Validate configuration data
    validateConfigData($backupType, $configData);
    
    // Debug logging
    $logFile = APP_ROOT . '/debug_update.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "UPDATE CONFIG - ID: $configId, Name: $name, Type: $backupType\n", FILE_APPEND);
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "UPDATE CONFIG - Data: " . print_r($configData, true) . "\n", FILE_APPEND);
    
    $backupManager->updateConfig($configId, $name, $configData, $user['id'], $backupType);
    
    // Verify update
    $updated = $db->fetch("SELECT config_data FROM backup_configs WHERE id = ?", [$configId]);
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "UPDATE CONFIG - Stored data: " . $updated['config_data'] . "\n", FILE_APPEND);
    
    echo json_encode([
        'success' => true,
        'message' => 'Configuration updated successfully'
    ]);
}

function handleDeleteConfig() {
    global $backupManager, $user;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        throw new Exception('Method not allowed');
    }
    
    $configId = $_GET['config_id'] ?? null;
    
    if (!$configId) {
        throw new Exception('Configuration ID required');
    }
    
    $backupManager->deleteConfig($configId, $user['id']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Configuration deleted successfully'
    ]);
}

function handleGetConfig() {
    global $db;
    
    $configId = $_GET['config_id'] ?? null;
    
    if (!$configId) {
        throw new Exception('Configuration ID required');
    }
    
    $config = $db->fetch("SELECT * FROM backup_configs WHERE id = ?", [$configId]);
    
    if (!$config) {
        throw new Exception('Configuration not found');
    }
    
    // Decrypt sensitive data
    $configData = json_decode($config['config_data'], true);
    $backupManager = new BackupManager($db);
    $config['config_data'] = $backupManager->decryptConfigData($configData);
    
    echo json_encode([
        'success' => true,
        'config' => $config
    ]);
}

function validateConfigData($backupType, $configData) {
    switch ($backupType) {
        case 'files':
            if (empty($configData['files']) || !is_array($configData['files'])) {
                throw new Exception('Files configuration must include files array');
            }
            
            // Validate each file path
            foreach ($configData['files'] as $filePath) {
                if (!isPathSafe($filePath)) {
                    throw new Exception("Unsafe file path: {$filePath}");
                }
            }
            break;
            
        case 'mysql':
            if (empty($configData['username']) || empty($configData['databases'])) {
                throw new Exception('MySQL configuration must include username and databases');
            }
            
            if (!is_array($configData['databases'])) {
                throw new Exception('MySQL databases must be an array');
            }
            
            if (empty($configData['host'])) {
                $configData['host'] = 'localhost';
            }
            break;
            
        case 'postgresql':
            if (empty($configData['username']) || empty($configData['databases'])) {
                throw new Exception('PostgreSQL configuration must include username and databases');
            }
            
            if (!is_array($configData['databases'])) {
                throw new Exception('PostgreSQL databases must be an array');
            }
            
            if (empty($configData['host'])) {
                $configData['host'] = 'localhost';
            }
            break;
            
        default:
            throw new Exception('Invalid backup type');
    }
}

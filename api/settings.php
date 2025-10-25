<?php
/**
 * Settings API Endpoints
 * REST API for system settings management
 */

define('BACKUP_MANAGER', true);
require_once '../config.php';

$db = new Database();
$auth = new Auth($db);

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
        case 'get':
            handleGetSettings();
            break;
        case 'update':
            handleUpdateSettings();
            break;
        case 'users':
            handleUserManagement();
            break;
        case 'system':
            handleSystemInfo();
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

function handleGetSettings() {
    global $db;
    
    $settings = $db->fetchAll("SELECT setting_key, setting_value FROM settings ORDER BY setting_key");
    
    $settingsArray = [];
    foreach ($settings as $setting) {
        $settingsArray[$setting['setting_key']] = $setting['setting_value'];
    }
    
    echo json_encode([
        'success' => true,
        'settings' => $settingsArray
    ]);
}

function handleUpdateSettings() {
    global $db, $user;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $settings = $input['settings'] ?? [];
    
    if (empty($settings)) {
        throw new Exception('Settings data required');
    }
    
    // Validate settings
    validateSettings($settings);
    
    // Update settings
    foreach ($settings as $key => $value) {
        $db->setSetting($key, $value);
    }
    
    // Log activity
    $db->logActivity($user['id'], 'update_settings', 'Updated system settings');
    
    echo json_encode([
        'success' => true,
        'message' => 'Settings updated successfully'
    ]);
}

function handleUserManagement() {
    global $auth, $user;
    
    $subAction = $_GET['sub_action'] ?? '';
    
    switch ($subAction) {
        case 'list':
            $users = $auth->getAllUsers();
            echo json_encode([
                'success' => true,
                'users' => $users
            ]);
            break;
            
        case 'create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? '';
            $email = $input['email'] ?? '';
            $role = $input['role'] ?? 'user';
            
            if (empty($username) || empty($password)) {
                throw new Exception('Username and password are required');
            }
            
            $userId = $auth->createUser($username, $password, $email, $role);
            
            echo json_encode([
                'success' => true,
                'user_id' => $userId,
                'message' => 'User created successfully'
            ]);
            break;
            
        case 'update':
            if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
                throw new Exception('Method not allowed');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $userId = $input['user_id'] ?? null;
            $userData = $input['user_data'] ?? [];
            
            if (!$userId || empty($userData)) {
                throw new Exception('User ID and user data are required');
            }
            
            $auth->updateUser($userId, $userData);
            
            echo json_encode([
                'success' => true,
                'message' => 'User updated successfully'
            ]);
            break;
            
        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
                throw new Exception('Method not allowed');
            }
            
            $userId = $_GET['user_id'] ?? null;
            
            if (!$userId) {
                throw new Exception('User ID required');
            }
            
            $auth->deleteUser($userId);
            
            echo json_encode([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
            break;
            
        default:
            throw new Exception('Invalid sub-action');
    }
}

function handleSystemInfo() {
    $systemInfo = [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'os' => PHP_OS,
        'disk_free_space' => disk_free_space('/'),
        'disk_total_space' => disk_total_space('/'),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'requirements' => checkSystemRequirements()
    ];
    
    echo json_encode([
        'success' => true,
        'system' => $systemInfo
    ]);
}

function validateSettings($settings) {
    $allowedSettings = [
        'backup_directory',
        'retention_days',
        'max_backup_size',
        'compression_level',
        'email_notifications',
        'email_smtp_host',
        'email_smtp_port',
        'email_smtp_user',
        'email_smtp_pass',
        'email_from',
        'email_to'
    ];
    
    foreach ($settings as $key => $value) {
        if (!in_array($key, $allowedSettings)) {
            throw new Exception("Invalid setting: {$key}");
        }
        
        // Validate specific settings
        switch ($key) {
            case 'retention_days':
                if (!is_numeric($value) || $value < 1) {
                    throw new Exception('Retention days must be a positive number');
                }
                break;
                
            case 'email_smtp_port':
                if (!is_numeric($value) || $value < 1 || $value > 65535) {
                    throw new Exception('SMTP port must be a valid port number');
                }
                break;
                
            case 'email_from':
            case 'email_to':
                if (!empty($value) && !isValidEmail($value)) {
                    throw new Exception("Invalid email address: {$value}");
                }
                break;
        }
    }
}

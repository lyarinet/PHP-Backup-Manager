<?php
/**
 * Settings Page
 * Global configuration, user management, and system settings
 */

define('BACKUP_MANAGER', true);
require_once 'config.php';

$db = new Database();
$auth = new Auth($db);

// Require authentication
$auth->requireLogin();

$user = $auth->getCurrentUser();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_settings':
                handleUpdateSettings();
                break;
            case 'create_user':
                handleCreateUser();
                break;
            case 'update_user':
                handleUpdateUser();
                break;
            case 'delete_user':
                handleDeleteUser();
                break;
            case 'change_password':
                handleChangePassword();
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

function handleUpdateSettings() {
    global $db, $user;
    
    $settings = [
        'backup_directory' => sanitizeInput($_POST['backup_directory'] ?? ''),
        'retention_days' => (int)($_POST['retention_days'] ?? 7),
        'max_backup_size' => sanitizeInput($_POST['max_backup_size'] ?? '10G'),
        'compression_level' => (int)($_POST['compression_level'] ?? 6),
        'email_notifications' => (int)($_POST['email_notifications'] ?? 0),
        'email_smtp_host' => sanitizeInput($_POST['email_smtp_host'] ?? ''),
        'email_smtp_port' => (int)($_POST['email_smtp_port'] ?? 587),
        'email_smtp_user' => sanitizeInput($_POST['email_smtp_user'] ?? ''),
        'email_smtp_pass' => $_POST['email_smtp_pass'] ?? '',
        'email_from' => sanitizeInput($_POST['email_from'] ?? ''),
        'email_to' => sanitizeInput($_POST['email_to'] ?? '')
    ];
    
    // Validate settings
    if ($settings['retention_days'] < 1) {
        throw new Exception('Retention days must be at least 1');
    }
    
    if ($settings['compression_level'] < 1 || $settings['compression_level'] > 9) {
        throw new Exception('Compression level must be between 1 and 9');
    }
    
    if ($settings['email_smtp_port'] < 1 || $settings['email_smtp_port'] > 65535) {
        throw new Exception('SMTP port must be between 1 and 65535');
    }
    
    if (!empty($settings['email_from']) && !isValidEmail($settings['email_from'])) {
        throw new Exception('Invalid email address for sender');
    }
    
    if (!empty($settings['email_to']) && !isValidEmail($settings['email_to'])) {
        throw new Exception('Invalid email address for recipient');
    }
    
    // Update settings
    foreach ($settings as $key => $value) {
        $db->setSetting($key, $value);
    }
    
    $db->logActivity($user['id'], 'update_settings', 'Updated system settings');
    
    header('Location: settings.php?success=settings_updated');
    exit;
}

function handleCreateUser() {
    global $auth, $user;
    
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = sanitizeInput($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'user';
    
    if (empty($username) || empty($password)) {
        throw new Exception('Username and password are required');
    }
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        throw new Exception('Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters');
    }
    
    if (!empty($email) && !isValidEmail($email)) {
        throw new Exception('Invalid email address');
    }
    
    $auth->createUser($username, $password, $email, $role);
    
    header('Location: settings.php?success=user_created');
    exit;
}

function handleUpdateUser() {
    global $auth, $user;
    
    $userId = (int)($_POST['user_id'] ?? 0);
    $userData = [
        'username' => sanitizeInput($_POST['username'] ?? ''),
        'email' => sanitizeInput($_POST['email'] ?? ''),
        'role' => $_POST['role'] ?? 'user'
    ];
    
    if (empty($userData['username'])) {
        throw new Exception('Username is required');
    }
    
    if (!empty($userData['email']) && !isValidEmail($userData['email'])) {
        throw new Exception('Invalid email address');
    }
    
    $auth->updateUser($userId, $userData);
    
    header('Location: settings.php?success=user_updated');
    exit;
}

function handleDeleteUser() {
    global $auth, $user;
    
    $userId = (int)($_POST['user_id'] ?? 0);
    
    if ($userId === $user['id']) {
        throw new Exception('Cannot delete your own account');
    }
    
    $auth->deleteUser($userId);
    
    header('Location: settings.php?success=user_deleted');
    exit;
}

function handleChangePassword() {
    global $auth, $user;
    
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        throw new Exception('All password fields are required');
    }
    
    if ($newPassword !== $confirmPassword) {
        throw new Exception('New passwords do not match');
    }
    
    if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
        throw new Exception('New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters');
    }
    
    $auth->changePassword($user['id'], $currentPassword, $newPassword);
    
    header('Location: settings.php?success=password_changed');
    exit;
}

// Get current settings
$settings = [];
$settingsResult = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
foreach ($settingsResult as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

// Get users
$users = $auth->getAllUsers();

// Get system info
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

// Get success message
$success = $_GET['success'] ?? '';
$successMessages = [
    'settings_updated' => 'Settings updated successfully',
    'user_created' => 'User created successfully',
    'user_updated' => 'User updated successfully',
    'user_deleted' => 'User deleted successfully',
    'password_changed' => 'Password changed successfully'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-shield-check me-2"></i>
                <?php echo APP_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-house me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="configurations.php">
                            <i class="bi bi-gear me-1"></i>Configurations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="history.php">
                            <i class="bi bi-clock-history me-1"></i>History
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="settings.php">
                            <i class="bi bi-sliders me-1"></i>Settings
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <?php echo htmlspecialchars($user['username']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">
                                <i class="bi bi-person me-2"></i>Profile
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3 mb-0">
                    <i class="bi bi-sliders me-2"></i>Settings
                </h1>
                <p class="text-muted">Manage system settings and users</p>
            </div>
        </div>

        <!-- Success Message -->
        <?php if ($success && isset($successMessages[$success])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?php echo $successMessages[$success]; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Settings Tabs -->
        <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button">
                    <i class="bi bi-gear me-2"></i>General
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button">
                    <i class="bi bi-envelope me-2"></i>Email
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button">
                    <i class="bi bi-people me-2"></i>Users
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button">
                    <i class="bi bi-cpu me-2"></i>System
                </button>
            </li>
        </ul>

        <div class="tab-content" id="settingsTabContent">
            <!-- General Settings -->
            <div class="tab-pane fade show active" id="general" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="bi bi-gear me-2"></i>General Settings
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_settings">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="backup_directory" class="form-label">Backup Directory</label>
                                    <input type="text" class="form-control" id="backup_directory" name="backup_directory" 
                                           value="<?php echo htmlspecialchars($settings['backup_directory'] ?? ''); ?>" required>
                                    <div class="form-text">Directory where backup files will be stored</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="retention_days" class="form-label">Retention Days</label>
                                    <input type="number" class="form-control" id="retention_days" name="retention_days" 
                                           value="<?php echo htmlspecialchars($settings['retention_days'] ?? '7'); ?>" min="1" required>
                                    <div class="form-text">Number of days to keep backup files</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="max_backup_size" class="form-label">Max Backup Size</label>
                                    <input type="text" class="form-control" id="max_backup_size" name="max_backup_size" 
                                           value="<?php echo htmlspecialchars($settings['max_backup_size'] ?? '10G'); ?>">
                                    <div class="form-text">Maximum size for individual backups (e.g., 10G, 500M)</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="compression_level" class="form-label">Compression Level</label>
                                    <select class="form-select" id="compression_level" name="compression_level">
                                        <?php for ($i = 1; $i <= 9; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo ($settings['compression_level'] ?? '6') == $i ? 'selected' : ''; ?>>
                                                Level <?php echo $i; ?> <?php echo $i == 6 ? '(Default)' : ''; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <div class="form-text">Higher levels = better compression but slower</div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i>Save Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Email Settings -->
            <div class="tab-pane fade" id="email" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="bi bi-envelope me-2"></i>Email Notifications
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_settings">
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" value="1" 
                                           <?php echo ($settings['email_notifications'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="email_notifications">
                                        Enable email notifications
                                    </label>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email_smtp_host" class="form-label">SMTP Host</label>
                                    <input type="text" class="form-control" id="email_smtp_host" name="email_smtp_host" 
                                           value="<?php echo htmlspecialchars($settings['email_smtp_host'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email_smtp_port" class="form-label">SMTP Port</label>
                                    <input type="number" class="form-control" id="email_smtp_port" name="email_smtp_port" 
                                           value="<?php echo htmlspecialchars($settings['email_smtp_port'] ?? '587'); ?>" min="1" max="65535">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email_smtp_user" class="form-label">SMTP Username</label>
                                    <input type="text" class="form-control" id="email_smtp_user" name="email_smtp_user" 
                                           value="<?php echo htmlspecialchars($settings['email_smtp_user'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email_smtp_pass" class="form-label">SMTP Password</label>
                                    <input type="password" class="form-control" id="email_smtp_pass" name="email_smtp_pass">
                                    <div class="form-text">Leave blank to keep current password</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email_from" class="form-label">From Email</label>
                                    <input type="email" class="form-control" id="email_from" name="email_from" 
                                           value="<?php echo htmlspecialchars($settings['email_from'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email_to" class="form-label">To Email</label>
                                    <input type="email" class="form-control" id="email_to" name="email_to" 
                                           value="<?php echo htmlspecialchars($settings['email_to'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i>Save Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- User Management -->
            <div class="tab-pane fade" id="users" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="bi bi-people me-2"></i>User Management
                        </h6>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createUserModal">
                            <i class="bi bi-plus-circle me-2"></i>Add User
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $userData): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($userData['username']); ?></strong>
                                                <?php if ($userData['id'] == $user['id']): ?>
                                                    <span class="badge bg-primary ms-2">You</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($userData['email'] ?: '-'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $userData['role'] === 'admin' ? 'danger' : 'secondary'; ?>">
                                                    <?php echo ucfirst($userData['role']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($userData['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button class="btn btn-outline-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($userData)); ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php if ($userData['id'] != $user['id']): ?>
                                                        <button class="btn btn-outline-danger" onclick="deleteUser(<?php echo $userData['id']; ?>)">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Information -->
            <div class="tab-pane fade" id="system" role="tabpanel">
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="bi bi-info-circle me-2"></i>System Information
                                </h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>PHP Version</strong></td>
                                        <td><?php echo $systemInfo['php_version']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Server Software</strong></td>
                                        <td><?php echo htmlspecialchars($systemInfo['server_software']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Operating System</strong></td>
                                        <td><?php echo $systemInfo['os']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Memory Limit</strong></td>
                                        <td><?php echo $systemInfo['memory_limit']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Max Execution Time</strong></td>
                                        <td><?php echo $systemInfo['max_execution_time']; ?>s</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="bi bi-hdd me-2"></i>Disk Usage
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Free Space</span>
                                        <span><?php echo formatBytes($systemInfo['disk_free_space']); ?></span>
                                    </div>
                                    <div class="progress mt-1">
                                        <div class="progress-bar" style="width: <?php echo round(($systemInfo['disk_free_space'] / $systemInfo['disk_total_space']) * 100); ?>%"></div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Total Space</span>
                                        <span><?php echo formatBytes($systemInfo['disk_total_space']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="bi bi-check-circle me-2"></i>System Requirements
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($systemInfo['requirements'] as $requirement => $status): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-<?php echo $status ? 'check-circle text-success' : 'x-circle text-danger'; ?> me-2"></i>
                                        <span><?php echo ucwords(str_replace('_', ' ', $requirement)); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-plus me-2"></i>Create User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_user">
                        
                        <div class="mb-3">
                            <label for="new_username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="new_username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="new_email" name="email">
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="new_password" name="password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_role" class="form-label">Role</label>
                            <select class="form-select" id="new_role" name="role">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-gear me-2"></i>Edit User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Role</label>
                            <select class="form-select" id="edit_role" name="role">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-key me-2"></i>Change Password
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_role').value = user.role;
            
            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();
        }
        
        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Show change password modal for current user
        document.addEventListener('DOMContentLoaded', function() {
            const userDropdown = document.getElementById('userDropdown');
            if (userDropdown) {
                userDropdown.addEventListener('click', function(e) {
                    e.preventDefault();
                    const modal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
                    modal.show();
                });
            }
        });
    </script>
</body>
</html>

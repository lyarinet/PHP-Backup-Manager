<?php
/**
 * Configurations Page
 * Interface for managing backup configurations
 */

define('BACKUP_MANAGER', true);
require_once 'config.php';

$db = new Database();
$auth = new Auth($db);
$backupManager = new BackupManager($db);

// Require authentication
$auth->requireLogin();

$user = $auth->getCurrentUser();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create':
                handleCreateConfig();
                break;
            case 'update':
                handleUpdateConfig();
                break;
            case 'delete':
                handleDeleteConfig();
                break;
            case 'toggle':
                handleToggleConfig();
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

function handleCreateConfig() {
    global $backupManager, $user;
    
    $name = sanitizeInput($_POST['name'] ?? '');
    $backupType = $_POST['backup_type'] ?? '';
    $configData = [];
    
    // Build configuration data based on type
    switch ($backupType) {
        case 'files':
            $configData = [
                'files' => array_filter(explode("\n", $_POST['files'] ?? ''))
            ];
            break;
        case 'mysql':
            $configData = [
                'host' => sanitizeInput($_POST['mysql_host'] ?? 'localhost'),
                'username' => sanitizeInput($_POST['mysql_username'] ?? ''),
                'password' => $_POST['mysql_password'] ?? '',
                'databases' => array_filter(explode("\n", $_POST['mysql_databases'] ?? ''))
            ];
            break;
        case 'postgresql':
            $configData = [
                'host' => sanitizeInput($_POST['postgres_host'] ?? 'localhost'),
                'username' => sanitizeInput($_POST['postgres_username'] ?? ''),
                'password' => $_POST['postgres_password'] ?? '',
                'databases' => array_filter(explode("\n", $_POST['postgres_databases'] ?? ''))
            ];
            break;
    }
    
    $backupManager->createConfig($name, $backupType, $configData, $user['id']);
    header('Location: configurations.php?success=created');
    exit;
}

function handleUpdateConfig() {
    global $backupManager, $user;
    
    $configId = (int)($_POST['config_id'] ?? 0);
    $name = sanitizeInput($_POST['name'] ?? '');
    $backupType = $_POST['backup_type'] ?? '';
    $configData = [];
    
    // Debug logging
    $logFile = APP_ROOT . '/debug_update.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "CONFIGS.PHP - UPDATE CALLED\n", FILE_APPEND);
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);
    
    // Build configuration data based on type
    switch ($backupType) {
        case 'files':
            $configData = [
                'files' => array_filter(explode("\n", $_POST['files'] ?? ''))
            ];
            break;
        case 'mysql':
            $configData = [
                'host' => sanitizeInput($_POST['mysql_host'] ?? 'localhost'),
                'username' => sanitizeInput($_POST['mysql_username'] ?? ''),
                'password' => $_POST['mysql_password'] ?? '',
                'databases' => array_filter(explode("\n", $_POST['mysql_databases'] ?? ''))
            ];
            break;
        case 'postgresql':
            $configData = [
                'host' => sanitizeInput($_POST['postgres_host'] ?? 'localhost'),
                'username' => sanitizeInput($_POST['postgres_username'] ?? ''),
                'password' => $_POST['postgres_password'] ?? '',
                'databases' => array_filter(explode("\n", $_POST['postgres_databases'] ?? ''))
            ];
            break;
    }
    
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Config data built: " . print_r($configData, true) . "\n", FILE_APPEND);
    
    $backupManager->updateConfig($configId, $name, $configData, $user['id'], $backupType);
    
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Update completed\n", FILE_APPEND);
    
    header('Location: configurations.php?success=updated');
    exit;
}

function handleDeleteConfig() {
    global $backupManager, $user;
    
    $configId = (int)($_POST['config_id'] ?? 0);
    $backupManager->deleteConfig($configId, $user['id']);
    header('Location: configurations.php?success=deleted');
    exit;
}

function handleToggleConfig() {
    global $db, $user;
    
    $configId = (int)($_POST['config_id'] ?? 0);
    $enabled = (int)($_POST['enabled'] ?? 0);
    
    $db->update('backup_configs', ['enabled' => $enabled], 'id = ?', [$configId]);
    $db->logActivity($user['id'], 'toggle_config', "Toggled config ID: {$configId} to " . ($enabled ? 'enabled' : 'disabled'));
    
    header('Location: configurations.php?success=toggled');
    exit;
}

// Get configurations
$configs = $backupManager->getConfigs();

// Get success message
$success = $_GET['success'] ?? '';
$successMessages = [
    'created' => 'Configuration created successfully',
    'updated' => 'Configuration updated successfully',
    'deleted' => 'Configuration deleted successfully',
    'toggled' => 'Configuration status updated'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Configurations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .auto-select-card {
            border: 2px dashed #dee2e6;
            transition: all 0.3s ease;
        }
        .auto-select-card:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        .form-check-input:checked + .form-check-label {
            color: #0d6efd;
            font-weight: 500;
        }
        .auto-select-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .database-checkboxes {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 0.75rem;
            background-color: #f8f9fa;
        }
        .database-checkboxes .form-check {
            margin-bottom: 0.5rem;
        }
        .database-checkboxes .form-check-input:checked + .form-check-label {
            color: #0d6efd;
            font-weight: 500;
        }
        .auto-selected-field {
            border-left: 3px solid #0d6efd;
            background-color: #f8f9fa;
        }
        .auto-selected-field:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
    </style>
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
                        <a class="nav-link active" href="configurations.php">
                            <i class="bi bi-gear me-1"></i>Configurations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="history.php">
                            <i class="bi bi-clock-history me-1"></i>History
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="bi bi-sliders me-1"></i>Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings_paths.php">
                            <i class="bi bi-folder me-1"></i>Path Settings
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
                    <i class="bi bi-gear me-2"></i>Backup Configurations
                </h1>
                <p class="text-muted">Manage your backup configurations</p>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createConfigModal" onclick="resetModalForNewConfig()">
                    <i class="bi bi-plus-circle me-2"></i>New Configuration
                </button>
                <!-- <button class="btn btn-warning btn-sm ms-2" onclick="showProgressModal(15, 'Test Configuration')">
                    <i class="bi bi-hourglass-split me-2"></i>Test Progress
                </button> -->
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

        <!-- Configurations List -->
        <div class="row">
            <?php if (empty($configs)): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-gear fs-1 text-muted mb-3"></i>
                            <h4 class="text-muted">No configurations found</h4>
                            <p class="text-muted">Create your first backup configuration to get started.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createConfigModal">
                                <i class="bi bi-plus-circle me-2"></i>Create Configuration
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($configs as $config): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card config-card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="bi bi-<?php echo $config['backup_type'] === 'files' ? 'folder' : 'database'; ?> me-2"></i>
                                    <?php echo htmlspecialchars($config['name']); ?>
                                </h6>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <button class="dropdown-item" onclick="editConfig(<?php echo htmlspecialchars(json_encode($config)); ?>)">
                                                <i class="bi bi-pencil me-2"></i>Edit
                                            </button>
                                        </li>
                                        <li>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="config_id" value="<?php echo $config['id']; ?>">
                                                <input type="hidden" name="enabled" value="<?php echo $config['enabled'] ? '0' : '1'; ?>">
                                                <button type="submit" class="dropdown-item">
                                                    <i class="bi bi-<?php echo $config['enabled'] ? 'pause' : 'play'; ?> me-2"></i>
                                                    <?php echo $config['enabled'] ? 'Disable' : 'Enable'; ?>
                                                </button>
                                            </form>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this configuration?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="config_id" value="<?php echo $config['id']; ?>">
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="bi bi-trash me-2"></i>Delete
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <span class="badge bg-<?php echo $config['enabled'] ? 'success' : 'secondary'; ?> config-type-badge">
                                        <?php echo ucfirst($config['backup_type']); ?>
                                    </span>
                                    <span class="badge bg-<?php echo $config['enabled'] ? 'success' : 'secondary'; ?> config-type-badge">
                                        <?php echo $config['enabled'] ? 'Enabled' : 'Disabled'; ?>
                                    </span>
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted">Created: <?php echo date('M j, Y', strtotime($config['created_at'])); ?></small>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button class="btn btn-primary btn-sm" onclick="startBackup(<?php echo $config['id']; ?>)">
                                        <i class="bi bi-play-circle me-2"></i>Run Backup
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="editConfig(<?php echo htmlspecialchars(json_encode($config)); ?>)">
                                        <i class="bi bi-pencil me-2"></i>Edit Configuration
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create/Edit Configuration Modal -->
    <div class="modal fade" id="createConfigModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-2"></i>New Configuration
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="configForm" onsubmit="return submitConfigForm(event)">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create" id="formAction">
                        <input type="hidden" name="config_id" id="configId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Configuration Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="backup_type" class="form-label">Backup Type</label>
                                <select class="form-select" id="backup_type" name="backup_type" required onchange="toggleConfigFields(); clearAutoSelectCheckboxes()">
                                    <option value="">Select type...</option>
                                    <option value="files">Files & Directories</option>
                                    <option value="mysql">MySQL Database</option>
                                    <option value="postgresql">PostgreSQL Database</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Auto-Select Database Options -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="card auto-select-card">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="bi bi-lightning me-2"></i>Quick Setup
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted mb-3">Auto-select common database configurations:</p>
                                        <div class="row">
                                            <div class="col-md-4 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="auto_mysql" onchange="autoSelectDatabase('mysql')">
                                                    <label class="form-check-label" for="auto_mysql">
                                                        <i class="bi bi-database me-1"></i>MySQL (localhost)
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="auto_postgres" onchange="autoSelectDatabase('postgresql')">
                                                    <label class="form-check-label" for="auto_postgres">
                                                        <i class="bi bi-database me-1"></i>PostgreSQL (localhost)
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="auto_files" onchange="autoSelectFiles()">
                                                    <label class="form-check-label" for="auto_files">
                                                        <i class="bi bi-folder me-1"></i>Common Files
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Files Configuration -->
                        <div id="filesConfig" class="config-section" style="display: none;">
                            <h6 class="mb-3">Files to Backup</h6>
                            <div class="mb-3">
                                <label for="files" class="form-label">File/Directory Paths (one per line)</label>
                                <textarea class="form-control" id="files" name="files" rows="4" 
                                          placeholder="/var/www&#10;/home/user&#10;/etc/nginx"></textarea>
                                <div class="form-text">Enter one path per line. Use absolute paths.</div>
                            </div>
                        </div>
                        
                        <!-- MySQL Configuration -->
                        <div id="mysqlConfig" class="config-section" style="display: none;">
                            <h6 class="mb-3">MySQL Configuration</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="mysql_host" class="form-label">Host</label>
                                    <input type="text" class="form-control" id="mysql_host" name="mysql_host" value="localhost">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="mysql_username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="mysql_username" name="mysql_username">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="mysql_password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="mysql_password" name="mysql_password">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Database Selection</label>
                                <div class="input-group mb-2">
                                    <button class="btn btn-outline-primary" type="button" id="discoverMysqlDatabases" onclick="discoverDatabases('mysql')">
                                        <i class="bi bi-search me-1"></i>Discover Databases
                                    </button>
                                    <button class="btn btn-outline-secondary" type="button" id="selectAllMysql" onclick="selectAllDatabases('mysql')" style="display: none;">
                                        <i class="bi bi-check-square me-1"></i>Select All
                                    </button>
                                    <button class="btn btn-outline-secondary" type="button" id="deselectAllMysql" onclick="deselectAllDatabases('mysql')" style="display: none;">
                                        <i class="bi bi-square me-1"></i>Deselect All
                                    </button>
                                </div>
                                <div id="mysql_database_checkboxes" class="database-checkboxes" style="display: none;">
                                    <!-- Database checkboxes will be populated here -->
                                </div>
                                <div class="form-text">Click "Discover Databases" to find available databases, then select which ones to backup</div>
                                <!-- Hidden field to store selected databases -->
                                <input type="hidden" id="mysql_databases" name="mysql_databases" value="">
                            </div>
                        </div>
                        
                        <!-- PostgreSQL Configuration -->
                        <div id="postgresConfig" class="config-section" style="display: none;">
                            <h6 class="mb-3">PostgreSQL Configuration</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="postgres_host" class="form-label">Host</label>
                                    <input type="text" class="form-control" id="postgres_host" name="postgres_host" value="localhost">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="postgres_username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="postgres_username" name="postgres_username">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="postgres_password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="postgres_password" name="postgres_password">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Database Selection</label>
                                <div class="input-group mb-2">
                                    <button class="btn btn-outline-primary" type="button" id="discoverPostgresDatabases" onclick="discoverDatabases('postgresql')">
                                        <i class="bi bi-search me-1"></i>Discover Databases
                                    </button>
                                    <button class="btn btn-outline-secondary" type="button" id="selectAllPostgres" onclick="selectAllDatabases('postgresql')" style="display: none;">
                                        <i class="bi bi-check-square me-1"></i>Select All
                                    </button>
                                    <button class="btn btn-outline-secondary" type="button" id="deselectAllPostgres" onclick="deselectAllDatabases('postgresql')" style="display: none;">
                                        <i class="bi bi-square me-1"></i>Deselect All
                                    </button>
                                </div>
                                <div id="postgres_database_checkboxes" class="database-checkboxes" style="display: none;">
                                    <!-- Database checkboxes will be populated here -->
                                </div>
                                <div class="form-text">Click "Discover Databases" to find available databases, then select which ones to backup</div>
                                <!-- Hidden field to store selected databases -->
                                <input type="hidden" id="postgres_databases" name="postgres_databases" value="">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Save Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        function toggleConfigFields() {
            const backupType = document.getElementById('backup_type').value;
            const sections = document.querySelectorAll('.config-section');
            
            // Clear auto-select styling from all fields
            clearAutoSelectStyling();
            
            sections.forEach(section => {
                section.style.display = 'none';
            });
            
            if (backupType) {
                // Map backup types to their config section IDs
                const configSectionMap = {
                    'files': 'filesConfig',
                    'mysql': 'mysqlConfig', 
                    'postgresql': 'postgresConfig'
                };
                
                const sectionId = configSectionMap[backupType];
                if (sectionId) {
                    const section = document.getElementById(sectionId);
                    if (section) {
                        section.style.display = 'block';
                    }
                }
            }
        }
        
        function clearAutoSelectStyling() {
            // Remove auto-select styling from all database fields
            const fields = ['mysql_host', 'mysql_username', 'postgres_host', 'postgres_username'];
            fields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.classList.remove('auto-selected-field');
                }
            });
        }
        
        function clearAutoSelectCheckboxes() {
            // Uncheck all auto-select checkboxes when user manually changes backup type
            document.getElementById('auto_mysql').checked = false;
            document.getElementById('auto_postgres').checked = false;
            document.getElementById('auto_files').checked = false;
            
            // Reset labels to default
            document.querySelector('label[for="auto_mysql"]').innerHTML = '<i class="bi bi-database me-1"></i>MySQL (localhost)';
            document.querySelector('label[for="auto_postgres"]').innerHTML = '<i class="bi bi-database me-1"></i>PostgreSQL (localhost)';
        }
        
        function resetModalForNewConfig() {
            // Reset modal title for new configuration
            document.querySelector('#createConfigModal .modal-title').innerHTML = '<i class="bi bi-plus-circle me-2"></i>New Configuration';
            
            // Reset save button text
            document.querySelector('#createConfigModal .btn-primary').innerHTML = '<i class="bi bi-check-circle me-2"></i>Save Configuration';
            
            // Reset form action
            document.getElementById('formAction').value = 'create';
            document.getElementById('configId').value = '';
        }
        
        function editConfig(config) {
            console.log('editConfig called with:', config);
            
            document.getElementById('formAction').value = 'update';
            document.getElementById('configId').value = config.id;
            document.getElementById('name').value = config.name;
            document.getElementById('backup_type').value = config.backup_type;
            
            // Update modal title to show "Edit Configuration"
            const titleElement = document.querySelector('#createConfigModal .modal-title');
            if (titleElement) {
                titleElement.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Edit Configuration';
                console.log('Title updated to Edit Configuration');
            }
            
            // Update save button text
            const buttonElement = document.querySelector('#createConfigModal .btn-primary');
            if (buttonElement) {
                buttonElement.innerHTML = '<i class="bi bi-check-circle me-2"></i>Update Configuration';
                console.log('Button updated to Update Configuration');
            }
            
            // Populate configuration data
            let configData;
            try {
                // Parse config_data if it's a JSON string
                configData = typeof config.config_data === 'string' ? JSON.parse(config.config_data) : config.config_data;
                console.log('Parsed config data:', configData);
            } catch (e) {
                console.error('Error parsing config data:', e);
                configData = {};
            }
            
            switch (config.backup_type) {
                case 'files':
                    let filesValue = '';
                    if (configData.files) {
                        if (Array.isArray(configData.files)) {
                            // Clean up carriage returns and join with newlines
                            filesValue = configData.files.map(file => file.replace(/\r/g, '')).join('\n');
                        } else {
                            filesValue = configData.files.replace(/\r/g, '');
                        }
                    }
                    document.getElementById('files').value = filesValue;
                    console.log('Files value set to:', filesValue);
                    break;
                case 'mysql':
                    document.getElementById('mysql_host').value = configData.host || 'localhost';
                    document.getElementById('mysql_username').value = configData.username || '';
                    
                    // Store the selected databases for later use
                    window.selectedMySQLDatabases = configData.databases || [];
                    
                    // Auto-discover databases if we have credentials
                    if (configData.host && configData.username && configData.password) {
                        document.getElementById('mysql_password').value = configData.password;
                        console.log('Auto-discovering MySQL databases...');
                        discoverDatabases('mysql');
                    }
                    console.log('MySQL config loaded');
                    break;
                case 'postgresql':
                    document.getElementById('postgres_host').value = configData.host || 'localhost';
                    document.getElementById('postgres_username').value = configData.username || '';
                    
                    // Store the selected databases for later use
                    window.selectedPostgresDatabases = configData.databases || [];
                    
                    // Auto-discover databases if we have credentials
                    if (configData.host && configData.username && configData.password) {
                        document.getElementById('postgres_password').value = configData.password;
                        console.log('Auto-discovering PostgreSQL databases...');
                        discoverDatabases('postgresql');
                    }
                    console.log('PostgreSQL config loaded');
                    break;
            }
            
            toggleConfigFields();
            
            // Small delay to ensure DOM updates are complete
            setTimeout(() => {
                const modal = new bootstrap.Modal(document.getElementById('createConfigModal'));
                modal.show();
            }, 100);
        }
        
        function startBackup(configId) {
            if (window.backupManager) {
                window.backupManager.startBackup(configId);
            }
        }
        
        function autoSelectDatabase(type) {
            // Uncheck other database options
            if (type === 'mysql') {
                document.getElementById('auto_postgres').checked = false;
                document.getElementById('auto_files').checked = false;
            } else if (type === 'postgresql') {
                document.getElementById('auto_mysql').checked = false;
                document.getElementById('auto_files').checked = false;
            }
            
            // Set backup type
            document.getElementById('backup_type').value = type;
            toggleConfigFields();
            
            // Auto-fill common database settings
            if (type === 'mysql') {
                document.getElementById('mysql_host').value = 'localhost';
                document.getElementById('mysql_username').value = 'root';
                document.getElementById('mysql_databases').value = ''; // Leave empty for discovery
                
                // Clear any existing checkboxes
                document.getElementById('mysql_database_checkboxes').innerHTML = '';
                document.getElementById('mysql_database_checkboxes').style.display = 'none';
                document.getElementById('selectAllMysql').style.display = 'none';
                document.getElementById('deselectAllMysql').style.display = 'none';
                
                // Add visual styling to show it's auto-selected
                document.getElementById('mysql_host').classList.add('auto-selected-field');
                document.getElementById('mysql_username').classList.add('auto-selected-field');
                
                // Add event listener to allow custom host editing
                document.getElementById('mysql_host').addEventListener('input', function() {
                    updateAutoSelectLabel('auto_mysql', this.value);
                });
            } else if (type === 'postgresql') {
                document.getElementById('postgres_host').value = 'localhost';
                document.getElementById('postgres_username').value = 'postgres';
                document.getElementById('postgres_databases').value = ''; // Leave empty for discovery
                
                // Clear any existing checkboxes
                document.getElementById('postgres_database_checkboxes').innerHTML = '';
                document.getElementById('postgres_database_checkboxes').style.display = 'none';
                document.getElementById('selectAllPostgres').style.display = 'none';
                document.getElementById('deselectAllPostgres').style.display = 'none';
                
                // Add visual styling to show it's auto-selected
                document.getElementById('postgres_host').classList.add('auto-selected-field');
                document.getElementById('postgres_username').classList.add('auto-selected-field');
                
                // Add event listener to allow custom host editing
                document.getElementById('postgres_host').addEventListener('input', function() {
                    updateAutoSelectLabel('auto_postgres', this.value);
                });
            }
        }
        
        function updateAutoSelectLabel(checkboxId, hostValue) {
            const checkbox = document.getElementById(checkboxId);
            const label = checkbox.nextElementSibling;
            const icon = label.querySelector('i');
            const text = label.textContent.trim();
            
            // Update the label text to show the current host
            if (hostValue && hostValue !== 'localhost') {
                label.innerHTML = icon.outerHTML + ' ' + (checkboxId === 'auto_mysql' ? 'MySQL' : 'PostgreSQL') + ' (' + hostValue + ')';
            } else {
                label.innerHTML = icon.outerHTML + ' ' + (checkboxId === 'auto_mysql' ? 'MySQL' : 'PostgreSQL') + ' (localhost)';
            }
        }
        
        function autoSelectFiles() {
            // Uncheck database options
            document.getElementById('auto_mysql').checked = false;
            document.getElementById('auto_postgres').checked = false;
            
            // Set backup type
            document.getElementById('backup_type').value = 'files';
            toggleConfigFields();
            
            // Auto-fill common file paths
            document.getElementById('files').value = '/var/www\n/home\n/etc/nginx\n/etc/apache2\n/opt\n/usr/local';
        }
        
        async function discoverDatabases(type) {
            const hostField = type === 'mysql' ? 'mysql_host' : 'postgres_host';
            const usernameField = type === 'mysql' ? 'mysql_username' : 'postgres_username';
            const passwordField = type === 'mysql' ? 'mysql_password' : 'postgres_password';
            const databasesField = type === 'mysql' ? 'mysql_databases' : 'postgres_databases';
            const discoverButton = type === 'mysql' ? 'discoverMysqlDatabases' : 'discoverPostgresDatabases';
            const checkboxesContainer = type === 'mysql' ? 'mysql_database_checkboxes' : 'postgres_database_checkboxes';
            const selectAllButton = type === 'mysql' ? 'selectAllMysql' : 'selectAllPostgres';
            const deselectAllButton = type === 'mysql' ? 'deselectAllMysql' : 'deselectAllPostgres';
            
            const host = document.getElementById(hostField).value;
            const username = document.getElementById(usernameField).value;
            const password = document.getElementById(passwordField).value;
            
            if (!host || !username || !password) {
                alert('Please fill in host, username, and password before discovering databases.');
                return;
            }
            
            // Show loading state
            const button = document.getElementById(discoverButton);
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Discovering...';
            button.disabled = true;
            
            try {
                // Create AbortController for timeout
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 15000); // 15 second timeout
                
                const response = await fetch('api/discover.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    signal: controller.signal,
                    body: JSON.stringify({
                        type: type,
                        host: host,
                        username: username,
                        password: password
                    })
                });
                
                clearTimeout(timeoutId);
                
                const result = await response.json();
                
                if (result.success) {
                    // Populate database checkboxes
                    populateDatabaseCheckboxes(type, result.databases);
                    
                    // Show success message
                    showToast('Found ' + result.count + ' databases', 'success');
                } else {
                    alert('Error discovering databases: ' + result.message);
                }
            } catch (error) {
                if (error.name === 'AbortError') {
                    alert('Discovery timed out. Please check if the database server is running and accessible.');
                } else {
                    alert('Error: ' + error.message);
                }
            } finally {
                // Restore button state
                button.innerHTML = originalText;
                button.disabled = false;
            }
        }
        
        function populateDatabaseCheckboxes(type, databases) {
            const checkboxesContainer = type === 'mysql' ? 'mysql_database_checkboxes' : 'postgres_database_checkboxes';
            const selectAllButton = type === 'mysql' ? 'selectAllMysql' : 'selectAllPostgres';
            const deselectAllButton = type === 'mysql' ? 'deselectAllMysql' : 'deselectAllPostgres';
            const hiddenField = type === 'mysql' ? 'mysql_databases' : 'postgres_databases';
            
            const container = document.getElementById(checkboxesContainer);
            container.innerHTML = '';
            
            if (databases.length === 0) {
                container.innerHTML = '<div class="text-muted">No databases found</div>';
                return;
            }
            
            // Get previously selected databases
            const selectedDatabases = type === 'mysql' ? (window.selectedMySQLDatabases || []) : (window.selectedPostgresDatabases || []);
            
            // Create checkboxes for each database
            databases.forEach((db, index) => {
                const checkboxId = `${type}_db_${index}`;
                const isChecked = selectedDatabases.includes(db);
                const checkbox = document.createElement('div');
                checkbox.className = 'form-check';
                checkbox.innerHTML = `
                    <input class="form-check-input" type="checkbox" id="${checkboxId}" value="${db}" ${isChecked ? 'checked' : ''} onchange="updateSelectedDatabases('${type}')">
                    <label class="form-check-label" for="${checkboxId}">
                        <i class="bi bi-database me-1"></i>${db}
                    </label>
                `;
                container.appendChild(checkbox);
            });
            
            // Show the checkboxes container and control buttons
            container.style.display = 'block';
            document.getElementById(selectAllButton).style.display = 'inline-block';
            document.getElementById(deselectAllButton).style.display = 'inline-block';
            
            // Update hidden field
            updateSelectedDatabases(type);
        }
        
        function updateSelectedDatabases(type) {
            const checkboxesContainer = type === 'mysql' ? 'mysql_database_checkboxes' : 'postgres_database_checkboxes';
            const hiddenField = type === 'mysql' ? 'mysql_databases' : 'postgres_databases';
            
            const container = document.getElementById(checkboxesContainer);
            const checkboxes = container.querySelectorAll('input[type="checkbox"]:checked');
            const selectedDatabases = Array.from(checkboxes).map(cb => cb.value);
            
            document.getElementById(hiddenField).value = selectedDatabases.join('\n');
        }
        
        function selectAllDatabases(type) {
            const checkboxesContainer = type === 'mysql' ? 'mysql_database_checkboxes' : 'postgres_database_checkboxes';
            const container = document.getElementById(checkboxesContainer);
            const checkboxes = container.querySelectorAll('input[type="checkbox"]');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            
            updateSelectedDatabases(type);
        }
        
        function deselectAllDatabases(type) {
            const checkboxesContainer = type === 'mysql' ? 'mysql_database_checkboxes' : 'postgres_database_checkboxes';
            const container = document.getElementById(checkboxesContainer);
            const checkboxes = container.querySelectorAll('input[type="checkbox"]');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            
            updateSelectedDatabases(type);
        }
        
        function submitConfigForm(event) {
            console.log('Form submission started');
            
            // Get form data
            const formData = new FormData(event.target);
            console.log('Form data:');
            for (let [key, value] of formData.entries()) {
                console.log(`${key}: ${value}`);
            }
            
            // Check if it's an update
            const action = document.getElementById('formAction').value;
            const configId = document.getElementById('configId').value;
            
            if (action === 'update' && configId) {
                console.log('This is an UPDATE operation');
                console.log('Config ID:', configId);
                
                // Check MySQL fields specifically
                const mysqlHost = document.getElementById('mysql_host').value;
                const mysqlUsername = document.getElementById('mysql_username').value;
                const mysqlPassword = document.getElementById('mysql_password').value;
                
                console.log('MySQL Host:', mysqlHost);
                console.log('MySQL Username:', mysqlUsername);
                console.log('MySQL Password:', mysqlPassword ? '[SET]' : '[EMPTY]');
            }
            
            // Let the form submit normally
            return true;
        }
        
        function showToast(message, type = 'info') {
            // Create a simple toast notification
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(toast);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 3000);
        }
    </script>

    <!-- Progress Bar Modal -->
    <div class="modal fade" id="progressModal" tabindex="-1" aria-labelledby="progressModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="progressModalLabel">
                        <i class="bi bi-hourglass-split me-2"></i>Backup Progress
                    </h5>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <div class="spinner-border text-primary" role="status" id="progressSpinner">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span id="progressStep">Initializing backup...</span>
                            <span id="progressPercent">0%</span>
                        </div>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" 
                                 id="progressBar" 
                                 style="width: 0%" 
                                 aria-valuenow="0" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-muted small" id="progressDetails">
                        <div>Configuration: <span id="progressConfigName">-</span></div>
                        <div>Started: <span id="progressStartTime">-</span></div>
                        <div>Status: <span id="progressStatus" class="badge bg-primary">Running</span></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="closeProgressModal" disabled>
                        <i class="bi bi-x-circle me-2"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let progressInterval = null;
        let currentHistoryId = null;

        function showProgressModal(historyId, configName) {
            currentHistoryId = historyId;
            document.getElementById('progressConfigName').textContent = configName;
            document.getElementById('progressStartTime').textContent = new Date().toLocaleString();
            
            // Reset progress
            document.getElementById('progressBar').style.width = '0%';
            document.getElementById('progressBar').setAttribute('aria-valuenow', '0');
            document.getElementById('progressPercent').textContent = '0%';
            document.getElementById('progressStep').textContent = 'Initializing backup...';
            document.getElementById('progressStatus').textContent = 'Running';
            document.getElementById('progressStatus').className = 'badge bg-primary';
            document.getElementById('closeProgressModal').disabled = true;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('progressModal'));
            modal.show();
            
            // Start polling for progress
            startProgressPolling();
        }

        function startProgressPolling() {
            if (progressInterval) {
                clearInterval(progressInterval);
            }
            
            // Safety timeout - stop polling after 5 minutes
            window.progressSafetyTimeout = setTimeout(() => {
                console.log('Progress polling timeout - stopping after 5 minutes');
                stopProgressPolling();
            }, 300000); // 5 minutes
            
            progressInterval = setInterval(() => {
                fetchProgress();
            }, 1000); // Poll every second
        }

        function stopProgressPolling() {
            if (progressInterval) {
                clearInterval(progressInterval);
                progressInterval = null;
            }
            
            // Clear any safety timeouts
            if (window.progressSafetyTimeout) {
                clearTimeout(window.progressSafetyTimeout);
                window.progressSafetyTimeout = null;
            }
        }

        async function fetchProgress() {
            if (!currentHistoryId) return;
            
            console.log('Fetching progress for history ID:', currentHistoryId);
            
            try {
                const response = await fetch(`api/progress.php?history_id=${currentHistoryId}`, {
                    credentials: 'same-origin'
                });
                
                console.log('Progress API response status:', response.status);
                
                if (!response.ok) {
                    throw new Error('Failed to fetch progress: ' + response.status);
                }
                
                const data = await response.json();
                console.log('Progress API data:', data);
                
                if (data.success && data.progress) {
                    const progress = data.progress;
                    
                    console.log('Updating progress:', progress.progress + '%', progress.current_step);
                    
                    // Update progress bar
                    const progressBar = document.getElementById('progressBar');
                    const progressPercent = document.getElementById('progressPercent');
                    const progressStep = document.getElementById('progressStep');
                    const progressStatus = document.getElementById('progressStatus');
                    const closeButton = document.getElementById('closeProgressModal');
                    
                    progressBar.style.width = progress.progress + '%';
                    progressBar.setAttribute('aria-valuenow', progress.progress);
                    progressPercent.textContent = progress.progress + '%';
                    progressStep.textContent = progress.current_step || 'Processing...';
                    
                    // Update status
                    if (progress.status === 'completed') {
                        progressStatus.textContent = 'Completed';
                        progressStatus.className = 'badge bg-success';
                        closeButton.disabled = false;
                        stopProgressPolling();
                        
                        // Auto-close modal after 3 seconds
                        setTimeout(() => {
                            const modal = bootstrap.Modal.getInstance(document.getElementById('progressModal'));
                            if (modal) {
                                modal.hide();
                            }
                        }, 3000);
                    } else if (progress.status === 'failed') {
                        progressStatus.textContent = 'Failed';
                        progressStatus.className = 'badge bg-danger';
                        closeButton.disabled = false;
                        stopProgressPolling();
                    }
                } else {
                    console.log('No progress data received');
                }
            } catch (error) {
                console.error('Error fetching progress:', error);
            }
        }

        // Close modal handler
        document.getElementById('closeProgressModal').addEventListener('click', function() {
            console.log('Close button clicked - stopping progress polling');
            stopProgressPolling();
            const modal = bootstrap.Modal.getInstance(document.getElementById('progressModal'));
            if (modal) {
                modal.hide();
            }
        });
        
        // Also handle modal hidden event to ensure cleanup
        document.getElementById('progressModal').addEventListener('hidden.bs.modal', function() {
            console.log('Progress modal hidden - ensuring polling is stopped');
            stopProgressPolling();
            currentHistoryId = null;
        });
    </script>
</body>
</html>

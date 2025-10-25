<?php
/**
 * Path Settings Management
 * Interface for managing allowed backup paths
 */

define('BACKUP_MANAGER', true);
require_once 'config.php';

$db = new Database();
$auth = new Auth($db);

// Require authentication
$auth->requireLogin();

$user = $auth->getCurrentUser();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_paths':
                $allowedPaths = sanitizeInput($_POST['allowed_paths'] ?? '');
                $db->setSetting('allowed_backup_paths', $allowedPaths);
                
                $db->logActivity($user['id'], 'update_path_settings', "Updated allowed backup paths: {$allowedPaths}");
                
                $success = 'Path settings updated successfully';
                break;
                
            case 'add_path':
                $newPath = sanitizeInput($_POST['new_path'] ?? '');
                if (empty($newPath)) {
                    throw new Exception('Path cannot be empty');
                }
                
                // Get current paths
                $currentPaths = $db->getSetting('allowed_backup_paths', '/var/www,/home,/opt');
                $paths = array_map('trim', explode(',', $currentPaths));
                
                // Add new path if not already exists
                if (!in_array($newPath, $paths)) {
                    $paths[] = $newPath;
                    $updatedPaths = implode(',', $paths);
                    $db->setSetting('allowed_backup_paths', $updatedPaths);
                    
                    $db->logActivity($user['id'], 'add_backup_path', "Added backup path: {$newPath}");
                    $success = 'Path added successfully';
                } else {
                    $error = 'Path already exists';
                }
                break;
                
            case 'remove_path':
                $pathToRemove = sanitizeInput($_POST['path_to_remove'] ?? '');
                if (empty($pathToRemove)) {
                    throw new Exception('Path cannot be empty');
                }
                
                // Get current paths
                $currentPaths = $db->getSetting('allowed_backup_paths', '/var/www,/home,/opt');
                $paths = array_map('trim', explode(',', $currentPaths));
                
                // Remove path
                $paths = array_filter($paths, function($path) use ($pathToRemove) {
                    return $path !== $pathToRemove;
                });
                
                $updatedPaths = implode(',', $paths);
                $db->setSetting('allowed_backup_paths', $updatedPaths);
                
                $db->logActivity($user['id'], 'remove_backup_path', "Removed backup path: {$pathToRemove}");
                $success = 'Path removed successfully';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current settings
$allowedPaths = $db->getSetting('allowed_backup_paths', '/var/www,/home,/opt');
$pathArray = array_map('trim', explode(',', $allowedPaths));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Path Settings</title>
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
                        <a class="nav-link" href="settings.php">
                            <i class="bi bi-sliders me-1"></i>Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="settings_paths.php">
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
                    <i class="bi bi-folder me-2"></i>Backup Path Settings
                </h1>
                <p class="text-muted">Manage allowed backup paths for security</p>
            </div>
        </div>

        <!-- Success Message -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?php echo htmlspecialchars($success); ?>
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

        <!-- Current Paths -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-list me-2"></i>Current Allowed Paths
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pathArray)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-folder-x fs-1 text-muted mb-3"></i>
                                <h5 class="text-muted">No paths configured</h5>
                                <p class="text-muted">Add paths below to allow backup access</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($pathArray as $index => $path): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                            <div>
                                                <i class="bi bi-folder me-2"></i>
                                                <code><?php echo htmlspecialchars($path); ?></code>
                                            </div>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Remove this path?')">
                                                <input type="hidden" name="action" value="remove_path">
                                                <input type="hidden" name="path_to_remove" value="<?php echo htmlspecialchars($path); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Add New Path -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-plus-circle me-2"></i>Add New Path
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_path">
                            <div class="mb-3">
                                <label for="new_path" class="form-label">Path</label>
                                <input type="text" class="form-control" id="new_path" name="new_path" 
                                       placeholder="/var/www" required>
                                <div class="form-text">Enter absolute path (e.g., /var/www, /home, /opt)</div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus me-2"></i>Add Path
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Bulk Update -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-pencil me-2"></i>Bulk Update
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_paths">
                            <div class="mb-3">
                                <label for="allowed_paths" class="form-label">All Paths (comma-separated)</label>
                                <textarea class="form-control" id="allowed_paths" name="allowed_paths" rows="4" 
                                          placeholder="/var/www,/home,/opt,/var/big"><?php echo htmlspecialchars($allowedPaths); ?></textarea>
                                <div class="form-text">Enter all allowed paths separated by commas</div>
                            </div>
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-save me-2"></i>Update All Paths
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-lightning me-2"></i>Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <button class="btn btn-outline-primary w-100" onclick="addCommonPath('/var/www')">
                                    <i class="bi bi-globe me-2"></i>Web Root
                                </button>
                            </div>
                            <div class="col-md-3 mb-2">
                                <button class="btn btn-outline-primary w-100" onclick="addCommonPath('/home')">
                                    <i class="bi bi-house me-2"></i>Home Directory
                                </button>
                            </div>
                            <div class="col-md-3 mb-2">
                                <button class="btn btn-outline-primary w-100" onclick="addCommonPath('/opt')">
                                    <i class="bi bi-box me-2"></i>Opt Directory
                                </button>
                            </div>
                            <div class="col-md-3 mb-2">
                                <button class="btn btn-outline-primary w-100" onclick="addCommonPath('/var/big')">
                                    <i class="bi bi-hdd me-2"></i>Big Data
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function addCommonPath(path) {
            const currentPaths = document.getElementById('allowed_paths').value;
            const paths = currentPaths ? currentPaths.split(',') : [];
            
            if (!paths.includes(path)) {
                paths.push(path);
                document.getElementById('allowed_paths').value = paths.join(',');
            }
        }
    </script>
</body>
</html>

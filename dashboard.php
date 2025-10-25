<?php
/**
 * Dashboard Page
 * Main interface with statistics, recent backups, and quick actions
 */

define('BACKUP_MANAGER', true);
require_once 'config.php';

$db = new Database();
$auth = new Auth($db);
$backupManager = new BackupManager($db);

// Require authentication
$auth->requireLogin();

$user = $auth->getCurrentUser();

// Get dashboard data
$stats = $backupManager->getBackupStats();
$recentBackups = $backupManager->getRecentBackups(5);
$configs = $backupManager->getConfigs();

// Get system info
$systemRequirements = checkSystemRequirements();
$backupDirSize = getDirectorySize($db->getSetting('backup_directory', BACKUP_DIR));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Dashboard</title>
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
                        <a class="nav-link active" href="dashboard.php">
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
                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                </h1>
                <p class="text-muted">Welcome back, <?php echo htmlspecialchars($user['username']); ?>!</p>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quickBackupModal">
                    <i class="bi bi-play-circle me-2"></i>Quick Backup
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Backups
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['total_backups']); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-archive fs-1 text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Success Rate
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo $stats['success_rate']; ?>%
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-check-circle fs-1 text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Total Size
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo formatBytes($stats['total_size']); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-hdd fs-1 text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Last Backup
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php 
                                    if ($stats['last_backup']) {
                                        echo date('M j, Y', strtotime($stats['last_backup']));
                                    } else {
                                        echo 'Never';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-clock fs-1 text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Backups and Quick Actions -->
        <div class="row">
            <!-- Recent Backups -->
            <div class="col-lg-8 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="bi bi-clock-history me-2"></i>Recent Backups
                        </h6>
                        <a href="history.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentBackups)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <p class="text-muted mt-2">No backups yet</p>
                                <a href="configurations.php" class="btn btn-primary">Create Configuration</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Configuration</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Size</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentBackups as $backup): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($backup['config_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?php echo htmlspecialchars($backup['backup_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    $statusIcon = '';
                                                    switch ($backup['status']) {
                                                        case 'completed':
                                                            $statusClass = 'success';
                                                            $statusIcon = 'check-circle';
                                                            break;
                                                        case 'failed':
                                                            $statusClass = 'danger';
                                                            $statusIcon = 'x-circle';
                                                            break;
                                                        case 'running':
                                                            $statusClass = 'warning';
                                                            $statusIcon = 'clock';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                                        <i class="bi bi-<?php echo $statusIcon; ?> me-1"></i>
                                                        <?php echo ucfirst($backup['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatBytes($backup['size_bytes']); ?></td>
                                                <td><?php echo date('M j, Y H:i', strtotime($backup['start_time'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="bi bi-lightning me-2"></i>Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#quickBackupModal">
                                <i class="bi bi-play-circle me-2"></i>Run Backup Now
                            </button>
                            <a href="configurations.php" class="btn btn-outline-success">
                                <i class="bi bi-plus-circle me-2"></i>New Configuration
                            </a>
                            <a href="history.php" class="btn btn-outline-info">
                                <i class="bi bi-clock-history me-2"></i>View History
                            </a>
                            <a href="settings.php" class="btn btn-outline-warning">
                                <i class="bi bi-sliders me-2"></i>Settings
                            </a>
                        </div>
                    </div>
                </div>

                <!-- System Status -->
                <div class="card shadow mt-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="bi bi-activity me-2"></i>System Status
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Backup Directory</span>
                                <span class="text-muted"><?php echo formatBytes($backupDirSize); ?></span>
                            </div>
                            <div class="progress mt-1" style="height: 4px;">
                                <div class="progress-bar" style="width: 25%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-2">
                            <small class="text-muted">
                                <i class="bi bi-check-circle text-success me-1"></i>
                                PHP <?php echo PHP_VERSION; ?>
                            </small>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">
                                <i class="bi bi-<?php echo $systemRequirements['pdo_sqlite'] ? 'check-circle text-success' : 'x-circle text-danger'; ?> me-1"></i>
                                SQLite Support
                            </small>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">
                                <i class="bi bi-<?php echo $systemRequirements['tar'] ? 'check-circle text-success' : 'x-circle text-danger'; ?> me-1"></i>
                                Tar Available
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Backup Modal -->
    <div class="modal fade" id="quickBackupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-play-circle me-2"></i>Quick Backup
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($configs)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-exclamation-triangle fs-1 text-warning"></i>
                            <p class="mt-2">No backup configurations found</p>
                            <a href="configurations.php" class="btn btn-primary">Create Configuration</a>
                        </div>
                    <?php else: ?>
                        <form id="quickBackupForm">
                            <div class="mb-3">
                                <label for="configSelect" class="form-label">Select Configuration</label>
                                <select class="form-select" id="configSelect" name="config_id" required>
                                    <option value="">Choose a configuration...</option>
                                    <?php foreach ($configs as $config): ?>
                                        <option value="<?php echo $config['id']; ?>">
                                            <?php echo htmlspecialchars($config['name']); ?> 
                                            (<?php echo ucfirst($config['backup_type']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <?php if (!empty($configs)): ?>
                        <button type="button" class="btn btn-primary" id="startBackupBtn">
                            <i class="bi bi-play me-2"></i>Start Backup
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        // Quick backup functionality
        document.getElementById('startBackupBtn')?.addEventListener('click', function() {
            const configId = document.getElementById('configSelect').value;
            if (configId) {
                // Start backup via API
                fetch('api/backup.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'start',
                        config_id: configId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close modal and show success message
                        bootstrap.Modal.getInstance(document.getElementById('quickBackupModal')).hide();
                        showToast('Backup started successfully', 'success');
                        // Refresh page after a moment
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showToast('Failed to start backup: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    showToast('Error starting backup: ' + error.message, 'error');
                });
            }
        });
    </script>
</body>
</html>

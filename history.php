<?php
/**
 * History Page
 * Backup history viewer, logs display, and monitoring features
 */

define('BACKUP_MANAGER', true);
require_once 'config.php';

$db = new Database();
$auth = new Auth($db);
$backupManager = new BackupManager($db);

// Require authentication
$auth->requireLogin();

$user = $auth->getCurrentUser();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'delete':
                handleDeleteBackup();
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

function handleDeleteBackup() {
    global $db, $user;
    
    $historyId = (int)($_POST['history_id'] ?? 0);
    
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
    $db->logActivity($user['id'], 'delete_backup_history', "Deleted backup history ID: {$historyId}");
    
    header('Location: history.php?success=deleted');
    exit;
}

// Get filters
$status = $_GET['status'] ?? '';
$configId = $_GET['config_id'] ?? '';
$limit = (int)($_GET['limit'] ?? 20);
$offset = (int)($_GET['offset'] ?? 0);

// Build query
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

// Get backup history
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

// Get configurations for filter
$configs = $backupManager->getConfigs();

// Get statistics
$stats = $backupManager->getBackupStats();

// Get success message
$success = $_GET['success'] ?? '';
$successMessages = [
    'deleted' => 'Backup deleted successfully'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Backup History</title>
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
                        <a class="nav-link active" href="history.php">
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
                    <i class="bi bi-clock-history me-2"></i>Backup History
                </h1>
                <p class="text-muted">View and manage your backup history</p>
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
                                    Successful
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['successful_backups']); ?>
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
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                    Failed
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['failed_backups']); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-x-circle fs-1 text-danger"></i>
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
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="running" <?php echo $status === 'running' ? 'selected' : ''; ?>>Running</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="config_id" class="form-label">Configuration</label>
                        <select class="form-select" id="config_id" name="config_id">
                            <option value="">All Configurations</option>
                            <?php foreach ($configs as $config): ?>
                                <option value="<?php echo $config['id']; ?>" <?php echo $configId == $config['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($config['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="limit" class="form-label">Per Page</label>
                        <select class="form-select" id="limit" name="limit">
                            <option value="10" <?php echo $limit === 10 ? 'selected' : ''; ?>>10</option>
                            <option value="20" <?php echo $limit === 20 ? 'selected' : ''; ?>>20</option>
                            <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-funnel me-1"></i>Filter
                        </button>
                        <a href="history.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </form>
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

        <!-- Backup History Table -->
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-list-ul me-2"></i>Backup History
                    <span class="badge bg-secondary ms-2"><?php echo $total; ?> total</span>
                </h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($history)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox fs-1 text-muted"></i>
                        <p class="text-muted mt-2">No backup history found</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Configuration</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Size</th>
                                    <th>Files</th>
                                    <th>Start Time</th>
                                    <th>Duration</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $backup): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($backup['config_name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo ucfirst($backup['backup_type']); ?>
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
                                        <td>
                                            <span class="file-size"><?php echo formatBytes($backup['size_bytes']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $backup['file_count']; ?></span>
                                        </td>
                                        <td>
                                            <?php echo date('M j, Y H:i', strtotime($backup['start_time'])); ?>
                                        </td>
                                        <td>
                                            <?php
                                            if ($backup['end_time']) {
                                                $start = new DateTime($backup['start_time']);
                                                $end = new DateTime($backup['end_time']);
                                                $duration = $start->diff($end);
                                                echo formatDuration($duration->s + ($duration->i * 60) + ($duration->h * 3600));
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button class="btn btn-outline-primary" onclick="viewBackupDetails(<?php echo $backup['id']; ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <?php if ($backup['status'] === 'completed'): ?>
                                                    <button class="btn btn-outline-success" onclick="downloadBackup(<?php echo $backup['id']; ?>)">
                                                        <i class="bi bi-download"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-outline-danger" onclick="deleteBackup(<?php echo $backup['id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total > $limit): ?>
            <nav aria-label="Backup history pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php
                    $currentPage = floor($offset / $limit) + 1;
                    $totalPages = ceil($total / $limit);
                    $maxPages = 5;
                    $startPage = max(1, $currentPage - floor($maxPages / 2));
                    $endPage = min($totalPages, $startPage + $maxPages - 1);
                    
                    if ($startPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['offset' => 0])); ?>">First</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?php echo $i === $currentPage ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['offset' => ($i - 1) * $limit])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['offset' => ($totalPages - 1) * $limit])); ?>">Last</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <!-- Backup Details Modal -->
    <div class="modal fade" id="backupDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-info-circle me-2"></i>Backup Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="backupDetailsContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        function viewBackupDetails(historyId) {
            const modal = new bootstrap.Modal(document.getElementById('backupDetailsModal'));
            const content = document.getElementById('backupDetailsContent');
            
            // Show loading
            content.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            
            modal.show();
            
            // Fetch backup details
            fetch(`api/history.php?action=get&history_id=${historyId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const backup = data.history;
                        content.innerHTML = `
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Configuration</h6>
                                    <p>${backup.config_name}</p>
                                    
                                    <h6>Type</h6>
                                    <p><span class="badge bg-secondary">${backup.backup_type}</span></p>
                                    
                                    <h6>Status</h6>
                                    <p><span class="badge bg-${backup.status === 'completed' ? 'success' : backup.status === 'failed' ? 'danger' : 'warning'}">${backup.status}</span></p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Start Time</h6>
                                    <p>${new Date(backup.start_time).toLocaleString()}</p>
                                    
                                    <h6>End Time</h6>
                                    <p>${backup.end_time ? new Date(backup.end_time).toLocaleString() : 'Still running'}</p>
                                    
                                    <h6>Size</h6>
                                    <p>${formatBytes(backup.size_bytes)}</p>
                                </div>
                            </div>
                            
                            ${backup.files && backup.files.length > 0 ? `
                                <h6 class="mt-4">Backup Files</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>File</th>
                                                <th>Size</th>
                                                <th>Type</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${backup.files.map(file => `
                                                <tr>
                                                    <td>${file.file_path.split('/').pop()}</td>
                                                    <td>${formatBytes(file.file_size)}</td>
                                                    <td><span class="badge bg-secondary">${file.backup_type}</span></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="downloadFile(${file.id})">
                                                            <i class="bi bi-download"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            ` : ''}
                            
                            ${backup.error_log ? `
                                <h6 class="mt-4">Error Log</h6>
                                <pre class="bg-light p-3 rounded">${backup.error_log}</pre>
                            ` : ''}
                        `;
                    } else {
                        content.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Error loading backup details: ${data.message}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    content.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Error loading backup details: ${error.message}
                        </div>
                    `;
                });
        }
        
        function downloadBackup(historyId) {
            // This would typically redirect to a download endpoint
            window.open(`api/backup.php?action=download&history_id=${historyId}`, '_blank');
        }
        
        function downloadFile(fileId) {
            window.open(`api/backup.php?action=download&file_id=${fileId}`, '_blank');
        }
        
        function deleteBackup(historyId) {
            if (confirm('Are you sure you want to delete this backup? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="history_id" value="${historyId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    </script>
</body>
</html>

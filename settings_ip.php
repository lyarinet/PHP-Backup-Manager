<?php
/**
 * IP Whitelist Management Page
 * Manage IP whitelist settings and access control
 */

define('BACKUP_MANAGER', true);
require_once 'config.php';
require_once 'includes/IPWhitelist.class.php';

$db = new Database();
$auth = new Auth($db);
$ipWhitelist = new IPWhitelist($db);

// Require authentication
$auth->requireLogin();

$user = $auth->getCurrentUser();

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'toggle':
                $enabled = isset($_POST['enabled']);
                $ipWhitelist->setEnabled($enabled);
                $message = $enabled ? 'IP whitelist enabled' : 'IP whitelist disabled';
                $messageType = 'success';
                break;
                
            case 'add_ip':
                $ip = trim($_POST['ip'] ?? '');
                if (empty($ip)) {
                    throw new Exception('IP address is required');
                }
                
                if (!$ipWhitelist->validateIP($ip)) {
                    throw new Exception('Invalid IP address or CIDR range');
                }
                
                if ($ipWhitelist->addIP($ip)) {
                    $message = "IP {$ip} added to whitelist";
                    $messageType = 'success';
                } else {
                    $message = "Failed to add IP {$ip}";
                    $messageType = 'danger';
                }
                break;
                
            case 'remove_ip':
                $ip = $_POST['ip'] ?? '';
                if ($ipWhitelist->removeIP($ip)) {
                    $message = "IP {$ip} removed from whitelist";
                    $messageType = 'success';
                } else {
                    $message = "Failed to remove IP {$ip}";
                    $messageType = 'danger';
                }
                break;
                
            case 'add_current':
                $currentIP = $ipWhitelist->getCurrentIP();
                if ($ipWhitelist->addIP($currentIP)) {
                    $message = "Current IP {$currentIP} added to whitelist";
                    $messageType = 'success';
                } else {
                    $message = "Failed to add current IP";
                    $messageType = 'danger';
                }
                break;
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

// Get current statistics
$stats = $ipWhitelist->getStats();
$whitelist = $ipWhitelist->getWhitelist();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - IP Whitelist</title>
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
                        <a class="nav-link" href="settings_paths.php">
                            <i class="bi bi-folder me-1"></i>Path Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="settings_ip.php">
                            <i class="bi bi-shield-lock me-1"></i>IP Whitelist
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="ipSettingsDropdown" role="button" data-bs-toggle="dropdown">
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

    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1">
                            <i class="bi bi-shield-lock me-2"></i>IP Whitelist Management
                        </h1>
                        <p class="text-muted">Control access to the backup system by IP address</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
        <div class="row">
            <div class="col-12">
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-shield-check text-<?php echo $stats['enabled'] ? 'success' : 'secondary'; ?> fs-2"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="text-muted small">Status</div>
                                <div class="fw-bold"><?php echo $stats['enabled'] ? 'Enabled' : 'Disabled'; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-list-ul text-primary fs-2"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="text-muted small">Total IPs</div>
                                <div class="fw-bold"><?php echo $stats['total_ips']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-geo-alt text-info fs-2"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="text-muted small">Single IPs</div>
                                <div class="fw-bold"><?php echo $stats['single_ips']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-diagram-3 text-warning fs-2"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="text-muted small">CIDR Ranges</div>
                                <div class="fw-bold"><?php echo $stats['cidr_ranges']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Enable/Disable Toggle -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-power me-2"></i>IP Whitelist Control
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="toggle">
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="enabled" name="enabled" 
                                           <?php echo $stats['enabled'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="enabled">
                                        Enable IP Whitelist
                                    </label>
                                </div>
                                <div class="form-text">
                                    When enabled, only whitelisted IP addresses can access the system.
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i>Save Settings
                            </button>
                        </form>
                        
                        <hr>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Current IP:</strong> <?php echo htmlspecialchars($stats['current_ip']); ?>
                        </div>
                        
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="add_current">
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-plus-circle me-1"></i>Add Current IP
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Add New IP -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-plus-circle me-2"></i>Add IP Address
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_ip">
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <label for="ip" class="form-label">IP Address or CIDR Range</label>
                                    <input type="text" class="form-control" id="ip" name="ip" 
                                           placeholder="192.168.1.1 or 192.168.1.0/24" required>
                                    <div class="form-text">
                                        Enter a single IP address (e.g., 192.168.1.1) or CIDR range (e.g., 192.168.1.0/24)
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-plus me-1"></i>Add IP
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- IP Whitelist -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-list me-2"></i>Current IP Whitelist
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($whitelist)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-shield-x text-muted fs-1"></i>
                            <p class="text-muted mt-2">No IP addresses in whitelist</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>IP Address / Range</th>
                                        <th>Type</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($whitelist as $ip): ?>
                                    <tr>
                                        <td>
                                            <code><?php echo htmlspecialchars($ip); ?></code>
                                        </td>
                                        <td>
                                            <?php if (strpos($ip, '/') !== false): ?>
                                                <span class="badge bg-warning">CIDR Range</span>
                                            <?php else: ?>
                                                <span class="badge bg-info">Single IP</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline" 
                                                  onsubmit="return confirm('Are you sure you want to remove this IP?')">
                                                <input type="hidden" name="action" value="remove_ip">
                                                <input type="hidden" name="ip" value="<?php echo htmlspecialchars($ip); ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i class="bi bi-trash me-1"></i>Remove
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

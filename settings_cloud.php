<?php
/**
 * Cloud Storage Management Page
 * Manage cloud storage providers and settings
 */

define('BACKUP_MANAGER', true);
require_once 'config.php';

$db = new Database();
$auth = new Auth($db);

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
            case 'add_provider':
                $name = sanitizeInput($_POST['name'] ?? '');
                $type = sanitizeInput($_POST['type'] ?? '');
                $enabled = isset($_POST['enabled']);
                
                if (empty($name) || empty($type)) {
                    throw new Exception('Name and type are required');
                }
                
                // Prepare configuration based on type
                $config = [
                    'name' => $name,
                    'type' => $type,
                    'enabled' => $enabled,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                // Add type-specific configuration
                if ($type === 'ftp') {
                    $config['host'] = sanitizeInput($_POST['ftp_host'] ?? '');
                    $config['port'] = (int)($_POST['ftp_port'] ?? 21);
                    $config['username'] = sanitizeInput($_POST['ftp_username'] ?? '');
                    $config['password'] = $_POST['ftp_password'] ?? '';
                    $config['ssl'] = isset($_POST['ftp_ssl']);
                    $config['passive'] = isset($_POST['ftp_passive']);
                    $config['remote_path'] = sanitizeInput($_POST['ftp_remote_path'] ?? '/');
                } elseif ($type === 's3') {
                    $config['endpoint'] = sanitizeInput($_POST['s3_endpoint'] ?? '');
                    $config['region'] = sanitizeInput($_POST['s3_region'] ?? 'us-east-1');
                    $config['bucket'] = sanitizeInput($_POST['s3_bucket'] ?? '');
                    $config['access_key'] = $_POST['s3_access_key'] ?? '';
                    $config['secret_key'] = $_POST['s3_secret_key'] ?? '';
                    $config['storage_class'] = sanitizeInput($_POST['s3_storage_class'] ?? 'STANDARD');
                } elseif ($type === 'google_drive') {
                    $config['client_id'] = sanitizeInput($_POST['gd_client_id'] ?? '');
                    $config['client_secret'] = $_POST['gd_client_secret'] ?? '';
                    $config['redirect_uri'] = sanitizeInput($_POST['gd_redirect_uri'] ?? '');
                    $config['access_token'] = $_POST['gd_access_token'] ?? '';
                    $config['refresh_token'] = $_POST['gd_refresh_token'] ?? '';
                }
                
                $providerId = $db->insert('cloud_providers', $config);
                
                $db->logActivity($user['id'], 'add_cloud_provider', "Added cloud provider: {$name}");
                $message = "Cloud provider '{$name}' added successfully";
                $messageType = 'success';
                break;
                
            case 'test_connection':
                $providerId = (int)($_POST['provider_id'] ?? 0);
                $provider = $db->fetch("SELECT * FROM cloud_providers WHERE id = ?", [$providerId]);
                
                if (!$provider) {
                    throw new Exception('Provider not found');
                }
                
                // Test connection based on provider type
                if ($provider['type'] === 'ftp') {
                    require_once 'includes/storage/FTPStorage.class.php';
                    $storage = new FTPStorage($provider, $db);
                } elseif ($provider['type'] === 's3') {
                    require_once 'includes/storage/S3Storage.class.php';
                    $storage = new S3Storage($provider, $db);
                } elseif ($provider['type'] === 'google_drive') {
                    // Include Composer autoloader
                    if (file_exists('vendor/autoload.php')) {
                        require_once 'vendor/autoload.php';
                    }
                    
                    require_once 'includes/storage/GoogleDriveStorage.class.php';
                    try {
                        $storage = new GoogleDriveStorage($provider, $db);
                    } catch (Exception $e) {
                        if (strpos($e->getMessage(), 'Google API client not available') !== false) {
                            throw new Exception('Google API client library not installed. Please install google/apiclient package. See INSTALL_GOOGLE_API.md for instructions.');
                        }
                        throw $e;
                    }
                } else {
                    throw new Exception('Unsupported provider type');
                }
                
                $result = $storage->testConnection();
                
                if ($result['success']) {
                    $message = "Connection test successful: " . $result['message'];
                    $messageType = 'success';
                } else {
                    $message = "Connection test failed: " . $result['message'];
                    $messageType = 'danger';
                }
                break;
                
            case 'delete_provider':
                $providerId = (int)($_POST['provider_id'] ?? 0);
                $provider = $db->fetch("SELECT * FROM cloud_providers WHERE id = ?", [$providerId]);
                
                if (!$provider) {
                    throw new Exception('Provider not found');
                }
                
                $db->query("DELETE FROM cloud_providers WHERE id = ?", [$providerId]);
                
                $db->logActivity($user['id'], 'delete_cloud_provider', "Deleted cloud provider: {$provider['name']}");
                $message = "Cloud provider '{$provider['name']}' deleted successfully";
                $messageType = 'success';
                break;
                
            case 'toggle_provider':
                $providerId = (int)($_POST['provider_id'] ?? 0);
                $enabled = (int)($_POST['enabled'] ?? 0);
                
                $db->query("UPDATE cloud_providers SET enabled = ? WHERE id = ?", [$enabled, $providerId]);
                
                $status = $enabled ? 'enabled' : 'disabled';
                $db->logActivity($user['id'], 'toggle_cloud_provider', "Cloud provider {$status}");
                $message = "Cloud provider {$status} successfully";
                $messageType = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

// Get cloud providers
$providers = $db->fetchAll("SELECT * FROM cloud_providers ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Cloud Storage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script>
        // Apply theme immediately to prevent FOUC
        (function() {
            try {
                const savedTheme = localStorage.getItem('backup_manager_theme');
                const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                
                let theme = 'light';
                if (savedTheme) {
                    theme = savedTheme;
                } else if (systemPrefersDark) {
                    theme = 'dark';
                }
                
                // Apply theme to HTML element immediately
                if (theme === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    document.documentElement.removeAttribute('data-theme');
                }
                
                // Also apply to body when it becomes available
                function applyBodyTheme() {
                    if (document.body) {
                        if (theme === 'dark') {
                            document.body.classList.add('dark-theme');
                        } else {
                            document.body.classList.remove('dark-theme');
                        }
                    }
                }
                
                // Apply immediately if body exists
                applyBodyTheme();
                
                // Also apply when DOM is ready
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', applyBodyTheme);
                }
                
            } catch (e) {
                console.error('Theme application error:', e);
            }
        })();
    </script>
    <script src="assets/js/theme.js"></script>
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
                        <a class="nav-link" href="settings_ip.php">
                            <i class="bi bi-shield-lock me-1"></i>IP Whitelist
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="settings_cloud.php">
                            <i class="bi bi-cloud me-1"></i>Cloud Storage
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

    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1">
                            <i class="bi bi-cloud me-2"></i>Cloud Storage Management
                        </h1>
                        <p class="text-muted">Configure cloud storage providers for automatic backup uploads</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProviderModal">
                        <i class="bi bi-plus-circle me-1"></i>Add Provider
                    </button>
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

        <!-- Cloud Providers -->
        <div class="row">
            <?php if (empty($providers)): ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="bi bi-cloud text-muted fs-1"></i>
                    <h4 class="text-muted mt-3">No Cloud Providers</h4>
                    <p class="text-muted">Add your first cloud storage provider to enable automatic backup uploads.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProviderModal">
                        <i class="bi bi-plus-circle me-1"></i>Add Provider
                    </button>
                </div>
            </div>
            <?php else: ?>
            <?php foreach ($providers as $provider): ?>
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0">
                                <i class="bi bi-<?php echo $provider['type'] === 'ftp' ? 'hdd-network' : 'cloud'; ?> me-2"></i>
                                <?php echo htmlspecialchars($provider['name']); ?>
                            </h5>
                            <small class="text-muted">
                                <?php echo ucfirst($provider['type']); ?> Provider
                            </small>
                        </div>
                        <div class="d-flex gap-2">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="test_connection">
                                <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                                <button type="submit" class="btn btn-outline-info btn-sm">
                                    <i class="bi bi-wifi me-1"></i>Test
                                </button>
                            </form>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this provider?')">
                                <input type="hidden" name="action" value="delete_provider">
                                <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-trash me-1"></i>Delete
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <strong>Status:</strong>
                                <span class="badge bg-<?php echo $provider['enabled'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $provider['enabled'] ? 'Enabled' : 'Disabled'; ?>
                                </span>
                            </div>
                            <div class="col-6">
                                <strong>Created:</strong>
                                <?php echo date('M j, Y', strtotime($provider['created_at'])); ?>
                            </div>
                        </div>
                        
                        <?php if ($provider['type'] === 'ftp'): ?>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <strong>Host:</strong> <?php echo htmlspecialchars($provider['host']); ?>
                            </div>
                            <div class="col-6">
                                <strong>Port:</strong> <?php echo $provider['port']; ?>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <strong>Username:</strong> <?php echo htmlspecialchars($provider['username']); ?>
                            </div>
                            <div class="col-6">
                                <strong>SSL:</strong> <?php echo $provider['ssl'] ? 'Yes' : 'No'; ?>
                            </div>
                        </div>
                        <?php elseif ($provider['type'] === 's3'): ?>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <strong>Endpoint:</strong> <?php echo htmlspecialchars($provider['endpoint'] ?: 'AWS S3'); ?>
                            </div>
                            <div class="col-6">
                                <strong>Region:</strong> <?php echo htmlspecialchars($provider['region']); ?>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <strong>Bucket:</strong> <?php echo htmlspecialchars($provider['bucket']); ?>
                            </div>
                            <div class="col-6">
                                <strong>Storage Class:</strong> <?php echo htmlspecialchars($provider['storage_class']); ?>
                            </div>
                        </div>
                        <?php elseif ($provider['type'] === 'google_drive'): ?>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <strong>Client ID:</strong> <?php echo htmlspecialchars(substr($provider['client_id'], 0, 20) . '...'); ?>
                            </div>
                            <div class="col-6">
                                <strong>Redirect URI:</strong> <?php echo htmlspecialchars($provider['redirect_uri']); ?>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <strong>Access Token:</strong> 
                                <?php echo !empty($provider['access_token']) ? '<span class="badge bg-success">Configured</span>' : '<span class="badge bg-warning">Not Set</span>'; ?>
                            </div>
                            <div class="col-6">
                                <strong>Refresh Token:</strong> 
                                <?php echo !empty($provider['refresh_token']) ? '<span class="badge bg-success">Configured</span>' : '<span class="badge bg-warning">Not Set</span>'; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="toggle_provider">
                                <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                                <input type="hidden" name="enabled" value="<?php echo $provider['enabled'] ? '0' : '1'; ?>">
                                <button type="submit" class="btn btn-<?php echo $provider['enabled'] ? 'warning' : 'success'; ?> btn-sm">
                                    <i class="bi bi-<?php echo $provider['enabled'] ? 'pause' : 'play'; ?> me-1"></i>
                                    <?php echo $provider['enabled'] ? 'Disable' : 'Enable'; ?>
                                </button>
                            </form>
                            
                            <?php if ($provider['type'] === 'google_drive' && !empty($provider['client_id']) && !empty($provider['client_secret'])): ?>
                            <a href="oauth_google_drive.php?provider_id=<?php echo $provider['id']; ?>" 
                               class="btn btn-outline-primary btn-sm ms-2">
                                <i class="bi bi-google me-1"></i>Authenticate
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Provider Modal -->
    <div class="modal fade" id="addProviderModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Cloud Storage Provider</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_provider">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Provider Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="type" class="form-label">Provider Type</label>
                                <select class="form-select" id="type" name="type" required onchange="toggleProviderFields()">
                                    <option value="">Select Type</option>
                                    <option value="ftp">FTP/FTPS</option>
                                    <option value="s3">S3-Compatible</option>
                                    <option value="google_drive">Google Drive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="enabled" name="enabled" checked>
                            <label class="form-check-label" for="enabled">
                                Enable this provider
                            </label>
                        </div>
                        
                        <!-- FTP Configuration -->
                        <div id="ftp-config" style="display: none;">
                            <h6>FTP Configuration</h6>
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <label for="ftp_host" class="form-label">Host</label>
                                    <input type="text" class="form-control" id="ftp_host" name="ftp_host">
                                </div>
                                <div class="col-md-4">
                                    <label for="ftp_port" class="form-label">Port</label>
                                    <input type="number" class="form-control" id="ftp_port" name="ftp_port" value="21">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="ftp_username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="ftp_username" name="ftp_username">
                                </div>
                                <div class="col-md-6">
                                    <label for="ftp_password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="ftp_password" name="ftp_password">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="ftp_remote_path" class="form-label">Remote Path</label>
                                    <input type="text" class="form-control" id="ftp_remote_path" name="ftp_remote_path" value="/">
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="ftp_ssl" name="ftp_ssl">
                                        <label class="form-check-label" for="ftp_ssl">
                                            Use SSL/TLS (FTPS)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="ftp_passive" name="ftp_passive" checked>
                                        <label class="form-check-label" for="ftp_passive">
                                            Passive Mode
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- S3 Configuration -->
                        <div id="s3-config" style="display: none;">
                            <h6>S3-Compatible Configuration</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="s3_endpoint" class="form-label">Endpoint URL</label>
                                    <input type="url" class="form-control" id="s3_endpoint" name="s3_endpoint" placeholder="Leave empty for AWS S3">
                                </div>
                                <div class="col-md-6">
                                    <label for="s3_region" class="form-label">Region</label>
                                    <input type="text" class="form-control" id="s3_region" name="s3_region" value="us-east-1">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="s3_bucket" class="form-label">Bucket Name</label>
                                    <input type="text" class="form-control" id="s3_bucket" name="s3_bucket">
                                </div>
                                <div class="col-md-6">
                                    <label for="s3_storage_class" class="form-label">Storage Class</label>
                                    <select class="form-select" id="s3_storage_class" name="s3_storage_class">
                                        <option value="STANDARD">Standard</option>
                                        <option value="STANDARD_IA">Standard-IA</option>
                                        <option value="GLACIER">Glacier</option>
                                        <option value="DEEP_ARCHIVE">Deep Archive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="s3_access_key" class="form-label">Access Key ID</label>
                                    <input type="text" class="form-control" id="s3_access_key" name="s3_access_key">
                                </div>
                                <div class="col-md-6">
                                    <label for="s3_secret_key" class="form-label">Secret Access Key</label>
                                    <input type="password" class="form-control" id="s3_secret_key" name="s3_secret_key">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Google Drive Configuration -->
                        <div id="google-drive-config" style="display: none;">
                            <h6>Google Drive Configuration</h6>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Setup Required:</strong> You need to create a Google Cloud Project and enable the Google Drive API. 
                                <a href="https://console.cloud.google.com/" target="_blank" class="alert-link">Go to Google Cloud Console</a>
                            </div>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Library Required:</strong> You must install the Google API client library first. 
                                <a href="INSTALL_GOOGLE_API.md" target="_blank" class="alert-link">See installation instructions</a>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="gd_client_id" class="form-label">Client ID</label>
                                    <input type="text" class="form-control" id="gd_client_id" name="gd_client_id" placeholder="Your Google OAuth Client ID">
                                </div>
                                <div class="col-md-6">
                                    <label for="gd_client_secret" class="form-label">Client Secret</label>
                                    <input type="password" class="form-control" id="gd_client_secret" name="gd_client_secret" placeholder="Your Google OAuth Client Secret">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="gd_redirect_uri" class="form-label">Redirect URI</label>
                                    <input type="url" class="form-control" id="gd_redirect_uri" name="gd_redirect_uri" 
                                           value="<?php echo (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/oauth_callback.php'; ?>"
                                           placeholder="OAuth redirect URI">
                                    <div class="form-text">This should match the redirect URI in your Google Cloud Console</div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="gd_access_token" class="form-label">Access Token (JSON)</label>
                                    <textarea class="form-control" id="gd_access_token" name="gd_access_token" rows="3" 
                                              placeholder='{"access_token":"...","expires_in":3600,"token_type":"Bearer"}'></textarea>
                                    <div class="form-text">Paste the JSON access token from OAuth flow</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="gd_refresh_token" class="form-label">Refresh Token</label>
                                    <input type="text" class="form-control" id="gd_refresh_token" name="gd_refresh_token" 
                                           placeholder="Refresh token for automatic token renewal">
                                    <div class="form-text">Optional: For automatic token refresh</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Provider</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleProviderFields() {
            const type = document.getElementById('type').value;
            const ftpConfig = document.getElementById('ftp-config');
            const s3Config = document.getElementById('s3-config');
            const gdConfig = document.getElementById('google-drive-config');
            
            // Hide all configs
            ftpConfig.style.display = 'none';
            s3Config.style.display = 'none';
            gdConfig.style.display = 'none';
            
            // Show relevant config
            if (type === 'ftp') {
                ftpConfig.style.display = 'block';
            } else if (type === 's3') {
                s3Config.style.display = 'block';
            } else if (type === 'google_drive') {
                gdConfig.style.display = 'block';
            }
        }
    </script>
</body>
</html>

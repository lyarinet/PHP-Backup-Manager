<?php
/**
 * Google Drive Authentication Helper
 * Simple page to complete Google Drive OAuth authentication
 */

define('BACKUP_MANAGER', true);
require_once 'config.php';

$db = new Database();
$auth = new Auth($db);

// Require authentication
$auth->requireLogin();

$user = $auth->getCurrentUser();

// Get Google Drive provider
$provider = $db->fetch("SELECT * FROM cloud_providers WHERE type = 'google_drive' LIMIT 1");

if (!$provider) {
    die("No Google Drive provider found. Please configure one first in Settings > Cloud Storage.");
}

$message = '';
$messageType = '';

// Handle authentication
if (isset($_GET['auth'])) {
    try {
        require_once 'includes/storage/GoogleDriveStorage.class.php';
        $storage = new GoogleDriveStorage($provider, $db);
        
        // Store provider ID in session for callback
        $_SESSION['oauth_provider_id'] = $provider['id'];
        
        // Get authorization URL
        $authUrl = $storage->getAuthUrl();
        
        // Redirect to Google OAuth
        header("Location: " . $authUrl);
        exit;
        
    } catch (Exception $e) {
        $message = "Authentication failed: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Check if we have valid tokens
$hasValidTokens = false;
if (!empty($provider['access_token']) && !empty($provider['refresh_token'])) {
    try {
        require_once 'includes/storage/GoogleDriveStorage.class.php';
        $storage = new GoogleDriveStorage($provider, $db);
        $result = $storage->testConnection();
        $hasValidTokens = $result['success'];
    } catch (Exception $e) {
        $hasValidTokens = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Google Drive Authentication</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="bi bi-google me-2"></i>Google Drive Authentication
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                            <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Provider Information</h5>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($provider['name']); ?></p>
                                <p><strong>Client ID:</strong> <?php echo htmlspecialchars(substr($provider['client_id'], 0, 20) . '...'); ?></p>
                                <p><strong>Status:</strong> 
                                    <?php if ($hasValidTokens): ?>
                                        <span class="badge bg-success">Authenticated</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Not Authenticated</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h5>Authentication Status</h5>
                                <ul class="list-unstyled">
                                    <li>
                                        <i class="bi bi-<?php echo !empty($provider['client_id']) ? 'check-circle text-success' : 'x-circle text-danger'; ?> me-2"></i>
                                        Client ID: <?php echo !empty($provider['client_id']) ? 'Configured' : 'Missing'; ?>
                                    </li>
                                    <li>
                                        <i class="bi bi-<?php echo !empty($provider['client_secret']) ? 'check-circle text-success' : 'x-circle text-danger'; ?> me-2"></i>
                                        Client Secret: <?php echo !empty($provider['client_secret']) ? 'Configured' : 'Missing'; ?>
                                    </li>
                                    <li>
                                        <i class="bi bi-<?php echo !empty($provider['access_token']) ? 'check-circle text-success' : 'x-circle text-danger'; ?> me-2"></i>
                                        Access Token: <?php echo !empty($provider['access_token']) ? 'Present' : 'Missing'; ?>
                                    </li>
                                    <li>
                                        <i class="bi bi-<?php echo !empty($provider['refresh_token']) ? 'check-circle text-success' : 'x-circle text-danger'; ?> me-2"></i>
                                        Refresh Token: <?php echo !empty($provider['refresh_token']) ? 'Present' : 'Missing'; ?>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <?php if ($hasValidTokens): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>Authentication Complete!</strong> Your Google Drive is properly connected and ready to use.
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="settings_cloud.php" class="btn btn-primary">
                                <i class="bi bi-arrow-left me-1"></i>Back to Cloud Storage
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Authentication Required:</strong> You need to complete the Google OAuth flow to connect your Google Drive account.
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="settings_cloud.php" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-arrow-left me-1"></i>Back to Cloud Storage
                            </a>
                            <a href="?auth=1" class="btn btn-primary">
                                <i class="bi bi-google me-1"></i>Authenticate with Google
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">Troubleshooting</h5>
                    </div>
                    <div class="card-body">
                        <h6>Common Issues:</h6>
                        <ul>
                            <li><strong>Authentication fails:</strong> Make sure your redirect URI in Google Cloud Console matches: <code>http://backup.lyaritech.com/oauth_callback.php</code></li>
                            <li><strong>Permission denied:</strong> Ensure you grant all requested permissions during OAuth</li>
                            <li><strong>Token expired:</strong> Re-authenticate to get fresh tokens</li>
                            <li><strong>Connection test fails:</strong> Check your internet connection and Google API availability</li>
                        </ul>
                        
                        <h6>Need Help?</h6>
                        <p>If you continue to have issues, check the <a href="GOOGLE_DRIVE_AUTH_GUIDE.md" target="_blank">authentication guide</a> for detailed instructions.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/theme.js"></script>
</body>
</html>

<?php
/**
 * OAuth Callback Handler for Google Drive
 * Handles OAuth callback from Google Drive authentication
 */

define('BACKUP_MANAGER', true);
require_once 'config.php';

$db = new Database();
$auth = new Auth($db);

// Require authentication
$auth->requireLogin();

$user = $auth->getCurrentUser();

// Get provider ID from session or redirect
$providerId = $_SESSION['oauth_provider_id'] ?? null;
$code = $_GET['code'] ?? null;
$error = $_GET['error'] ?? null;

if ($error) {
    $message = "OAuth error: " . htmlspecialchars($error);
    $messageType = 'danger';
} elseif (!$providerId) {
    $message = "No provider ID found in session";
    $messageType = 'danger';
} elseif (!$code) {
    $message = "No authorization code received";
    $messageType = 'danger';
} else {
    try {
        // Get provider configuration
        $provider = $db->fetch("SELECT * FROM cloud_providers WHERE id = ?", [$providerId]);
        
        if (!$provider) {
            throw new Exception("Provider not found");
        }
        
        if ($provider['type'] !== 'google_drive') {
            throw new Exception("Provider is not Google Drive");
        }
        
        // Include Composer autoloader
        if (file_exists('vendor/autoload.php')) {
            require_once 'vendor/autoload.php';
        }
        
        // Exchange code for token
        require_once 'includes/storage/GoogleDriveStorage.class.php';
        $storage = new GoogleDriveStorage($provider, $db);
        
        $token = $storage->exchangeCodeForToken($code);
        
        // Update provider with new token
        $db->query(
            "UPDATE cloud_providers SET access_token = ?, refresh_token = ? WHERE id = ?",
            [
                json_encode($token),
                $token['refresh_token'] ?? null,
                $providerId
            ]
        );
        
        // Clear session
        unset($_SESSION['oauth_provider_id']);
        
        $message = "Google Drive authentication successful! Provider has been configured.";
        $messageType = 'success';
        
        // Log activity
        $db->logActivity($user['id'], 'oauth_success', "Google Drive OAuth completed for provider: {$provider['name']}");
        
    } catch (Exception $e) {
        $message = "OAuth failed: " . $e->getMessage();
        $messageType = 'danger';
        
        // Log error
        $db->logActivity($user['id'], 'oauth_error', "Google Drive OAuth failed: " . $e->getMessage());
    }
}

// Redirect back to cloud settings
header("Location: settings_cloud.php?message=" . urlencode($message) . "&type=" . urlencode($messageType));
exit;
?>

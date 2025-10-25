<?php
/**
 * Google Drive OAuth Initiation
 * Start OAuth flow for Google Drive authentication
 */

define('BACKUP_MANAGER', true);
require_once 'config.php';

$db = new Database();
$auth = new Auth($db);

// Require authentication
$auth->requireLogin();

$user = $auth->getCurrentUser();

$providerId = $_GET['provider_id'] ?? null;

if (!$providerId) {
    header("Location: settings_cloud.php?message=" . urlencode("No provider ID provided") . "&type=danger");
    exit;
}

try {
    // Get provider configuration
    $provider = $db->fetch("SELECT * FROM cloud_providers WHERE id = ?", [$providerId]);
    
    if (!$provider) {
        throw new Exception("Provider not found");
    }
    
    if ($provider['type'] !== 'google_drive') {
        throw new Exception("Provider is not Google Drive");
    }
    
    if (empty($provider['client_id']) || empty($provider['client_secret'])) {
        throw new Exception("Google Drive provider not properly configured. Please set Client ID and Client Secret first.");
    }
    
    // Include Composer autoloader
    if (file_exists('vendor/autoload.php')) {
        require_once 'vendor/autoload.php';
    }
    
    // Create Google Drive storage instance
    require_once 'includes/storage/GoogleDriveStorage.class.php';
    
    try {
        $storage = new GoogleDriveStorage($provider, $db);
        
        // Get authorization URL
        $authUrl = $storage->getAuthUrl();
    } catch (Exception $e) {
        throw new Exception("Failed to initialize Google Drive storage: " . $e->getMessage());
    }
    
    // Store provider ID in session for callback
    $_SESSION['oauth_provider_id'] = $providerId;
    
    // Redirect to Google OAuth
    header("Location: " . $authUrl);
    exit;
    
} catch (Exception $e) {
    header("Location: settings_cloud.php?message=" . urlencode("OAuth initiation failed: " . $e->getMessage()) . "&type=danger");
    exit;
}
?>

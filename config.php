<?php
/**
 * Backup Manager Configuration
 * Database connection and application settings
 */

// Prevent direct access
if (!defined('BACKUP_MANAGER')) {
    die('Direct access not allowed');
}

// Application constants
define('APP_NAME', 'Backup Manager');
define('APP_VERSION', '2.0');
define('APP_ROOT', __DIR__);
define('BACKUP_DIR', '/opt/backups');
define('LOG_DIR', APP_ROOT . '/logs');
define('ENCRYPTION_KEY_FILE', APP_ROOT . '/.encryption_key');

// Database configuration
define('DB_PATH', APP_ROOT . '/backups.db');
define('DB_TIMEOUT', 30);

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('CSRF_TOKEN_LIFETIME', 1800); // 30 minutes
define('PASSWORD_MIN_LENGTH', 8);

// Backup settings
define('DEFAULT_RETENTION_DAYS', 7);
define('MAX_BACKUP_SIZE', '10G');
define('COMPRESSION_LEVEL', 6);

// File permissions
define('DB_FILE_PERMISSIONS', 0600);
define('CONFIG_FILE_PERMISSIONS', 0600);

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', '');
ini_set('session.cookie_samesite', 'Lax');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate encryption key if not exists
if (!file_exists(ENCRYPTION_KEY_FILE)) {
    $key = bin2hex(random_bytes(32));
    file_put_contents(ENCRYPTION_KEY_FILE, $key);
    chmod(ENCRYPTION_KEY_FILE, 0600);
}

// Load encryption key
define('ENCRYPTION_KEY', file_get_contents(ENCRYPTION_KEY_FILE));

// Timezone
date_default_timezone_set('UTC');

// Include required files
require_once APP_ROOT . '/includes/Database.class.php';
require_once APP_ROOT . '/includes/Auth.class.php';
require_once APP_ROOT . '/includes/BackupManager.class.php';
require_once APP_ROOT . '/includes/functions.php';

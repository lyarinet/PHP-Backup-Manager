<?php
/**
 * Helper Functions
 * Utility functions for the backup manager
 */

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Format time duration
 */
function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . 's';
    } elseif ($seconds < 3600) {
        return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return $hours . 'h ' . $minutes . 'm';
    }
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate random string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Encrypt sensitive data
 */
function encryptData($data) {
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', ENCRYPTION_KEY, 0, $iv);
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt sensitive data
 */
function decryptData($encryptedData) {
    $data = base64_decode($encryptedData);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    return openssl_decrypt($encrypted, 'AES-256-CBC', ENCRYPTION_KEY, 0, $iv);
}

/**
 * Check if path is safe (no directory traversal)
 */
function isPathSafe($path) {
    global $db;
    
    // Get allowed paths from database settings
    $allowedPathsSetting = $db ? $db->getSetting('allowed_backup_paths', '/var/www,/home,/opt') : '/var/www,/home,/opt';
    $allowedPaths = array_map('trim', explode(',', $allowedPathsSetting));
    
    // Add backup directory to allowed paths
    $allowedPaths[] = realpath(BACKUP_DIR);
    
    // Convert to real paths
    $realAllowedPaths = [];
    foreach ($allowedPaths as $allowed) {
        $realPath = realpath($allowed);
        if ($realPath) {
            $realAllowedPaths[] = $realPath;
        }
    }
    
    $realPath = realpath($path);
    if (!$realPath) {
        return false;
    }
    
    foreach ($realAllowedPaths as $allowed) {
        if (strpos($realPath, $allowed) === 0) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get file size recursively
 */
function getDirectorySize($path) {
    $size = 0;
    if (is_dir($path)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
    }
    return $size;
}

/**
 * Clean old files based on retention policy
 */
function cleanOldFiles($directory, $retentionDays) {
    $cutoffTime = time() - ($retentionDays * 24 * 60 * 60);
    $deletedCount = 0;
    
    if (is_dir($directory)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getMTime() < $cutoffTime) {
                if (unlink($file->getPathname())) {
                    $deletedCount++;
                }
            }
        }
    }
    
    return $deletedCount;
}

/**
 * Send email notification
 */
function sendEmailNotification($to, $subject, $message, $settings) {
    if (empty($settings['email_smtp_host']) || empty($settings['email_from'])) {
        return false;
    }
    
    $headers = [
        'From: ' . $settings['email_from'],
        'Reply-To: ' . $settings['email_from'],
        'X-Mailer: PHP/' . phpversion(),
        'Content-Type: text/html; charset=UTF-8'
    ];
    
    return mail($to, $subject, $message, implode("\r\n", $headers));
}

/**
 * Get backup statistics
 */
function getBackupStats($db) {
    $stats = [];
    
    // Total backups
    $result = $db->fetch("SELECT COUNT(*) as total FROM backup_history");
    $stats['total_backups'] = $result['total'];
    
    // Successful backups
    $result = $db->fetch("SELECT COUNT(*) as successful FROM backup_history WHERE status = 'completed'");
    $stats['successful_backups'] = $result['successful'];
    
    // Failed backups
    $result = $db->fetch("SELECT COUNT(*) as failed FROM backup_history WHERE status = 'failed'");
    $stats['failed_backups'] = $result['failed'];
    
    // Total size
    $result = $db->fetch("SELECT SUM(size_bytes) as total_size FROM backup_history WHERE status = 'completed'");
    $stats['total_size'] = $result['total_size'] ?: 0;
    
    // Last backup
    $result = $db->fetch("SELECT start_time FROM backup_history WHERE status = 'completed' ORDER BY start_time DESC LIMIT 1");
    $stats['last_backup'] = $result ? $result['start_time'] : null;
    
    // Success rate
    if ($stats['total_backups'] > 0) {
        $stats['success_rate'] = round(($stats['successful_backups'] / $stats['total_backups']) * 100, 1);
    } else {
        $stats['success_rate'] = 0;
    }
    
    return $stats;
}

/**
 * Get recent backup history
 */
function getRecentBackups($db, $limit = 10) {
    return $db->fetchAll("
        SELECT 
            bh.*,
            bc.name as config_name,
            bc.backup_type
        FROM backup_history bh
        JOIN backup_configs bc ON bh.config_id = bc.id
        ORDER BY bh.start_time DESC
        LIMIT ?
    ", [$limit]);
}

/**
 * Validate cron expression
 */
function isValidCronExpression($expression) {
    $parts = explode(' ', $expression);
    if (count($parts) !== 5) {
        return false;
    }
    
    $validRanges = [
        [0, 59],    // minute
        [0, 23],    // hour
        [1, 31],    // day
        [1, 12],    // month
        [0, 6]      // day of week
    ];
    
    foreach ($parts as $i => $part) {
        if ($part === '*') continue;
        
        $values = explode(',', $part);
        foreach ($values as $value) {
            if (is_numeric($value)) {
                if ($value < $validRanges[$i][0] || $value > $validRanges[$i][1]) {
                    return false;
                }
            } elseif (strpos($value, '-') !== false) {
                $range = explode('-', $value);
                if (count($range) !== 2 || !is_numeric($range[0]) || !is_numeric($range[1])) {
                    return false;
                }
            } elseif (strpos($value, '/') !== false) {
                $step = explode('/', $value);
                if (count($step) !== 2 || !is_numeric($step[1])) {
                    return false;
                }
            }
        }
    }
    
    return true;
}

/**
 * Calculate next run time for cron expression
 */
function getNextCronRun($cronExpression) {
    if (!isValidCronExpression($cronExpression)) {
        return null;
    }
    
    // Simple implementation - in production, use a proper cron parser
    $parts = explode(' ', $cronExpression);
    $now = new DateTime();
    
    // For now, return next hour if it's a simple expression
    if ($parts[0] === '*' && $parts[1] === '*') {
        $next = clone $now;
        $next->add(new DateInterval('PT1H'));
        return $next->format('Y-m-d H:i:s');
    }
    
    return null;
}

/**
 * Log error with context
 */
function logError($message, $context = []) {
    $logMessage = date('Y-m-d H:i:s') . ' - ' . $message;
    if (!empty($context)) {
        $logMessage .= ' - Context: ' . json_encode($context);
    }
    error_log($logMessage);
}

/**
 * Check system requirements
 */
function checkSystemRequirements() {
    $requirements = [
        'php_version' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'pdo_sqlite' => extension_loaded('pdo_sqlite'),
        'openssl' => extension_loaded('openssl'),
        'tar' => !empty(shell_exec('which tar')),
        'gzip' => !empty(shell_exec('which gzip')),
        'mysqldump' => !empty(shell_exec('which mysqldump')),
        'pg_dump' => !empty(shell_exec('which pg_dump'))
    ];
    
    return $requirements;
}

<?php
/**
 * Cron Job Executor
 * Scheduled task executor for automated backups
 */

define('BACKUP_MANAGER', true);
require_once 'config.php';

// Only allow execution from command line or cron
if (php_sapi_name() !== 'cli' && !isset($_GET['cron_key'])) {
    http_response_code(403);
    die('Access denied. This script can only be executed from command line or with proper authorization.');
}

// Check for cron key if accessed via web
if (isset($_GET['cron_key'])) {
    $expectedKey = $db->getSetting('cron_key', '');
    if (empty($expectedKey) || $_GET['cron_key'] !== $expectedKey) {
        http_response_code(403);
        die('Invalid cron key');
    }
}

$db = new Database();
$backupManager = new BackupManager($db);

// Log cron execution
$db->logActivity(null, 'cron_execution', 'Cron job started');

try {
    // Get all enabled schedules
    $schedules = $db->fetchAll("
        SELECT 
            bs.*,
            bc.name as config_name,
            bc.backup_type,
            bc.config_data
        FROM backup_schedules bs
        JOIN backup_configs bc ON bs.config_id = bc.id
        WHERE bs.enabled = 1 AND bc.enabled = 1
    ");
    
    $executedCount = 0;
    $errorCount = 0;
    
    foreach ($schedules as $schedule) {
        try {
            // Check if it's time to run this schedule
            if (shouldRunSchedule($schedule)) {
                echo "Executing scheduled backup: {$schedule['config_name']}\n";
                
                // Update last run time
                $db->update('backup_schedules', [
                    'last_run' => date('Y-m-d H:i:s'),
                    'next_run' => calculateNextRun($schedule['cron_expression'])
                ], 'id = ?', [$schedule['id']]);
                
                // Execute backup
                $backupManager->executeBackup($schedule['config_id']);
                
                $executedCount++;
                echo "✓ Backup completed: {$schedule['config_name']}\n";
                
            } else {
                echo "Skipping schedule: {$schedule['config_name']} (not time to run)\n";
            }
            
        } catch (Exception $e) {
            $errorCount++;
            echo "✗ Error executing backup: {$schedule['config_name']} - " . $e->getMessage() . "\n";
            
            // Log error
            $db->logActivity(null, 'cron_error', "Error executing schedule {$schedule['id']}: " . $e->getMessage());
        }
    }
    
    // Clean old backups
    echo "Cleaning old backups...\n";
    $deletedCount = $backupManager->cleanOldBackups();
    echo "✓ Cleaned {$deletedCount} old backup files\n";
    
    // Send summary email if configured
    if ($executedCount > 0 || $errorCount > 0) {
        sendCronSummary($executedCount, $errorCount);
    }
    
    echo "Cron execution completed. Executed: {$executedCount}, Errors: {$errorCount}\n";
    
    $db->logActivity(null, 'cron_execution', "Cron job completed. Executed: {$executedCount}, Errors: {$errorCount}");
    
} catch (Exception $e) {
    echo "Fatal error in cron execution: " . $e->getMessage() . "\n";
    $db->logActivity(null, 'cron_fatal_error', "Fatal error: " . $e->getMessage());
    exit(1);
}

/**
 * Check if a schedule should run based on cron expression and last run time
 */
function shouldRunSchedule($schedule) {
    $now = new DateTime();
    $lastRun = $schedule['last_run'] ? new DateTime($schedule['last_run']) : null;
    $nextRun = $schedule['next_run'] ? new DateTime($schedule['next_run']) : null;
    
    // If never run before, check if it's time based on cron expression
    if (!$lastRun) {
        return isCronTime($schedule['cron_expression'], $now);
    }
    
    // If next run time is set and we're past it
    if ($nextRun && $now >= $nextRun) {
        return true;
    }
    
    // Fallback: check if cron expression matches current time
    return isCronTime($schedule['cron_expression'], $now);
}

/**
 * Simple cron expression parser
 * Supports: minute hour day month dayofweek
 * Examples: "0 2 * * *" (daily at 2 AM), "30 1 * * 0" (weekly on Sunday at 1:30 AM)
 */
function isCronTime($cronExpression, $dateTime) {
    $parts = explode(' ', trim($cronExpression));
    if (count($parts) !== 5) {
        return false;
    }
    
    $minute = (int)$dateTime->format('i');
    $hour = (int)$dateTime->format('H');
    $day = (int)$dateTime->format('j');
    $month = (int)$dateTime->format('n');
    $dayOfWeek = (int)$dateTime->format('w'); // 0 = Sunday
    
    return (
        matchesCronField($parts[0], $minute) &&
        matchesCronField($parts[1], $hour) &&
        matchesCronField($parts[2], $day) &&
        matchesCronField($parts[3], $month) &&
        matchesCronField($parts[4], $dayOfWeek)
    );
}

/**
 * Check if a value matches a cron field
 */
function matchesCronField($field, $value) {
    if ($field === '*') {
        return true;
    }
    
    // Handle comma-separated values
    if (strpos($field, ',') !== false) {
        $values = explode(',', $field);
        foreach ($values as $v) {
            if (matchesCronField(trim($v), $value)) {
                return true;
            }
        }
        return false;
    }
    
    // Handle ranges
    if (strpos($field, '-') !== false) {
        list($start, $end) = explode('-', $field, 2);
        return $value >= (int)$start && $value <= (int)$end;
    }
    
    // Handle step values
    if (strpos($field, '/') !== false) {
        list($range, $step) = explode('/', $field, 2);
        if ($range === '*') {
            return $value % (int)$step === 0;
        }
        // Handle step with range
        if (strpos($range, '-') !== false) {
            list($start, $end) = explode('-', $range, 2);
            return $value >= (int)$start && $value <= (int)$end && ($value - (int)$start) % (int)$step === 0;
        }
    }
    
    // Exact match
    return (int)$field === $value;
}

/**
 * Calculate next run time for a cron expression
 */
function calculateNextRun($cronExpression) {
    $now = new DateTime();
    $nextRun = clone $now;
    
    // Simple implementation: add 1 hour and check if it matches
    // In production, you'd want a more sophisticated cron parser
    for ($i = 0; $i < 24 * 7; $i++) { // Check up to 1 week ahead
        $nextRun->add(new DateInterval('PT1H'));
        if (isCronTime($cronExpression, $nextRun)) {
            return $nextRun->format('Y-m-d H:i:s');
        }
    }
    
    // Fallback: next day at same time
    $nextRun = clone $now;
    $nextRun->add(new DateInterval('P1D'));
    return $nextRun->format('Y-m-d H:i:s');
}

/**
 * Send cron execution summary email
 */
function sendCronSummary($executedCount, $errorCount) {
    global $db;
    
    $emailEnabled = $db->getSetting('email_notifications', '0');
    if (!$emailEnabled) {
        return;
    }
    
    $emailTo = $db->getSetting('email_to', '');
    $emailFrom = $db->getSetting('email_from', '');
    
    if (empty($emailTo) || empty($emailFrom)) {
        return;
    }
    
    $subject = "Backup Manager - Cron Summary";
    $message = "
        <h2>Backup Manager Cron Summary</h2>
        <p><strong>Execution Time:</strong> " . date('Y-m-d H:i:s') . "</p>
        <p><strong>Backups Executed:</strong> {$executedCount}</p>
        <p><strong>Errors:</strong> {$errorCount}</p>
        
        " . ($errorCount > 0 ? '<p style="color: red;"><strong>⚠ Some backups failed. Please check the logs.</strong></p>' : '') . "
        
        <p>This is an automated message from the Backup Manager system.</p>
    ";
    
    sendEmailNotification($emailTo, $subject, $message, [
        'email_smtp_host' => $db->getSetting('email_smtp_host', ''),
        'email_smtp_port' => $db->getSetting('email_smtp_port', '587'),
        'email_smtp_user' => $db->getSetting('email_smtp_user', ''),
        'email_smtp_pass' => $db->getSetting('email_smtp_pass', ''),
        'email_from' => $emailFrom
    ]);
}

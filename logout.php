<?php
/**
 * Logout Page
 * Handles user logout and session cleanup
 */

define('BACKUP_MANAGER', true);
require_once 'config.php';

$db = new Database();
$auth = new Auth($db);

// Logout user
$auth->logout();

// Redirect to login page
header('Location: index.php');
exit;

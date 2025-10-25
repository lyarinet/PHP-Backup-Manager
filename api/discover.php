<?php
/**
 * Database Discovery API
 * Discovers available databases for MySQL and PostgreSQL
 */

define('BACKUP_MANAGER', true);
require_once dirname(__DIR__) . '/config.php';

$db = new Database();
$auth = new Auth($db);

// Require authentication
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Set JSON header
header('Content-Type: application/json');

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? '';
$host = $input['host'] ?? 'localhost';
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (empty($type) || empty($username)) {
    echo json_encode([
        'success' => false,
        'message' => 'Type, username, and password are required'
    ]);
    exit;
}

try {
    $databases = [];
    
    if ($type === 'mysql') {
        $databases = discoverMysqlDatabases($host, $username, $password);
    } elseif ($type === 'postgresql') {
        $databases = discoverPostgresDatabases($host, $username, $password);
    } else {
        throw new Exception('Invalid database type');
    }
    
    echo json_encode([
        'success' => true,
        'databases' => $databases,
        'count' => count($databases)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function discoverMysqlDatabases($host, $username, $password) {
    $databases = [];
    
    // Test connection and get databases with timeout and SSL options
    $command = sprintf(
        'timeout 10 mysql -h %s -u %s -p%s --skip-ssl -e "SHOW DATABASES;" 2>&1',
        escapeshellarg($host),
        escapeshellarg($username),
        escapeshellarg($password)
    );
    
    $output = [];
    $returnVar = 0;
    exec($command, $output, $returnVar);
    
    if ($returnVar !== 0) {
        if ($returnVar === 124) {
            throw new Exception('Connection timeout. Please check if the MySQL server is running and accessible.');
        } else {
            // Get the actual error message
            $errorMsg = implode(' ', $output);
            if (strpos($errorMsg, 'not allowed to connect') !== false) {
                throw new Exception('Host not allowed to connect. Please add your IP address to MySQL server\'s allowed hosts.');
            } elseif (strpos($errorMsg, 'Access denied') !== false) {
                throw new Exception('Access denied. Please check username and password.');
            } elseif (strpos($errorMsg, 'TLS/SSL error') !== false || strpos($errorMsg, 'certificate') !== false) {
                throw new Exception('SSL certificate error. The MySQL server is using SSL/TLS with a self-signed certificate. Please configure the MySQL server to allow non-SSL connections or provide a valid certificate.');
            } elseif (strpos($errorMsg, 'unknown variable') !== false) {
                throw new Exception('MySQL version compatibility issue. The MySQL client version does not support the SSL options. Please try connecting without SSL or update your MySQL client.');
            } else {
                throw new Exception('Failed to connect: ' . $errorMsg);
            }
        }
    }
    
    // Parse database list
    foreach ($output as $line) {
        $line = trim($line);
        // Skip only the header and empty lines, include more databases
        if (!empty($line) && $line !== 'Database') {
            $databases[] = $line;
        }
    }
    
    return $databases;
}

function discoverPostgresDatabases($host, $username, $password) {
    $databases = [];
    
    // Set password environment variable
    putenv("PGPASSWORD=" . $password);
    
    // Test connection and get databases with timeout and SSL options
    $command = sprintf(
        'timeout 10 psql -h %s -U %s --set=sslmode=disable -l -t 2>/dev/null',
        escapeshellarg($host),
        escapeshellarg($username)
    );
    
    $output = [];
    $returnVar = 0;
    exec($command, $output, $returnVar);
    
    if ($returnVar !== 0) {
        if ($returnVar === 124) {
            throw new Exception('Connection timeout. Please check if the PostgreSQL server is running and accessible.');
        } else {
            // Get the actual error message
            $errorMsg = implode(' ', $output);
            if (strpos($errorMsg, 'SSL') !== false || strpos($errorMsg, 'certificate') !== false) {
                throw new Exception('SSL certificate error. The PostgreSQL server is using SSL/TLS with a self-signed certificate. Please configure the PostgreSQL server to allow non-SSL connections or provide a valid certificate.');
            } else {
                throw new Exception('Failed to connect to PostgreSQL server: ' . $errorMsg);
            }
        }
    }
    
    // Parse database list
    foreach ($output as $line) {
        $line = trim($line);
        if (!empty($line)) {
            $parts = explode('|', $line);
            if (count($parts) > 0) {
                $dbName = trim($parts[0]);
                // Skip system databases and empty names
                if (!empty($dbName) && !in_array($dbName, ['template0', 'template1', 'postgres'])) {
                    $databases[] = $dbName;
                }
            }
        }
    }
    
    return $databases;
}
?>

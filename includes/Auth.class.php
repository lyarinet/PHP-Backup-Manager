<?php
/**
 * Authentication Class
 * User authentication, session management, and RBAC
 */

class Auth {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function login($username, $password) {
        $user = $this->db->fetch(
            "SELECT * FROM users WHERE username = ?", 
            [$username]
        );
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        
        // Log activity
        $this->db->logActivity($user['id'], 'login', 'User logged in');
        
        return true;
    }
    
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->db->logActivity($_SESSION['user_id'], 'logout', 'User logged out');
        }
        
        // Destroy session
        session_destroy();
        
        // Clear session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
    }
    
    public function isLoggedIn() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
            return false;
        }
        
        // Check session timeout
        if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
            $this->logout();
            return false;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: index.php');
            exit;
        }
    }
    
    public function requireRole($requiredRole) {
        $this->requireLogin();
        
        $userRole = $_SESSION['role'];
        $roleHierarchy = ['user' => 1, 'admin' => 2];
        
        if (!isset($roleHierarchy[$userRole]) || 
            $roleHierarchy[$userRole] < $roleHierarchy[$requiredRole]) {
            header('HTTP/1.1 403 Forbidden');
            die('Access denied');
        }
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role']
        ];
    }
    
    public function createUser($username, $password, $email = '', $role = 'user') {
        // Validate input
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            throw new Exception('Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters');
        }
        
        if (!in_array($role, ['user', 'admin'])) {
            throw new Exception('Invalid role');
        }
        
        // Check if username exists
        $existing = $this->db->fetch("SELECT id FROM users WHERE username = ?", [$username]);
        if ($existing) {
            throw new Exception('Username already exists');
        }
        
        // Create user
        $userId = $this->db->insert('users', [
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'email' => $email,
            'role' => $role
        ]);
        
        $this->db->logActivity($_SESSION['user_id'] ?? null, 'create_user', "Created user: {$username}");
        
        return $userId;
    }
    
    public function updateUser($userId, $data) {
        $allowedFields = ['username', 'email', 'role'];
        $updateData = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updateData[$key] = $value;
            }
        }
        
        if (isset($data['password']) && !empty($data['password'])) {
            if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
                throw new Exception('Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters');
            }
            $updateData['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        if (empty($updateData)) {
            return false;
        }
        
        $this->db->update('users', $updateData, 'id = ?', [$userId]);
        $this->db->logActivity($_SESSION['user_id'] ?? null, 'update_user', "Updated user ID: {$userId}");
        
        return true;
    }
    
    public function deleteUser($userId) {
        if ($userId == $_SESSION['user_id']) {
            throw new Exception('Cannot delete your own account');
        }
        
        $this->db->delete('users', 'id = ?', [$userId]);
        $this->db->logActivity($_SESSION['user_id'] ?? null, 'delete_user', "Deleted user ID: {$userId}");
        
        return true;
    }
    
    public function getAllUsers() {
        return $this->db->fetchAll("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
    }
    
    public function getUser($userId) {
        return $this->db->fetch("SELECT id, username, email, role, created_at FROM users WHERE id = ?", [$userId]);
    }
    
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token']) || 
            time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_LIFETIME) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        
        return $_SESSION['csrf_token'];
    }
    
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public function changePassword($userId, $currentPassword, $newPassword) {
        $user = $this->db->fetch("SELECT password_hash FROM users WHERE id = ?", [$userId]);
        
        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            throw new Exception('Current password is incorrect');
        }
        
        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            throw new Exception('New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters');
        }
        
        $this->db->update('users', [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)
        ], 'id = ?', [$userId]);
        
        $this->db->logActivity($userId, 'change_password', 'Password changed');
        
        return true;
    }
}

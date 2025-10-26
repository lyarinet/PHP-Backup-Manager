<?php
/**
 * User Profile Page
 * User profile management and account settings
 */

define('BACKUP_MANAGER', true);
require_once 'config.php';

$db = new Database();
$auth = new Auth($db);

// Require authentication
$auth->requireLogin();

$user = $auth->getCurrentUser();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_profile':
                handleUpdateProfile();
                break;
            case 'change_password':
                handleChangePassword();
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

function handleUpdateProfile() {
    global $db, $user;
    
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    
    if (empty($username)) {
        throw new Exception('Username is required');
    }
    
    // Check if username is already taken by another user
    $existingUser = $db->fetch("SELECT id FROM users WHERE username = ? AND id != ?", [$username, $user['id']]);
    if ($existingUser) {
        throw new Exception('Username already taken');
    }
    
    // Update user profile
    $db->update('users', [
        'username' => $username,
        'email' => $email
    ], 'id = ?', [$user['id']]);
    
    // Update session
    $_SESSION['username'] = $username;
    
    // Log activity
    $db->logActivity($user['id'], 'update_profile', "Updated profile information");
    
    $success = 'Profile updated successfully';
}

function handleChangePassword() {
    global $db, $user;
    
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        throw new Exception('All password fields are required');
    }
    
    if ($newPassword !== $confirmPassword) {
        throw new Exception('New passwords do not match');
    }
    
    if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
        throw new Exception('Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long');
    }
    
    // Verify current password
    $userData = $db->fetch("SELECT password_hash FROM users WHERE id = ?", [$user['id']]);
    if (!password_verify($currentPassword, $userData['password_hash'])) {
        throw new Exception('Current password is incorrect');
    }
    
    // Update password
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $db->update('users', [
        'password_hash' => $newPasswordHash
    ], 'id = ?', [$user['id']]);
    
    // Log activity
    $db->logActivity($user['id'], 'change_password', "Changed password");
    
    $success = 'Password changed successfully';
}

// Get user data
$userData = $db->fetch("SELECT * FROM users WHERE id = ?", [$user['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Profile</title>
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
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
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
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($user['username']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">
                                <i class="bi bi-person me-2"></i>Profile
                            </a></li>
                            <li><a class="dropdown-item" href="settings.php">
                                <i class="bi bi-sliders me-2"></i>Settings
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
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="bi bi-person-circle me-2"></i>User Profile</h1>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Profile Information -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-person me-2"></i>Profile Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?php echo htmlspecialchars($userData['username']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Role</label>
                                        <input type="text" class="form-control" value="<?php echo ucfirst($userData['role']); ?>" readonly>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Member Since</label>
                                        <input type="text" class="form-control" value="<?php echo date('F j, Y', strtotime($userData['created_at'])); ?>" readonly>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-2"></i>Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-lock me-2"></i>Change Password
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" 
                                               minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>
                                        <div class="form-text">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                               minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-warning">
                                        <i class="bi bi-key me-2"></i>Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Statistics -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-graph-up me-2"></i>Account Statistics
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h3 class="text-primary"><?php 
                                                $backupCount = $db->fetch("SELECT COUNT(*) as count FROM backup_history")['count'];
                                                echo $backupCount;
                                            ?></h3>
                                            <p class="text-muted mb-0">Total Backups</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h3 class="text-success"><?php 
                                                $successCount = $db->fetch("SELECT COUNT(*) as count FROM backup_history WHERE status = 'completed'")['count'];
                                                echo $successCount;
                                            ?></h3>
                                            <p class="text-muted mb-0">Successful</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h3 class="text-danger"><?php 
                                                $failedCount = $db->fetch("SELECT COUNT(*) as count FROM backup_history WHERE status = 'failed'")['count'];
                                                echo $failedCount;
                                            ?></h3>
                                            <p class="text-muted mb-0">Failed</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h3 class="text-info"><?php 
                                                $configCount = $db->fetch("SELECT COUNT(*) as count FROM backup_configs")['count'];
                                                echo $configCount;
                                            ?></h3>
                                            <p class="text-muted mb-0">Configurations</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>

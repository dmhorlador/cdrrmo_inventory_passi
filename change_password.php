<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Change Password';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirm password do not match';
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (password_verify($current_password, $user['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                log_password_change($conn, $_SESSION['user_id'], true);
                $success = 'Password changed successfully!';
            } else {
                log_password_change($conn, $_SESSION['user_id'], false);
                $error = 'Failed to change password. Please try again.';
            }
            $stmt->close();
        } else {
            $error = 'Current password is incorrect';
        }
    }
}

require_once 'includes/header.php';
?>

<div class="change-pw-wrapper">
    <div class="card">
        <div class="change-pw-body">
            <h2 class="change-pw-title">
                <i class="fas fa-key"></i> Change Password
            </h2>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i> Current Password
                    </label>
                    <input 
                        type="password" 
                        name="current_password" 
                        class="form-control" 
                        required
                        placeholder="Enter your current password"
                    >
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-key"></i> New Password
                    </label>
                    <input 
                        type="password" 
                        name="new_password" 
                        class="form-control" 
                        required
                        minlength="6"
                        placeholder="Enter new password (min. 6 characters)"
                    >
                    <small class="password-hint">
                        Password must be at least 6 characters long
                    </small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-check-circle"></i> Confirm New Password
                    </label>
                    <input 
                        type="password" 
                        name="confirm_password" 
                        class="form-control" 
                        required
                        placeholder="Confirm your new password"
                    >
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Change Password
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--gray-200);">
                <h4 style="color: var(--gray-700); font-size: 14px; margin-bottom: 10px;">
                    <i class="fas fa-info-circle"></i> Password Tips:
                </h4>
                <ul style="color: var(--gray-600); font-size: 13px; line-height: 1.8;">
                    <li>Use at least 6 characters (longer is better)</li>
                    <li>Include a mix of letters, numbers, and symbols</li>
                    <li>Don't use personal information</li>
                    <li>Don't reuse passwords from other accounts</li>
                    <li>Change your password regularly</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
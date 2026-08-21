<?php
require_once 'includes/config.php';
check_admin();

$page_title = 'User Management';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_user') {
            $username = sanitize_input($_POST['username']);
            $password = $_POST['password'];
            $full_name = sanitize_input($_POST['full_name']);
            $email = sanitize_input($_POST['email']);
            $phone = sanitize_input($_POST['phone']);
            $role = sanitize_input($_POST['role']);
            
            // Validate
            $errors = [];
            if (strlen($username) < 3) {
                $errors[] = "Username must be at least 3 characters";
            }
            if (strlen($password) < 6) {
                $errors[] = "Password must be at least 6 characters";
            }
            
            // Check if username exists
            $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check->bind_param("s", $username);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $errors[] = "Username already exists";
            }
            $check->close();
            
            if (empty($errors)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, phone, role, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssi", $username, $hashed_password, $full_name, $email, $phone, $role, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    log_activity($_SESSION['user_id'], 'create_user', "Created new user: $username");
                    $_SESSION['success'] = "User created successfully";
                } else {
                    $_SESSION['error'] = "Failed to create user";
                }
                $stmt->close();
                header('Location: users.php');
                exit();
            } else {
                $_SESSION['error'] = implode(', ', $errors);
            }
        } elseif ($_POST['action'] === 'edit_user') {
            $user_id = (int)$_POST['user_id'];
            $full_name = sanitize_input($_POST['full_name']);
            $email = sanitize_input($_POST['email']);
            $phone = sanitize_input($_POST['phone']);
            $role = sanitize_input($_POST['role']);
            
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, role = ? WHERE id = ? AND id != ?");
            $stmt->bind_param("ssssii", $full_name, $email, $phone, $role, $user_id, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                log_activity($_SESSION['user_id'], 'update_user', "Updated user ID: $user_id");
                $_SESSION['success'] = "User updated successfully";
            } else {
                $_SESSION['error'] = "Failed to update user";
            }
            $stmt->close();
            header('Location: users.php');
            exit();
        } elseif ($_POST['action'] === 'reset_password') {
            $user_id = (int)$_POST['user_id'];
            $new_password = $_POST['new_password'];
            
            if (strlen($new_password) < 6) {
                $_SESSION['error'] = "Password must be at least 6 characters";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ? AND id != ?");
                $stmt->bind_param("sii", $hashed_password, $user_id, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    log_activity($_SESSION['user_id'], 'reset_password', "Reset password for user ID: $user_id");
                    $_SESSION['success'] = "Password reset successfully";
                } else {
                    $_SESSION['error'] = "Failed to reset password";
                }
                $stmt->close();
            }
            header('Location: users.php');
            exit();
        } elseif ($_POST['action'] === 'delete_user') {
            $user_id = (int)$_POST['user_id'];
            
            if ($user_id == $_SESSION['user_id']) {
                $_SESSION['error'] = "You cannot delete your own account";
            } else {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                
                if ($stmt->execute()) {
                    log_activity($_SESSION['user_id'], 'delete_user', "Deleted user ID: $user_id");
                    $_SESSION['success'] = "User deleted successfully";
                } else {
                    $_SESSION['error'] = "Failed to delete user";
                }
                $stmt->close();
            }
            header('Location: users.php');
            exit();
        } elseif ($_POST['action'] === 'toggle_status') {
            $user_id = (int)$_POST['user_id'];
            $new_status = (int)$_POST['new_status'];
            
            $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ? AND id != ?");
            $stmt->bind_param("iii", $new_status, $user_id, $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
            
            log_activity($_SESSION['user_id'], 'toggle_user_status', "Changed user status to " . ($new_status ? 'active' : 'inactive'));
            echo json_encode(['success' => true]);
            exit();
        } elseif ($_POST['action'] === 'update_role') {
            $user_id = (int)$_POST['user_id'];
            $new_role = sanitize_input($_POST['role']);
            
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ? AND id != ?");
            $stmt->bind_param("sii", $new_role, $user_id, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                log_activity($_SESSION['user_id'], 'update_user_role', "Changed user role to $new_role");
                $_SESSION['success'] = "User role updated successfully";
            } else {
                $_SESSION['error'] = "Failed to update user role";
            }
            $stmt->close();
            header('Location: users.php');
            exit();
        }
    }
}

// Get all users
$users = $conn->query("
    SELECT 
        u.*,
        creator.full_name as created_by_name,
        (SELECT COUNT(*) FROM audit_log WHERE user_id = u.id) as activity_count
    FROM users u
    LEFT JOIN users creator ON u.created_by = creator.id
    ORDER BY u.created_at DESC
");

require_once 'includes/header.php';
?>

<div class="users-actions">
    <button class="btn btn-success" onclick="showAddUserModal()">
        <i class="fas fa-plus"></i> Add New User
    </button>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<div class="table-container">
    <!-- <div class="table-header">
        <h3 class="table-title">System Users</h3>
    </div> -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Created By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $users->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                        <td>
                            <?php if ($user['id'] === $_SESSION['user_id']): ?>
                                <span class="badge badge-primary"><?php echo ucfirst($user['role']); ?></span>
                            <?php else: ?>
                                <select class="form-control user-role-select" onchange="updateUserRole(<?php echo $user['id']; ?>, this.value)">
                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="staff" <?php echo $user['role'] === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                </select>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['id'] === $_SESSION['user_id']): ?>
                                <span class="badge badge-success">Active (You)</span>
                            <?php else: ?>
                                <label class="user-checkbox-label">
                                    <input 
                                        type="checkbox" 
                                        <?php echo $user['is_active'] ? 'checked' : ''; ?>
                                        onchange="toggleUserStatus(<?php echo $user['id']; ?>, this.checked)"
                                    >
                                    <span class="badge <?php echo $user['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </label>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            if ($user['last_login']) {
                                echo date('M d, Y H:i', strtotime($user['last_login']));
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($user['created_by_name'] ?? 'System'); ?></td>
                        <td>
                            <div class="user-action-btns">
                                <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                    <!-- <button class="btn btn-sm btn-info" onclick="viewPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['plain_password'] ?? 'N/A', ENT_QUOTES); ?>')" title="View Password">
                                        <i class="fas fa-eye"></i>
                                    </button> -->
                                    <button class="btn btn-sm btn-warning" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['phone'] ?? '', ENT_QUOTES); ?>', '<?php echo $user['role']; ?>')" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-primary" onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')" title="Reset Password">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal" id="addUserModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add New User</h3>
            <button class="modal-close" onclick="hideAddUserModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_user">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Role *</label>
                    <select name="role" class="form-control" required>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideAddUserModal()">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal" id="editUserModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Edit User</h3>
            <button class="modal-close" onclick="hideEditUserModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" id="edit_phone" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Role *</label>
                    <select name="role" id="edit_role" class="form-control" required>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideEditUserModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal" id="resetPasswordModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Reset Password</h3>
            <button class="modal-close" onclick="hideResetPasswordModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="reset_user_id">
            <div class="modal-body">
                <p class="reset-pw-note">Resetting password for: <strong id="reset_username"></strong></p>
                
                <div class="form-group">
                    <label class="form-label">New Password *</label>
                    <input type="text" name="new_password" class="form-control" required minlength="6" placeholder="Enter new password">
                    <small style="color: var(--gray-500);">Minimum 6 characters</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideResetPasswordModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-key"></i> Reset Password
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Password Modal -->
<div class="modal" id="viewPasswordModal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3 class="modal-title">User Password</h3>
            <button class="modal-close" onclick="hideViewPasswordModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="item-code-banner">
                <div style="font-size: 13px; color: var(--gray-600); margin-bottom: 10px;">Password</div>
                <div style="font-size: 24px; font-weight: 700; color: var(--primary); font-family: monospace;" id="user_password">
                    N/A
                </div>
            </div>
            <p style="margin-top: 15px; font-size: 12px; color: var(--gray-500); text-align: center;">
                <i class="fas fa-info-circle"></i> For security purposes only. Do not share.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="hideViewPasswordModal()">Close</button>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal" id="deleteUserModal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header" style="background: var(--danger); color: white;">
            <h3 class="modal-title">
                <i class="fas fa-exclamation-triangle"></i> Delete User
            </h3>
            <button class="modal-close" onclick="hideDeleteUserModal()" style="color: white;">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="user_id" id="delete_user_id">
            <div class="modal-body">
                <p style="font-size: 15px; margin-bottom: 15px;">
                    Are you sure you want to delete user <strong id="delete_username"></strong>?
                </p>
                <div style="background: #fee2e2; border-left: 4px solid #dc2626; padding: 12px; border-radius: 4px;">
                    <strong style="color: #991b1b;">Warning:</strong>
                    <p style="margin: 5px 0 0 0; color: #991b1b; font-size: 13px;">
                        This action cannot be undone. All user data and activity logs will be permanently deleted.
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideDeleteUserModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Delete User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddUserModal() {
    document.getElementById('addUserModal').classList.add('show');
}

function hideAddUserModal() {
    document.getElementById('addUserModal').classList.remove('show');
}

function editUser(userId, fullName, email, phone, role) {
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_full_name').value = fullName;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_phone').value = phone;
    document.getElementById('edit_role').value = role;
    document.getElementById('editUserModal').classList.add('show');
}

function hideEditUserModal() {
    document.getElementById('editUserModal').classList.remove('show');
}

function resetPassword(userId, username) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_username').textContent = username;
    document.getElementById('resetPasswordModal').classList.add('show');
}

function hideResetPasswordModal() {
    document.getElementById('resetPasswordModal').classList.remove('show');
}

function viewPassword(userId, password) {
    document.getElementById('user_password').textContent = password;
    document.getElementById('viewPasswordModal').classList.add('show');
}

function hideViewPasswordModal() {
    document.getElementById('viewPasswordModal').classList.remove('show');
}

function deleteUser(userId, username) {
    document.getElementById('delete_user_id').value = userId;
    document.getElementById('delete_username').textContent = username;
    document.getElementById('deleteUserModal').classList.add('show');
}

function hideDeleteUserModal() {
    document.getElementById('deleteUserModal').classList.remove('show');
}

function toggleUserStatus(userId, isActive) {
    const formData = new FormData();
    formData.append('action', 'toggle_status');
    formData.append('user_id', userId);
    formData.append('new_status', isActive ? 1 : 0);
    
    fetch('users.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function updateUserRole(userId, newRole) {
    if (confirm('Are you sure you want to change this user\'s role?')) {
        const formData = new FormData();
        formData.append('action', 'update_role');
        formData.append('user_id', userId);
        formData.append('role', newRole);
        
        fetch('users.php', {
            method: 'POST',
            body: formData
        })
        .then(() => {
            location.reload();
        });
    } else {
        location.reload();
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    const modals = ['addUserModal', 'editUserModal', 'resetPasswordModal', 'viewPasswordModal', 'deleteUserModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target === modal) {
            modal.classList.remove('show');
        }
    });
}

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideAddUserModal();
        hideEditUserModal();
        hideResetPasswordModal();
        hideViewPasswordModal();
        hideDeleteUserModal();
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
<?php
require_once 'includes/config.php';
check_login();

// // Only admin can access
// if ($_SESSION['role'] !== 'admin') {
//     header('Location: dashboard.php');
//     exit();
// }

$page_title = 'Manage Feedback';

// Handle response
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['respond'])) {
    $feedback_id = (int)$_POST['feedback_id'];
    $admin_response = sanitize_input($_POST['admin_response']);
    $status = sanitize_input($_POST['status']);
    
    $stmt = $conn->prepare("
        UPDATE user_feedback 
        SET admin_response = ?, 
            status = ?,
            responded_by = ?,
            responded_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->bind_param("ssii", $admin_response, $status, $_SESSION['user_id'], $feedback_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Response sent successfully!";
    } else {
        $_SESSION['error'] = "Failed to send response.";
    }
    $stmt->close();
}

// Get filter
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : 'all';
$type_filter = isset($_GET['type']) ? sanitize_input($_GET['type']) : 'all';

// Build query
$where = ["1=1"];
if ($status_filter !== 'all') {
    $where[] = "f.status = '$status_filter'";
}
if ($type_filter !== 'all') {
    $where[] = "f.feedback_type = '$type_filter'";
}

$where_clause = implode(' AND ', $where);

// Get feedback
$feedback_list = $conn->query("
    SELECT 
        f.*,
        u.full_name as user_name,
        r.full_name as responded_by_name
    FROM user_feedback f
    JOIN users u ON f.user_id = u.id
    LEFT JOIN users r ON f.responded_by = r.id
    WHERE $where_clause
    ORDER BY 
        CASE f.status
            WHEN 'pending' THEN 1
            WHEN 'reviewing' THEN 2
            WHEN 'resolved' THEN 3
            WHEN 'closed' THEN 4
        END,
        f.created_at DESC
");

require_once 'includes/header.php';
?>

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

<div class="card filter-card">
    <div class="filter-card-body feedback-filter-sm" style="padding:20px;">
        <h2 class="txn-card-title">
            <i class="fas fa-comment-dots"></i> User Feedback Management
        </h2>
        
        <!-- Filters -->
        <form method="GET" class="feedback-filter-form">
            <div class="form-group feedback-filter-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="reviewing" <?php echo $status_filter === 'reviewing' ? 'selected' : ''; ?>>Reviewing</option>
                    <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
            </div>
            
            <div class="form-group feedback-filter-group">
                <label class="form-label">Type</label>
                <select name="type" class="form-control">
                    <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                    <option value="bug" <?php echo $type_filter === 'bug' ? 'selected' : ''; ?>>Bug Report</option>
                    <option value="suggestion" <?php echo $type_filter === 'suggestion' ? 'selected' : ''; ?>>Suggestion</option>
                    <option value="complaint" <?php echo $type_filter === 'complaint' ? 'selected' : ''; ?>>Complaint</option>
                    <option value="praise" <?php echo $type_filter === 'praise' ? 'selected' : ''; ?>>Praise</option>
                    <option value="other" <?php echo $type_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Filter
            </button>
            <a href="admin_feedback.php" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Reset
            </a>
        </form>
    </div>
</div>

<div class="feedback-list-grid">
    <?php if ($feedback_list->num_rows > 0): ?>
        <?php while ($feedback = $feedback_list->fetch_assoc()): ?>
            <div class="card">
                <div class="filter-card-body feedback-filter-sm" style="padding:20px;">
                    <div class="feedback-card-header">
                        <div>
                            <h3 class="feedback-card-title">
                                <?php echo htmlspecialchars($feedback['subject']); ?>
                            </h3>
                            <div class="feedback-card-meta">
                                <span class="feedback-meta-date">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($feedback['user_name']); ?>
                                </span>
                                <span class="feedback-meta-sep">•</span>
                                <span class="feedback-meta-type">
                                    <?php echo date('M d, Y g:i A', strtotime($feedback['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="feedback-card-actions">
                            <?php
                            $type_colors = [
                                'bug' => 'danger',
                                'suggestion' => 'info',
                                'complaint' => 'warning',
                                'praise' => 'success',
                                'other' => 'primary'
                            ];
                            $type_badge = $type_colors[$feedback['feedback_type']] ?? 'primary';
                            
                            $status_colors = [
                                'pending' => 'warning',
                                'reviewing' => 'info',
                                'resolved' => 'success',
                                'closed' => 'primary'
                            ];
                            $status_badge = $status_colors[$feedback['status']] ?? 'primary';
                            ?>
                            <span class="badge badge-<?php echo $type_badge; ?>">
                                <?php echo ucfirst($feedback['feedback_type']); ?>
                            </span>
                            <span class="badge badge-<?php echo $status_badge; ?>">
                                <?php echo ucfirst($feedback['status']); ?>
                            </span>
                            <?php if ($feedback['priority'] === 'high'): ?>
                                <span class="badge badge-danger">High Priority</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="feedback-message-box">
                        <p style="margin: 0; color: var(--gray-700); font-size: 14px; line-height: 1.6; white-space: pre-wrap;"><?php echo htmlspecialchars($feedback['message']); ?></p>
                    </div>
                    
                    <?php if ($feedback['admin_response']): ?>
                        <div class="feedback-reply-box">
                            <div class="feedback-reply-label">
                                <i class="fas fa-reply"></i> Response by <?php echo htmlspecialchars($feedback['responded_by_name'] ?? 'Admin'); ?>
                                <?php if ($feedback['responded_at']): ?>
                                    • <?php echo date('M d, Y', strtotime($feedback['responded_at'])); ?>
                                <?php endif; ?>
                            </div>
                            <p style="margin: 0; color: var(--gray-800); font-size: 14px; line-height: 1.6; white-space: pre-wrap;"><?php echo htmlspecialchars($feedback['admin_response']); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <button onclick="showResponseForm(<?php echo $feedback['id']; ?>)" class="btn btn-primary btn-sm">
                        <i class="fas fa-reply"></i> <?php echo $feedback['admin_response'] ? 'Update Response' : 'Respond'; ?>
                    </button>
                    
                    <!-- Response Form (Hidden) -->
                    <div id="response-form-<?php echo $feedback['id']; ?>" style="display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--gray-200);">
                        <form method="POST">
                            <input type="hidden" name="feedback_id" value="<?php echo $feedback['id']; ?>">
                            
                            <div class="form-group">
                                <label class="form-label">Your Response</label>
                                <textarea name="admin_response" class="form-control" rows="4" required 
                                    placeholder="Type your response here..."><?php echo htmlspecialchars($feedback['admin_response'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Update Status</label>
                                <select name="status" class="form-control">
                                    <option value="reviewing" <?php echo $feedback['status'] === 'reviewing' ? 'selected' : ''; ?>>Reviewing</option>
                                    <option value="resolved" <?php echo $feedback['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                    <option value="closed" <?php echo $feedback['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                </select>
                            </div>
                            
                            <div class="feedback-reply-actions">
                                <button type="submit" name="respond" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Send Response
                                </button>
                                <button type="button" onclick="hideResponseForm(<?php echo $feedback['id']; ?>)" class="btn btn-secondary">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="card">
            <div class="feedback-empty">
                <i class="fas fa-comment-slash feedback-empty-icon"></i>
                <h3>No feedback found</h3>
                <p>No feedback matches your current filters.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function showResponseForm(id) {
    document.getElementById('response-form-' + id).style.display = 'block';
}

function hideResponseForm(id) {
    document.getElementById('response-form-' + id).style.display = 'none';
}
</script>

<?php require_once 'includes/footer.php'; ?>
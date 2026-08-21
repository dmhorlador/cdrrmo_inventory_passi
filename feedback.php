<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Send Feedback';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $feedback_type = sanitize_input($_POST['feedback_type']);
    $subject = sanitize_input($_POST['subject']);
    $message = sanitize_input($_POST['message']);
    $priority = sanitize_input($_POST['priority']);
    
    $stmt = $conn->prepare("
        INSERT INTO user_feedback (user_id, feedback_type, subject, message, priority)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("issss", $_SESSION['user_id'], $feedback_type, $subject, $message, $priority);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Your feedback has been submitted successfully!";
        header('Location: feedback.php');
        exit();
    } else {
        $error = "Failed to submit feedback. Please try again.";
    }
    $stmt->close();
}

// Get user's previous feedback
$my_feedback = $conn->query("
    SELECT 
        f.*,
        u.full_name as responded_by_name
    FROM user_feedback f
    LEFT JOIN users u ON f.responded_by = u.id
    WHERE f.user_id = {$_SESSION['user_id']}
    ORDER BY f.created_at DESC
");

require_once 'includes/header.php';
?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<div class="feedback-layout">
    <!-- Submit Feedback Form -->
    <div class="card">
        <div class="feedback-info-body">
            <h2 class="feedback-info-title">
                <i class="fas fa-comment-dots"></i> Send Feedback
            </h2>
            
            <p class="feedback-info-desc">
                We value your feedback! Help us improve the system by sharing your thoughts, reporting bugs, or suggesting features.
            </p>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Feedback Type *</label>
                    <select name="feedback_type" class="form-control" required>
                        <option value="suggestion">💡 Suggestion</option>
                        <option value="bug">🐛 Bug Report</option>
                        <option value="complaint">⚠️ Complaint</option>
                        <option value="praise">👍 Praise</option>
                        <option value="other">📝 Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-control">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Subject *</label>
                    <input type="text" name="subject" class="form-control" required 
                        placeholder="Brief description of your feedback" maxlength="200">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Message *</label>
                    <textarea name="message" class="form-control" rows="6" required 
                        placeholder="Please provide details about your feedback..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-paper-plane"></i> Submit Feedback
                </button>
            </form>
        </div>
    </div>
    
    <!-- My Feedback History -->
    <div class="card">
        <div class="feedback-form-body">
            <h2 class="feedback-info-title">
                <i class="fas fa-history"></i> My Feedback History
            </h2>
            
            <?php if ($my_feedback->num_rows > 0): ?>
                <div class="feedback-item-list">
                    <?php while ($feedback = $my_feedback->fetch_assoc()): ?>
                        <div style="border: 1px solid var(--gray-200); border-radius: 8px; padding: 15px; background: var(--gray-50);">
                            <div class="feedback-item-header">
                                <div>
                                    <h4 class="feedback-item-title">
                                        <?php echo htmlspecialchars($feedback['subject']); ?>
                                    </h4>
                                    <div class="feedback-item-badges">
                                        <?php
                                        $type_colors = [
                                            'bug' => 'danger',
                                            'suggestion' => 'info',
                                            'complaint' => 'warning',
                                            'praise' => 'success',
                                            'other' => 'primary'
                                        ];
                                        $type_badge = $type_colors[$feedback['feedback_type']] ?? 'primary';
                                        ?>
                                        <span class="badge badge-<?php echo $type_badge; ?>" class="feedback-item-badge">
                                            <?php echo ucfirst($feedback['feedback_type']); ?>
                                        </span>
                                        <?php
                                        $status_colors = [
                                            'pending' => 'warning',
                                            'reviewing' => 'info',
                                            'resolved' => 'success',
                                            'closed' => 'primary'
                                        ];
                                        $status_badge = $status_colors[$feedback['status']] ?? 'primary';
                                        ?>
                                        <span class="badge badge-<?php echo $status_badge; ?>" class="feedback-item-badge">
                                            <?php echo ucfirst($feedback['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                <small class="feedback-item-meta">
                                    <?php echo date('M d, Y', strtotime($feedback['created_at'])); ?>
                                </small>
                            </div>
                            
                            <p class="feedback-item-body">
                                <?php echo nl2br(htmlspecialchars($feedback['message'])); ?>
                            </p>
                            
                            <?php if ($feedback['admin_response']): ?>
                                <div class="feedback-reply-received">
                                    <div class="feedback-reply-received-label">
                                        <i class="fas fa-reply"></i> Admin Response
                                        <?php if ($feedback['responded_by_name']): ?>
                                            by <?php echo htmlspecialchars($feedback['responded_by_name']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <p class="feedback-reply-received-text">
                                        <?php echo nl2br(htmlspecialchars($feedback['admin_response'])); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="feedback-empty-state">
                    <i class="fas fa-comment-slash" class="feedback-empty-state-icon"></i>
                    <p >You haven't submitted any feedback yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
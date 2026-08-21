<?php
require_once 'includes/config.php';
check_admin(); // Only admins can view logs

$page_title = 'Activity Logs';

// Pagination and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$user_filter = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$action_filter = isset($_GET['action']) ? sanitize_input($_GET['action']) : '';
$date_from = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : '';

// Build query
$where = ["1=1"];
$params = [];
$types = "";

if ($search) {
    $where[] = "(u.full_name LIKE ? OR al.action LIKE ? OR al.details LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

if ($user_filter) {
    $where[] = "al.user_id = ?";
    $params[] = $user_filter;
    $types .= "i";
}

if ($action_filter) {
    $where[] = "al.action = ?";
    $params[] = $action_filter;
    $types .= "s";
}

if ($date_from) {
    $where[] = "DATE(al.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if ($date_to) {
    $where[] = "DATE(al.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$where_clause = implode(' AND ', $where);

// Get total count
$count_query = "
    SELECT COUNT(*) as total 
    FROM audit_log al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE $where_clause
";
$stmt = $conn->prepare($count_query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $per_page);
$stmt->close();

// Get logs
$query = "
    SELECT 
        al.*,
        u.full_name,
        u.username,
        u.role
    FROM audit_log al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE $where_clause
    ORDER BY al.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";
$stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result();
$stmt->close();

// Get users for filter
$users = $conn->query("SELECT id, full_name FROM users ORDER BY full_name");

// Get unique actions for filter
$actions = $conn->query("SELECT DISTINCT action FROM audit_log ORDER BY action");

require_once 'includes/header.php';
?>

<!-- Filters -->
<div class="card filter-card">
    <form method="GET" action="activity_logs.php">
        <div class="filter-grid">
            <div class="form-group filter-group-inline">
                <label class="form-label">Search</label>
                <input 
                    type="text" 
                    name="search" 
                    class="form-control" 
                    placeholder="User, action, details..."
                    value="<?php echo htmlspecialchars($search); ?>"
                >
            </div>
            
            <div class="form-group filter-group-inline">
                <label class="form-label">User</label>
                <select name="user" class="form-control">
                    <option value="">All Users</option>
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group filter-group-inline">
                <label class="form-label">Action</label>
                <select name="action" class="form-control">
                    <option value="">All Actions</option>
                    <?php while ($act = $actions->fetch_assoc()): ?>
                        <option value="<?php echo $act['action']; ?>" <?php echo $action_filter === $act['action'] ? 'selected' : ''; ?>>
                            <?php echo ucwords(str_replace('_', ' ', $act['action'])); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group filter-group-inline">
                <label class="form-label">Date From</label>
                <input 
                    type="date" 
                    name="date_from" 
                    class="form-control"
                    value="<?php echo htmlspecialchars($date_from); ?>"
                >
            </div>
            
            <div class="form-group filter-group-inline">
                <label class="form-label">Date To</label>
                <input 
                    type="date" 
                    name="date_to" 
                    class="form-control"
                    value="<?php echo htmlspecialchars($date_to); ?>"
                >
            </div>
            
            <div class="filter-actions-row">
                <button type="submit" class="btn btn-primary btn-flex">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <?php if ($search || $user_filter || $action_filter || $date_from || $date_to): ?>
                    <a href="activity_logs.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- Logs Table -->
<div class="table-container">
    <div class="table-header">
        <h3 class="table-title">Activity Logs (<?php echo number_format($total); ?> records)</h3>
        <button class="btn btn-sm btn-primary" onclick="printTable('logsTable')">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
    <div class="table-responsive">
        <table id="logsTable">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs->num_rows > 0): ?>
                    <?php while ($log = $logs->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="log-user-name">
                                    <?php echo date('M d, Y', strtotime($log['created_at'])); ?>
                                </div>
                                <div class="log-user-meta">
                                    <?php echo date('h:i:s A', strtotime($log['created_at'])); ?>
                                </div>
                            </td>
                            <td>
                                <div class="log-user-name">
                                    <?php echo htmlspecialchars($log['full_name'] ?? 'System'); ?>
                                </div>
                                <div class="log-user-meta">
                                    @<?php echo htmlspecialchars($log['username'] ?? 'system'); ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?php 
                                    if ($log['role'] === 'admin') echo 'badge-danger';
                                    elseif ($log['role'] === 'staff') echo 'badge-primary';
                                    else echo 'badge-info';
                                ?>">
                                    <?php echo ucfirst($log['role'] ?? 'system'); ?>
                                </span>
                            </td>
                            <td>
                                <strong class="log-action-primary">
                                    <?php echo ucwords(str_replace('_', ' ', $log['action'])); ?>
                                </strong>
                            </td>
                            <td>
                                <?php 
                                // Try to get details from new_values JSON first
                                $details = '-';
                                if ($log['new_values']) {
                                    $new_values = json_decode($log['new_values'], true);
                                    if (isset($new_values['details'])) {
                                        $details = $new_values['details'];
                                    } elseif (isset($new_values['description'])) {
                                        $details = $new_values['description'];
                                    }
                                } elseif ($log['details']) {
                                    $details = $log['details'];
                                }
                                
                                $details = htmlspecialchars($details);
                                
                                // Show full details or truncated
                                if (strlen($details) > 80) {
                                    echo '<span title="' . $details . '">';
                                    echo substr($details, 0, 80) . '...';
                                    echo '</span>';
                                } else {
                                    echo $details;
                                }
                                ?>
                            </td>
                            <td>
                                <code class="log-code">
                                    <?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?>
                                </code>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">No activity logs found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&user=<?php echo $user_filter; ?>&action=<?php echo $action_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&user=<?php echo $user_filter; ?>&action=<?php echo $action_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
                   class="<?php echo $i === $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&user=<?php echo $user_filter; ?>&action=<?php echo $action_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
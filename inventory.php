<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Inventory Items';

// Check if current user is admin
$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
// Both admin and staff can delete
$can_delete = isset($_SESSION['user_id']);

// Handle single item delete (soft delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $can_delete) {

    if ($_POST['action'] === 'delete' && isset($_POST['item_id'])) {
        $item_id = (int)$_POST['item_id'];
        $stmt = $conn->prepare("UPDATE inventory_items SET is_active = 0 WHERE id = ?");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Item deleted successfully.'];
        header("Location: inventory.php?" . http_build_query(array_intersect_key($_GET, array_flip(['search','category','status','location','page']))));
        exit;
    }

    if ($_POST['action'] === 'bulk_delete' && isset($_POST['item_ids']) && is_array($_POST['item_ids'])) {
        $ids = array_map('intval', $_POST['item_ids']);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $stmt = $conn->prepare("UPDATE inventory_items SET is_active = 0 WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $count = $stmt->affected_rows;
        $stmt->close();
        $_SESSION['flash_message'] = ['type' => 'success', 'text' => "$count item(s) deleted successfully."];
        header("Location: inventory.php?" . http_build_query(array_intersect_key($_GET, array_flip(['search','category','status','location','page']))));
        exit;
    }
}

// Handle search and filters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$location_filter = isset($_GET['location']) ? (int)$_GET['location'] : 0;

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where = ["i.is_active = 1"];
$params = [];
$types = "";

if ($search) {
    $where[] = "(i.item_code LIKE ? OR i.item_description LIKE ? OR i.brand LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

if ($category_filter) {
    $where[] = "i.category_id = ?";
    $params[] = $category_filter;
    $types .= "i";
}

if ($location_filter) {
    $where[] = "i.storage_location_id = ?";
    $params[] = $location_filter;
    $types .= "i";
}

if ($status_filter === 'low_stock') {
    $where[] = "i.items_on_hand > 0 AND i.items_on_hand <= i.minimum_stock_level";
} elseif ($status_filter === 'out_of_stock') {
    $where[] = "i.items_on_hand <= 0";
} elseif ($status_filter === 'in_stock') {
    $where[] = "i.items_on_hand > i.minimum_stock_level";
}

$where_clause = implode(' AND ', $where);

// Get total count
$count_query = "SELECT COUNT(*) as total FROM inventory_items i WHERE $where_clause";
$stmt = $conn->prepare($count_query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $per_page);
$stmt->close();

// Get items
$query = "
    SELECT 
        i.*,
        c.category_name,
        c.color as category_color,
        sl.location_name
    FROM inventory_items i
    JOIN categories c ON i.category_id = c.id
    LEFT JOIN storage_locations sl ON i.storage_location_id = sl.id
    WHERE $where_clause
    ORDER BY i.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";
$stmt->bind_param($types, ...$params);
$stmt->execute();
$items = $stmt->get_result();
$stmt->close();

// Get categories for filter
$categories = $conn->query("SELECT id, category_name FROM categories WHERE is_active = 1 ORDER BY category_name");

// Get storage locations for filter
$storage_locations = $conn->query("SELECT id, location_name FROM storage_locations WHERE is_active = 1 ORDER BY location_name");

// Flash message
$flash = isset($_SESSION['flash_message']) ? $_SESSION['flash_message'] : null;
unset($_SESSION['flash_message']);

require_once 'includes/header.php';
?>

<?php if ($flash): ?>
<div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>" 
     style="padding: 12px 16px; margin-bottom: 16px; border-radius: 6px; 
            background-color: <?php echo $flash['type'] === 'success' ? '#d1fae5' : '#fee2e2'; ?>; 
            color: <?php echo $flash['type'] === 'success' ? '#065f46' : '#991b1b'; ?>; 
            border: 1px solid <?php echo $flash['type'] === 'success' ? '#6ee7b7' : '#fca5a5'; ?>;">
    <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <?php echo htmlspecialchars($flash['text']); ?>
</div>
<?php endif; ?>

<div class="inventory-toolbar">
    <div class="inventory-filters">
        <form method="GET" class="inventory-filter-form">
            <div class="search-bar">
                <i class="fas fa-search search-icon"></i>
                <input 
                    type="text" 
                    id="searchInput"
                    name="search" 
                    class="search-input" 
                    placeholder="Search items..."
                    value="<?php echo htmlspecialchars($search); ?>"
                    autocomplete="off"
                >
            </div>
            
            <select name="category" class="form-control inventory-cat-select">
                <option value="">All Categories</option>
                <?php while ($cat = $categories->fetch_assoc()): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['category_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            
            <select name="location" class="form-control inventory-cat-select">
                <option value="">All Storage Locations</option>
                <?php while ($loc = $storage_locations->fetch_assoc()): ?>
                    <option value="<?php echo $loc['id']; ?>" <?php echo $location_filter == $loc['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($loc['location_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            
            <select name="status" class="form-control" style="width:150px;">
                <option value="">All Status</option>
                <option value="in_stock" <?php echo $status_filter === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                <option value="low_stock" <?php echo $status_filter === 'low_stock' ? 'selected' : ''; ?>>Stock Alert</option>
                <option value="out_of_stock" <?php echo $status_filter === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
            </select>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Filter
            </button>
            
            <?php if ($search || $category_filter || $status_filter || $location_filter): ?>
                <a href="inventory.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>
    
    <div class="flex gap-10 align-center">
        <?php if ($can_delete): ?>
            <button id="bulkDeleteBtn" class="btn btn-danger" style="display: none;" onclick="confirmBulkDelete()">
                <i class="fas fa-trash-alt"></i> Delete Selected (<span id="selectedCount">0</span>)
            </button>
        <?php endif; ?>
        <a href="add_item.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Add New Item
        </a>
    </div>
</div>

<!-- Bulk delete form (hidden, submitted via JS) -->
<!-- <?php if ($can_delete): ?> -->
<form id="bulkDeleteForm" method="POST" action="inventory.php?<?php echo http_build_query(array_intersect_key($_GET, array_flip(['search','category','status','page']))); ?>">
    <input type="hidden" name="action" value="bulk_delete">
    <div id="bulkIdsContainer"></div>
</form>
<!-- <?php endif; ?> -->

<div class="table-container">
    <div class="table-header">
        <h3 class="table-title">Inventory Items (<?php echo number_format($total); ?> total)</h3>
        <?php if ($can_delete && $total > 0): ?>
            <label class="user-checkbox-label" style="font-size:13px;color:#6b7280;">
                <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" style="margin-right:6px;">
                Select All on Page
            </label>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <?php if ($can_delete): ?>
                        <th style="width: 36px;"></th>
                    <?php endif; ?>
                    <th>Item Code</th>
                    <th>Description</th>
                    <!-- <th>Category</th> -->
                    <th>On Hand</th>
                    <th>Unit</th>
                    <th>Received</th>
                    <th>Distributed</th>
                    <th>Expiry Date</th>
                    <th>Min Level</th>
                    <!-- <th>Unit Cost</th>
                    <th>Total Value</th> -->
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($items->num_rows > 0): ?>
                    <?php while ($row = $items->fetch_assoc()): ?>
                        <tr id="row-<?php echo $row['id']; ?>">
                            <?php if ($can_delete): ?>
                                <td>
                                    <input 
                                        type="checkbox" 
                                        class="row-checkbox" 
                                        value="<?php echo $row['id']; ?>" 
                                        onchange="updateBulkDeleteBtn()"
                                        class="bulk-action-btn"
                                    >
                                </td>
                            <?php endif; ?>
                            <td><strong><?php echo htmlspecialchars($row['item_code']); ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars(substr($row['item_description'], 0, 50)) . (strlen($row['item_description']) > 50 ? '...' : ''); ?>
                            </td>
                            <!-- <td>
                                <span class="badge badge-primary" style="background-color: <?php echo htmlspecialchars($row['category_color'] ?? '#3b82f6'); ?>20; color: <?php echo htmlspecialchars($row['category_color'] ?? '#3b82f6'); ?>;">
                                    <?php echo htmlspecialchars($row['category_name']); ?>
                                </span>
                            </td> -->
                            <td>
                                <?php if ($row['items_on_hand'] <= 0): ?>
                                    <span class="badge badge-danger"><?php echo number_format($row['items_on_hand']); ?></span>
                                <?php elseif ($row['items_on_hand'] <= $row['minimum_stock_level']): ?>
                                    <span class="badge badge-warning"><?php echo number_format($row['items_on_hand']); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-success"><?php echo number_format($row['items_on_hand']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['unit'] ?? 'pcs'); ?></td>
                            <td><?php echo number_format($row['items_received']); ?></td>
                            <td><?php echo number_format($row['items_distributed']); ?></td>
                            <td>
                                <?php if ($row['expiration_date']): ?>
                                    <?php 
                                    $expiry = strtotime($row['expiration_date']);
                                    $today = time();
                                    $days_diff = floor(($expiry - $today) / (60 * 60 * 24));
                                    
                                    if ($days_diff < 0): ?>
                                        <span class="badge badge-danger" title="Expired <?php echo abs($days_diff); ?> days ago">
                                            <?php echo date('M d, Y', $expiry); ?>
                                        </span>
                                    <?php elseif ($days_diff <= 30): ?>
                                        <span class="badge badge-warning" title="Expires in <?php echo $days_diff; ?> days">
                                            <?php echo date('M d, Y', $expiry); ?>
                                        </span>
                                    <?php else: ?>
                                        <?php echo date('M d, Y', $expiry); ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($row['minimum_stock_level']); ?></td>
                            <!-- <td>₱<?php echo number_format($row['unit_cost'], 2); ?></td>
                            <td>₱<?php echo number_format($row['items_on_hand'] * $row['unit_cost'], 2); ?></td> -->
                            <td>
                                <?php if ($row['items_on_hand'] <= 0): ?>
                                    <span class="badge badge-danger">Out of Stock</span>
                                <?php elseif ($row['items_on_hand'] <= $row['minimum_stock_level']): ?>
                                    <span class="badge badge-warning">Stock Alert</span>
                                <?php else: ?>
                                    <span class="badge badge-success">In Stock</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="flex gap-10 align-center">
                                    <a href="view_item.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary" title="View Details" style="padding: 6px 12px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_item.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning" title="Edit" style="padding: 6px 12px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                   
                                        <button onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['item_code'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars(substr($row['item_description'], 0, 50), ENT_QUOTES); ?>')" class="btn btn-sm btn-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                               
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $can_delete ? 11 : 10; ?>" class="text-center">No items found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" 
                   class="<?php echo $i === $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($can_delete): ?>

<!-- Single Delete Confirmation Modal -->
<div id="deleteModal" style="display:none; position:fixed; inset:0; z-index:1000; align-items:center; justify-content:center;">
    <!-- Backdrop -->
    <div onclick="closeDeleteModal()" class="inline-modal-overlay"></div>
    <!-- Dialog -->
    <div class="inline-modal-box">
        <div style="display:flex; align-items:center; gap:14px; margin-bottom:14px;">
            <div style="background:#fee2e2; border-radius:50%; width:44px; height:44px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i class="fas fa-trash" style="color:#dc2626; font-size:18px;"></i>
            </div>
            <div>
                <h4 style="margin:0; font-size:16px; font-weight:600; color:#111827;">Delete Item</h4>
                <p style="margin:4px 0 0; font-size:13px; color:#6b7280;">This action cannot be undone.</p>
            </div>
        </div>
        <p id="deleteModalBody" style="font-size:14px; color:#374151; margin:0 0 20px; line-height:1.5;"></p>
        <div style="display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" onclick="closeDeleteModal()" class="btn btn-secondary">Cancel</button>
            <a href="#" id="deleteConfirmBtn" class="btn btn-danger" style="text-decoration: none;">
                <i class="fas fa-trash"></i> Delete
            </a>
        </div>
    </div>
</div>

<!-- Bulk Delete Confirmation Modal -->
<div id="bulkDeleteModal" style="display:none; position:fixed; inset:0; z-index:1000; align-items:center; justify-content:center;">
    <div onclick="closeBulkDeleteModal()" class="inline-modal-overlay"></div>
    <div class="inline-modal-box">
        <div style="display:flex; align-items:center; gap:14px; margin-bottom:14px;">
            <div style="background:#fee2e2; border-radius:50%; width:44px; height:44px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i class="fas fa-trash-alt" style="color:#dc2626; font-size:18px;"></i>
            </div>
            <div>
                <h4 style="margin:0; font-size:16px; font-weight:600; color:#111827;">Delete Selected Items</h4>
                <p style="margin:4px 0 0; font-size:13px; color:#6b7280;">This action cannot be undone.</p>
            </div>
        </div>
        <p id="bulkDeleteModalBody" style="font-size:14px; color:#374151; margin:0 0 20px; line-height:1.5;"></p>
        <div style="display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" onclick="closeBulkDeleteModal()" class="btn btn-secondary">Cancel</button>
            <button type="button" onclick="submitBulkDelete()" class="btn btn-danger">
                <i class="fas fa-trash-alt"></i> Delete All Selected
            </button>
        </div>
    </div>
</div>

<script>
// ── Single delete ──────────────────────────────────────────────
function confirmDelete(id, code, description) {
    document.getElementById('deleteModalBody').innerHTML =
        'Are you sure you want to delete item <strong>' + code + '</strong> &ndash; ' + description + '?';
    
    // Build the delete URL with current filters
    const params = new URLSearchParams(window.location.search);
    let deleteUrl = 'delete_item.php?id=' + id;
    if (params.has('search')) deleteUrl += '&search=' + encodeURIComponent(params.get('search'));
    if (params.has('category')) deleteUrl += '&category=' + encodeURIComponent(params.get('category'));
    if (params.has('status')) deleteUrl += '&status=' + encodeURIComponent(params.get('status'));
    
    const deleteBtn = document.getElementById('deleteConfirmBtn');
    deleteBtn.href = deleteUrl;
    
    // Add loading indicator on click
    deleteBtn.onclick = function(e) {
        e.preventDefault();
        showFormLoading('Deleting item...');
        // Navigate after short delay to show loading
        setTimeout(() => {
            window.location.href = deleteUrl;
        }, 100);
    };
    
    document.getElementById('deleteModal').style.display = 'flex';
}
function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// ── Bulk delete ────────────────────────────────────────────────
function updateBulkDeleteBtn() {
    const checked = document.querySelectorAll('.row-checkbox:checked');
    const btn = document.getElementById('bulkDeleteBtn');
    document.getElementById('selectedCount').textContent = checked.length;
    btn.style.display = checked.length > 0 ? 'inline-flex' : 'none';

    // Keep "select all" checkbox in sync
    const all = document.querySelectorAll('.row-checkbox');
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
        selectAll.checked = all.length > 0 && checked.length === all.length;
    }
}

function toggleSelectAll(checkbox) {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = checkbox.checked);
    updateBulkDeleteBtn();
}

function confirmBulkDelete() {
    const checked = document.querySelectorAll('.row-checkbox:checked');
    if (checked.length === 0) return;
    document.getElementById('bulkDeleteModalBody').innerHTML =
        'You are about to delete <strong>' + checked.length + ' item(s)</strong>. They will be removed from the active inventory.';
    document.getElementById('bulkDeleteModal').style.display = 'flex';
}
function closeBulkDeleteModal() {
    document.getElementById('bulkDeleteModal').style.display = 'none';
}
function submitBulkDelete() {
    const container = document.getElementById('bulkIdsContainer');
    container.innerHTML = '';
    document.querySelectorAll('.row-checkbox:checked').forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'item_ids[]';
        input.value = cb.value;
        container.appendChild(input);
    });
    
    // Show loading indicator before submitting bulk delete form
    showFormLoading('Deleting items...');
    
    document.getElementById('bulkDeleteForm').submit();
}

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
        closeBulkDeleteModal();
    }
});

// ── Live table search (filters rows as you type) ───────────────
(function () {
    const input = document.getElementById('searchInput');
    if (!input) return;

    let debounceTimer = null;

    function fetchTable(q) {
        const params = new URLSearchParams(window.location.search);
        params.set('search', q);
        params.delete('page'); // reset to page 1 on new search

        fetch('inventory.php?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.text())
        .then(html => {
            const doc = parser.parseFromString(html, 'text/html');

            // Update tbody
            const newTbody = doc.querySelector('tbody');
            const curTbody = document.querySelector('tbody');
            if (newTbody && curTbody) {
                curTbody.style.opacity = '0.5';
                curTbody.innerHTML = newTbody.innerHTML;
                curTbody.style.opacity = '1';
                <?php if ($can_delete): ?>
                document.querySelectorAll('.row-checkbox').forEach(cb => {
                    cb.addEventListener('change', updateBulkDeleteBtn);
                });
                updateBulkDeleteBtn();
                <?php endif; ?>
            }

            // Update title count
            const newTitle = doc.querySelector('.table-title');
            const curTitle = document.querySelector('.table-title');
            if (newTitle && curTitle) curTitle.innerHTML = newTitle.innerHTML;

            // Update pagination
            const newPag = doc.querySelector('.pagination');
            const curPag = document.querySelector('.pagination');
            if (curPag) curPag.replaceWith(newPag || document.createTextNode(''));
            else if (newPag) document.querySelector('.table-container').appendChild(newPag);
        })
        .catch(() => {});
    }

    const parser = new DOMParser();

    input.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchTable(input.value.trim()), 250);
    });
})();

// ── Auto-refresh inventory (2 seconds) ──────────────────────────
let autoRefreshInterval;
let lastUpdateTime = Date.now();
let isUserInteracting = false;
let interactionTimeout;

function startAutoRefresh() {
    // Refresh every 2 seconds
    autoRefreshInterval = setInterval(refreshInventory, 2000);
}

function pauseAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
}

function refreshInventory() {
    // Don't refresh if user is actively typing or interacting
    if (isUserInteracting) {
        return;
    }

    // Build current URL with filters
    const params = new URLSearchParams(window.location.search);

    // Keep whatever the user has typed in the search box
    const searchInput = document.getElementById('searchInput');
    if (searchInput && searchInput.value.trim()) {
        params.set('search', searchInput.value.trim());
    }

    params.set('ajax', '1'); // Add ajax parameter
    
    fetch('?' + params.toString(), {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.text())
    .then(html => {
        // Parse the response
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newTbody = doc.querySelector('tbody');
        const currentTbody = document.querySelector('tbody');
        
        if (newTbody && currentTbody) {
            // Check if content has changed
            if (newTbody.innerHTML !== currentTbody.innerHTML) {
                // Save scroll position
                const scrollY = window.scrollY;
                
                // Smooth fade transition
                currentTbody.style.transition = 'opacity 0.15s ease';
                currentTbody.style.opacity = '0.7';
                
                setTimeout(() => {
                    // Update content
                    currentTbody.innerHTML = newTbody.innerHTML;
                    
                    // Restore opacity
                    currentTbody.style.opacity = '1';
                    
                    // Restore scroll position
                    window.scrollTo(0, scrollY);
                    
                    // Reattach event listeners for checkboxes (if admin)
                    <?php if ($can_delete): ?>
                    document.querySelectorAll('.row-checkbox').forEach(cb => {
                        cb.addEventListener('change', updateBulkDeleteBtn);
                    });
                    updateBulkDeleteBtn();
                    
                    // Reset modal state
                    closeDeleteModal();
                    closeBulkDeleteModal();
                    <?php endif; ?>
                }, 150);
            }
        }
    })
    .catch(error => {
        console.log('Auto-refresh skipped:', error);
    });
}

// Track user interaction
document.addEventListener('input', function() {
    isUserInteracting = true;
    clearTimeout(interactionTimeout);
    
    // Resume auto-refresh 2 seconds after user stops typing
    interactionTimeout = setTimeout(() => {
        isUserInteracting = false;
    }, 2000);
});

// Start auto-refresh on page load
document.addEventListener('DOMContentLoaded', function() {
    startAutoRefresh();
});

// Pause when page is hidden, resume when visible
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        pauseAutoRefresh();
    } else {
        startAutoRefresh();
    }
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
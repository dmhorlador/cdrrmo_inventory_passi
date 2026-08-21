<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Categories';

// Check if current user is admin
$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $name = sanitize_input($_POST['category_name']);
        $code = sanitize_input($_POST['category_code']);
        $description = sanitize_input($_POST['description']);
        $color = sanitize_input($_POST['color']);
        
        $stmt = $conn->prepare("INSERT INTO categories (category_name, category_code, description, color) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $code, $description, $color);
        
        if ($stmt->execute()) {
            log_activity($_SESSION['user_id'], 'create_category', "Created category: $name");
            $_SESSION['success'] = "Category created successfully";
        } else {
            $_SESSION['error'] = "Failed to create category";
        }
        $stmt->close();
        header('Location: categories.php');
        exit();
    } elseif ($_POST['action'] === 'update') {
        $id = (int)$_POST['category_id'];
        $name = sanitize_input($_POST['category_name']);
        $description = sanitize_input($_POST['description']);
        $color = sanitize_input($_POST['color']);
        
        $stmt = $conn->prepare("UPDATE categories SET category_name = ?, description = ?, color = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $description, $color, $id);
        
        if ($stmt->execute()) {
            log_activity($_SESSION['user_id'], 'update_category', "Updated category: $name");
            $_SESSION['success'] = "Category updated successfully";
        } else {
            $_SESSION['error'] = "Failed to update category";
        }
        $stmt->close();
        header('Location: categories.php');
        exit();
    } elseif ($_POST['action'] === 'toggle_status') {
        $id = (int)$_POST['category_id'];
        $status = (int)$_POST['status'];
        
        $stmt = $conn->prepare("UPDATE categories SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $status, $id);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => true]);
        exit();
    } elseif ($_POST['action'] === 'delete' && $is_admin) {
        $id = (int)$_POST['category_id'];
        
        // Check if category has items
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventory_items WHERE category_id = ? AND is_active = 1");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $count_result = $check_stmt->get_result();
        $item_count = $count_result->fetch_assoc()['count'];
        $check_stmt->close();
        
        if ($item_count > 0) {
            $_SESSION['error'] = "Cannot delete category with active items. Please move or delete the items first.";
        } else {
            // Get category name for logging
            $stmt = $conn->prepare("SELECT category_name FROM categories WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $category = $result->fetch_assoc();
            $stmt->close();
            
            // Soft delete
            $stmt = $conn->prepare("UPDATE categories SET is_active = 0 WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                log_activity($_SESSION['user_id'], 'delete_category', "Deleted category: " . $category['category_name']);
                $_SESSION['success'] = "Category deleted successfully";
            } else {
                $_SESSION['error'] = "Failed to delete category";
            }
            $stmt->close();
        }
        
        header('Location: categories.php');
        exit();
    } elseif ($_POST['action'] === 'permanent_delete' && $is_admin) {
        $id = (int)$_POST['category_id'];
        
        // Check if category has items
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventory_items WHERE category_id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $count_result = $check_stmt->get_result();
        $item_count = $count_result->fetch_assoc()['count'];
        $check_stmt->close();
        
        if ($item_count > 0) {
            $_SESSION['error'] = "Cannot permanently delete category with items. Please delete all items first.";
        } else {
            // Get category name for logging
            $stmt = $conn->prepare("SELECT category_name FROM categories WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $category = $result->fetch_assoc();
            $stmt->close();
            
            // PERMANENT DELETE from database
            $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                log_activity($_SESSION['user_id'], 'permanent_delete_category', "Permanently deleted category: " . $category['category_name']);
                $_SESSION['success'] = "Category permanently deleted from database";
            } else {
                $_SESSION['error'] = "Failed to permanently delete category";
            }
            $stmt->close();
        }
        
        header('Location: categories.php');
        exit();
    }
}

// Get all categories with item count (both active and inactive)
$categories = $conn->query("
    SELECT 
        c.*,
        COUNT(i.id) as item_count,
        SUM(CASE WHEN i.items_on_hand > 0 THEN 1 ELSE 0 END) as items_in_stock
    FROM categories c
    LEFT JOIN inventory_items i ON c.id = i.category_id AND i.is_active = 1
    GROUP BY c.id
    ORDER BY c.is_active DESC, c.display_order, c.category_name
");

require_once 'includes/header.php';
?>
<?php if ($is_admin): ?>
<div class="page-actions-right">
    <button class="btn btn-success" onclick="showAddModal()">
        <i class="fas fa-plus"></i> Add Category
    </button>
</div>
<?php endif; ?>

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

<div class="dashboard-cards">
    <?php while ($cat = $categories->fetch_assoc()): ?>
        <div class="card" style="display: flex; flex-direction: column;">
            <div class="category-card-header">
                <div class="category-card-title-row">
                    <div style="width: 48px; height: 48px; border-radius: var(--radius); background: <?php echo htmlspecialchars($cat['color'] ?? '#3b82f6'); ?>18; display: flex; align-items: center; justify-content: center; color: <?php echo htmlspecialchars($cat['color'] ?? '#3b82f6'); ?>; font-size: 13px; font-weight: 800; flex-shrink: 0; letter-spacing: -.3px;">
                        <?php echo htmlspecialchars($cat['category_code']); ?>
                    </div>
                    <div style="min-width: 0;">
                        <h3 class="category-name"><?php echo htmlspecialchars($cat['category_name']); ?></h3>
                        <p class="category-subtitle">
                            <?php echo htmlspecialchars($cat['description'] ?? 'No description'); ?>
                        </p>
                    </div>
                </div>
                <div class="category-header-right">
                    <span class="badge <?php echo $cat['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                        <?php echo $cat['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>
            </div>
            
            <div style="flex: 1;"></div>
            
            <div class="category-stats-grid">
                <div>
                    <div class="category-stat-label">Total Items</div>
                    <div style="font-size: 22px; font-weight: 800; color: var(--gray-800);"><?php echo number_format($cat['item_count']); ?></div>
                </div>
                <div>
                    <div class="category-stat-label">In Stock</div>
                    <div style="font-size: 22px; font-weight: 800; color: var(--success);"><?php echo number_format($cat['items_in_stock']); ?></div>
                </div>
            </div>
            
            <div class="category-action-row">
                <button class="btn btn-sm btn-primary" onclick="viewCategory(<?php echo $cat['id']; ?>)" style="flex: 1;">
                    <i class="fas fa-eye"></i> View Items
                </button>
                <button class="btn btn-sm btn-warning" onclick="editCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['category_name']); ?>', '<?php echo htmlspecialchars($cat['description'] ?? ''); ?>', '<?php echo htmlspecialchars($cat['color'] ?? '#3b82f6'); ?>')">
                    <i class="fas fa-edit"></i>
                </button>
                
                <?php if ($is_admin): ?>
                    <button class="btn btn-sm <?php echo $cat['is_active'] ? 'btn-secondary' : 'btn-success'; ?>" 
                            onclick="toggleStatus(<?php echo $cat['id']; ?>, <?php echo $cat['is_active'] ? 0 : 1; ?>)"
                            title="<?php echo $cat['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                        <i class="fas fa-<?php echo $cat['is_active'] ? 'ban' : 'check'; ?>"></i>
                    </button>
                    
                    <?php if (!$cat['is_active']): ?>
                        <button class="btn btn-sm btn-danger" 
                                onclick="confirmPermanentDelete(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['category_name'], ENT_QUOTES); ?>', <?php echo $cat['item_count']; ?>)" 
                                title="Permanently Delete">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endwhile; ?>
</div>

<!-- Add Category Modal -->
<div class="modal" id="addModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add New Category</h3>
            <button class="modal-close" onclick="hideAddModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Category Name *</label>
                    <input type="text" name="category_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Category Code *</label>
                    <input type="text" name="category_code" class="form-control" required maxlength="10" class="input-uppercase">
                    <small class="password-hint">Short code (e.g., MED, OFF, SRR)</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Color</label>
                    <input type="color" name="color" class="form-control" value="#3b82f6">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideAddModal()">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Edit Category</h3>
            <button class="modal-close" onclick="hideEditModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="category_id" id="edit_category_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Category Name *</label>
                    <input type="text" name="category_name" id="edit_category_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Color</label>
                    <input type="color" name="color" id="edit_color" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Category
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<?php if ($is_admin): ?>
<div class="modal" id="deleteModal">
    <div class="modal-content modal-content-sm">
        <div class="modal-header modal-header-warn">
            <h3 class="modal-title modal-title-warn">
                <i class="fas fa-exclamation-triangle"></i> Delete Category
            </h3>
            <button class="modal-close" onclick="hideDeleteModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="category_id" id="delete_category_id">
            <div class="modal-body">
                <div id="deleteWarning" class="alert-box-yellow" style="display:none;">
                    <strong class="alert-label-orange">⚠️ Warning:</strong>
                    <p class="alert-text-orange" id="deleteWarningText"></p>
                </div>
                
                <div class="alert-box-blue">
                    <strong class="alert-label-blue">ℹ️ Note:</strong>
                    <p class="alert-text-blue">This will deactivate the category. Existing items will remain in the database.</p>
                </div>
                
                <p class="confirm-text">
                    Are you sure you want to delete category: <strong id="delete_category_name" class="confirm-name-warn"></strong>?
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideDeleteModal()">Cancel</button>
                <button type="submit" class="btn btn-danger" id="deleteButton">
                    <i class="fas fa-trash"></i> Delete Category
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Permanent Delete Modal -->
<div class="modal" id="permanentDeleteModal">
    <div class="modal-content modal-content-md">
        <div class="modal-header modal-header-danger-dark">
            <h3 class="modal-title modal-title-white">
                <i class="fas fa-exclamation-triangle"></i> PERMANENT DELETE
            </h3>
            <button class="modal-close modal-close-white" onclick="hidePermanentDeleteModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="permanent_delete">
            <input type="hidden" name="category_id" id="perm_delete_category_id">
            <div class="modal-body">
                <div class="alert-box-danger-dark">
                    <strong class="alert-label-dark">🚨 DANGER ZONE:</strong>
                    <p class="alert-text-dark">This action CANNOT be undone! The category will be permanently removed from the database.</p>
                </div>
                
                <div id="permDeleteWarning" class="alert-box-yellow" style="display:none;">
                    <strong class="alert-label-orange">⚠️ Warning:</strong>
                    <p class="alert-text-orange" id="permDeleteWarningText"></p>
                </div>
                
                <p class="confirm-text">
                    Permanently delete category: <strong id="perm_delete_category_name" class="confirm-name-dark"></strong>?
                </p>
                
                <p class="confirm-note">
                    This will remove all records from the database. Use only for cleanup purposes.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hidePermanentDeleteModal()">Cancel</button>
                <button type="submit" class="btn btn-danger btn-dark-danger" id="permDeleteButton">
                    <i class="fas fa-skull-crossbones"></i> PERMANENT DELETE
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function showAddModal() {
    document.getElementById('addModal').classList.add('show');
}

function hideAddModal() {
    document.getElementById('addModal').classList.remove('show');
}

function editCategory(id, name, description, color) {
    document.getElementById('edit_category_id').value = id;
    document.getElementById('edit_category_name').value = name;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_color').value = color;
    document.getElementById('editModal').classList.add('show');
}

function hideEditModal() {
    document.getElementById('editModal').classList.remove('show');
}

function confirmDelete(categoryId, categoryName, itemCount) {
    document.getElementById('delete_category_id').value = categoryId;
    document.getElementById('delete_category_name').textContent = categoryName;
    
    const warning = document.getElementById('deleteWarning');
    const warningText = document.getElementById('deleteWarningText');
    const deleteButton = document.getElementById('deleteButton');
    
    if (itemCount > 0) {
        warning.style.display = 'block';
        warningText.textContent = `This category has ${itemCount} active item(s). You must move or delete these items before deleting the category.`;
        deleteButton.disabled = true;
        deleteButton.style.opacity = '0.5';
        deleteButton.style.cursor = 'not-allowed';
    } else {
        warning.style.display = 'none';
        deleteButton.disabled = false;
        deleteButton.style.opacity = '1';
        deleteButton.style.cursor = 'pointer';
    }
    
    document.getElementById('deleteModal').classList.add('show');
}

function hideDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
}

function confirmPermanentDelete(categoryId, categoryName, itemCount) {
    document.getElementById('perm_delete_category_id').value = categoryId;
    document.getElementById('perm_delete_category_name').textContent = categoryName;
    
    const warning = document.getElementById('permDeleteWarning');
    const warningText = document.getElementById('permDeleteWarningText');
    const deleteButton = document.getElementById('permDeleteButton');
    
    if (itemCount > 0) {
        warning.style.display = 'block';
        warningText.textContent = `This category has ${itemCount} item(s). You must delete all items before permanently deleting the category.`;
        deleteButton.disabled = true;
        deleteButton.style.opacity = '0.5';
        deleteButton.style.cursor = 'not-allowed';
    } else {
        warning.style.display = 'none';
        deleteButton.disabled = false;
        deleteButton.style.opacity = '1';
        deleteButton.style.cursor = 'pointer';
    }
    
    document.getElementById('permanentDeleteModal').classList.add('show');
}

function hidePermanentDeleteModal() {
    document.getElementById('permanentDeleteModal').classList.remove('show');
}

function toggleStatus(categoryId, newStatus) {
    const formData = new FormData();
    formData.append('action', 'toggle_status');
    formData.append('category_id', categoryId);
    formData.append('status', newStatus);
    
    fetch('categories.php', {
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

function viewCategory(id) {
    window.location.href = 'inventory.php?category=' + id;
}

function toggleStatus(categoryId, newStatus) {
    const formData = new FormData();
    formData.append('action', 'toggle_status');
    formData.append('category_id', categoryId);
    formData.append('status', newStatus);
    
    fetch('categories.php', {
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

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        hideAddModal();
        hideEditModal();
        hideDeleteModal();
        hidePermanentDeleteModal();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
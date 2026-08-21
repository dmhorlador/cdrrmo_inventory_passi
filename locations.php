<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Storage Locations';

// Check if current user is admin
$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

// Handle location actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $name = sanitize_input($_POST['location_name']);
        $code = sanitize_input($_POST['location_code']);
        $description = sanitize_input($_POST['description']);
        $capacity = !empty($_POST['capacity']) ? (int)$_POST['capacity'] : null;
        
        $stmt = $conn->prepare("INSERT INTO storage_locations (location_name, location_code, description, capacity) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $name, $code, $description, $capacity);
        
        if ($stmt->execute()) {
            log_activity($_SESSION['user_id'], 'create_location', "Created location: $name");
            $_SESSION['success'] = "Location created successfully";
        } else {
            $_SESSION['error'] = "Failed to create location";
        }
        $stmt->close();
        header('Location: locations.php');
        exit();
    } elseif ($_POST['action'] === 'update') {
        $id = (int)$_POST['location_id'];
        $name = sanitize_input($_POST['location_name']);
        $description = sanitize_input($_POST['description']);
        $capacity = !empty($_POST['capacity']) ? (int)$_POST['capacity'] : null;
        
        $stmt = $conn->prepare("UPDATE storage_locations SET location_name = ?, description = ?, capacity = ? WHERE id = ?");
        $stmt->bind_param("ssii", $name, $description, $capacity, $id);
        
        if ($stmt->execute()) {
            log_activity($_SESSION['user_id'], 'update_location', "Updated location: $name");
            $_SESSION['success'] = "Location updated successfully";
        } else {
            $_SESSION['error'] = "Failed to update location";
        }
        $stmt->close();
        header('Location: locations.php');
        exit();
    } elseif ($_POST['action'] === 'toggle_status') {
        $id = (int)$_POST['location_id'];
        $status = (int)$_POST['status'];
        
        $stmt = $conn->prepare("UPDATE storage_locations SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $status, $id);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => true]);
        exit();
    } elseif ($_POST['action'] === 'delete' && $is_admin) {
        $id = (int)$_POST['location_id'];
        
        // Get location name for logging
        $stmt = $conn->prepare("SELECT location_name FROM storage_locations WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $location = $result->fetch_assoc();
        $stmt->close();
        
        // Soft delete (set is_active = 0)
        $stmt = $conn->prepare("UPDATE storage_locations SET is_active = 0 WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            log_activity($_SESSION['user_id'], 'delete_location', "Deleted location: " . $location['location_name']);
            $_SESSION['success'] = "Storage location deleted successfully";
        } else {
            $_SESSION['error'] = "Failed to delete storage location";
        }
        $stmt->close();
        
        header('Location: locations.php');
        exit();
    } elseif ($_POST['action'] === 'permanent_delete' && $is_admin) {
        $id = (int)$_POST['location_id'];
        
        // Check if location has items assigned
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventory_items WHERE storage_location_id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $count_result = $check_stmt->get_result();
        $item_count = $count_result->fetch_assoc()['count'];
        $check_stmt->close();
        
        if ($item_count > 0) {
            $_SESSION['error'] = "Cannot permanently delete location with assigned items. Please reassign or delete items first.";
        } else {
            // Get location name for logging
            $stmt = $conn->prepare("SELECT location_name FROM storage_locations WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $location = $result->fetch_assoc();
            $stmt->close();
            
            // PERMANENT DELETE from database
            $stmt = $conn->prepare("DELETE FROM storage_locations WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                log_activity($_SESSION['user_id'], 'permanent_delete_location', "Permanently deleted location: " . $location['location_name']);
                $_SESSION['success'] = "Storage location permanently deleted from database";
            } else {
                $_SESSION['error'] = "Failed to permanently delete storage location";
            }
            $stmt->close();
        }
        
        header('Location: locations.php');
        exit();
    }
}

// Get all locations with item count (active and inactive)
$locations = $conn->query("
    SELECT 
        sl.*,
        COUNT(i.id) as item_count,
        SUM(CASE WHEN i.items_on_hand > 0 THEN 1 ELSE 0 END) as items_with_stock
    FROM storage_locations sl
    LEFT JOIN inventory_items i ON sl.id = i.storage_location_id AND i.is_active = 1
    GROUP BY sl.id
    ORDER BY sl.is_active DESC, sl.location_name
");

require_once 'includes/header.php';
?>
<?php if ($is_admin): ?>
<div class="locations-actions">
    <button class="btn btn-success" onclick="showAddModal()">
        <i class="fas fa-plus"></i> Add Location
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

<div class="table-container">
    <!-- <div class="table-header">
        <h3 class="table-title">Storage Locations</h3>
    </div> -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Location Code</th>
                    <th>Location Name</th>
                    <th>Description</th>
                    <th>Capacity</th>
                    <th>Items Stored</th>
                    <th>Items with Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($locations->num_rows > 0): ?>
                    <?php while ($location = $locations->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($location['location_code'] ?? '-'); ?></strong></td>
                            <td>
                                <i class="fas fa-warehouse location-icon-primary"></i>
                                <?php echo htmlspecialchars($location['location_name']); ?>
                            </td>
                            <td><?php echo htmlspecialchars(substr($location['description'] ?? '-', 0, 50)) . (strlen($location['description'] ?? '') > 50 ? '...' : ''); ?></td>
                            <td>
                                <?php 
                                if ($location['capacity']) {
                                    echo number_format($location['capacity']);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td><?php echo number_format($location['item_count']); ?></td>
                            <td>
                                <span class="badge badge-success">
                                    <?php echo number_format($location['items_with_stock']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $location['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo $location['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick='editLocation(<?php echo json_encode($location); ?>)' title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <?php if ($is_admin): ?>
                                    <!-- Toggle Active/Inactive -->
                                    <button class="btn btn-sm <?php echo $location['is_active'] ? 'btn-secondary' : 'btn-success'; ?>" 
                                            onclick="toggleStatus(<?php echo $location['id']; ?>, <?php echo $location['is_active'] ? 0 : 1; ?>)"
                                            title="<?php echo $location['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                        <i class="fas fa-<?php echo $location['is_active'] ? 'ban' : 'check'; ?>"></i>
                                    </button>
                                    
                                    <!-- Permanent Delete (only for inactive) -->
                                    <?php if (!$location['is_active']): ?>
                                        <button class="btn btn-sm btn-danger" 
                                                onclick="confirmPermanentDelete(<?php echo $location['id']; ?>, '<?php echo htmlspecialchars($location['location_name'], ENT_QUOTES); ?>', <?php echo $location['item_count']; ?>)" 
                                                title="Permanently Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center">No storage locations found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Location Modal -->
<div class="modal" id="addModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add Storage Location</h3>
            <button class="modal-close" onclick="hideAddModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Location Name *</label>
                    <input type="text" name="location_name" class="form-control" required 
                        placeholder="e.g., Main Warehouse, Storage Room A">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Location Code *</label>
                    <input type="text" name="location_code" class="form-control" required 
                        placeholder="e.g., WH-01, SR-A" class="input-uppercase">
                    <small class="password-hint">Unique identifier for this location</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" 
                        placeholder="Brief description of this storage location"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Capacity (optional)</label>
                    <input type="number" name="capacity" class="form-control" min="1" 
                        placeholder="Maximum storage capacity">
                    <small class="password-hint">Leave blank if no specific capacity limit</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideAddModal()">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add Location
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Location Modal -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Edit Storage Location</h3>
            <button class="modal-close" onclick="hideEditModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="location_id" id="edit_location_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Location Name *</label>
                    <input type="text" name="location_name" id="edit_location_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Capacity (optional)</label>
                    <input type="number" name="capacity" id="edit_capacity" class="form-control" min="1">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Location
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<?php if ($is_admin): ?>
<div class="modal" id="deleteModal">
    <div class="modal-content" class="modal-content-sm">
        <div class="modal-header" class="modal-header-warn">
            <h3 class="modal-title modal-title-warn">
                <i class="fas fa-exclamation-triangle"></i> Delete Storage Location
            </h3>
            <button class="modal-close" onclick="hideDeleteModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="location_id" id="delete_location_id">
            <div class="modal-body">
                <div class="alert-box-yellow">
                    <strong class="alert-label-orange">⚠️ Warning:</strong>
                    <p class="alert-text-orange">This will deactivate the storage location. Items assigned to this location will remain in the database.</p>
                </div>
                
                <p class="confirm-text">
                    Are you sure you want to delete storage location: <strong class="confirm-name-warn"></strong>?
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideDeleteModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Delete Location
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Permanent Delete Modal -->
<div class="modal" id="permanentDeleteModal">
    <div class="modal-content" style="max-width: 550px;">
        <div class="modal-header" style="background: #7f1d1d; border-bottom: 2px solid #450a0a;">
            <h3 class="modal-title" style="color: #ffffff;">
                <i class="fas fa-exclamation-triangle"></i> PERMANENT DELETE
            </h3>
            <button class="modal-close" style="color: #ffffff;" onclick="hidePermanentDeleteModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="permanent_delete">
            <input type="hidden" name="location_id" id="perm_delete_location_id">
            <div class="modal-body">
                <div style="background: #7f1d1d20; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #7f1d1d;">
                    <strong style="color: #7f1d1d;">🚨 DANGER ZONE:</strong>
                    <p style="margin: 5px 0 0 0; color: #7f1d1d; font-weight: 600;">This action CANNOT be undone! The location will be permanently removed from the database.</p>
                </div>
                
                <div id="permDeleteWarning" style="background: #fef3c7; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #f59e0b; display: none;">
                    <strong class="alert-label-orange">⚠️ Warning:</strong>
                    <p style="margin: 5px 0 0 0; color: #92400e;" id="permDeleteWarningText"></p>
                </div>
                
                <p class="confirm-text">
                    Permanently delete location: <strong id="perm_delete_location_name" style="color: #7f1d1d;"></strong>?
                </p>
                
                <p class="confirm-note">
                    This will remove all records from the database. Use only for cleanup purposes.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hidePermanentDeleteModal()">Cancel</button>
                <button type="submit" class="btn btn-danger" id="permDeleteButton" style="background: #7f1d1d;">
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

function editLocation(location) {
    document.getElementById('edit_location_id').value = location.id;
    document.getElementById('edit_location_name').value = location.location_name;
    document.getElementById('edit_description').value = location.description || '';
    document.getElementById('edit_capacity').value = location.capacity || '';
    document.getElementById('editModal').classList.add('show');
}

function hideEditModal() {
    document.getElementById('editModal').classList.remove('show');
}

function confirmDelete(locationId, locationName) {
    document.getElementById('delete_location_id').value = locationId;
    document.getElementById('delete_location_name').textContent = locationName;
    document.getElementById('deleteModal').classList.add('show');
}

function hideDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
}

function confirmPermanentDelete(locationId, locationName, itemCount) {
    document.getElementById('perm_delete_location_id').value = locationId;
    document.getElementById('perm_delete_location_name').textContent = locationName;
    
    const warning = document.getElementById('permDeleteWarning');
    const warningText = document.getElementById('permDeleteWarningText');
    const deleteButton = document.getElementById('permDeleteButton');
    
    if (itemCount > 0) {
        warning.style.display = 'block';
        warningText.textContent = `This location has ${itemCount} item(s) assigned. You must reassign or delete all items before permanently deleting the location.`;
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

function toggleStatus(locationId, newStatus) {
    const formData = new FormData();
    formData.append('action', 'toggle_status');
    formData.append('location_id', locationId);
    formData.append('status', newStatus);
    
    fetch('locations.php', {
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
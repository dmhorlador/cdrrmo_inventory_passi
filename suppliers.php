<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Suppliers';

// Check if current user is admin
$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

// Handle supplier actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $name = sanitize_input($_POST['supplier_name']);
        $contact = sanitize_input($_POST['contact_person']);
        $phone = sanitize_input($_POST['phone']);
        $email = sanitize_input($_POST['email']);
        $address = sanitize_input($_POST['address']);
        
        $stmt = $conn->prepare("INSERT INTO suppliers (supplier_name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $contact, $phone, $email, $address);
        
        if ($stmt->execute()) {
            log_activity($_SESSION['user_id'], 'create_supplier', "Created supplier: $name");
            $_SESSION['success'] = "Supplier created successfully";
        } else {
            $_SESSION['error'] = "Failed to create supplier";
        }
        $stmt->close();
        header('Location: suppliers.php');
        exit();
    } elseif ($_POST['action'] === 'update') {
        $id = (int)$_POST['supplier_id'];
        $name = sanitize_input($_POST['supplier_name']);
        $contact = sanitize_input($_POST['contact_person']);
        $phone = sanitize_input($_POST['phone']);
        $email = sanitize_input($_POST['email']);
        $address = sanitize_input($_POST['address']);
        
        $stmt = $conn->prepare("UPDATE suppliers SET supplier_name = ?, contact_person = ?, phone = ?, email = ?, address = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $name, $contact, $phone, $email, $address, $id);
        
        if ($stmt->execute()) {
            log_activity($_SESSION['user_id'], 'update_supplier', "Updated supplier: $name");
            $_SESSION['success'] = "Supplier updated successfully";
        } else {
            $_SESSION['error'] = "Failed to update supplier";
        }
        $stmt->close();
        header('Location: suppliers.php');
        exit();
    } elseif ($_POST['action'] === 'toggle_status') {
        $id = (int)$_POST['supplier_id'];
        $status = (int)$_POST['status'];
        
        $stmt = $conn->prepare("UPDATE suppliers SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $status, $id);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => true]);
        exit();
    } elseif ($_POST['action'] === 'delete' && $is_admin) {
        $id = (int)$_POST['supplier_id'];
        
        // Get supplier name for logging
        $stmt = $conn->prepare("SELECT supplier_name FROM suppliers WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $supplier = $result->fetch_assoc();
        $stmt->close();
        
        // Soft delete (set is_active = 0)
        $stmt = $conn->prepare("UPDATE suppliers SET is_active = 0 WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            log_activity($_SESSION['user_id'], 'delete_supplier', "Deleted supplier: " . $supplier['supplier_name']);
            $_SESSION['success'] = "Supplier deleted successfully";
        } else {
            $_SESSION['error'] = "Failed to delete supplier";
        }
        $stmt->close();
        
        header('Location: suppliers.php');
        exit();
    } elseif ($_POST['action'] === 'permanent_delete' && $is_admin) {
        $id = (int)$_POST['supplier_id'];
        
        // Check if supplier has transactions
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM transactions WHERE supplier_id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $count_result = $check_stmt->get_result();
        $transaction_count = $count_result->fetch_assoc()['count'];
        $check_stmt->close();
        
        if ($transaction_count > 0) {
            $_SESSION['error'] = "Cannot permanently delete supplier with transactions. Please delete transactions first.";
        } else {
            // Get supplier name for logging
            $stmt = $conn->prepare("SELECT supplier_name FROM suppliers WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $supplier = $result->fetch_assoc();
            $stmt->close();
            
            // PERMANENT DELETE from database
            $stmt = $conn->prepare("DELETE FROM suppliers WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                log_activity($_SESSION['user_id'], 'permanent_delete_supplier', "Permanently deleted supplier: " . $supplier['supplier_name']);
                $_SESSION['success'] = "Supplier permanently deleted from database";
            } else {
                $_SESSION['error'] = "Failed to permanently delete supplier";
            }
            $stmt->close();
        }
        
        header('Location: suppliers.php');
        exit();
    }
}

// Get all suppliers with transaction count (active and inactive)
$suppliers = $conn->query("
    SELECT 
        s.*,
        COUNT(t.id) as transaction_count,
        MAX(t.transaction_date) as last_transaction_date,
        SUM(t.total_cost) as total_value
    FROM suppliers s
    LEFT JOIN transactions t ON s.id = t.supplier_id
    GROUP BY s.id
    ORDER BY s.is_active DESC, s.supplier_name
");

require_once 'includes/header.php';
?>
<?php if ($is_admin):?>
<div class="supplier-actions">
    <button class="btn btn-success" onclick="showAddModal()">
        <i class="fas fa-plus"></i> Add Supplier
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
        <h3 class="table-title">Supplier List</h3>
    </div> -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Supplier Name</th>
                    <th>Contact Person</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Address</th>
                    <th>Transactions</th>
                    <th>Total Value</th>
                    <th>Last Transaction</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($suppliers->num_rows > 0): ?>
                    <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($supplier['supplier_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($supplier['contact_person'] ?? '-'); ?></td>
                            <td>
                                <?php if ($supplier['phone']): ?>
                                    <a href="tel:<?php echo $supplier['phone']; ?>" class="supplier-phone-link">
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($supplier['phone']); ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($supplier['email']): ?>
                                    <a href="mailto:<?php echo $supplier['email']; ?>" class="supplier-email-link">
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($supplier['email']); ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(substr($supplier['address'] ?? '-', 0, 40)) . (strlen($supplier['address'] ?? '') > 40 ? '...' : ''); ?></td>
                            <td><?php echo number_format($supplier['transaction_count']); ?></td>
                            <td>₱<?php echo number_format($supplier['total_value'] ?? 0, 2); ?></td>
                            <td>
                                <?php 
                                if ($supplier['last_transaction_date']) {
                                    echo date('M d, Y', strtotime($supplier['last_transaction_date']));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $supplier['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo $supplier['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick='editSupplier(<?php echo json_encode($supplier); ?>)' title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <?php if ($is_admin): ?>
                                    <!-- Toggle Active/Inactive -->
                                    <button class="btn btn-sm <?php echo $supplier['is_active'] ? 'btn-secondary' : 'btn-success'; ?>" 
                                            onclick="toggleStatus(<?php echo $supplier['id']; ?>, <?php echo $supplier['is_active'] ? 0 : 1; ?>)"
                                            title="<?php echo $supplier['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                        <i class="fas fa-<?php echo $supplier['is_active'] ? 'ban' : 'check'; ?>"></i>
                                    </button>
                                    
                                    <!-- Permanent Delete (only for inactive) -->
                                    <?php if (!$supplier['is_active']): ?>
                                        <button class="btn btn-sm btn-danger" 
                                                onclick="confirmPermanentDelete(<?php echo $supplier['id']; ?>, '<?php echo htmlspecialchars($supplier['supplier_name'], ENT_QUOTES); ?>', <?php echo $supplier['transaction_count']; ?>)" 
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
                        <td colspan="10" class="text-center">No suppliers found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Supplier Modal -->
<div class="modal" id="addModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add New Supplier</h3>
            <button class="modal-close" onclick="hideAddModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Supplier Name *</label>
                    <input type="text" name="supplier_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Contact Person</label>
                    <input type="text" name="contact_person" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideAddModal()">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add Supplier
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Supplier Modal -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Edit Supplier</h3>
            <button class="modal-close" onclick="hideEditModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="supplier_id" id="edit_supplier_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Supplier Name *</label>
                    <input type="text" name="supplier_name" id="edit_supplier_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Contact Person</label>
                    <input type="text" name="contact_person" id="edit_contact_person" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" id="edit_phone" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" id="edit_address" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Supplier
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
                <i class="fas fa-exclamation-triangle"></i> Delete Supplier
            </h3>
            <button class="modal-close" onclick="hideDeleteModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="supplier_id" id="delete_supplier_id">
            <div class="modal-body">
                <div class="alert-box-yellow">
                    <strong class="alert-label-orange">⚠️ Warning:</strong>
                    <p class="alert-text-orange">This will deactivate the supplier. Existing transactions will be preserved.</p>
                </div>
                
                <p class="confirm-text">
                    Are you sure you want to delete supplier: <strong class="confirm-name-warn"></strong>?
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideDeleteModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Delete Supplier
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
            <input type="hidden" name="supplier_id" id="perm_delete_supplier_id">
            <div class="modal-body">
                <div style="background: #7f1d1d20; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #7f1d1d;">
                    <strong style="color: #7f1d1d;">🚨 DANGER ZONE:</strong>
                    <p style="margin: 5px 0 0 0; color: #7f1d1d; font-weight: 600;">This action CANNOT be undone! The supplier will be permanently removed from the database.</p>
                </div>
                
                <div id="permDeleteWarning" style="background: #fef3c7; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #f59e0b; display: none;">
                    <strong class="alert-label-orange">⚠️ Warning:</strong>
                    <p class="alert-text-orange"></p>
                </div>
                
                <p class="confirm-text">
                    Permanently delete supplier: <strong id="perm_delete_supplier_name" style="color: #7f1d1d;"></strong>?
                </p>
                
                <p style="font-size: 13px; color: #6b7280; margin-top: 15px;">
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

function editSupplier(supplier) {
    document.getElementById('edit_supplier_id').value = supplier.id;
    document.getElementById('edit_supplier_name').value = supplier.supplier_name;
    document.getElementById('edit_contact_person').value = supplier.contact_person || '';
    document.getElementById('edit_phone').value = supplier.phone || '';
    document.getElementById('edit_email').value = supplier.email || '';
    document.getElementById('edit_address').value = supplier.address || '';
    document.getElementById('editModal').classList.add('show');
}

function hideEditModal() {
    document.getElementById('editModal').classList.remove('show');
}

function confirmDelete(supplierId, supplierName) {
    document.getElementById('delete_supplier_id').value = supplierId;
    document.getElementById('delete_supplier_name').textContent = supplierName;
    document.getElementById('deleteModal').classList.add('show');
}

function hideDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
}

function confirmPermanentDelete(supplierId, supplierName, transactionCount) {
    document.getElementById('perm_delete_supplier_id').value = supplierId;
    document.getElementById('perm_delete_supplier_name').textContent = supplierName;
    
    const warning = document.getElementById('permDeleteWarning');
    const warningText = document.getElementById('permDeleteWarningText');
    const deleteButton = document.getElementById('permDeleteButton');
    
    if (transactionCount > 0) {
        warning.style.display = 'block';
        warningText.textContent = `This supplier has ${transactionCount} transaction(s). You must delete all transactions before permanently deleting the supplier.`;
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

function toggleStatus(supplierId, newStatus) {
    const formData = new FormData();
    formData.append('action', 'toggle_status');
    formData.append('supplier_id', supplierId);
    formData.append('status', newStatus);
    
    fetch('suppliers.php', {
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
<?php
require_once 'includes/config.php';
check_login();

if (!isset($_GET['id'])) {
    echo '<div class="alert alert-error">Invalid transaction ID</div>';
    exit();
}

$transaction_id = (int)$_GET['id'];

// Get transaction details
$query = "
    SELECT 
        t.*,
        i.item_code,
        i.item_description,
        i.brand,
        i.model,
        i.unit,
        c.category_name,
        c.color as category_color,
        s.supplier_name,
        s.contact_person as supplier_contact,
        s.phone as supplier_phone,
        sl.location_name,
        creator.full_name as created_by_name,
        approver.full_name as approved_by_name
    FROM transactions t
    JOIN inventory_items i ON t.item_id = i.id
    JOIN categories c ON i.category_id = c.id
    LEFT JOIN suppliers s ON t.supplier_id = s.id
    LEFT JOIN storage_locations sl ON i.storage_location_id = sl.id
    LEFT JOIN users creator ON t.created_by = creator.id
    LEFT JOIN users approver ON t.approved_by = approver.id
    WHERE t.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-error">Transaction not found</div>';
    exit();
}

$t = $result->fetch_assoc();
$stmt->close();
?>

<div class="txn-detail-wrap">
    <!-- Transaction Header -->
    <div class="txn-detail-header">
        <div class="txn-detail-header-row">
            <div>
                <h2 class="txn-detail-code">
                    <?php echo htmlspecialchars($t['transaction_code']); ?>
                </h2>
                <p class="txn-detail-date">
                    <?php echo date('F d, Y', strtotime($t['transaction_date'])); ?>
                </p>
            </div>
            <div>
                <?php 
                $badge_class = 'badge-info';
                $icon = 'fa-exchange-alt';
                switch ($t['transaction_type']) {
                    case 'received':
                        $badge_class = 'badge-success';
                        $icon = 'fa-arrow-down';
                        break;
                    case 'distributed':
                        $badge_class = 'badge-warning';
                        $icon = 'fa-arrow-up';
                        break;
                    case 'adjustment':
                        $badge_class = 'badge-info';
                        $icon = 'fa-edit';
                        break;
                    case 'return':
                        $badge_class = 'badge-primary';
                        $icon = 'fa-undo';
                        break;
                    case 'damaged':
                        $badge_class = 'badge-danger';
                        $icon = 'fa-exclamation-triangle';
                        break;
                    case 'expired':
                        $badge_class = 'badge-danger';
                        $icon = 'fa-clock';
                        break;
                }
                ?>
                <span class="badge <?php echo $badge_class; ?>" txn-detail-badge-lg">
                    <i class="fas <?php echo $icon; ?>"></i>
                    <?php echo ucfirst($t['transaction_type']); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Item Information -->
    <div class="txn-section">
        <h3 class="txn-section-title">
            <i class="fas fa-box"></i> Item Information
        </h3>
        <div class="txn-info-grid">
            <div>
                <div class="txn-detail-field-label">Item Code</div>
                <div class="txn-detail-field-value">
                    <?php echo htmlspecialchars($t['item_code']); ?>
                </div>
            </div>
            <div>
                <div class="txn-detail-field-label">Category</div>
                <div>
                    <span class="badge" style="background-color: <?php echo htmlspecialchars($t['category_color'] ?? '#3b82f6'); ?>20; color: <?php echo htmlspecialchars($t['category_color'] ?? '#3b82f6'); ?>;">
                        <?php echo htmlspecialchars($t['category_name']); ?>
                    </span>
                </div>
            </div>
            <div class="txn-info-full-row">
                <div class="txn-detail-field-label">Description</div>
                <div class="txn-detail-field-value">
                    <?php echo htmlspecialchars($t['item_description']); ?>
                </div>
            </div>
            <?php if ($t['brand']): ?>
            <div>
                <div class="txn-detail-field-label">Brand</div>
                <div class="txn-detail-field-value"><?php echo htmlspecialchars($t['brand']); ?></div>
            </div>
            <?php endif; ?>
            <?php if ($t['model']): ?>
            <div>
                <div class="txn-detail-field-label">Model</div>
                <div class="txn-detail-field-value"><?php echo htmlspecialchars($t['model']); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Transaction Details -->
    <div class="txn-section">
        <h3 class="txn-section-title">
            <i class="fas fa-info-circle"></i> Transaction Details
        </h3>
        <div class="txn-info-grid">
            <div>
                <div class="txn-detail-field-label">Quantity</div>
                <div style="font-size: 24px; font-weight: 700; color: <?php echo in_array($t['transaction_type'], ['distributed', 'damaged', 'expired']) ? 'var(--danger)' : 'var(--success)'; ?>">
                    <?php echo in_array($t['transaction_type'], ['distributed', 'damaged', 'expired']) ? '-' : '+'; ?>
                    <?php echo number_format($t['quantity']); ?> <?php echo htmlspecialchars($t['unit']); ?>
                </div>
            </div>
            <div>
                <div class="txn-detail-field-label">Unit Cost</div>
                <div class="txn-detail-field-value-md">
                    ₱<?php echo number_format($t['unit_cost'], 2); ?>
                </div>
            </div>
            <div class="txn-total-box txn-info-full-row">
                <div class="txn-total-label">Total Cost</div>
                <div class="txn-total-value">
                    ₱<?php echo number_format($t['total_cost'], 2); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Supplier/Recipient Information -->
    <?php if ($t['transaction_type'] === 'received' && $t['supplier_name']): ?>
    <div class="txn-section">
        <h3 class="txn-section-title">
            <i class="fas fa-truck"></i> Supplier Information
        </h3>
        <div class="txn-info-grid">
            <div>
                <div class="txn-detail-field-label">Supplier Name</div>
                <div class="txn-detail-field-value">
                    <?php echo htmlspecialchars($t['supplier_name']); ?>
                </div>
            </div>
            <?php if ($t['supplier_contact']): ?>
            <div>
                <div class="txn-detail-field-label">Contact Person</div>
                <div class="txn-detail-field-value"><?php echo htmlspecialchars($t['supplier_contact']); ?></div>
            </div>
            <?php endif; ?>
            <?php if ($t['supplier_phone']): ?>
            <div>
                <div class="txn-detail-field-label">Phone</div>
                <div class="txn-detail-field-value"><?php echo htmlspecialchars($t['supplier_phone']); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php elseif ($t['transaction_type'] === 'distributed' && $t['recipient_name']): ?>
    <div class="txn-section">
        <h3 class="txn-section-title">
            <i class="fas fa-user"></i> Recipient Information
        </h3>
        <div class="txn-info-grid">
            <div>
                <div class="txn-detail-field-label">Recipient Name</div>
                <div class="txn-detail-field-value">
                    <?php echo htmlspecialchars($t['recipient_name']); ?>
                </div>
            </div>
            <?php if ($t['recipient_organization']): ?>
            <div>
                <div class="txn-detail-field-label">Organization</div>
                <div class="txn-detail-field-value"><?php echo htmlspecialchars($t['recipient_organization']); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Additional Information -->
    <?php if ($t['reference_number'] || $t['batch_number'] || $t['serial_number'] || $t['expiration_date'] || $t['purpose'] || $t['notes']): ?>
    <div class="txn-section">
        <h3 class="txn-section-title">
            <i class="fas fa-clipboard"></i> Additional Information
        </h3>
        <div class="txn-info-grid">
            <?php if ($t['reference_number']): ?>
            <div>
                <div class="txn-detail-field-label">Reference Number</div>
                <div class="txn-detail-field-value"><?php echo htmlspecialchars($t['reference_number']); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($t['batch_number']): ?>
            <div>
                <div class="txn-detail-field-label">Batch Number</div>
                <div class="txn-detail-field-value"><?php echo htmlspecialchars($t['batch_number']); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($t['serial_number']): ?>
            <div>
                <div class="txn-detail-field-label">Serial Number</div>
                <div class="txn-detail-field-value"><?php echo htmlspecialchars($t['serial_number']); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($t['expiration_date']): ?>
            <div>
                <div class="txn-detail-field-label">Expiration Date</div>
                <div class="txn-detail-field-value"><?php echo date('F d, Y', strtotime($t['expiration_date'])); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($t['location_name']): ?>
            <div>
                <div class="txn-detail-field-label">Storage Location</div>
                <div class="txn-detail-field-value"><?php echo htmlspecialchars($t['location_name']); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($t['purpose']): ?>
            <div class="txn-info-full-row">
                <div class="txn-detail-field-label">Purpose</div>
                <div class="txn-detail-field-value"><?php echo nl2br(htmlspecialchars($t['purpose'])); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($t['notes']): ?>
            <div class="txn-info-full-row">
                <div class="txn-detail-field-label">Notes</div>
                <div class="txn-detail-field-value"><?php echo nl2br(htmlspecialchars($t['notes'])); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Audit Information -->
    <div class="txn-additional-box">
        <h4 class="txn-additional-title">
            <i class="fas fa-history"></i> Audit Information
        </h4>
        <div class="txn-additional-grid">
            <div>
                <span class="txn-meta-label">Created By:</span>
                <strong class="txn-meta-value">
                    <?php echo htmlspecialchars($t['created_by_name'] ?? 'System'); ?>
                </strong>
            </div>
            <div>
                <span class="txn-meta-label">Created At:</span>
                <strong class="txn-meta-value">
                    <?php echo date('M d, Y h:i A', strtotime($t['created_at'])); ?>
                </strong>
            </div>
            <?php if ($t['approved_by']): ?>
            <div>
                <span class="txn-meta-label">Approved By:</span>
                <strong class="txn-meta-value">
                    <?php echo htmlspecialchars($t['approved_by_name']); ?>
                </strong>
            </div>
            <div>
                <span class="txn-meta-label">Approved At:</span>
                <strong class="txn-meta-value">
                    <?php echo date('M d, Y h:i A', strtotime($t['approved_at'])); ?>
                </strong>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
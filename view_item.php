<?php
require_once 'includes/config.php';
check_login();

$page_title = 'View Item';

if (!isset($_GET['id'])) {
    header('Location: inventory.php');
    exit();
}

$item_id = (int)$_GET['id'];

$query = "
    SELECT i.*, c.category_name, c.color as category_color, sl.location_name
    FROM inventory_items i
    JOIN categories c ON i.category_id = c.id
    LEFT JOIN storage_locations sl ON i.storage_location_id = sl.id
    WHERE i.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Item not found";
    header('Location: inventory.php');
    exit();
}

$item = $result->fetch_assoc();
$stmt->close();

$transactions = $conn->query("
    SELECT t.*, u.full_name as created_by_name, s.supplier_name
    FROM transactions t
    LEFT JOIN users u ON t.created_by = u.id
    LEFT JOIN suppliers s ON t.supplier_id = s.id
    WHERE t.item_id = $item_id
    ORDER BY t.transaction_date DESC, t.created_at DESC
    LIMIT 10
");

require_once 'includes/header.php';
?>

<div class="inventory-view">
    <div class="view-actions-top">
        <a href="inventory.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Inventory
        </a>
        <div class="view-actions-top-right">
            <a href="edit_item.php?id=<?php echo $item_id; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit Item
            </a>
            <a href="receive_items.php?item_id=<?php echo $item_id; ?>" class="btn btn-success">
                <i class="fas fa-plus-circle"></i> Receive Stock
            </a>
            <a href="distribute_items.php?item_id=<?php echo $item_id; ?>" class="btn btn-primary">
                <i class="fas fa-minus-circle"></i> Distribute Stock
            </a>
        </div>
    </div>

    <div class="inventory-form">
        <div class="form-header">
            <h1>PASSI CITY</h1>
            <h2>DISASTER RISK REDUCTION & MANAGEMENT OFFICE</h2>
            <h3>INVENTORY OF SUPPLIES (<?php echo htmlspecialchars($item['category_name']); ?>)</h3>
        </div>

        <div class="item-code-banner">
            <div class="item-code-label">ITEM CODE</div>
            <div class="item-code-value"><?php echo htmlspecialchars($item['item_code']); ?></div>
        </div>

        <table class="inventory-table">
            <thead>
                <tr>
                    <th style="width:60px;">No.</th>
                    <th>Item Description</th>
                    <th style="width:130px;">No. of items<br>received</th>
                    <th style="width:130px;">No. of items<br>distributed</th>
                    <th style="width:130px;">No. of items<br>on hand</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="item-row-number">1</td>
                    <td class="item-row-desc"><?php echo nl2br(htmlspecialchars($item['item_description'])); ?></td>
                    <td class="status-good"><?php echo number_format($item['items_received']); ?></td>
                    <td class="status-warning-cell"><?php echo number_format($item['items_distributed']); ?></td>
                    <td class="<?php
                        if ($item['items_on_hand'] <= 0) echo 'status-danger-cell';
                        elseif ($item['items_on_hand'] <= $item['minimum_stock_level']) echo 'status-warning-cell';
                        else echo 'status-good';
                    ?>"><?php echo number_format($item['items_on_hand']); ?></td>
                </tr>
            </tbody>
        </table>

        <?php if ($item['items_on_hand'] <= 0 || $item['items_on_hand'] <= $item['minimum_stock_level']): ?>
        <div class="item-stock-alert-container">
            <?php if ($item['items_on_hand'] <= 0): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <strong>OUT OF STOCK</strong> - Immediate restocking required
                </div>
            <?php elseif ($item['items_on_hand'] <= $item['minimum_stock_level']): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <strong>STOCK ALERT WARNING</strong> - Current stock (<?php echo number_format($item['items_on_hand']); ?>) is below minimum level (<?php echo number_format($item['minimum_stock_level']); ?>)
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="item-details-section">
            <h4><i class="fas fa-info-circle"></i> Item Details</h4>
            <div class="item-details-grid">
                <div>
                    <div class="item-detail-label">Unit of Measurement</div>
                    <div class="item-detail-value"><?php echo htmlspecialchars($item['unit'] ?? 'pcs'); ?></div>
                </div>
                <div>
                    <div class="item-detail-label">Unit Cost</div>
                    <div class="item-detail-value">₱<?php echo number_format($item['unit_cost'] ?? 0, 2); ?></div>
                </div>
                <div>
                    <div class="item-detail-label">Total Value</div>
                    <div class="item-detail-value item-detail-value-primary">
                        ₱<?php echo number_format(($item['items_on_hand'] ?? 0) * ($item['unit_cost'] ?? 0), 2); ?>
                    </div>
                </div>
                <div>
                    <div class="item-detail-label">Minimum Stock Level</div>
                    <div class="item-detail-value"><?php echo number_format($item['minimum_stock_level'] ?? 0); ?> <?php echo htmlspecialchars($item['unit'] ?? 'pcs'); ?></div>
                </div>
                <div>
                    <div class="item-detail-label">Storage Location</div>
                    <div class="item-detail-value">
                        <?php if ($item['location_name']): ?>
                            <i class="fas fa-warehouse" style="color:var(--primary);margin-right:5px;"></i>
                            <?php echo htmlspecialchars($item['location_name']); ?>
                        <?php else: ?>
                            <span class="item-no-location">Not assigned</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <div class="item-detail-label">Expiration Date</div>
                    <div class="item-detail-value">
                        <?php if ($item['expiration_date']): ?>
                            <?php
                            $expiry = strtotime($item['expiration_date']);
                            $today = time();
                            $days_diff = floor(($expiry - $today) / (60 * 60 * 24));
                            if ($days_diff < 0): ?>
                                <span class="item-detail-expiry-danger"><?php echo date('M d, Y', $expiry); ?> (Expired)</span>
                            <?php elseif ($days_diff <= 30): ?>
                                <span class="item-detail-expiry-warning"><?php echo date('M d, Y', $expiry); ?> (<?php echo $days_diff; ?> days)</span>
                            <?php else: ?>
                                <span class="item-detail-expiry-ok"><?php echo date('M d, Y', $expiry); ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="item-detail-no-expiry">No expiration</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="table-container">
        <div class="table-header">
            <h3 class="table-title">Recent Transaction History</h3>
            <a href="transactions.php?item_id=<?php echo $item_id; ?>" class="btn btn-sm btn-primary">View All Transactions</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Transaction Code</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <!-- <th>Supplier/Recipient</th> -->
                        <th>Processed By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($transactions->num_rows > 0): ?>
                        <?php while ($trans = $transactions->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($trans['transaction_date'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($trans['transaction_code']); ?></strong></td>
                                <td>
                                    <?php
                                    $badge_class = 'badge-info';
                                    if ($trans['transaction_type'] === 'received') $badge_class = 'badge-success';
                                    if ($trans['transaction_type'] === 'distributed') $badge_class = 'badge-warning';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($trans['transaction_type']); ?></span>
                                </td>
                                <td>
                                    <?php $is_neg = in_array($trans['transaction_type'], ['distributed','damaged','expired']); ?>
                                    <strong class="<?php echo $is_neg ? 'trans-qty-negative' : 'trans-qty-positive'; ?>">
                                        <?php echo $is_neg ? '-' : '+'; ?><?php echo number_format($trans['quantity']); ?>
                                    </strong>
                                </td>
                                <!-- <td>
                                    <?php
                                    if ($trans['supplier_name']) echo '<i class="fas fa-truck"></i> ' . htmlspecialchars($trans['supplier_name']);
                                    elseif ($trans['recipient_name']) echo '<i class="fas fa-user"></i> ' . htmlspecialchars($trans['recipient_name']);
                                    else echo '-';
                                    ?>
                                </td> -->
                                <td><?php echo htmlspecialchars($trans['created_by_name'] ?? 'System'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="trans-empty-cell">
                                <i class="fas fa-inbox trans-empty-icon"></i>
                                <div class="trans-empty-text">No transactions recorded yet for this item</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

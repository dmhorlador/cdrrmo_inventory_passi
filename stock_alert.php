<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Stock Alert';

// Get filter parameter
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : 'all';

// Build query based on filter
$where_conditions = ["i.is_active = 1"];

if ($status_filter === 'critical') {
    // Critical: Out of stock only
    $where_conditions[] = "i.items_on_hand = 0";
} elseif ($status_filter === 'low') {
    // Stock alert: Above 0 but at or below minimum
    $where_conditions[] = "i.items_on_hand > 0 AND i.items_on_hand <= i.minimum_stock_level";
} else {
    // All: Both critical and stock alert
    $where_conditions[] = "i.items_on_hand <= i.minimum_stock_level";
}

$where_clause = implode(' AND ', $where_conditions);

// Get stock alert items
$low_stock = $conn->query("
    SELECT 
        i.*,
        c.category_name,
        c.color as category_color,
        sl.location_name,
        (i.minimum_stock_level - i.items_on_hand) as shortage
    FROM inventory_items i
    JOIN categories c ON i.category_id = c.id
    LEFT JOIN storage_locations sl ON i.storage_location_id = sl.id
    WHERE $where_clause
    ORDER BY i.items_on_hand ASC, shortage DESC
");

$total_low_stock = $low_stock->num_rows;
$out_of_stock = 0;
$critical = 0;

// Count severity
$temp_result = $conn->query("SELECT items_on_hand FROM inventory_items WHERE items_on_hand <= minimum_stock_level AND is_active = 1");
while ($row = $temp_result->fetch_assoc()) {
    if ($row['items_on_hand'] == 0) {
        $out_of_stock++;
    } elseif ($row['items_on_hand'] <= 5) {
        $critical++;
    }
}

require_once 'includes/header.php';
?>



<!-- Alert Summary Cards -->
<div class="dashboard-cards stock-alert-stats">
    <div class="card stat-card">
        <div class="stat-icon red">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Out of Stock</div>
            <div class="stat-value"><?php echo number_format($out_of_stock); ?></div>
        </div>
    </div>
    
    <div class="card stat-card">
        <div class="stat-icon yellow">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Critical (≤5 units)</div>
            <div class="stat-value"><?php echo number_format($critical); ?></div>
        </div>
    </div>
    
    <div class="card stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-boxes"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Total Stock Alert</div>
            <div class="stat-value"><?php echo number_format($total_low_stock); ?></div>
        </div>
    </div>
</div>

<?php if ($total_low_stock > 0): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i>
    <strong>Attention!</strong> You have <?php echo $total_low_stock; ?> item(s) that need to be restocked.
    <?php if ($out_of_stock > 0): ?>
        <strong><?php echo $out_of_stock; ?> item(s) are completely out of stock!</strong>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Filter Panel -->
<div class="card stock-filter-card no-print">
    <div class="stock-filter-body">
        <h3 class="stock-filter-title"><i class="fas fa-filter"></i> Filter Stock Status</h3>
        <form method="GET" class="report-filter-form-end">
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-control" style="width: 250px; padding: 8px 12px; border: 1px solid var(--gray-300); border-radius: 5px;">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All (Critical & Stock Alert)</option>
                    <option value="critical" <?php echo $status_filter === 'critical' ? 'selected' : ''; ?>>Critical Only (Out of Stock)</option>
                    <option value="low" <?php echo $status_filter === 'low' ? 'selected' : ''; ?>>Stock Alert Only</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Apply Filter
            </button>
            
            <?php if ($status_filter !== 'all'): ?>
                <a href="stock_alert.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Clear Filter
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="table-container">
    <div class="table-header">
        <h3 class="table-title">Stock Alert</h3>
        <div class="flex gap-10">
    <button onclick="printProfessionalReport('lowStockTable', 'STOCK ALERT ALERT REPORT', '')" class="btn btn-primary">
        <i class="fas fa-print"></i> Print Report
    </button>
    <button onclick="exportReportToExcel('lowStockTable', 'STOCK ALERT ALERT REPORT', 'Low_Stock_Report_<?php echo date('Y-m-d'); ?>.xlsx')" class="btn btn-success">
        <i class="fas fa-file-excel"></i> Export Excel
    </button>
</div>
    </div>
    <div class="table-responsive">
        <table id="lowStockTable">
            <thead>
                <tr>
                    <th>Priority</th>
                    <th>Item Code</th>
                    <th>Description</th>
                    <th>Category</th>
                    <th>Location</th>
                    <th>On Hand</th>
                    <th>Min Level</th>
                    <th>Shortage</th>
                    <th>Unit Cost</th>
                    <th>Restock Cost</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($total_low_stock > 0): ?>
                    <?php 
                    $low_stock->data_seek(0);
                    while ($row = $low_stock->fetch_assoc()): 
                        $priority = 'Low';
                        $priority_badge = 'badge-warning';
                        
                        if ($row['items_on_hand'] == 0) {
                            $priority = 'Critical';
                            $priority_badge = 'badge-danger';
                        } elseif ($row['items_on_hand'] <= 5) {
                            $priority = 'High';
                            $priority_badge = 'badge-danger';
                        }
                        
                        $restock_cost = $row['shortage'] * $row['unit_cost'];
                    ?>
                        <tr>
                            <td>
                                <span class="badge <?php echo $priority_badge; ?>">
                                    <?php echo $priority; ?>
                                </span>
                            </td>
                            <td><strong><?php echo htmlspecialchars($row['item_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['item_description']); ?></td>
                            <td>
                                <span class="badge" style="background-color: <?php echo htmlspecialchars($row['category_color'] ?? '#3b82f6'); ?>20; color: <?php echo htmlspecialchars($row['category_color'] ?? '#3b82f6'); ?>;">
                                    <?php echo htmlspecialchars($row['category_name']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($row['location_name'] ?? '-'); ?></td>
                            <td>
                                <span class="badge <?php echo $row['items_on_hand'] == 0 ? 'badge-danger' : 'badge-warning'; ?>">
                                    <?php echo number_format($row['items_on_hand']); ?>
                                </span>
                            </td>
                            <td><?php echo number_format($row['minimum_stock_level']); ?></td>
                            <td>
                                <strong style="color: var(--danger);">
                                    <?php echo number_format($row['shortage']); ?>
                                </strong>
                            </td>
                            <td>₱<?php echo number_format($row['unit_cost'], 2); ?></td>
                            <td>
                                <strong style="color: var(--primary);">
                                    ₱<?php echo number_format($restock_cost, 2); ?>
                                </strong>
                            </td>
                            <td>
                                <?php if ($row['items_on_hand'] == 0): ?>
                                    <span class="badge badge-danger">Out of Stock</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Stock Alert</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="receive_items.php?item_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success" title="Restock">
                                    <i class="fas fa-plus-circle"></i> Restock
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <tr style="background: var(--gray-100); font-weight: bold;">
                        <td colspan="9" class="text-right">Total Estimated Restock Cost:</td>
                        <td>
                            <?php
                            $low_stock->data_seek(0);
                            $total_cost = 0;
                            while ($row = $low_stock->fetch_assoc()) {
                                $total_cost += $row['shortage'] * $row['unit_cost'];
                            }
                            ?>
                            <strong style="color: var(--primary); font-size: 16px;">
                                ₱<?php echo number_format($total_cost, 2); ?>
                            </strong>
                        </td>
                        <td colspan="2"></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="12" class="text-center">
                            <div style="padding: 40px;">
                                <i class="fas fa-check-circle" style="font-size: 48px; color: var(--success); margin-bottom: 15px;"></i>
                                <h3 style="color: var(--success); margin: 0;">All Items are Well Stocked!</h3>
                                <p style="color: var(--gray-500); margin-top: 10px;">No items are currently below the minimum stock level.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($total_low_stock > 0): ?>
<div style="margin-top: 30px; padding: 20px; background: var(--primary-50); border-radius: 8px; border-left: 4px solid var(--primary-light);">
    <h4 style="margin: 0 0 10px 0; color: var(--primary);">
        <i class="fas fa-lightbulb"></i> Recommendations
    </h4>
    <ul style="margin: 0; padding-left: 20px; color: var(--gray-700);">
        <li>Prioritize restocking items marked as "Critical" or "Out of Stock"</li>
        <li>Contact suppliers for items with high restock costs to negotiate bulk pricing</li>
        <li>Review minimum stock levels - adjust if items frequently run low</li>
        <li>Consider increasing order frequency for fast-moving items</li>
        <li>Set up automatic purchase orders for critical supplies</li>
    </ul>
</div>
<?php endif; ?>

<?php require_once 'includes/report_functions.php'; ?>
<?php require_once 'includes/footer.php'; ?>
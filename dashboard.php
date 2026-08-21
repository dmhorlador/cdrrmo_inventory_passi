<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Dashboard';

// Get statistics
$stats = [
    'total_items' => 0,
    'total_categories' => 0,
    'low_stock' => 0,
    'total_value' => 0
];

// Total active items
$result = $conn->query("SELECT COUNT(*) as count FROM inventory_items WHERE is_active = 1");
$stats['total_items'] = $result->fetch_assoc()['count'];

// Total categories
$result = $conn->query("SELECT COUNT(*) as count FROM categories WHERE is_active = 1");
$stats['total_categories'] = $result->fetch_assoc()['count'];

// Stock alert items
$result = $conn->query("SELECT COUNT(*) as count FROM inventory_items WHERE items_on_hand <= minimum_stock_level AND is_active = 1");
$stats['low_stock'] = $result->fetch_assoc()['count'];

// Total inventory value
$result = $conn->query("SELECT SUM(items_on_hand * unit_cost) as total FROM inventory_items WHERE is_active = 1");
$stats['total_value'] = $result->fetch_assoc()['total'] ?? 0;

// Get recent transactions
$recent_transactions = $conn->query("
    SELECT 
        t.transaction_code,
        t.transaction_type,
        t.transaction_date,
        i.item_description,
        t.quantity,
        u.full_name as created_by_name
    FROM transactions t
    JOIN inventory_items i ON t.item_id = i.id
    LEFT JOIN users u ON t.created_by = u.id
    ORDER BY t.created_at DESC
    LIMIT 10
");

// Get stock alert items
$low_stock_items = $conn->query("
    SELECT 
        i.item_code,
        i.item_description,
        c.category_name,
        i.items_on_hand,
        i.minimum_stock_level
    FROM inventory_items i
    JOIN categories c ON i.category_id = c.id
    WHERE i.items_on_hand <= i.minimum_stock_level
    AND i.is_active = 1
    ORDER BY i.items_on_hand ASC
    LIMIT 5
");

// ── Expiring Soon: batch-based + item-level, with filter support ──────────────
$expiry_filter = isset($_GET['expiry_filter']) ? $_GET['expiry_filter'] : 'all';

$expiring_batches   = [];
$expiring_items_arr = [];

// Received Items (item_batches): all batches expiring within 30 days with qty > 0
if ($expiry_filter === 'all' || $expiry_filter === 'received') {
    $batch_q = $conn->query("
        SELECT
            ib.id AS batch_id,
            ib.batch_number,
            ib.ris_no,
            ib.quantity_on_hand,
            ib.expiration_date,
            ib.received_date,
            i.item_code,
            i.item_description,
            DATEDIFF(ib.expiration_date, CURDATE()) AS days_until_expiry
        FROM item_batches ib
        JOIN inventory_items i ON ib.item_id = i.id
        WHERE ib.expiration_date IS NOT NULL
          AND ib.expiration_date >= CURDATE()
          AND DATEDIFF(ib.expiration_date, CURDATE()) <= 30
          AND ib.quantity_on_hand > 0
          AND i.is_active = 1
        ORDER BY ib.expiration_date ASC
    ");
    while ($row = $batch_q->fetch_assoc()) {
        $expiring_batches[] = $row;
    }
}

// Inventory Items (items table expiration_date): legacy/item-level
if ($expiry_filter === 'all' || $expiry_filter === 'items') {
    $items_q = $conn->query("
        SELECT
            i.item_code,
            i.item_description,
            i.expiration_date,
            i.items_on_hand,
            DATEDIFF(i.expiration_date, CURDATE()) AS days_until_expiry
        FROM inventory_items i
        WHERE i.expiration_date IS NOT NULL
          AND i.expiration_date >= CURDATE()
          AND DATEDIFF(i.expiration_date, CURDATE()) <= 30
          AND i.is_active = 1
        ORDER BY i.expiration_date ASC
    ");
    while ($row = $items_q->fetch_assoc()) {
        $expiring_items_arr[] = $row;
    }
}

$has_expiring = !empty($expiring_batches) || !empty($expiring_items_arr);

// Get category breakdown for bar chart
$category_chart = $conn->query("
    SELECT 
        c.category_name,
        c.category_code,
        c.description,
        c.color,
        COUNT(i.id) as total_items,
        SUM(CASE WHEN i.items_on_hand > i.minimum_stock_level THEN 1 ELSE 0 END) as in_stock,
        SUM(CASE WHEN i.items_on_hand <= i.minimum_stock_level AND i.items_on_hand > 0 THEN 1 ELSE 0 END) as low_stock_count,
        SUM(CASE WHEN i.items_on_hand <= 0 THEN 1 ELSE 0 END) as out_of_stock
    FROM categories c
    LEFT JOIN inventory_items i ON c.id = i.category_id AND i.is_active = 1
    WHERE c.is_active = 1
    GROUP BY c.id, c.category_name, c.category_code, c.color, c.description
    ORDER BY total_items DESC
");
$chart_data = [];
while ($row = $category_chart->fetch_assoc()) {
    $chart_data[] = $row;
}

require_once 'includes/header.php';
?>

<!-- Dashboard Cards -->
<div class="dashboard-cards">
    <div class="card stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-boxes"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Total Items</div>
            <div class="stat-value"><?php echo number_format($stats['total_items']); ?></div>
        </div>
    </div>

    
    <div class="card stat-card">
        <div class="stat-icon red">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Stock Alert Items</div>
            <div class="stat-value"><?php echo number_format($stats['low_stock']); ?></div>
        </div>
    </div>
    
    <div class="card stat-card">
        <div class="stat-icon green">
            <i class="fas fa-peso-sign"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Total Value</div>
            <div class="stat-value">₱<?php echo number_format($stats['total_value'], 2); ?></div>
        </div>
    </div>
</div>

<!-- Category Bar Chart -->
<div class="card category-chart-card">
    <div class="category-chart-card-inner">
        <div class="category-chart-header">
            <h3>
                <i class="fas fa-chart-bar"></i> Items by Category
            </h3>
            <a href="categories.php" class="btn btn-sm btn-secondary"><i class="fas fa-tags"></i> Manage</a>
        </div>
        <div id="categoryChart" class="category-chart-container">
            <?php if (empty($chart_data)): ?>
                <div class="category-chart-empty">No categories found</div>
            <?php else: ?>
                <?php
                    $max_items = max(array_column($chart_data, 'total_items'));
                    if ($max_items == 0) $max_items = 1;
                    $bar_colors = ['#1a3370','#d4a017','#dc2626','#0d9f6e','#3577f0','#e6930a','#8b5cf6','#06b6d4'];
                    $max_bar_px = 180; // tallest bar in pixels
                    $min_bar_px = 28;  // shortest bar minimum
                ?>
                <?php foreach ($chart_data as $idx => $cat): ?>
                    <?php 
                        $color = $cat['color'] ?: $bar_colors[$idx % count($bar_colors)];
                        // Pixel height: proportional to max, with a guaranteed minimum
                        $bar_px = round(($cat['total_items'] / $max_items) * $max_bar_px);
                        if ($bar_px < $min_bar_px && $cat['total_items'] > 0) $bar_px = $min_bar_px;
                        if ($cat['total_items'] == 0) $bar_px = 6;
                    ?>
                    <div class="category-chart-bar-wrapper">
                        <div class="category-chart-bar-count">
                            <?php echo number_format($cat['total_items']); ?>
                        </div>
                        <div class="category-chart-bar" style="height: <?php echo $bar_px; ?>px; background: linear-gradient(180deg, <?php echo $color; ?> 0%, <?php echo $color; ?>bb 100%);"
                        title="<?php echo htmlspecialchars($cat['category_name']); ?>: <?php echo $cat['total_items']; ?> items (<?php echo (int)$cat['in_stock']; ?> in stock, <?php echo (int)$cat['low_stock_count']; ?> low, <?php echo (int)$cat['out_of_stock']; ?> out)"
                        onclick="window.location.href='inventory.php?category=<?php echo $cat['category_code']; ?>'">
                        </div>
                        <div class="category-chart-bar-label">
                            <div class="category-chart-bar-code">
                                <?php echo htmlspecialchars($cat['category_code'] ?: $cat['category_name']); ?>
                            </div>
                            <div class="category-chart-bar-name">
                                <?php echo htmlspecialchars($cat['description']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Transactions and Stock Alert -->
<div class="dashboard-main-grid">
    <!-- Recent Transactions -->
    <div class="table-container">
        <div class="table-header">
            <h3 class="table-title">Recent Transactions</h3>
            <a href="transactions.php" class="btn btn-sm btn-primary">View All</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Transaction Code</th>
                        <th>Type</th>
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>Date</th>
                        <th>By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_transactions->num_rows > 0): ?>
                        <?php while ($row = $recent_transactions->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['transaction_code']); ?></strong></td>
                                <td>
                                    <?php 
                                    $badge_class = 'badge-info';
                                    if ($row['transaction_type'] === 'received') $badge_class = 'badge-success';
                                    if ($row['transaction_type'] === 'distributed') $badge_class = 'badge-warning';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo ucfirst($row['transaction_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars(substr($row['item_description'], 0, 40)) . (strlen($row['item_description']) > 40 ? '...' : ''); ?></td>
                                <td><?php echo number_format($row['quantity']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['transaction_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['created_by_name']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No transactions yet</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Stock Alert Alert -->
    <div class="table-container">
        <div class="table-header">
            <h3 class="table-title">Stock Alert Alert</h3>
            <a href="stock_alert.php" class="btn btn-sm btn-danger">View All</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($low_stock_items->num_rows > 0): ?>
                        <?php while ($row = $low_stock_items->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="dashboard-item-code"><?php echo htmlspecialchars($row['item_code']); ?></div>
                                    <div class="dashboard-item-desc">
                                        <?php echo htmlspecialchars(substr($row['item_description'], 0, 30)) . (strlen($row['item_description']) > 30 ? '...' : ''); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo $row['items_on_hand'] == 0 ? 'badge-danger' : 'badge-warning'; ?>">
                                        <?php echo $row['items_on_hand']; ?> / <?php echo $row['minimum_stock_level']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2" class="text-center">All items are well stocked!</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Expiring Soon (Batch-based + Item-level with filter) -->
<?php if ($has_expiring || true): ?>
<div class="table-container">
    <div class="table-header">
        <h3 class="table-title">
            <i class="fas fa-clock" style="color: var(--warning);"></i>
            Expiring Soon (Within 30 Days)
        </h3>
        <div class="expiry-filter-wrap">
            <select class="expiry-filter-select" onchange="location.href='dashboard.php?expiry_filter='+this.value+'<?php echo isset($_GET['expiry_filter']) ? '' : ''; ?>'">
                <option value="all"      <?php echo ($expiry_filter === 'all')      ? 'selected' : ''; ?>>All</option>
                <option value="received" <?php echo ($expiry_filter === 'received') ? 'selected' : ''; ?>>Received Items (Batch)</option>
                <option value="items"    <?php echo ($expiry_filter === 'items')    ? 'selected' : ''; ?>>Inventory Items</option>
            </select>
        </div>
    </div>

    <?php if (!$has_expiring): ?>
        <div class="table-responsive">
            <p style="text-align:center; padding:20px; color: var(--text-muted);">No items expiring within 30 days.</p>
        </div>
    <?php else: ?>

    <?php if (!empty($expiring_batches)): ?>
        <p class="expiry-section-label"><i class="fas fa-layer-group"></i> Received Item Batches</p>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Description</th>
                        <th>Batch / RIS No.</th>
                        <th>Qty on Hand</th>
                        <th>Expiration Date</th>
                        <th>Days Left</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expiring_batches as $row): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['item_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars(substr($row['item_description'], 0, 45)) . (strlen($row['item_description']) > 45 ? '...' : ''); ?></td>
                            <td>
                                <?php if ($row['batch_number']): ?>
                                    <span class="badge badge-info"><?php echo htmlspecialchars($row['batch_number']); ?></span>
                                <?php endif; ?>
                                <?php if ($row['ris_no']): ?>
                                    <small style="color:var(--text-muted);"><?php echo htmlspecialchars($row['ris_no']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo number_format($row['quantity_on_hand']); ?></strong></td>
                            <td><?php echo date('M d, Y', strtotime($row['expiration_date'])); ?></td>
                            <td><?php echo $row['days_until_expiry']; ?> days</td>
                            <td>
                                <?php if ($row['days_until_expiry'] <= 7): ?>
                                    <span class="badge badge-danger">Critical</span>
                                <?php elseif ($row['days_until_expiry'] <= 14): ?>
                                    <span class="badge badge-warning">Warning</span>
                                <?php else: ?>
                                    <span class="badge badge-info">Monitor</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($expiring_items_arr)): ?>
        <p class="expiry-section-label" style="margin-top:18px;"><i class="fas fa-boxes"></i> Inventory Items</p>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Description</th>
                        <th>Stock on Hand</th>
                        <th>Expiration Date</th>
                        <th>Days Left</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expiring_items_arr as $row): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['item_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars(substr($row['item_description'], 0, 45)) . (strlen($row['item_description']) > 45 ? '...' : ''); ?></td>
                            <td><?php echo number_format($row['items_on_hand']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['expiration_date'])); ?></td>
                            <td><?php echo $row['days_until_expiry']; ?> days</td>
                            <td>
                                <?php if ($row['days_until_expiry'] <= 7): ?>
                                    <span class="badge badge-danger">Critical</span>
                                <?php elseif ($row['days_until_expiry'] <= 14): ?>
                                    <span class="badge badge-warning">Warning</span>
                                <?php else: ?>
                                    <span class="badge badge-info">Monitor</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
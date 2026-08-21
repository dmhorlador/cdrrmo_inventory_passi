<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Category Report';

// Get category data with totals
$query = "
    SELECT 
        c.id,
        c.category_name,
        c.category_code,
        c.color,
        COUNT(i.id) as item_count,
        SUM(i.items_on_hand) as total_on_hand,
        SUM(i.items_received) as total_received,
        SUM(i.items_distributed) as total_distributed,
        SUM(i.items_on_hand * i.unit_cost) as total_value,
        SUM(CASE WHEN i.items_on_hand <= i.minimum_stock_level THEN 1 ELSE 0 END) as low_stock_count
    FROM categories c
    LEFT JOIN inventory_items i ON c.id = i.category_id AND i.is_active = 1
    WHERE c.is_active = 1
    GROUP BY c.id, c.category_name, c.category_code, c.color
    ORDER BY c.category_name
";

$categories = $conn->query($query);

// Calculate grand totals
$grand_totals = [
    'items' => 0,
    'on_hand' => 0,
    'received' => 0,
    'distributed' => 0,
    'value' => 0,
    'low_stock' => 0
];

$cat_data = [];
while ($row = $categories->fetch_assoc()) {
    $cat_data[] = $row;
    $grand_totals['items'] += $row['item_count'];
    $grand_totals['on_hand'] += $row['total_on_hand'];
    $grand_totals['received'] += $row['total_received'];
    $grand_totals['distributed'] += $row['total_distributed'];
    $grand_totals['value'] += $row['total_value'];
    $grand_totals['low_stock'] += $row['low_stock_count'];
}

require_once 'includes/header.php';
?>

<div class="no-print report-page-actions">
    <div>
        <a href="reports.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Reports
        </a>
    </div>
    <div class="report-page-btns">
    <button onclick="printProfessionalReport('categoryTable', 'INVENTORY BY CATEGORY REPORT', '')" class="btn btn-primary">
        <i class="fas fa-print"></i> Print Report
    </button>
    <button onclick="exportReportToExcel('categoryTable', 'INVENTORY BY CATEGORY REPORT', 'Category_Report_<?php echo date('Y-m-d'); ?>.xlsx')" class="btn btn-success">
        <i class="fas fa-file-excel"></i> Export Excel
    </button>
</div>
</div>

<!-- Report Header with Dual Logos -->
<div class="card report-header-card">
    <div class="report-header-body">
        <div class="report-header-center">
            <img src="images/logo.jpg" alt="CDRRMO Logo" class="report-logo">
            <div class="report-org-block">
                <div class="report-republic">Republic of the Philippines</div>
                <div class="report-province">Province of Iloilo</div>
                <h1 class="report-city">CITY OF PASSI</h1>
                <h2 class="report-office">City Disaster Risk Reduction and Management Office</h2>
                <div class="report-title-block">
                    <h3 class="report-doc-title">Inventory By Category Report</h3>
                    <p style="margin: 4px 0 0; color: var(--gray-500); font-size: 12px;">Generated: <?php echo date('F d, Y h:i A'); ?></p>
                </div>
            </div>
            <img src="images/logo1.png" alt="City Logo" class="report-logo report-logo-contain">
        </div>
    </div>
</div>

<!-- Summary Statistics -->
<div class="card report-header-card">
    <div class="report-filter-body">
        <h3 class="report-summary-title">Summary</h3>
        <div class="report-summary-grid-6">
            <div class="report-stat-blue">
                <div class="report-stat-label-blue">Total Items</div>
                <div class="report-stat-value-blue">
                    <?php echo number_format($grand_totals['items']); ?>
                </div>
            </div>
            <div class="report-stat-green">
                <div class="report-stat-label-green">On Hand</div>
                <div class="report-stat-value-green">
                    <?php echo number_format($grand_totals['on_hand']); ?>
                </div>
            </div>
            <div class="report-stat-green">
                <div class="report-stat-label-green">Received</div>
                <div class="report-stat-value-green">
                    <?php echo number_format($grand_totals['received']); ?>
                </div>
            </div>
            <div class="report-stat-yellow">
                <div class="report-stat-label-yellow">Distributed</div>
                <div class="report-stat-value-yellow">
                    <?php echo number_format($grand_totals['distributed']); ?>
                </div>
            </div>
            <div class="report-stat-red">
                <div class="report-stat-label-red">Total Value</div>
                <div class="report-stat-value-red">
                    ₱<?php echo number_format($grand_totals['value'], 2); ?>
                </div>
            </div>
            <div class="report-stat-red">
                <div class="report-stat-label-red">Stock Alert</div>
                <div class="report-stat-value-red">
                    <?php echo number_format($grand_totals['low_stock']); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Category Table -->
<div class="card">
    <div class="report-filter-body">
<table class="report-data-table" id="categoryTable">
                <thead>
                <tr class="report-thead-row">
                    <th class="report-th report-th-left">Category</th>
                    <th class="report-th report-th-left">Code</th>
                    <th class="report-th report-th-center">Item Count</th>
                    <th class="report-th report-th-center">On Hand</th>
                    <th class="report-th report-th-center">Received</th>
                    <th class="report-th report-th-center">Distributed</th>
                    <th class="report-th report-th-right">Total Value</th>
                    <th class="report-th report-th-center">Stock Alert</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cat_data as $cat): ?>
                    <tr>
                        <td class="report-td">
                            <span style="display: inline-block; width: 12px; height: 12px; background: <?php echo htmlspecialchars($cat['color']); ?>; border-radius: 3px; margin-right: 8px;"></span>
                            <strong><?php echo htmlspecialchars($cat['category_name']); ?></strong>
                        </td>
                        <td class="report-td"><?php echo htmlspecialchars($cat['category_code']); ?></td>
                        <td class="report-td report-td-center report-td-bold">
                            <?php echo number_format($cat['item_count']); ?>
                        </td>
                        <td class="report-td report-td-center report-td-bold">
                            <?php echo number_format($cat['total_on_hand']); ?>
                        </td>
                        <td class="report-td report-td-center">
                            <?php echo number_format($cat['total_received']); ?>
                        </td>
                        <td class="report-td report-td-center">
                            <?php echo number_format($cat['total_distributed']); ?>
                        </td>
                        <td class="report-td report-td-right report-td-bold">
                            ₱<?php echo number_format($cat['total_value'], 2); ?>
                        </td>
                        <td class="report-td report-td-center">
                            <?php if ($cat['low_stock_count'] > 0): ?>
                                <span style="color: #dc2626; font-weight: 600;"><?php echo number_format($cat['low_stock_count']); ?></span>
                            <?php else: ?>
                                <span class="report-zero-good">0</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <!-- Totals Row -->
                <tr class="report-totals-row">
                    <td colspan="2" class="report-totals-label">TOTALS:</td>
                    <td class="report-totals-value"><?php echo number_format($grand_totals['items']); ?></td>
                    <td class="report-totals-value"><?php echo number_format($grand_totals['on_hand']); ?></td>
                    <td class="report-totals-value"><?php echo number_format($grand_totals['received']); ?></td>
                    <td class="report-totals-value"><?php echo number_format($grand_totals['distributed']); ?></td>
                    <td class="report-totals-value-right">₱<?php echo number_format($grand_totals['value'], 2); ?></td>
                    <td class="report-totals-value"><?php echo number_format($grand_totals['low_stock']); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/report_functions.php'; ?>
<?php require_once 'includes/footer.php'; ?>
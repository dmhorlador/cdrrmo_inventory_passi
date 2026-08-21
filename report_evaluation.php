<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Valuation Report';

// Get valuation data
$query = "
    SELECT 
        i.*,
        c.category_name,
        c.color as category_color,
        (i.items_on_hand * i.unit_cost) as current_value,
        (i.items_received * i.unit_cost) as total_received_value,
        (i.items_distributed * i.unit_cost) as total_distributed_value
    FROM inventory_items i
    JOIN categories c ON i.category_id = c.id
    WHERE i.is_active = 1
    ORDER BY current_value DESC
";

$items = $conn->query($query);

// Calculate totals
$totals = [
    'current_value' => 0,
    'received_value' => 0,
    'distributed_value' => 0,
    'items' => 0
];

$items_data = [];
while ($row = $items->fetch_assoc()) {
    $items_data[] = $row;
    $totals['current_value'] += $row['current_value'];
    $totals['received_value'] += $row['total_received_value'];
    $totals['distributed_value'] += $row['total_distributed_value'];
    $totals['items']++;
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
    <button onclick="printProfessionalReport('valuationTable', 'INVENTORY VALUATION REPORT', '')" class="btn btn-primary">
        <i class="fas fa-print"></i> Print Report
    </button>
    <button onclick="exportReportToExcel('valuationTable', 'INVENTORY VALUATION REPORT', 'Valuation_Report_<?php echo date('Y-m-d'); ?>.xlsx')" class="btn btn-success">
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
                    <h3 class="report-doc-title">Inventory Valuation Report</h3>
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
        <div class="report-summary-grid-4">
            <div class="report-stat-lg-blue">
                <div class="report-stat-label-lg report-stat-label-blue">Current Inventory Value</div>
                <div class="report-stat-value-lg report-stat-value-blue">
                    ₱<?php echo number_format($totals['current_value'], 2); ?>
                </div>
                <div class="report-stat-note">Based on current stock</div>
            </div>
            <div class="report-stat-lg-green">
                <div class="report-stat-label-lg report-stat-label-green">Total Received Value</div>
                <div class="report-stat-value-lg report-stat-value-green">
                    ₱<?php echo number_format($totals['received_value'], 2); ?>
                </div>
                <div class="report-stat-note">All time</div>
            </div>
            <div class="report-stat-lg-yellow">
                <div class="report-stat-label-lg report-stat-label-yellow">Total Distributed Value</div>
                <div class="report-stat-value-lg report-stat-value-yellow">
                    ₱<?php echo number_format($totals['distributed_value'], 2); ?>
                </div>
                <div class="report-stat-note">All time</div>
            </div>
            <div class="report-stat-lg-red">
                <div class="report-stat-label-lg report-stat-label-red">Total Items</div>
                <div class="report-stat-value-lg report-stat-value-red">
                    <?php echo number_format($totals['items']); ?>
                </div>
                <div class="report-stat-note">Active items</div>
            </div>
        </div>
    </div>
</div>

<!-- Valuation Table -->
<div class="card">
    <div class="report-filter-body">
        <table class="report-data-table-auto" id="valuationTable">
            <thead>
                <tr class="report-thead-row">
                    <th class="report-th report-th-left">Item Code</th>
                    <th class="report-th report-th-left">Description</th>
                    <th class="report-th report-th-center">Category</th>
                    <th class="report-th report-th-center">On Hand</th>
                    <th class="report-th report-th-right">Unit Cost</th>
                    <th class="report-th report-th-right">Current Value</th>
                    <th class="report-th report-th-right">% of Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items_data as $item): ?>
                    <?php $percentage = $totals['current_value'] > 0 ? ($item['current_value'] / $totals['current_value']) * 100 : 0; ?>
                    <tr>
                        <td class="report-td"><strong><?php echo htmlspecialchars($item['item_code']); ?></strong></td>
                        <td class="report-td"><?php echo htmlspecialchars($item['item_description']); ?></td>
                        <td class="report-td report-td-center"><?php echo htmlspecialchars($item['category_name']); ?></td>
                        <td class="report-td report-td-center report-td-bold">
                            <?php echo number_format($item['items_on_hand']); ?>
                        </td>
                        <td class="report-td report-td-right">₱<?php echo number_format($item['unit_cost'], 2); ?></td>
                        <td class="report-td report-td-right report-td-bold report-value-primary">
                            ₱<?php echo number_format($item['current_value'], 2); ?>
                        </td>
                        <td class="report-td report-td-right">
                            <?php echo number_format($percentage, 2); ?>%
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <!-- Totals Row -->
                <tr class="report-totals-row">
                    <td class="report-totals-value-right">TOTALS:</td>
                    <td class="report-totals-value-right report-value-primary">
                        ₱<?php echo number_format($totals['current_value'], 2); ?>
                    </td>
                    <td class="report-totals-value-right">100.00%</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/report_functions.php'; ?>

<?php require_once 'includes/footer.php'; ?>
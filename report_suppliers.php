<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Supplier Report';

// Get supplier data with transaction totals
$query = "
    SELECT 
        s.*,
        COUNT(t.id) as transaction_count,
        SUM(t.quantity) as total_quantity,
        SUM(t.total_cost) as total_value,
        MAX(t.transaction_date) as last_transaction_date
    FROM suppliers s
    LEFT JOIN transactions t ON s.id = t.supplier_id AND t.transaction_type = 'received'
    WHERE s.is_active = 1
    GROUP BY s.id
    ORDER BY total_value DESC
";

$suppliers = $conn->query($query);

// Calculate grand totals
$grand_totals = [
    'suppliers' => 0,
    'transactions' => 0,
    'quantity' => 0,
    'value' => 0
];

$supplier_data = [];
while ($row = $suppliers->fetch_assoc()) {
    $supplier_data[] = $row;
    $grand_totals['suppliers']++;
    $grand_totals['transactions'] += $row['transaction_count'];
    $grand_totals['quantity'] += $row['total_quantity'];
    $grand_totals['value'] += $row['total_value'];
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
    <button onclick="printProfessionalReport('supplierTable', 'SUPPLIER TRANSACTION REPORT', '')" class="btn btn-primary">
        <i class="fas fa-print"></i> Print Report
    </button>
    <button onclick="exportReportToExcel('supplierTable', 'SUPPLIER TRANSACTION REPORT', 'Supplier_Report_<?php echo date('Y-m-d'); ?>.xlsx')" class="btn btn-success">
        <i class="fas fa-file-excel"></i> Export Excel
    </button>
</div>
</div>

<!-- Report Header -->
<div class="card report-header-card">
    <div class="report-header-body-alt">
        <h1 class="report-org-name">PASSI CITY</h1>
        <h2 class="report-supplier-h2">DISASTER RISK REDUCTION & MANAGEMENT OFFICE</h2>
        <h3 class="report-supplier-h3">SUPPLIER TRANSACTION REPORT</h3>
        <p style="margin: 0; color: var(--gray-600);">Generated: <?php echo date('F d, Y h:i A'); ?></p>
    </div>
</div>

<!-- Summary Statistics -->
<div class="card report-header-card">
    <div class="report-filter-body">
        <div class="report-summary-grid-4">
            <div class="report-stat-blue">
                <div class="report-stat-label-blue">Total Suppliers</div>
                <div class="report-stat-value-blue">
                    <?php echo number_format($grand_totals['suppliers']); ?>
                </div>
            </div>
            <div class="report-stat-green">
                <div class="report-stat-label-green">Total Transactions</div>
                <div class="report-stat-value-green">
                    <?php echo number_format($grand_totals['transactions']); ?>
                </div>
            </div>
            <div class="report-stat-yellow">
                <div class="report-stat-label-yellow">Total Items Received</div>
                <div class="report-stat-value-yellow">
                    <?php echo number_format($grand_totals['quantity']); ?>
                </div>
            </div>
            <div class="report-stat-red">
                <div class="report-stat-label-red">Total Value</div>
                <div class="report-stat-value-red">
                    ₱<?php echo number_format($grand_totals['value'], 2); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Supplier Table -->
<div class="card">
    <div class="report-filter-body">
        <table class="report-data-table-auto" id="supplierTable">
            <thead>
                <tr class="report-thead-row">
                    <th class="report-th report-th-left">Supplier Name</th>
                    <th class="report-th report-th-left">Contact Person</th>
                    <th class="report-th report-th-left">Phone</th>
                    <th class="report-th report-th-center">Transactions</th>
                    <th class="report-th report-th-center">Items Received</th>
                    <th class="report-th report-th-right">Total Value</th>
                    <th class="report-th report-th-left">Last Transaction</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($supplier_data as $supplier): ?>
                    <tr>
                        <td class="report-td">
                            <strong><?php echo htmlspecialchars($supplier['supplier_name']); ?></strong>
                        </td>
                        <td class="report-td">
                            <?php echo htmlspecialchars($supplier['contact_person'] ?? '-'); ?>
                        </td>
                        <td class="report-td">
                            <?php echo htmlspecialchars($supplier['phone'] ?? '-'); ?>
                        </td>
                        <td class="report-td report-td-center report-td-bold">
                            <?php echo number_format($supplier['transaction_count']); ?>
                        </td>
                        <td class="report-td report-td-center report-td-bold">
                            <?php echo number_format($supplier['total_quantity']); ?>
                        </td>
                        <td class="report-td report-td-right report-td-bold report-value-primary">
                            ₱<?php echo number_format($supplier['total_value'], 2); ?>
                        </td>
                        <td class="report-td">
                            <?php 
                            if ($supplier['last_transaction_date']) {
                                echo date('M d, Y', strtotime($supplier['last_transaction_date']));
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <?php if (empty($supplier_data)): ?>
                    <tr>
                        <td colspan="7" class="report-empty-cell">
                            No suppliers found
                        </td>
                    </tr>
                <?php endif; ?>
                
                <!-- Totals Row -->
                <tr class="report-totals-row">
                    <td class="report-totals-value-right">TOTALS:</td>
                    <td class="report-totals-value">
                        <?php echo number_format($grand_totals['transactions']); ?>
                    </td>
                    <td class="report-totals-value">
                        <?php echo number_format($grand_totals['quantity']); ?>
                    </td>
                    <td class="report-totals-value-right report-value-primary">
                        ₱<?php echo number_format($grand_totals['value'], 2); ?>
                    </td>
                    <td class="report-totals-empty"></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>


<?php require_once 'includes/report_functions.php'; ?>

<?php require_once 'includes/footer.php'; ?>
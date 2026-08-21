<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Reports';

require_once 'includes/header.php';
?>

<!-- <div style="margin-bottom: 30px;">
    <h2 style="color: var(--primary); margin-bottom: 10px;">
        <i class="fas fa-chart-bar"></i> Inventory Reports
    </h2>
    <p style="color: var(--gray-600);">
        Generate and view various inventory reports and analytics
    </p>
</div> -->

<!-- Report Cards -->
<div class="dashboard-cards">
    <div class="card">
        <div class="report-card-body">
            <div class="report-card-header">
                <div class="report-card-icon report-card-icon-blue">
                    <i class="fas fa-boxes"></i>
                </div>
                <div>
                    <h3 class="report-card-title">Inventory Summary</h3>
                    <p class="report-card-subtitle">
                        Complete inventory
                    </p>
                </div>
            </div>
            <p class="report-card-desc">
                View all inventory items with current stock levels, values, and status.
            </p>
            <a href="report_inventory.php" class="btn btn-primary btn-block">
                <i class="fas fa-file-alt"></i> Generate Report
            </a>
        </div>
    </div>
    
    <div class="card">
        <div class="report-card-body">
            <div class="report-card-header">
                <div class="report-card-icon report-card-icon-green">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div>
                    <h3 class="report-card-title">Transaction Report</h3>
                    <p class="report-card-subtitle">
                        All inventory movements
                    </p>
                </div>
            </div>
            <p class="report-card-desc">
                View all transactions including received and distributed items with date range.
            </p>
            <a href="report_transactions.php" class="btn btn-success btn-block">
                <i class="fas fa-file-alt"></i> Generate Report
            </a>
        </div>
    </div>
    
    <div class="card">
        <div class="report-card-body">
            <div class="report-card-header">
                <div class="report-card-icon report-card-icon-orange">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <h3 class="report-card-title">Stock Alert Report</h3>
                    <p class="report-card-subtitle">
                        Items needing restock
                    </p>
                </div>
            </div>
            <p class="report-card-desc">
                List of items below minimum stock levels that need immediate attention.
            </p>
            <a href="stock_alert.php" class="btn btn-warning btn-block">
                <i class="fas fa-file-alt"></i> View Report
            </a>
        </div>
    </div>
    
    <div class="card">
        <div class="report-card-body">
            <div class="report-card-header">
                <div class="report-card-icon report-card-icon-gold">
                    <i class="fas fa-tags"></i>
                </div>
                <div>
                    <h3 class="report-card-title">Category Report</h3>
                    <p class="report-card-subtitle">
                        Items by category
                    </p>
                </div>
            </div>
            <p class="report-card-desc">
                View inventory breakdown by categories with stock and value totals.
            </p>
            <a href="report_categories.php" class="btn btn-gold btn-block">
                <i class="fas fa-file-alt"></i> Generate Report
            </a>
        </div>
    </div>
    
    <div class="card">
        <div class="report-card-body">
            <div class="report-card-header">
                <div class="report-card-icon report-card-icon-red">
                    <i class="fas fa-peso-sign"></i>
                </div>
                <div>
                    <h3 class="report-card-title">Valuation Report</h3>
                    <p class="report-card-subtitle">
                        Inventory value analysis
                    </p>
                </div>
            </div>
            <p class="report-card-desc">
                Calculate total inventory value, costs, and financial summaries.
            </p>
            <a href="report_evaluation.php" class="btn btn-danger btn-block">
                <i class="fas fa-file-alt"></i> Generate Report
            </a>
        </div>
    </div>
    
    <!-- <div class="card">
        <div class="report-card-body">
            <div class="report-card-header">
                <div class="report-card-icon report-card-icon-purple">
                    <i class="fas fa-truck"></i>
                </div>
                <div>
                    <h3 class="report-card-title">Supplier Report</h3>
                    <p class="report-card-subtitle">
                        Supplier transactions
                    </p>
                </div>
            </div>
            <p class="report-card-desc">
                View transaction history and totals for each supplier.
            </p>
            <a href="report_suppliers.php" class="btn btn-block" style="background:#8b5cf6;color:#fff;">
                <i class="fas fa-file-alt"></i> Generate Report
            </a>
        </div>
    </div> -->
    
    <div class="card">
        <div class="report-card-body">
            <div class="report-card-header">
                <div class="report-card-icon report-card-icon-green">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div>
                    <h3 class="report-card-title">Government Form</h3>
                    <p class="report-card-subtitle">
                        Official supplies report
                    </p>
                </div>
            </div>
            <p class="report-card-desc">
                Report of Supplies and Materials Issued - Government standard format.
            </p>
            <a href="report_supplies_materials.php" class="btn btn-success btn-block">
                <i class="fas fa-print"></i> Generate Form
            </a>
        </div>
    </div>
    
    <div class="card">
        <div class="report-card-body">
            <div class="report-card-header">
                <div class="report-card-icon report-card-icon-teal">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div>
                    <h3 class="report-card-title">Custodian Slip Report</h3>
                    <p class="report-card-subtitle">
                        Comprehensive slip report
                    </p>
                </div>
            </div>
            <p class="report-card-desc">
                View all distributed and returned items with filtering options.
            </p>
            <a href="custodian_slip_report.php" class="btn btn-block" style="background:#0891b2;color:#fff;">
                <i class="fas fa-file-alt"></i> Generate Report
            </a>
        </div>
    </div>
</div>

<!-- Quick Statistics -->
<!-- <?php
// Get quick stats for display
$stats_query = $conn->query("
    SELECT 
        COUNT(*) as total_items,
        SUM(items_on_hand) as total_stock,
        SUM(items_on_hand * unit_cost) as total_value,
        SUM(CASE WHEN items_on_hand <= minimum_stock_level THEN 1 ELSE 0 END) as low_stock_count
    FROM inventory_items 
    WHERE is_active = 1
");
$quick_stats = $stats_query->fetch_assoc();

$trans_stats = $conn->query("
    SELECT 
        COUNT(*) as total_transactions,
        SUM(CASE WHEN transaction_type = 'received' THEN quantity ELSE 0 END) as total_received,
        SUM(CASE WHEN transaction_type = 'distributed' THEN quantity ELSE 0 END) as total_distributed,
        SUM(total_cost) as total_value
    FROM transactions
    WHERE YEAR(transaction_date) = YEAR(CURDATE())
");
$trans_data = $trans_stats->fetch_assoc();
?>

<div class="card quick-stats-card">
    <div class="quick-stats-body">
        <h3 class="quick-stats-title">
            <i class="fas fa-chart-line"></i> Quick Statistics
        </h3>
        
        <div class="quick-stats-grid">
            <div class="quick-stat-item quick-stat-item-blue">
                <div class="quick-stat-label quick-stat-label-blue">Total Items</div>
                <div class="quick-stat-value quick-stat-value-blue">
                    <?php echo number_format($quick_stats['total_items']); ?>
                </div>
            </div>
            
            <div class="quick-stat-item quick-stat-item-green">
                <div class="quick-stat-label quick-stat-label-green">Total Stock Units</div>
                <div class="quick-stat-value quick-stat-value-green">
                    <?php echo number_format($quick_stats['total_stock']); ?>
                </div>
            </div>
            
            <div class="quick-stat-item quick-stat-item-yellow">
                <div class="quick-stat-label quick-stat-label-yellow">Inventory Value</div>
                <div class="quick-stat-value quick-stat-value-yellow">
                    ₱<?php echo number_format($quick_stats['total_value'], 2); ?>
                </div>
            </div>
            
            <div class="quick-stat-item quick-stat-item-red">
                <div class="quick-stat-label quick-stat-label-red">Stock Alert Items</div>
                <div class="quick-stat-value quick-stat-value-red">
                    <?php echo number_format($quick_stats['low_stock_count']); ?>
                </div>
            </div>
            
            <div class="quick-stat-item quick-stat-item-gray">
                <div class="quick-stat-label quick-stat-label-gray">Transactions (YTD)</div>
                <div class="quick-stat-value quick-stat-value-gray">
                    <?php echo number_format($trans_data['total_transactions']); ?>
                </div>
            </div>
            
            <div class="quick-stat-item quick-stat-item-green">
                <div class="quick-stat-label quick-stat-label-green">Items Received (YTD)</div>
                <div class="quick-stat-value quick-stat-value-green">
                    <?php echo number_format($trans_data['total_received']); ?>
                </div>
            </div>
            
            <div class="quick-stat-item quick-stat-item-yellow">
                <div class="quick-stat-label quick-stat-label-yellow">Items Distributed (YTD)</div>
                <div class="quick-stat-value quick-stat-value-yellow">
                    <?php echo number_format($trans_data['total_distributed']); ?>
                </div>
            </div>
            
            <div class="quick-stat-item quick-stat-item-blue">
                <div class="quick-stat-label quick-stat-label-blue">Transaction Value (YTD)</div>
                <div class="quick-stat-value quick-stat-value-blue">
                    ₱<?php echo number_format($trans_data['total_value'], 2); ?>
                </div>
            </div>
        </div>
    </div>
</div> -->

<?php require_once 'includes/footer.php'; ?>
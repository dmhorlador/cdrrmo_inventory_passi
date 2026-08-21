<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Settings & System Information';

// System version information
$system_version = '2.0.0';
$release_date = 'March 2026';
$last_updated = 'March 7, 2026';

// Get system statistics
$total_items = $conn->query("SELECT COUNT(*) as count FROM inventory_items WHERE is_active = 1")->fetch_assoc()['count'];
$total_categories = $conn->query("SELECT COUNT(*) as count FROM categories WHERE is_active = 1")->fetch_assoc()['count'];
$total_transactions = $conn->query("SELECT COUNT(*) as count FROM transactions")->fetch_assoc()['count'];
$total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1")->fetch_assoc()['count'];

// Get database size
$db_size_query = $conn->query("
    SELECT 
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
    FROM information_schema.TABLES
    WHERE table_schema = '" . DB_NAME . "'
");
$db_size = $db_size_query->fetch_assoc()['size_mb'];

// Get stock alert count
$low_stock_count = $conn->query("
    SELECT COUNT(*) as count 
    FROM inventory_items 
    WHERE items_on_hand <= minimum_stock_level 
    AND is_active = 1
")->fetch_assoc()['count'];

// Get total inventory value
$inventory_value = $conn->query("
    SELECT SUM(items_on_hand * unit_cost) as total
    FROM inventory_items
    WHERE is_active = 1
")->fetch_assoc()['total'];

require_once 'includes/header.php';
?>

<!-- <div class="alert-info">
    <i class="fas fa-info-circle"></i>
    <strong>System Settings & Information</strong> - View system version, features, statistics, and technical details about the CDRRMO Inventory Management System.
</div> -->

<!-- Version & Release Info -->
<div class="settings-card mb-30">
    <h3><i class="fas fa-code-branch"></i> Version Information</h3>
    <div class="settings-version-center">
        <div class="settings-version-badge-wrap"><span class="version-badge">v<?php echo $system_version; ?></span>
        </div>
        <div class="settings-version-name">
            <strong>CDRRMO Inventory Management System</strong>
        </div>
        <div class="settings-version-meta">
            <i class="fas fa-calendar-alt"></i> Released: <?php echo $release_date; ?>
        </div>
        <div class="settings-version-meta">
            <i class="fas fa-sync-alt"></i> Last Updated: <?php echo $last_updated; ?>
        </div>
    </div>
</div>

<!-- System Statistics -->
<div class="settings-grid">
    <div class="settings-card">
        <h3><i class="fas fa-chart-line"></i> System Statistics</h3>
        
        <div class="stat-card">
            <div class="stat-label">Total Items</div>
            <div class="stat-value"><?php echo number_format($total_items); ?></div>
        </div>
        
        <div class="stat-card">
            <div class="stat-label">Total Categories</div>
            <div class="stat-value"><?php echo number_format($total_categories); ?></div>
        </div>
        
        <div class="stat-card">
            <div class="stat-label">Total Transactions</div>
            <div class="stat-value"><?php echo number_format($total_transactions); ?></div>
        </div>
        
        <div class="stat-card">
            <div class="stat-label">Active Users</div>
            <div class="stat-value"><?php echo number_format($total_users); ?></div>
        </div>
        
        <div class="info-row">
            <span class="info-label">Database Size</span>
            <span class="info-value"><?php echo number_format($db_size, 2); ?> MB</span>
        </div>
        
        <div class="info-row">
            <span class="info-label">Stock Alert Items</span>
            <span class="info-value" style="color: <?php echo $low_stock_count > 0 ? 'var(--danger)' : 'var(--success)'; ?>">
                <?php echo number_format($low_stock_count); ?>
            </span>
        </div>
        
        <div class="info-row">
            <span class="info-label">Total Inventory Value</span>
            <span class="info-value text-primary">
                ₱<?php echo number_format($inventory_value, 2); ?>
            </span>
        </div>
    </div>
    
    <div class="settings-card">
        <h3><i class="fas fa-cog"></i> System Information</h3>
        
        <div class="info-row">
            <span class="info-label">Organization</span>
            <span class="info-value">CDRRMO</span>
        </div>
        
        <div class="info-row">
            <span class="info-label">Location</span>
            <span class="info-value">Passi City, Iloilo</span>
        </div>
        
        <div class="info-row">
            <span class="info-label">System Type</span>
            <span class="info-value">Inventory Management</span>
        </div>
        
        <div class="info-row">
            <span class="info-label">Server Time</span>
            <span class="info-value"><?php echo date('Y-m-d H:i:s'); ?></span>
        </div>
        
        <div class="info-row">
            <span class="info-label">PHP Version</span>
            <span class="info-value"><?php echo phpversion(); ?></span>
        </div>
        
        <div class="info-row">
            <span class="info-label">MySQL Version</span>
            <span class="info-value"><?php echo $conn->server_info; ?></span>
        </div>
        
        <div class="info-row">
            <span class="info-label">Logged In As</span>
            <span class="info-value"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        </div>
        
        <div class="info-row">
            <span class="info-label">User Role</span>
            <span class="info-value text-capitalize">
                <?php echo htmlspecialchars($_SESSION['user_role']); ?>
            </span>
        </div>
    </div>
</div>

<!-- Key Features -->
<div class="settings-grid">
    <div class="settings-card">
        <h3><i class="fas fa-star"></i> Key Features</h3>
        <ul class="feature-list">
            <li>
                <i class="fas fa-check-circle"></i>
                <span>Complete inventory tracking with real-time updates</span>
            </li>
            <li>
                <i class="fas fa-check-circle"></i>
                <span>Receive and distribute items with transaction history</span>
            </li>
            <li>
                <i class="fas fa-check-circle"></i>
                <span>Category-based organization with color coding</span>
            </li>
            <li>
                <i class="fas fa-check-circle"></i>
                <span>Stock alert alerts and automated notifications</span>
            </li>
            <li>
                <i class="fas fa-check-circle"></i>
                <span>Comprehensive reporting system (8 reports)</span>
            </li>
            <li>
                <i class="fas fa-check-circle"></i>
                <span>Custodian slip generation for borrowed items</span>
            </li>
            <li>
                <i class="fas fa-check-circle"></i>
                <span>Excel export for all reports</span>
            </li>
            <li>
                <i class="fas fa-check-circle"></i>
                <span>Professional print layouts for government use</span>
            </li>
            <li>
                <i class="fas fa-check-circle"></i>
                <span>User feedback and support system</span>
            </li>
            <li>
                <i class="fas fa-check-circle"></i>
                <span>Role-based access control (Admin/Staff)</span>
            </li>
        </ul>
    </div>
    
    <div class="settings-card">
        <h3><i class="fas fa-tools"></i> Technology Stack</h3>
        <div class="tech-stack">
            <div class="tech-item">
                <i class="fab fa-php"></i>
                <div class="tech-name">PHP</div>
                <div class="tech-version"><?php echo phpversion(); ?></div>
            </div>
            <div class="tech-item">
                <i class="fas fa-database"></i>
                <div class="tech-name">MySQL</div>
                <div class="tech-version"><?php echo explode('-', $conn->server_info)[0]; ?></div>
            </div>
            <div class="tech-item">
                <i class="fab fa-html5"></i>
                <div class="tech-name">HTML5</div>
                <div class="tech-version">Latest</div>
            </div>
            <div class="tech-item">
                <i class="fab fa-css3-alt"></i>
                <div class="tech-name">CSS3</div>
                <div class="tech-version">Latest</div>
            </div>
            <div class="tech-item">
                <i class="fab fa-js"></i>
                <div class="tech-name">JavaScript</div>
                <div class="tech-version">ES6+</div>
            </div>
            <div class="tech-item">
                <i class="fas fa-file-excel"></i>
                <div class="tech-name">SheetJS</div>
                <div class="tech-version">0.18.5</div>
            </div>
        </div>
        
        <div class="settings-tech-libs">
            <div class="info-label settings-tech-libs-label">Additional Libraries:</div>
            <div class="settings-tech-libs-list">
                • Font Awesome 6.4.0 (Icons)<br>
                • Chart.js (Analytics)<br>
                • Custom autocomplete search<br>
                • Responsive grid system<br>
                • Print-optimized layouts
            </div>
        </div>
    </div>
</div>

<!-- Module List -->
<div class="settings-card mt-20">
    <h3><i class="fas fa-layer-group"></i> System Modules</h3>
    <div class="settings-module-grid">
        <div class="settings-module-item">
            <div class="settings-module-title">
                <i class="fas fa-boxes"></i> Inventory Management
            </div>
            <div class="settings-module-desc">
                View, add, edit items with detailed tracking
            </div>
        </div>
        
        <div class="settings-module-item">
            <div class="settings-module-title">
                <i class="fas fa-arrow-down"></i> Receive Items
            </div>
            <div class="settings-module-desc">
                Record incoming inventory with RIS tracking
            </div>
        </div>
        
        <div class="settings-module-item">
            <div class="settings-module-title">
                <i class="fas fa-arrow-up"></i> Distribute Items
            </div>
            <div class="settings-module-desc">
                Issue items to departments/individuals
            </div>
        </div>
        
        <div class="settings-module-item">
            <div class="settings-module-title">
                <i class="fas fa-undo"></i> Return Items
            </div>
            <div class="settings-module-desc">
                Process borrowed item returns
            </div>
        </div>
        
        <div class="settings-module-item">
            <div class="settings-module-title">
                <i class="fas fa-exchange-alt"></i> Transactions
            </div>
            <div class="settings-module-desc">
                Complete transaction history & tracking
            </div>
        </div>
        
        <div class="settings-module-item">
            <div class="settings-module-title">
                <i class="fas fa-tags"></i> Categories
            </div>
            <div class="settings-module-desc">
                Organize items by category with colors
            </div>
        </div>
        
        <div class="settings-module-item">
            <div class="settings-module-title">
                <i class="fas fa-exclamation-triangle"></i> Stock Alert
            </div>
            <div class="settings-module-desc">
                Monitor stock alert with filters
            </div>
        </div>
        
        <div class="settings-module-item">
            <div class="settings-module-title">
                <i class="fas fa-file-alt"></i> Reports
            </div>
            <div class="settings-module-desc">
                8 comprehensive reports with exports
            </div>
        </div>
        
        <div class="settings-module-item">
            <div class="settings-module-title">
                <i class="fas fa-users"></i> User Management
            </div>
            <div class="settings-module-desc">
                Manage users & access permissions
            </div>
        </div>
        
        <div class="settings-module-item">
            <div class="settings-module-title">
                <i class="fas fa-comments"></i> Feedback System
            </div>
            <div class="settings-module-desc">
                User feedback & support tickets
            </div>
        </div>
    </div>
</div>

<!-- Available Reports -->
<div class="settings-card mt-20">
    <h3><i class="fas fa-chart-bar"></i> Available Reports</h3>
    <div class="settings-report-grid">
        <div class="settings-report-item">
            <strong>1. Inventory Report</strong>
            <div class="settings-report-desc">
                Complete list of all items with stock levels and values
            </div>
        </div>
        
        <div class="settings-report-item">
            <strong>2. Transaction Report</strong>
            <div class="settings-report-desc">
                All receive and distribute transactions with details
            </div>
        </div>
        
        <div class="settings-report-item">
            <strong>3. Category Report</strong>
            <div class="settings-report-desc">
                Inventory summary grouped by category
            </div>
        </div>
        
        <div class="settings-report-item">
            <strong>4. Valuation Report</strong>
            <div class="settings-report-desc">
                Item valuations with percentage breakdown
            </div>
        </div>
        
        <div class="settings-report-item">
            <strong>5. Supplier Report</strong>
            <div class="settings-report-desc">
                Supplier transactions and total values
            </div>
        </div>
        
        <div class="settings-report-item">
            <strong>6. Stock Alert Report</strong>
            <div class="settings-report-desc">
                Stock alert items with restock cost estimates
            </div>
        </div>
        
        <div class="settings-report-item">
            <strong>7. Supplies & Materials Report</strong>
            <div class="settings-report-desc">
                Government-format issued/received items report
            </div>
        </div>
        
        <div class="settings-report-item">
            <strong>8. Custodian Slip Report</strong>
            <div class="settings-report-desc">
                Borrowed items with date issued and returned
            </div>
        </div>
    </div>
</div>

<!-- Changelog -->
<!-- <div class="settings-card mt-20">
    <h3><i class="fas fa-history"></i> Recent Updates (Version 2.0.0)</h3>
    
    <div class="changelog">
        <div class="changelog-date">March 7, 2026</div>
        <ul>
            <li>Added logos to Report of Supplies and Materials</li>
            <li>Made RIS No. mandatory in Receive Items</li>
            <li>Made RIS No. mandatory in Distribute Items</li>
            <li>Auto-populate expiration date when selecting items</li>
            <li>Commented out supplier field in Receive Items</li>
        </ul>
    </div>
    
    <div class="changelog">
        <div class="changelog-date">March 6, 2026</div>
        <ul>
            <li>Fixed column alignment in all reports</li>
            <li>Added filter functionality to Stock Alert</li>
            <li>Changed "Stock Alert Alert" to "Stock Alert"</li>
            <li>Removed Total Cost column from Transactions</li>
            <li>Changed "Date Borrowed" to "Date Issued" in custodian slips</li>
        </ul>
    </div>
    
    <div class="changelog">
        <div class="changelog-date">February 27, 2026</div>
        <ul>
            <li>Added user feedback system</li>
            <li>Redesigned compact dashboard</li>
            <li>Enhanced report export functionality</li>
            <li>Added Excel export for all reports</li>
            <li>Improved professional print layouts</li>
        </ul>
    </div>
    
    <div class="changelog">
        <div class="changelog-date">February 20, 2026</div>
        <ul>
            <li>Initial release of version 2.0.0</li>
            <li>Complete system redesign</li>
            <li>8 comprehensive reports</li>
            <li>Enhanced inventory tracking</li>
            <li>Custodian slip generation</li>
        </ul>
    </div>
</div> -->

<!-- Support Information -->
<!-- <div class="settings-card mt-20">
    <h3><i class="fas fa-question-circle"></i> Support & Documentation</h3>
    <div class="settings-support-box">
        <div class="settings-support-title">
            <i class="fas fa-book"></i> Need Help?
        </div>
        <div class="settings-support-text">
            • Use the <strong>Feedback</strong> page to submit questions or issues<br>
            • Check the user manual for detailed instructions<br>
            • Contact your system administrator for technical support<br>
            • Visit the Reports section for data export options
        </div>
    </div>
    
    <div class="info-row">
        <span class="info-label">Organization</span>
        <span class="info-value">LGU - PASSI CITY, CDRRMO</span>
    </div>
    
    <div class="info-row">
        <span class="info-label">Fund Code</span>
        <span class="info-value">1192</span>
    </div>
    
    <div class="info-row">
        <span class="info-label">System Purpose</span>
        <span class="info-value">Disaster Risk Reduction Management</span>
    </div>
</div> -->

<!-- Developer Info -->
<!-- <div class="settings-card mt-20 bg-gray-50">
    <h3><i class="fas fa-code"></i> Development Information</h3>
    <div class="settings-dev-center">
        <div class="settings-dev-name">
            <strong>CDRRMO Inventory Management System</strong>
        </div>
        <div class="settings-dev-sub">
            Developed for LGU Passi City<br>
            City Disaster Risk Reduction & Management Office
        </div>
        <div class="settings-dev-footer">
            <div class="settings-dev-copy">
                © <?php echo date('Y'); ?> CDRRMO Inventory System. All rights reserved.
            </div>
        </div>
    </div>
</div> -->

<?php require_once 'includes/footer.php'; ?>
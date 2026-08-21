<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>CDRRMO Inventory System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="main-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="images/logo.jpg" alt="CDRRMO Logo" class="sidebar-logo">
                <div class="sidebar-brand-text">
                <div class="sidebar-title">PASSI CITY CDRRMO</div>
                <div class="sidebar-subtitle">Inventory Management System</div>
                </div>
            </div>
            
            <nav class="sidebar-menu">
                <div class="menu-divider">Main Menu</div>
                <a href="dashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                
                <div class="menu-divider">Inventory</div>
                <a href="inventory.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>">
                    <i class="fas fa-boxes"></i> All Items
                </a>
                <a href="stock_alert.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'stock_alert.php' ? 'active' : ''; ?>">
                    <i class="fas fa-exclamation-triangle"></i> Stock Alert
                </a>
                
                <div class="menu-divider">Transactions</div>
                <a href="receive_items.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'receive_items.php' ? 'active' : ''; ?>">
                    <i class="fas fa-plus-circle"></i> Receive Items
                </a>
                <a href="distribute_items.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'distribute_items.php' ? 'active' : ''; ?>">
                    <i class="fas fa-minus-circle"></i> Distribute Items
                </a>
                <a href="return_items.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'return_items.php' ? 'active' : ''; ?>">
                    <i class="fas fa-undo-alt"></i> Return Items
                </a>
                <a href="transactions.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'transactions.php' ? 'active' : ''; ?>">
                    <i class="fas fa-exchange-alt"></i> All Transactions
                </a>
                
                <div class="menu-divider">Management</div>
                <a href="categories.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tags"></i> Categories
                </a>
                <!-- <a href="suppliers.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'suppliers.php' ? 'active' : ''; ?>">
                    <i class="fas fa-truck"></i> Suppliers
                </a> -->
                <a href="locations.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'locations.php' ? 'active' : ''; ?>">
                    <i class="fas fa-warehouse"></i> Storage Locations
                </a>
                
                <div class="menu-divider">Reports</div>
                <a href="reports.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>

                <!-- <div class="menu-divider">Support</div> -->
                <!-- <a href="feedback.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'feedback.php' ? 'active' : ''; ?>">
                    <i class="fas fa-comment-dots"></i> Send Feedback
                </a>
                 -->
                <!-- <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <a href="admin_feedback.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin_feedback.php' ? 'active' : ''; ?>">
                        <i class="fas fa-inbox"></i> Manage Feedback
                    </a>
                <?php endif; ?> -->
                
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <div class="menu-divider">Administration</div>
                <a href="users.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> User Management
                </a>
                <a href="activity_logs.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'activity_logs.php' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i> Activity Logs
                </a>
                <?php endif; ?>

                <!-- Settings -->
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <a href="settings.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <?php endif; ?>
                
            
            </nav>
        </aside>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <nav class="topnav">
                <div class="topnav-left">
                    <h1><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h1>
                </div>
                <div class="topnav-right">
                    <div class="user-info-dropdown" id="userInfoDropdown">
                        <button type="button" class="user-info" id="userInfoToggle" onclick="toggleUserMenu(event)">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                            </div>
                            <div class="user-details">
                                <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                                <div class="user-role"><?php echo ucfirst($_SESSION['user_role']); ?></div>
                            </div>
                            <i class="fas fa-chevron-down user-info-caret"></i>
                        </button>
                        <div class="user-info-menu" id="userInfoMenu">
                            <a href="<?php echo htmlspecialchars(OTHER_SYSTEM_URL); ?>" target="_blank" rel="noopener noreferrer" class="user-info-menu-item">
                                <i class="fas fa-external-link-alt"></i> Open Other System
                            </a>
                            <div class="user-info-menu-divider"></div>
                            <a href="change_password.php" class="user-info-menu-item">
                                <i class="fas fa-key"></i> Change Password
                            </a>
                            <a href="logout.php" class="user-info-menu-item">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
                <script>
                function toggleUserMenu(e) {
                    e.stopPropagation();
                    document.getElementById('userInfoDropdown').classList.toggle('open');
                }
                document.addEventListener('click', function(e) {
                    const dropdown = document.getElementById('userInfoDropdown');
                    if (dropdown && !dropdown.contains(e.target)) {
                        dropdown.classList.remove('open');
                    }
                });
                </script>
            </nav>
            
            
            <!-- Page Content -->
            <div class="content">
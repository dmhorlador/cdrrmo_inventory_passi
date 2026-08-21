<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Inventory Custodian Slip';

// // Get filter parameters
// $month = isset($_GET['month']) ? $_GET['month'] : date('m');
// $year = isset($_GET['year']) ? $_GET['year'] : date('Y');
// $recipient_filter = isset($_GET['recipient']) ? sanitize_input($_GET['recipient']) : '';

// // Calculate date range
// $date_from = "$year-$month-01";
// $date_to = date('Y-m-t', strtotime($date_from));
// Get filter parameters
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$day = isset($_GET['day']) ? $_GET['day'] : ''; // '' means all days
$recipient_filter = isset($_GET['recipient']) ? sanitize_input($_GET['recipient']) : '';

// Calculate date range
if ($day) {
    $date_from = "$year-$month-" . str_pad($day, 2, '0', STR_PAD_LEFT);
    $date_to = $date_from;
} else {
    $date_from = "$year-$month-01";
    $date_to = date('Y-m-t', strtotime($date_from));
}

// Build query - only borrowed items (distributed with is_borrowed = 1)
$where = ["t.transaction_date BETWEEN ? AND ?", "t.transaction_type = 'distributed'", "t.is_borrowed = 1"];
$params = [$date_from, $date_to];
$types = "ss";

if ($recipient_filter) {
    $where[] = "t.recipient_name LIKE ?";
    $params[] = "%$recipient_filter%";
    $types .= "s";
}

$where_clause = implode(' AND ', $where);

// Get borrowed transactions
$query = "
    SELECT 
        t.id,
        t.transaction_code,
        t.transaction_date,
        t.quantity,
        t.recipient_name,
        t.recipient_organization,
        i.item_code,
        i.item_description,
        i.unit,
        COALESCE(
            (SELECT SUM(quantity) 
             FROM transactions 
             WHERE parent_transaction_id = t.id 
             AND transaction_type = 'returned'),
            0
        ) as returned_quantity,
        COALESCE(
            (SELECT MAX(transaction_date)
             FROM transactions 
             WHERE parent_transaction_id = t.id 
             AND transaction_type = 'returned'),
            NULL
        ) as return_date
    FROM transactions t
    JOIN inventory_items i ON t.item_id = i.id
    WHERE $where_clause
    ORDER BY t.recipient_name, t.transaction_date
";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}
$stmt->close();

// Get unique recipients for filter
$recipients_query = $conn->query("
    SELECT DISTINCT recipient_name 
    FROM transactions 
    WHERE recipient_name IS NOT NULL AND recipient_name != '' AND is_borrowed = 1
    ORDER BY recipient_name
");

// $period_display = date('F j', strtotime($date_from)) . '-' . date('j, Y', strtotime($date_to));
$period_display = $day 
    ? date('F j, Y', strtotime($date_from)) 
    : date('F j', strtotime($date_from)) . '-' . date('j, Y', strtotime($date_to));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Custodian Slip</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="print-controls no-print">
        <button class="btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> Print Slip
        </button>
        <button class="btn-print btn-secondary" onclick="window.location.href='reports.php'">
            <i class="fas fa-times"></i> Close
        </button>
    </div>

    <div class="slip-container">
        <!-- Filter Panel -->
        <div class="filter-panel no-print">
            <h3 class="txn-card-title">
                <i class="fas fa-filter"></i> Filter Options
            </h3>
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label class="filter-label">Month</label>
                        <select name="month" class="filter-control">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" 
                                    <?php echo $m == $month ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="filter-group">
    <label class="filter-label">Day</label>
    <select name="day" class="filter-control">
        <option value="">All Days</option>
        <?php 
        $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        for ($d = 1; $d <= $days_in_month; $d++): ?>
            <option value="<?php echo $d; ?>" <?php echo $day == $d ? 'selected' : ''; ?>>
                <?php echo $d; ?>
            </option>
        <?php endfor; ?>
    </select>
</div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Year</label>
                        <select name="year" class="filter-control">
                            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Recipient</label>
                        <select name="recipient" class="filter-control">
                            <option value="">All Recipients</option>
                            <?php while ($rec = $recipients_query->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($rec['recipient_name']); ?>"
                                    <?php echo $recipient_filter === $rec['recipient_name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($rec['recipient_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <a href="custodian_slip_report.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i>&nbsp; Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Header with Logo Placeholders -->
        <div class="slip-header">
            <div class="header-row">
                <!-- <div class="logo-left"> -->
                    <img src="images/logo.jpg" class="logo-left" alt="">
                <!-- </div> -->
                <div class="header-text">
                    <div class="header-title">Republic of the Philippines</div>
                    <div class="header-subtitle">Province of Iloilo</div>
                    <div class="header-title">CITY OF PASSI</div>
                </div>
                <!-- <div class="logo-right"> -->
                    <img src="images/logo1.png" class="logo-right" alt="">
                <!-- </div> -->
            </div>
        </div>
        
        <!-- Slip Box -->
        <div class="slip-box">
            <div class="slip-title">INVENTORY CUSTODIAN SLIP</div>
            
            <!-- Main Table -->
            <table class="main-table">
                <thead>
                    <tr>
                        <th class="col-qty" rowspan="2">QUANTITY</th>
                        <th class="col-unit" rowspan="2">UNIT</th>
                        <th class="col-desc" rowspan="2">DESCRIPTION</th>
                        <th class="col-date">DATE<br>ISSUED</th>
                        <th class="col-date">DATE<br>RETURNED</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($transactions) > 0): ?>
                        <?php foreach ($transactions as $trans): ?>
                            <tr class="item-row">
                                <td class="col-qty"><?php echo number_format($trans['quantity']); ?></td>
                                <td class="col-unit"><?php echo strtoupper(htmlspecialchars($trans['unit'])); ?></td>
                                <td class="col-desc"><?php echo htmlspecialchars($trans['item_description']); ?></td>
                                <td class="col-date"><?php echo date('n/j/Y', strtotime($trans['transaction_date'])); ?></td>
                                <td class="col-date"><?php echo $trans['return_date'] ? date('n/j/Y', strtotime($trans['return_date'])) : ''; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php 
                        // Fill remaining rows to reach 5 total
                        $remaining = 5 - count($transactions);
                        for ($i = 0; $i < $remaining; $i++): 
                        ?>
                            <tr class="item-row">
                                <td class="col-qty"></td>
                                <td class="col-unit"></td>
                                <td class="col-desc"></td>
                                <td class="col-date"></td>
                                <td class="col-date"></td>
                            </tr>
                        <?php endfor; ?>
                    <?php else: ?>
                        <?php for ($i = 0; $i < 5; $i++): ?>
                            <tr class="item-row">
                                <td class="col-qty"></td>
                                <td class="col-unit"></td>
                                <td class="col-desc"></td>
                                <td class="col-date"></td>
                                <td class="col-date"></td>
                            </tr>
                        <?php endfor; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Signature Section -->
            <div class="signature-section">
                <!-- Left: Borrower -->
                <div class="sig-left">
                    <div class="sig-box">
                        <div class="sig-line sig-name">
                            <?php 
                            // Get first recipient name if filtered
                            if (count($transactions) > 0) {
                                echo strtoupper(htmlspecialchars($transactions[0]['recipient_name']));
                            } else {
                                echo '&nbsp;';
                            }
                            ?>
                        </div>
                        <div class="sig-sublabel">Signature Over Printed Name</div>
                    </div>
                    
                    <div class="sig-box">
                        <div class="sig-line"></div>
                        <div class="sig-sublabel">Position / Office</div>
                    </div>
                    
                    <div class="sig-box">
                        <div class="sig-line"></div>
                        <div class="sig-sublabel">Date</div>
                    </div>
                </div>
                
                <!-- Right: Received From -->
                <div class="sig-right">
                    <div class="received-from-label">RECEIVED FROM:</div>
                    
                    <div class="sig-box">
                        <div class="sig-line"></div>
                        <div class="sig-sublabel"></div>
                    </div>
                    
                    <div class="sig-box">
                        <div class="sig-line"></div>
                        <div class="sig-sublabel">Date</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
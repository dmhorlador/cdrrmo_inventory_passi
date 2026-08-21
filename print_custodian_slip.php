<?php
require_once 'includes/config.php';
check_login();

// Get transaction ID
$transaction_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get transaction details with return information
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
    WHERE t.id = ? AND t.transaction_type = 'distributed'
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$result = $stmt->get_result();
$transaction = $result->fetch_assoc();
$stmt->close();

if (!$transaction) {
    die("Transaction not found");
}
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
        <button class="btn-print btn-secondary" onclick="window.close()">
            <i class="fas fa-times"></i> Close
        </button>
    </div>

    <div class="slip-container">
        <!-- Header with Logo Placeholders -->
        <div class="slip-header">
            <div class="header-row">
                <img src="images/logo.jpg" class="logo-left" alt="">
                <div class="header-text">
                    <div class="header-title">Republic of the Philippines</div>
                    <div class="header-subtitle">Province of Iloilo</div>
                    <div class="header-title">CITY OF PASSI</div>
                </div>
                <img src="images/logo1.png" class="logo-right" alt="">
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
                    <!-- First row with actual data -->
                    <tr class="item-row">
                        <td class="col-qty"><?php echo number_format($transaction['quantity']); ?></td>
                        <td class="col-unit"><?php echo strtoupper(htmlspecialchars($transaction['unit'])); ?></td>
                        <td class="col-desc"><?php echo htmlspecialchars($transaction['item_description']); ?></td>
                        <td class="col-date"><?php echo date('n/j/Y', strtotime($transaction['transaction_date'])); ?></td>
                        <td class="col-date"><?php echo $transaction['return_date'] ? date('n/j/Y', strtotime($transaction['return_date'])) : ''; ?></td>
                    </tr>
                    <!-- Empty rows to fill up to 5 total -->
                    <?php for ($i = 1; $i < 5; $i++): ?>
                        <tr class="item-row">
                            <td class="col-qty"></td>
                            <td class="col-unit"></td>
                            <td class="col-desc"></td>
                            <td class="col-date"></td>
                            <td class="col-date"></td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
            
            <!-- Signature Section -->
            <div class="signature-section">
                <!-- Left: Borrower -->
                <div class="sig-left">
                    <div class="sig-box">
                        <div class="sig-line sig-name">
                            <?php echo strtoupper(htmlspecialchars($transaction['recipient_name'])); ?>
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
<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Transaction Report';

// Get filter parameters
$type_filter = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';
$date_from   = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to     = isset($_GET['date_to'])   ? $_GET['date_to']   : date('Y-m-d');

// ── Column selection ──────────────────────────────────────────
$all_cols     = ['date', 'transaction_code', 'item_code', 'item_description', 'type', 'quantity', 'unit_cost', 'total_cost'];
$default_cols = ['date', 'transaction_code', 'item_code', 'item_description', 'type', 'quantity', 'unit_cost', 'total_cost'];
$selected_cols = isset($_GET['cols']) ? array_intersect((array)$_GET['cols'], $all_cols) : $default_cols;
if (empty($selected_cols)) $selected_cols = $default_cols;

$col_labels = [
    'date'             => 'Date',
    'transaction_code' => 'Transaction Code',
    'item_code'        => 'Item Code',
    'item_description' => 'Description',
    'type'             => 'Type',
    'quantity'         => 'Quantity',
    'unit_cost'        => 'Unit Cost',
    'total_cost'       => 'Total Cost',
];

// Build query
$where  = ["1=1"];
$params = [];
$types  = "";

if ($type_filter) {
    $where[]  = "t.transaction_type = ?";
    $params[] = $type_filter;
    $types   .= "s";
}
if ($date_from) {
    $where[]  = "t.transaction_date >= ?";
    $params[] = $date_from;
    $types   .= "s";
}
if ($date_to) {
    $where[]  = "t.transaction_date <= ?";
    $params[] = $date_to;
    $types   .= "s";
}

$where_clause = implode(' AND ', $where);

$query = "
    SELECT 
        t.*,
        i.item_code,
        i.item_description,
        c.category_name,
        s.supplier_name,
        u.full_name as created_by_name
    FROM transactions t
    JOIN inventory_items i ON t.item_id = i.id
    JOIN categories c ON i.category_id = c.id
    LEFT JOIN suppliers s ON t.supplier_id = s.id
    LEFT JOIN users u ON t.created_by = u.id
    WHERE $where_clause
    ORDER BY t.transaction_date DESC, t.created_at DESC
";

$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$transactions = $stmt->get_result();
$stmt->close();

// Totals
$totals = ['transactions' => 0, 'received' => 0, 'distributed' => 0, 'value' => 0];
$trans_data = [];
while ($row = $transactions->fetch_assoc()) {
    $trans_data[] = $row;
    $totals['transactions']++;
    if ($row['transaction_type'] === 'received')    $totals['received']    += $row['quantity'];
    if ($row['transaction_type'] === 'distributed') $totals['distributed'] += $row['quantity'];
    $totals['value'] += $row['total_cost'];
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
        <button onclick="printProfessionalReport('transactionsTable', 'TRANSACTION REPORT', 'Period: <?php echo date('M d, Y', strtotime($date_from)); ?> to <?php echo date('M d, Y', strtotime($date_to)); ?>')" class="btn btn-primary">
            <i class="fas fa-print"></i> Print Report
        </button>
        <button onclick="exportReportToExcel('transactionsTable', 'TRANSACTION REPORT', 'Transaction_Report_<?php echo date('Y-m-d'); ?>.xlsx')" class="btn btn-success">
            <i class="fas fa-file-excel"></i> Export Excel
        </button>
    </div>
</div>

<!-- ── Filters ─────────────────────────────────────────────────── -->
<div class="card report-filter-card no-print">
    <div class="report-filter-body">
        <h3 class="report-filter-title">Filters</h3>
        <form method="GET" id="reportForm" class="report-filter-form-end">
            <div>
                <label class="form-label">Transaction Type</label>
                <select name="type" class="form-control report-select-md">
                    <option value="">All Types</option>
                    <option value="received"    <?php echo $type_filter === 'received'    ? 'selected' : ''; ?>>Received</option>
                    <option value="distributed" <?php echo $type_filter === 'distributed' ? 'selected' : ''; ?>>Distributed</option>
                </select>
            </div>
            <div>
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div>
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>

            <!-- hidden col inputs injected by JS -->
            <div id="colHiddenInputs"></div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Apply Filters
            </button>
            <a href="report_transactions.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Clear
            </a>
        </form>
    </div>
</div>

<!-- ── Column Selector ─────────────────────────────────────────── -->
<div class="card col-selector-card no-print">
    <div class="col-selector-body">
        <h3 class="col-selector-title"><i class="fas fa-columns"></i> Report Contents (Columns)</h3>
        <p class="col-selector-subtitle">Choose which columns to include in the report, print, and export.</p>

        <div class="col-selector-grid">
            <?php foreach ($col_labels as $key => $label): ?>
            <label class="col-checkbox-card">
                <input type="checkbox"
                       class="col-toggle"
                       data-col="<?php echo $key; ?>"
                       value="<?php echo $key; ?>"
                       <?php echo in_array($key, $selected_cols) ? 'checked' : ''; ?>>
                <span class="col-checkbox-label"><?php echo htmlspecialchars($label); ?></span>
            </label>
            <?php endforeach; ?>
        </div>

        <div class="col-selector-actions">
            <button type="button" class="col-quick-link" onclick="selectAllCols()">Select All</button>
            <span class="col-selector-sep">|</span>
            <button type="button" class="col-quick-link" onclick="deselectAllCols()">Deselect All</button>
        </div>

        <div class="col-selector-warning" id="colWarning">
            <i class="fas fa-exclamation-triangle"></i>
            Please select at least one column before generating the report.
        </div>
    </div>
</div>

<!-- ── Report Header ───────────────────────────────────────────── -->
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
                    <h3 class="report-doc-title">Transaction Report</h3>
                    <p class="report-date-note">
                        Period: <?php echo date('M d, Y', strtotime($date_from)); ?> to <?php echo date('M d, Y', strtotime($date_to)); ?>
                    </p>
                    <p style="margin: 2px 0 0; color: var(--gray-500); font-size: 12px;">Generated: <?php echo date('F d, Y h:i A'); ?></p>
                </div>
            </div>
            <img src="images/logo1.png" alt="City Logo" class="report-logo report-logo-contain">
        </div>
    </div>
</div>

<!-- ── Summary Stats ───────────────────────────────────────────── -->
<div class="card report-header-card">
    <div class="report-filter-body">
        <div class="report-summary-grid-4">
            <div class="report-stat-blue">
                <div class="report-stat-label-blue">Total Transactions</div>
                <div class="report-stat-value-blue"><?php echo number_format($totals['transactions']); ?></div>
            </div>
            <div class="report-stat-green">
                <div class="report-stat-label-green">Items Received</div>
                <div class="report-stat-value-green"><?php echo number_format($totals['received']); ?></div>
            </div>
            <div class="report-stat-yellow">
                <div class="report-stat-label-yellow">Items Distributed</div>
                <div class="report-stat-value-yellow"><?php echo number_format($totals['distributed']); ?></div>
            </div>
            <div class="report-stat-red">
                <div class="report-stat-label-red">Total Value</div>
                <div class="report-stat-value-red">₱<?php echo number_format($totals['value'], 2); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- ── Transactions Table ──────────────────────────────────────── -->
<div class="card">
    <div class="report-filter-body">
        <table class="report-data-table-sm" id="transactionsTable">
            <thead>
                <tr class="report-thead-row">
                    <th class="report-th-sm report-th-sm-left   col-th-date"            >Date</th>
                    <th class="report-th-sm report-th-sm-left   col-th-transaction_code">Transaction Code</th>
                    <th class="report-th-sm report-th-sm-left   col-th-item_code"       >Item Code</th>
                    <th class="report-th-sm report-th-sm-left   col-th-item_description">Description</th>
                    <th class="report-th-sm report-th-sm-center col-th-type"            >Type</th>
                    <th class="report-th-sm report-th-sm-center col-th-quantity"        >Quantity</th>
                    <th class="report-th-sm report-th-sm-right  col-th-unit_cost"       >Unit Cost</th>
                    <th class="report-th-sm report-th-sm-right  col-th-total_cost"      >Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trans_data as $trans): ?>
                <tr>
                    <td class="report-td-sm                      col-td-date"            ><?php echo date('M d, Y', strtotime($trans['transaction_date'])); ?></td>
                    <td class="report-td-sm                      col-td-transaction_code"><strong><?php echo htmlspecialchars($trans['transaction_code']); ?></strong></td>
                    <td class="report-td-sm                      col-td-item_code"       ><?php echo htmlspecialchars($trans['item_code']); ?></td>
                    <td class="report-td-sm                      col-td-item_description"><?php echo htmlspecialchars(substr($trans['item_description'], 0, 40)); ?></td>
                    <td class="report-td-sm report-td-sm-center  col-td-type"            ><?php echo ucfirst($trans['transaction_type']); ?></td>
                    <td class="report-td-sm report-td-sm-center report-td-sm-bold col-td-quantity">
                        <?php echo in_array($trans['transaction_type'], ['distributed']) ? '-' : '+'; ?>
                        <?php echo number_format($trans['quantity']); ?>
                    </td>
                    <td style="padding:8px;border:1px solid #ddd;text-align:right;" class="col-td-unit_cost">₱<?php echo number_format($trans['unit_cost'], 2); ?></td>
                    <td style="padding:8px;border:1px solid #ddd;text-align:right;font-weight:600;" class="col-td-total_cost">₱<?php echo number_format($trans['total_cost'], 2); ?></td>
                </tr>
                <?php endforeach; ?>

                <?php if (empty($trans_data)): ?>
                <tr>
                    <td colspan="8" class="report-empty-cell">No transactions found for the selected period</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/report_functions.php'; ?>

<script>
const selectedCols = <?php echo json_encode(array_values($selected_cols)); ?>;

function applyColVisibility() {
    const checked = getCheckedCols();
    <?php foreach ($all_cols as $key): ?>
    const show_<?php echo $key; ?> = checked.includes('<?php echo $key; ?>');
    document.querySelectorAll('.col-th-<?php echo $key; ?>, .col-td-<?php echo $key; ?>').forEach(el => {
        el.classList.toggle('col-hidden', !show_<?php echo $key; ?>);
    });
    <?php endforeach; ?>
}

function getCheckedCols() {
    return Array.from(document.querySelectorAll('.col-toggle:checked')).map(cb => cb.value);
}

function selectAllCols() {
    document.querySelectorAll('.col-toggle').forEach(cb => cb.checked = true);
    applyColVisibility();
    syncHiddenInputs();
}

function deselectAllCols() {
    document.querySelectorAll('.col-toggle').forEach(cb => cb.checked = false);
    applyColVisibility();
    syncHiddenInputs();
}

function syncHiddenInputs() {
    const container = document.getElementById('colHiddenInputs');
    if (!container) return;
    container.innerHTML = '';
    getCheckedCols().forEach(col => {
        const inp = document.createElement('input');
        inp.type  = 'hidden';
        inp.name  = 'cols[]';
        inp.value = col;
        container.appendChild(inp);
    });
}

document.querySelectorAll('.col-toggle').forEach(cb => {
    cb.addEventListener('change', () => {
        applyColVisibility();
        syncHiddenInputs();
        const warning = document.getElementById('colWarning');
        if (warning) warning.classList.toggle('show', getCheckedCols().length === 0);
    });
});

document.getElementById('reportForm').addEventListener('submit', function(e) {
    if (getCheckedCols().length === 0) {
        e.preventDefault();
        const warning = document.getElementById('colWarning');
        if (warning) { warning.classList.add('show'); warning.scrollIntoView({behavior:'smooth'}); }
    }
});

applyColVisibility();
syncHiddenInputs();
</script>

<?php require_once 'includes/footer.php'; ?>

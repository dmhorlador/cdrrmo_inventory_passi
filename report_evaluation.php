<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Valuation Report';

// ── Column selection ──────────────────────────────────────────
$all_cols     = ['item_code', 'item_description', 'category', 'on_hand', 'unit_cost', 'current_value', 'pct_of_total'];
$default_cols = ['item_code', 'item_description', 'category', 'on_hand', 'unit_cost', 'current_value', 'pct_of_total'];
$selected_cols = isset($_GET['cols']) ? array_intersect((array)$_GET['cols'], $all_cols) : $default_cols;
if (empty($selected_cols)) $selected_cols = $default_cols;

$col_labels = [
    'item_code'        => 'Item Code',
    'item_description' => 'Description',
    'category'         => 'Category',
    'on_hand'          => 'On Hand',
    'unit_cost'        => 'Unit Cost',
    'current_value'    => 'Current Value',
    'pct_of_total'     => '% of Total',
];

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

$totals = ['current_value' => 0, 'received_value' => 0, 'distributed_value' => 0, 'items' => 0];
$items_data = [];
while ($row = $items->fetch_assoc()) {
    $items_data[] = $row;
    $totals['current_value']      += $row['current_value'];
    $totals['received_value']     += $row['total_received_value'];
    $totals['distributed_value']  += $row['total_distributed_value'];
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
                    <h3 class="report-doc-title">Inventory Valuation Report</h3>
                    <p style="margin: 4px 0 0; color: var(--gray-500); font-size: 12px;">Generated: <?php echo date('F d, Y h:i A'); ?></p>
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
            <div class="report-stat-lg-blue">
                <div class="report-stat-label-lg report-stat-label-blue">Current Inventory Value</div>
                <div class="report-stat-value-lg report-stat-value-blue">₱<?php echo number_format($totals['current_value'], 2); ?></div>
                <div class="report-stat-note">Based on current stock</div>
            </div>
            <div class="report-stat-lg-green">
                <div class="report-stat-label-lg report-stat-label-green">Total Received Value</div>
                <div class="report-stat-value-lg report-stat-value-green">₱<?php echo number_format($totals['received_value'], 2); ?></div>
                <div class="report-stat-note">All time</div>
            </div>
            <div class="report-stat-lg-yellow">
                <div class="report-stat-label-lg report-stat-label-yellow">Total Distributed Value</div>
                <div class="report-stat-value-lg report-stat-value-yellow">₱<?php echo number_format($totals['distributed_value'], 2); ?></div>
                <div class="report-stat-note">All time</div>
            </div>
            <div class="report-stat-lg-red">
                <div class="report-stat-label-lg report-stat-label-red">Total Items</div>
                <div class="report-stat-value-lg report-stat-value-red"><?php echo number_format($totals['items']); ?></div>
                <div class="report-stat-note">Active items</div>
            </div>
        </div>
    </div>
</div>

<!-- ── Valuation Table ─────────────────────────────────────────── -->
<div class="card">
    <div class="report-filter-body">
        <table class="report-data-table-auto" id="valuationTable">
            <thead>
                <tr class="report-thead-row">
                    <th class="report-th report-th-left   col-th-item_code"       >Item Code</th>
                    <th class="report-th report-th-left   col-th-item_description">Description</th>
                    <th class="report-th report-th-center col-th-category"        >Category</th>
                    <th class="report-th report-th-center col-th-on_hand"         >On Hand</th>
                    <th class="report-th report-th-right  col-th-unit_cost"       >Unit Cost</th>
                    <th class="report-th report-th-right  col-th-current_value"   >Current Value</th>
                    <th class="report-th report-th-right  col-th-pct_of_total"    >% of Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items_data as $item): ?>
                <?php $percentage = $totals['current_value'] > 0 ? ($item['current_value'] / $totals['current_value']) * 100 : 0; ?>
                <tr>
                    <td class="report-td                   col-td-item_code"       ><strong><?php echo htmlspecialchars($item['item_code']); ?></strong></td>
                    <td class="report-td                   col-td-item_description"><?php echo htmlspecialchars($item['item_description']); ?></td>
                    <td class="report-td report-td-center  col-td-category"        ><?php echo htmlspecialchars($item['category_name']); ?></td>
                    <td class="report-td report-td-center report-td-bold col-td-on_hand"><?php echo number_format($item['items_on_hand']); ?></td>
                    <td class="report-td report-td-right   col-td-unit_cost"       >₱<?php echo number_format($item['unit_cost'], 2); ?></td>
                    <td class="report-td report-td-right report-td-bold report-value-primary col-td-current_value">₱<?php echo number_format($item['current_value'], 2); ?></td>
                    <td class="report-td report-td-right   col-td-pct_of_total"    ><?php echo number_format($percentage, 2); ?>%</td>
                </tr>
                <?php endforeach; ?>

                <!-- Totals Row -->
                <tr class="report-totals-row">
                    <td class="report-totals-value-right col-td-item_code"       >TOTALS:</td>
                    <td class="report-totals-empty       col-td-item_description"></td>
                    <td class="report-totals-empty       col-td-category"        ></td>
                    <td class="report-totals-empty       col-td-on_hand"         ></td>
                    <td class="report-totals-empty       col-td-unit_cost"       ></td>
                    <td class="report-totals-value-right report-value-primary col-td-current_value">₱<?php echo number_format($totals['current_value'], 2); ?></td>
                    <td class="report-totals-value-right col-td-pct_of_total"    >100.00%</td>
                </tr>
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
}

function deselectAllCols() {
    document.querySelectorAll('.col-toggle').forEach(cb => cb.checked = false);
    applyColVisibility();
}

document.querySelectorAll('.col-toggle').forEach(cb => {
    cb.addEventListener('change', () => {
        applyColVisibility();
        const warning = document.getElementById('colWarning');
        if (warning) warning.classList.toggle('show', getCheckedCols().length === 0);
    });
});

applyColVisibility();
</script>

<?php require_once 'includes/footer.php'; ?>

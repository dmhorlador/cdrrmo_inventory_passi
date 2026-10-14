<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Category Report';

// ── Column selection ──────────────────────────────────────────
$all_cols     = ['category_name', 'category_code', 'item_count', 'on_hand', 'received', 'distributed', 'total_value', 'low_stock'];
$default_cols = ['category_name', 'category_code', 'item_count', 'on_hand', 'received', 'distributed', 'total_value', 'low_stock'];
$selected_cols = isset($_GET['cols']) ? array_intersect((array)$_GET['cols'], $all_cols) : $default_cols;
if (empty($selected_cols)) $selected_cols = $default_cols;

$col_labels = [
    'category_name' => 'Category',
    'category_code' => 'Code',
    'item_count'    => 'Item Count',
    'on_hand'       => 'On Hand',
    'received'      => 'Received',
    'distributed'   => 'Distributed',
    'total_value'   => 'Total Value',
    'low_stock'     => 'Stock Alert',
];

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

$grand_totals = ['items' => 0, 'on_hand' => 0, 'received' => 0, 'distributed' => 0, 'value' => 0, 'low_stock' => 0];
$cat_data = [];
while ($row = $categories->fetch_assoc()) {
    $cat_data[] = $row;
    $grand_totals['items']        += $row['item_count'];
    $grand_totals['on_hand']      += $row['total_on_hand'];
    $grand_totals['received']     += $row['total_received'];
    $grand_totals['distributed']  += $row['total_distributed'];
    $grand_totals['value']        += $row['total_value'];
    $grand_totals['low_stock']    += $row['low_stock_count'];
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
                    <h3 class="report-doc-title">Inventory By Category Report</h3>
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
        <h3 class="report-summary-title">Summary</h3>
        <div class="report-summary-grid-6">
            <div class="report-stat-blue">
                <div class="report-stat-label-blue">Total Items</div>
                <div class="report-stat-value-blue"><?php echo number_format($grand_totals['items']); ?></div>
            </div>
            <div class="report-stat-green">
                <div class="report-stat-label-green">On Hand</div>
                <div class="report-stat-value-green"><?php echo number_format($grand_totals['on_hand']); ?></div>
            </div>
            <div class="report-stat-green">
                <div class="report-stat-label-green">Received</div>
                <div class="report-stat-value-green"><?php echo number_format($grand_totals['received']); ?></div>
            </div>
            <div class="report-stat-yellow">
                <div class="report-stat-label-yellow">Distributed</div>
                <div class="report-stat-value-yellow"><?php echo number_format($grand_totals['distributed']); ?></div>
            </div>
            <div class="report-stat-red">
                <div class="report-stat-label-red">Total Value</div>
                <div class="report-stat-value-red">₱<?php echo number_format($grand_totals['value'], 2); ?></div>
            </div>
            <div class="report-stat-red">
                <div class="report-stat-label-red">Stock Alert</div>
                <div class="report-stat-value-red"><?php echo number_format($grand_totals['low_stock']); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- ── Category Table ─────────────────────────────────────────── -->
<div class="card">
    <div class="report-filter-body">
        <table class="report-data-table" id="categoryTable">
            <thead>
                <tr class="report-thead-row">
                    <th class="report-th report-th-left   col-th-category_name">Category</th>
                    <th class="report-th report-th-left   col-th-category_code">Code</th>
                    <th class="report-th report-th-center col-th-item_count"   >Item Count</th>
                    <th class="report-th report-th-center col-th-on_hand"      >On Hand</th>
                    <th class="report-th report-th-center col-th-received"     >Received</th>
                    <th class="report-th report-th-center col-th-distributed"  >Distributed</th>
                    <th class="report-th report-th-right  col-th-total_value"  >Total Value</th>
                    <th class="report-th report-th-center col-th-low_stock"    >Stock Alert</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cat_data as $cat): ?>
                <tr>
                    <td class="report-td col-td-category_name">
                        <span style="display:inline-block;width:12px;height:12px;background:<?php echo htmlspecialchars($cat['color']); ?>;border-radius:3px;margin-right:8px;"></span>
                        <strong><?php echo htmlspecialchars($cat['category_name']); ?></strong>
                    </td>
                    <td class="report-td                    col-td-category_code"><?php echo htmlspecialchars($cat['category_code']); ?></td>
                    <td class="report-td report-td-center report-td-bold col-td-item_count"><?php echo number_format($cat['item_count']); ?></td>
                    <td class="report-td report-td-center report-td-bold col-td-on_hand"><?php echo number_format($cat['total_on_hand']); ?></td>
                    <td class="report-td report-td-center   col-td-received"    ><?php echo number_format($cat['total_received']); ?></td>
                    <td class="report-td report-td-center   col-td-distributed" ><?php echo number_format($cat['total_distributed']); ?></td>
                    <td class="report-td report-td-right report-td-bold col-td-total_value">₱<?php echo number_format($cat['total_value'], 2); ?></td>
                    <td class="report-td report-td-center   col-td-low_stock"   >
                        <?php if ($cat['low_stock_count'] > 0): ?>
                            <span style="color:#dc2626;font-weight:600;"><?php echo number_format($cat['low_stock_count']); ?></span>
                        <?php else: ?>
                            <span class="report-zero-good">0</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>

                <!-- Totals Row -->
                <tr class="report-totals-row">
                    <td colspan="1" class="report-totals-label col-td-category_name">TOTALS:</td>
                    <td class="report-totals-empty       col-td-category_code"></td>
                    <td class="report-totals-value       col-td-item_count"   ><?php echo number_format($grand_totals['items']); ?></td>
                    <td class="report-totals-value       col-td-on_hand"      ><?php echo number_format($grand_totals['on_hand']); ?></td>
                    <td class="report-totals-value       col-td-received"     ><?php echo number_format($grand_totals['received']); ?></td>
                    <td class="report-totals-value       col-td-distributed"  ><?php echo number_format($grand_totals['distributed']); ?></td>
                    <td class="report-totals-value-right col-td-total_value"  >₱<?php echo number_format($grand_totals['value'], 2); ?></td>
                    <td class="report-totals-value       col-td-low_stock"    ><?php echo number_format($grand_totals['low_stock']); ?></td>
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

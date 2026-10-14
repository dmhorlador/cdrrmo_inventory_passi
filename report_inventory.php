<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Inventory Summary Report';

// Get filter parameters
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';

// ── Column selection (GET params, with sensible defaults) ──────
$all_cols = ['item_code', 'item_description', 'category', 'on_hand', 'unit', 'unit_cost', 'total_value', 'status'];
$default_cols = ['item_code', 'item_description', 'category', 'on_hand', 'unit', 'unit_cost', 'total_value', 'status'];
$selected_cols = isset($_GET['cols']) ? array_intersect((array)$_GET['cols'], $all_cols) : $default_cols;
if (empty($selected_cols)) $selected_cols = $default_cols;

$col_labels = [
    'item_code'        => 'Item Code',
    'item_description' => 'Description',
    'category'         => 'Category',
    'on_hand'          => 'On Hand',
    'unit'             => 'Unit',
    'unit_cost'        => 'Unit Cost',
    'total_value'      => 'Total Value',
    'status'           => 'Status',
];

// Build query
$where = ["i.is_active = 1"];
$params = [];
$types = "";

if ($category_filter) {
    $where[] = "i.category_id = ?";
    $params[] = $category_filter;
    $types .= "i";
}

if ($status_filter === 'low_stock') {
    $where[] = "i.items_on_hand <= i.minimum_stock_level";
} elseif ($status_filter === 'out_of_stock') {
    $where[] = "i.items_on_hand <= 0";
}

$where_clause = implode(' AND ', $where);

// Get inventory data
$query = "
    SELECT 
        i.*,
        c.category_name,
        c.color as category_color,
        (i.items_on_hand * i.unit_cost) as total_value
    FROM inventory_items i
    JOIN categories c ON i.category_id = c.id
    WHERE $where_clause
    ORDER BY c.category_name, i.item_code
";

$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$items = $stmt->get_result();
$stmt->close();

// Get categories for filter
$categories = $conn->query("SELECT id, category_name FROM categories WHERE is_active = 1 ORDER BY category_name");

// Calculate totals
$totals = [
    'items' => 0,
    'on_hand' => 0,
    'value' => 0,
    'received' => 0,
    'distributed' => 0
];

$items_data = [];
while ($row = $items->fetch_assoc()) {
    $items_data[] = $row;
    $totals['items']++;
    $totals['on_hand'] += $row['items_on_hand'];
    $totals['value'] += $row['total_value'];
    $totals['received'] += $row['items_received'];
    $totals['distributed'] += $row['items_distributed'];
}

// Helper: is column selected?
function col($key, $selected) { return in_array($key, $selected); }

require_once 'includes/header.php';
?>

<div class="no-print report-page-actions">
    <div>
        <a href="reports.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Reports
        </a>
    </div>
    <div class="report-page-btns">
        <button onclick="printProfessionalReport('inventoryTable', 'INVENTORY SUMMARY REPORT', '')" class="btn btn-primary">
            <i class="fas fa-print"></i> Print Report
        </button>
        <button onclick="exportReportToExcel('inventoryTable', 'INVENTORY SUMMARY REPORT', 'Inventory_Report_<?php echo date('Y-m-d'); ?>.xlsx')" class="btn btn-success">
            <i class="fas fa-file-excel"></i> Export Excel
        </button>
    </div>
</div>

<!-- ── Filters ─────────────────────────────────────────────────── -->
<div class="card report-filter-card no-print">
    <div class="report-filter-body">
        <h3 class="report-filter-title">Filters</h3>
        <form method="GET" id="reportForm" class="report-filter-form">
            <select name="category" class="form-control report-select-lg">
                <option value="">All Categories</option>
                <?php 
                $categories->data_seek(0);
                while ($cat = $categories->fetch_assoc()): 
                ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['category_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <select name="status" class="form-control report-select-md">
                <option value="">All Status</option>
                <option value="low_stock" <?php echo $status_filter === 'low_stock' ? 'selected' : ''; ?>>Stock Alert</option>
                <option value="out_of_stock" <?php echo $status_filter === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
            </select>

            <!-- hidden col inputs are injected by JS below -->
            <div id="colHiddenInputs"></div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Apply Filters
            </button>

            <?php if ($category_filter || $status_filter): ?>
                <a href="report_inventory.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ── Column Selector ─────────────────────────────────────────── -->
<div class="card col-selector-card no-print">
    <div class="col-selector-body">
        <h3 class="col-selector-title"><i class="fas fa-columns"></i> Report Contents (Columns)</h3>
        <p class="col-selector-subtitle">Choose which columns to include in the report, print, and export.</p>

        <div class="col-selector-grid" id="colSelectorGrid">
            <?php foreach ($col_labels as $key => $label): ?>
            <label class="col-checkbox-card">
                <input type="checkbox"
                       class="col-toggle"
                       data-col="<?php echo $key; ?>"
                       value="<?php echo $key; ?>"
                       <?php echo col($key, $selected_cols) ? 'checked' : ''; ?>>
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
                    <h3 class="report-doc-title">Inventory Summary Report</h3>
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
        <div class="report-summary-grid-5">
            <div class="report-stat-blue">
                <div class="report-stat-label-blue report-td-bold">Total Items</div>
                <div class="report-stat-value-blue"><?php echo number_format($totals['items']); ?></div>
            </div>
            <div class="report-stat-green">
                <div class="report-stat-label-green report-td-bold">Items on Hand</div>
                <div class="report-stat-value-green"><?php echo number_format($totals['on_hand']); ?></div>
            </div>
            <div class="report-stat-yellow">
                <div class="report-stat-label-yellow">Total Value</div>
                <div class="report-stat-value-yellow">₱<?php echo number_format($totals['value'], 2); ?></div>
            </div>
            <div class="report-stat-green">
                <div class="report-stat-label-green">Total Received</div>
                <div class="report-stat-value-green"><?php echo number_format($totals['received']); ?></div>
            </div>
            <div class="report-stat-red">
                <div class="report-stat-label-red">Total Distributed</div>
                <div class="report-stat-value-red"><?php echo number_format($totals['distributed']); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- ── Inventory Table ─────────────────────────────────────────── -->
<div class="card">
    <div class="report-filter-body">
        <table class="report-data-table-auto" id="inventoryTable">
            <thead>
                <tr class="report-thead-row">
                    <th class="report-th report-th-left    col-th-item_code"       >Item Code</th>
                    <th class="report-th report-th-left    col-th-item_description">Description</th>
                    <th class="report-th report-th-center  col-th-category"        >Category</th>
                    <th class="report-th report-th-center  col-th-on_hand"         >On Hand</th>
                    <th class="report-th report-th-center  col-th-unit"            >Unit</th>
                    <th class="report-th report-th-right   col-th-unit_cost"       >Unit Cost</th>
                    <th class="report-th report-th-right   col-th-total_value"     >Total Value</th>
                    <th class="report-th report-th-center  col-th-status"          >Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items_data as $item): ?>
                <tr>
                    <td class="report-td                    col-td-item_code"       ><strong><?php echo htmlspecialchars($item['item_code']); ?></strong></td>
                    <td class="report-td                    col-td-item_description"><?php echo htmlspecialchars($item['item_description']); ?></td>
                    <td class="report-td report-td-center   col-td-category"        ><?php echo htmlspecialchars($item['category_name']); ?></td>
                    <td class="report-td report-td-center report-td-bold col-td-on_hand"><?php echo number_format($item['items_on_hand']); ?></td>
                    <td class="report-td report-td-center   col-td-unit"            ><?php echo htmlspecialchars($item['unit'] ?? 'pcs'); ?></td>
                    <td class="report-td report-td-right    col-td-unit_cost"       >₱<?php echo number_format($item['unit_cost'], 2); ?></td>
                    <td class="report-td report-td-right report-td-bold col-td-total_value">₱<?php echo number_format($item['total_value'], 2); ?></td>
                    <td class="report-td report-td-center   col-td-status"          >
                        <?php if ($item['items_on_hand'] <= 0): ?>
                            <span class="status-out-of-stock">Out of Stock</span>
                        <?php elseif ($item['items_on_hand'] <= $item['minimum_stock_level']): ?>
                            <span class="status-low-stock">Stock Alert</span>
                        <?php else: ?>
                            <span class="status-in-stock">In Stock</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if (empty($items_data)): ?>
                <tr>
                    <td colspan="8" class="report-empty-cell">No items found</td>
                </tr>
                <?php endif; ?>

                <!-- Totals Row -->
                <tr class="report-totals-row">
                    <td class="report-totals-value-right col-td-item_code"       >TOTALS:</td>
                    <td class="report-totals-empty       col-td-item_description"></td>
                    <td class="report-totals-empty       col-td-category"        ></td>
                    <td class="report-totals-value       col-td-on_hand"         ><?php echo number_format($totals['on_hand']); ?></td>
                    <td class="report-totals-empty       col-td-unit"            ></td>
                    <td class="report-totals-empty       col-td-unit_cost"       ></td>
                    <td class="report-totals-value-right col-td-total_value"     >₱<?php echo number_format($totals['value'], 2); ?></td>
                    <td class="report-totals-empty       col-td-status"          ></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/report_functions.php'; ?>

<script>
// ── Column visibility logic ────────────────────────────────────
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
        inp.type = 'hidden';
        inp.name = 'cols[]';
        inp.value = col;
        container.appendChild(inp);
    });
}

// Attach change listeners
document.querySelectorAll('.col-toggle').forEach(cb => {
    cb.addEventListener('change', () => {
        applyColVisibility();
        syncHiddenInputs();
        const warning = document.getElementById('colWarning');
        if (warning) warning.classList.toggle('show', getCheckedCols().length === 0);
    });
});

// Guard form submit
document.getElementById('reportForm').addEventListener('submit', function(e) {
    if (getCheckedCols().length === 0) {
        e.preventDefault();
        const warning = document.getElementById('colWarning');
        if (warning) { warning.classList.add('show'); warning.scrollIntoView({behavior:'smooth'}); }
    }
});

// Apply on page load
applyColVisibility();
syncHiddenInputs();
</script>

<?php require_once 'includes/footer.php'; ?>

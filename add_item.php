<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Add New Item';

// Get categories
$categories = $conn->query("SELECT id, category_name FROM categories WHERE is_active = 1 ORDER BY category_name");

// Get storage locations
$storage_locations = $conn->query("SELECT id, location_name FROM storage_locations WHERE is_active = 1 ORDER BY location_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id          = (int)$_POST['category_id'];
    $unit                 = sanitize_input($_POST['unit']);
    $unit_cost            = (float)$_POST['unit_cost'];
    $minimum_stock_level  = (int)$_POST['minimum_stock_level'];
    $storage_location_id  = !empty($_POST['storage_location_id']) ? (int)$_POST['storage_location_id'] : null;
    $expiration_date      = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;

    $descriptions    = $_POST['item_description'];
    $items_received  = $_POST['items_received'];
    $items_distributed = $_POST['items_distributed'];

    $errors    = [];
    $successes = 0;

    foreach ($descriptions as $i => $raw_desc) {
        $item_description  = sanitize_input($raw_desc);
        if (empty($item_description)) continue; // skip blank rows

        $recv = (int)$items_received[$i];
        $dist = (int)$items_distributed[$i];
        $on_hand = $recv - $dist;

        // Generate item code
        $stmt = $conn->prepare("CALL sp_generate_item_code(?, @item_code)");
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $stmt->close();

        $result    = $conn->query("SELECT @item_code as item_code");
        $item_code = $result->fetch_assoc()['item_code'];

        // Insert item
        $stmt = $conn->prepare("
            INSERT INTO inventory_items (
                item_code, category_id, item_description, unit, unit_cost,
                minimum_stock_level, storage_location_id, expiration_date,
                items_received, items_distributed, items_on_hand,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "sissdiisiiii",
            $item_code, $category_id, $item_description, $unit, $unit_cost,
            $minimum_stock_level, $storage_location_id, $expiration_date,
            $recv, $dist, $on_hand,
            $_SESSION['user_id']
        );

        if ($stmt->execute()) {
            log_activity($_SESSION['user_id'], 'create_item', "Created item: $item_code - $item_description");
            $successes++;
        } else {
            $errors[] = "Failed to add row " . ($i + 1) . ": $item_description";
        }
        $stmt->close();
    }

    if ($successes > 0 && empty($errors)) {
        $_SESSION['success'] = "$successes item(s) added successfully!";
        header('Location: inventory.php');
        exit();
    } elseif ($successes > 0) {
        $_SESSION['success'] = "$successes item(s) added. Some rows had errors.";
    } else {
        $error = "Failed to add items. Please try again.";
    }
}

require_once 'includes/header.php';
?>

<div class="inventory-form">
    <div class="form-header">
        <h1>PASSI CITY</h1>
        <h2>DISASTER RISK REDUCTION & MANAGEMENT OFFICE</h2>
        <h3>INVENTORY OF SUPPLIES</h3>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <!-- Shared metadata -->
        <div class="form-group form-group-main">
            <label class="form-label">Select Category *</label>
            <select name="category_id" class="form-control" required>
                <option value="">-- Select Category --</option>
                <?php
                $categories->data_seek(0);
                while ($cat = $categories->fetch_assoc()):
                ?>
                    <option value="<?php echo $cat['id']; ?>">
                        <?php echo htmlspecialchars($cat['category_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-grid-3">
            <div class="form-group form-grid-col">
                <label class="form-label">Unit of Measurement *</label>
                <select name="unit" class="form-control" required>
                    <option value="" disabled selected>-- Select Unit --</option>
                    <option value="pcs">pcs (Pieces)</option>
                    <option value="boxes">boxes</option>
                    <option value="packs">packs</option>
                    <option value="bottles">bottles</option>
                    <option value="cans">cans</option>
                    <option value="rolls">rolls</option>
                    <option value="sets">sets</option>
                    <option value="pairs">pairs</option>
                    <option value="cases">cases</option>
                    <option value="bundles">bundles</option>
                    <option value="pads">pads</option>
                    <option value="sacks">sacks</option>
                    <option value="ream">ream</option>
                    <option value="dozen">dozen</option>
                    <option value="kg">kg (Kilograms)</option>
                    <option value="g">g (Grams)</option>
                    <option value="liters">liters</option>
                    <option value="gallons">gallons</option>
                    <option value="meters">meters</option>
                    <option value="units">units</option>
                </select>
            </div>

            <div class="form-group form-grid-col">
                <label class="form-label">Unit Cost (₱) *</label>
                <input type="number" name="unit_cost" class="form-control" step="0.01" min="0" required placeholder="0.00">
            </div>

            <div class="form-group form-grid-col">
                <label class="form-label">Minimum Stock Level *</label>
                <input type="number" name="minimum_stock_level" class="form-control" min="1" required value="5">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group form-grid-col">
                <label class="form-label">Storage Location</label>
                <select name="storage_location_id" class="form-control">
                    <option value="">-- Select Storage Location --</option>
                    <?php
                    $storage_locations->data_seek(0);
                    while ($loc = $storage_locations->fetch_assoc()):
                    ?>
                        <option value="<?php echo $loc['id']; ?>">
                            <?php echo htmlspecialchars($loc['location_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group form-grid-col">
                <label class="form-label">Expiration Date (Optional)</label>
                <input type="date" name="expiration_date" class="form-control">
                <small class="password-hint">Leave blank if item does not expire</small>
            </div>
        </div>

        <!-- Multi-row item table -->
        <table class="inventory-table" id="items-table">
            <thead>
                <tr>
                    <th style="width: 60px;">No.</th>
                    <th>Item Description</th>
                    <th style="width: 120px;">No. of items<br>received</th>
                    <th style="width: 120px;">No. of items<br>distributed</th>
                    <th style="width: 120px;">No. of items<br>on hand</th>
                    <th style="width: 60px;"></th>
                </tr>
            </thead>
            <tbody id="items-tbody">
                <tr data-row="0">
                    <td class="item-row-number">1</td>
                    <td>
                        <textarea name="item_description[]" required
                            placeholder="Enter item description&#10;e.g., Book paper 70 GSM 8.5 inch x 13 inch"></textarea>
                    </td>
                    <td style="text-align:center;">
                        <input type="number" name="items_received[]" min="0" value="0" required
                            oninput="calculateOnHand(this)" style="text-align:center;font-size:16px;">
                    </td>
                    <td style="text-align:center;">
                        <input type="number" name="items_distributed[]" min="0" value="0" required
                            oninput="calculateOnHand(this)" style="text-align:center;font-size:16px;">
                    </td>
                    <td class="calculated-field">
                        <span class="on-hand-display">0</span>
                    </td>
                    <td style="text-align:center;">
                        <!-- First row has no remove button -->
                    </td>
                </tr>
            </tbody>
        </table>

        <div style="margin: 10px 0 20px;">
            <button type="button" class="btn btn-secondary" onclick="addRow()">
                <i class="fas fa-plus"></i> Add Another Item
            </button>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-success btn-lg">
                <i class="fas fa-save"></i> Add Item(s) to Inventory
            </button>
            <a href="inventory.php" class="btn btn-secondary btn-lg">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
let rowCount = 1;

function calculateOnHand(inputEl) {
    const row = inputEl.closest('tr');
    const received    = parseInt(row.querySelector('input[name="items_received[]"]').value)    || 0;
    const distributed = parseInt(row.querySelector('input[name="items_distributed[]"]').value) || 0;
    row.querySelector('.on-hand-display').textContent = received - distributed;
}

function addRow() {
    const tbody = document.getElementById('items-tbody');
    const newRow = document.createElement('tr');
    newRow.dataset.row = rowCount;

    newRow.innerHTML = `
        <td class="item-row-number">${rowCount + 1}</td>
        <td>
            <textarea name="item_description[]"
                placeholder="Enter item description"></textarea>
        </td>
        <td style="text-align:center;">
            <input type="number" name="items_received[]" min="0" value="0"
                oninput="calculateOnHand(this)" style="text-align:center;font-size:16px;">
        </td>
        <td style="text-align:center;">
            <input type="number" name="items_distributed[]" min="0" value="0"
                oninput="calculateOnHand(this)" style="text-align:center;font-size:16px;">
        </td>
        <td class="calculated-field">
            <span class="on-hand-display">0</span>
        </td>
        <td style="text-align:center;">
            <button type="button" onclick="removeRow(this)" class="btn btn-danger btn-sm"
                title="Remove row" style="padding:4px 8px;">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;

    tbody.appendChild(newRow);
    rowCount++;
    renumberRows();

    // Focus the new textarea
    newRow.querySelector('textarea').focus();
}

function removeRow(btn) {
    const row = btn.closest('tr');
    row.remove();
    renumberRows();
}

function renumberRows() {
    document.querySelectorAll('#items-tbody tr').forEach((row, i) => {
        row.querySelector('.item-row-number').textContent = i + 1;
    });
}

// Loading indicator on submit
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function () {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) showFormLoading('Adding items to inventory...');
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
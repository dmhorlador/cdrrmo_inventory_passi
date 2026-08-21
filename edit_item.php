<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Edit Item';

if (!isset($_GET['id'])) {
    header('Location: inventory.php');
    exit();
}

$item_id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM inventory_items WHERE id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Item not found";
    header('Location: inventory.php');
    exit();
}

$item = $result->fetch_assoc();
$stmt->close();

$categories = $conn->query("SELECT id, category_name FROM categories WHERE is_active = 1 ORDER BY category_name");
$storage_locations = $conn->query("SELECT id, location_name FROM storage_locations WHERE is_active = 1 ORDER BY location_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = (int)$_POST['category_id'];
    $item_description = sanitize_input($_POST['item_description']);
    $unit = sanitize_input($_POST['unit']);
    $unit_cost = (float)$_POST['unit_cost'];
    $minimum_stock_level = (int)$_POST['minimum_stock_level'];
    $storage_location_id = !empty($_POST['storage_location_id']) ? (int)$_POST['storage_location_id'] : null;
    $expiration_date = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;
    
    $stmt = $conn->prepare("
        UPDATE inventory_items SET
            category_id = ?, item_description = ?, unit = ?, unit_cost = ?,
            minimum_stock_level = ?, storage_location_id = ?, expiration_date = ?, updated_by = ?
        WHERE id = ?
    ");
    $stmt->bind_param("issdiisii", $category_id, $item_description, $unit, $unit_cost, 
                      $minimum_stock_level, $storage_location_id, $expiration_date, $_SESSION['user_id'], $item_id);
    
    if ($stmt->execute()) {
        log_activity($_SESSION['user_id'], 'update_item', "Updated item: " . $item['item_code']);
        $_SESSION['success'] = "Item updated successfully!";
        header('Location: inventory.php');
        exit();
    } else {
        $error = "Failed to update item.";
    }
    $stmt->close();
}

require_once 'includes/header.php';
?>

<div class="inventory-form">
    <div class="form-header">
        <h1>PASSI CITY</h1>
        <h2>DISASTER RISK REDUCTION & MANAGEMENT OFFICE</h2>
        <h3>INVENTORY OF SUPPLIES - EDIT ITEM</h3>
    </div>

    <div class="info-box">
        <div class="info-box-inner">
            <div>
                <strong>Item Code:</strong>
                <span class="info-box-code"><?php echo htmlspecialchars($item['item_code']); ?></span>
            </div>
            <div class="info-box-note">
                <i class="fas fa-info-circle"></i> Stock levels are read-only. Use "Receive" or "Distribute" to update.
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group form-group-main">
            <label class="form-label">Category *</label>
            <select name="category_id" class="form-control" required>
                <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $cat['id'] == $item['category_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['category_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-grid-3">
            <div class="form-group form-grid-col">
                <label class="form-label">Unit of Measurement *</label>
                <?php $current_unit = $item['unit'] ?? 'pcs'; ?>
                <select name="unit" class="form-control" required>
                    <option value="" disabled>-- Select Unit --</option>
                    <?php
                    $unit_options = [
                        'pcs' => 'pcs (Pieces)',
                        'boxes' => 'boxes',
                        'packs' => 'packs',
                        'bottles' => 'bottles',
                        'cans' => 'cans',
                        'rolls' => 'rolls',
                        'sets' => 'sets',
                        'pairs' => 'pairs',
                        'cases' => 'cases',
                        'bundles' => 'bundles',
                        'pads' => 'pads',
                        'sacks' => 'sacks',
                        'ream' => 'ream',
                        'dozen' => 'dozen',
                        'kg' => 'kg (Kilograms)',
                        'g' => 'g (Grams)',
                        'liters' => 'liters',
                        'gallons' => 'gallons',
                        'meters' => 'meters',
                        'units' => 'units',
                    ];
                    $found = false;
                    foreach ($unit_options as $val => $label):
                        $sel = (strtolower(trim($current_unit)) === $val) ? 'selected' : '';
                        if ($sel) $found = true;
                    ?>
                        <option value="<?php echo $val; ?>" <?php echo $sel; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                    <?php if (!$found && $current_unit): ?>
                        <option value="<?php echo htmlspecialchars($current_unit); ?>" selected><?php echo htmlspecialchars($current_unit); ?></option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group form-grid-col">
                <label class="form-label">Unit Cost (₱) *</label>
                <input type="number" name="unit_cost" class="form-control" step="0.01" min="0" required value="<?php echo $item['unit_cost'] ?? 0; ?>">
            </div>
            <div class="form-group form-grid-col">
                <label class="form-label">Minimum Stock Level *</label>
                <input type="number" name="minimum_stock_level" class="form-control" min="1" required value="<?php echo $item['minimum_stock_level'] ?? 5; ?>">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group form-grid-col">
                <label class="form-label">Storage Location</label>
                <select name="storage_location_id" class="form-control">
                    <option value="">-- Select Storage Location --</option>
                    <?php $storage_locations->data_seek(0); while ($loc = $storage_locations->fetch_assoc()): ?>
                        <option value="<?php echo $loc['id']; ?>" <?php echo ($loc['id'] == $item['storage_location_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($loc['location_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group form-grid-col">
                <label class="form-label">Expiration Date (Optional)</label>
                <input type="date" name="expiration_date" class="form-control" value="<?php echo $item['expiration_date'] ?? ''; ?>">
                <small class="password-hint">Leave blank if item does not expire</small>
            </div>
        </div>

        <table class="inventory-table">
            <thead>
                <tr>
                    <th style="width:60px;">No.</th>
                    <th>Item Description</th>
                    <th style="width:120px;">No. of items<br>received</th>
                    <th style="width:120px;">No. of items<br>distributed</th>
                    <th style="width:120px;">No. of items<br>on hand</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="text-align:center;background:var(--gray-100);font-weight:600;">1</td>
                    <td><textarea name="item_description" required><?php echo htmlspecialchars($item['item_description']); ?></textarea></td>
                    <td class="readonly-field"><?php echo number_format($item['items_received']); ?></td>
                    <td class="readonly-field"><?php echo number_format($item['items_distributed']); ?></td>
                    <td class="readonly-field readonly-field-highlight"><?php echo number_format($item['items_on_hand']); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Update Item</button>
            <a href="view_item.php?id=<?php echo $item_id; ?>" class="btn btn-secondary btn-lg"><i class="fas fa-eye"></i> View Details</a>
            <a href="inventory.php" class="btn btn-secondary btn-lg"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </form>
</div>

<script>
// Add loading indicator to form submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            showFormLoading('Updating item...');
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>

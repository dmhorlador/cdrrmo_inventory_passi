<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Return Borrowed Items';

$success = '';
$error = '';

$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

$where = ["t.transaction_type = 'distributed'", "t.is_borrowed = 1"];
$params = [];
$types = "";

if ($search) {
    $where[] = "(t.transaction_code LIKE ? OR i.item_code LIKE ? OR i.item_description LIKE ? OR t.recipient_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

$where_clause = implode(' AND ', $where);

$query = "
    SELECT 
        t.id, t.transaction_code, t.transaction_date, t.quantity,
        t.recipient_name, t.recipient_organization, t.purpose,
        i.item_code, i.item_description, i.unit, c.category_name,
        COALESCE(
            (SELECT SUM(quantity) FROM transactions WHERE parent_transaction_id = t.id AND transaction_type = 'returned'),
            0
        ) as returned_quantity
    FROM transactions t
    JOIN inventory_items i ON t.item_id = i.id
    JOIN categories c ON i.category_id = c.id
    WHERE $where_clause
    ORDER BY t.transaction_date DESC
";

if ($types) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $borrowed_items = $stmt->get_result();
} else {
    $borrowed_items = $conn->query($query);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_id = (int)$_POST['transaction_id'];
    $return_quantity = (int)$_POST['return_quantity'];
    $return_date = sanitize_input($_POST['return_date']);
    $return_condition = sanitize_input($_POST['return_condition']);
    $return_notes = sanitize_input($_POST['return_notes']);
    $assessment_status = isset($_POST['assessment_status']) ? sanitize_input($_POST['assessment_status']) : null;
    $assessment_notes = isset($_POST['assessment_notes']) ? sanitize_input($_POST['assessment_notes']) : '';

    $check_stmt = $conn->prepare("
        SELECT t.*, i.unit_cost, i.id as item_id,
            COALESCE(
                (SELECT SUM(quantity) FROM transactions WHERE parent_transaction_id = t.id AND transaction_type = 'returned'),
                0
            ) as already_returned
        FROM transactions t
        JOIN inventory_items i ON t.item_id = i.id
        WHERE t.id = ? AND t.transaction_type = 'distributed' AND t.is_borrowed = 1
    ");
    $check_stmt->bind_param("i", $transaction_id);
    $check_stmt->execute();
    $original = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if (!$original) {
        $error = "Original transaction not found or not borrowable";
    } else {
        $remaining = $original['quantity'] - $original['already_returned'];
        if ($return_quantity <= 0) {
            $error = "Return quantity must be greater than 0";
        } elseif ($return_quantity > $remaining) {
            $error = "Cannot return more than borrowed. Remaining: $remaining";
        } else {
            // VALIDATION: Assessment is MANDATORY for Damaged/Fair items
            if (($return_condition === 'Damaged' || $return_condition === 'Fair') && !$assessment_status) {
                $error = "Assessment Status is required for items returned in Damaged or Fair condition";
            } elseif (!$assessment_status && $return_condition !== 'Good') {
                $error = "Assessment Status is required for items not in Good condition";
            } else {
                $year = date('Y', strtotime($return_date));
                $count_result = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE YEAR(transaction_date) = $year");
                $count = $count_result->fetch_assoc()['count'] + 1;
                $return_code = "RET-$year-" . str_pad($count, 4, '0', STR_PAD_LEFT);

                $stmt = $conn->prepare("
                    INSERT INTO transactions (
                        transaction_code, item_id, transaction_type, quantity, unit_cost,
                        transaction_date, parent_transaction_id, return_condition, notes, created_by
                    ) VALUES (?, ?, 'returned', ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("siidsissi",
                    $return_code, $original['item_id'], $return_quantity, $original['unit_cost'],
                    $return_date, $transaction_id, $return_condition, $return_notes, $_SESSION['user_id']
                );

            if ($stmt->execute()) {
                $update_stmt = $conn->prepare("
                    UPDATE inventory_items 
                    SET items_on_hand = items_on_hand + ?, items_distributed = items_distributed - ?, updated_by = ?
                    WHERE id = ?
                ");
                $update_stmt->bind_param("iiii", $return_quantity, $return_quantity, $_SESSION['user_id'], $original['item_id']);
                $update_stmt->execute();
                $update_stmt->close();

                // ── RETURNED ITEM STATUS TRACKING ───────────────────────────────
                // Simplified workflow: Available (Good) → Serviceable/Unserviceable (Damaged/Fair with assessment)
                $new_status = 'Available'; // Default if condition is Good
                $status_reason = '';
                
                if ($return_condition === 'Good') {
                    $new_status = 'Available';
                    $status_reason = "Item returned in good condition - available for reuse";
                } else {
                    // Fair or Damaged - assessment is mandatory (validated above)
                    if ($assessment_status === 'Serviceable') {
                        $new_status = 'Serviceable';
                        $status_reason = "Item returned in $return_condition condition, assessed as serviceable - repaired and ready for reuse";
                    } elseif ($assessment_status === 'Unserviceable') {
                        $new_status = 'Unserviceable';
                        $status_reason = "Item returned in $return_condition condition, assessed as unserviceable - beyond repair and flagged for disposal";
                        
                        // Decrease inventory for unserviceable items
                        $decrease_stmt = $conn->prepare("
                            UPDATE inventory_items 
                            SET items_on_hand = items_on_hand - ?, updated_by = ?
                            WHERE id = ?
                        ");
                        $decrease_stmt->bind_param("iii", $return_quantity, $_SESSION['user_id'], $original['item_id']);
                        $decrease_stmt->execute();
                        $decrease_stmt->close();
                    }
                }

                // Get current status before update
                $current_status_result = $conn->query("SELECT item_status FROM inventory_items WHERE id = {$original['item_id']}");
                $current_status_row = $current_status_result->fetch_assoc();
                $previous_status = $current_status_row['item_status'] ?? 'In-Use';

                $status_notes = "Returned by: {$original['recipient_name']}" . 
                                ($original['recipient_organization'] ? " ({$original['recipient_organization']})" : "");
                
                if ($assessment_notes) {
                    $status_notes .= "\n[Assessment Notes]: $assessment_notes";
                }

                $transaction_id = $stmt->insert_id;
                $conn->query("CALL log_item_status_change(
                    {$original['item_id']},
                    $transaction_id,
                    '$previous_status',
                    '$new_status',
                    '$status_reason',
                    '$status_notes',
                    '$return_condition',
                    '" . addslashes($assessment_notes) . "',
                    {$_SESSION['user_id']}
                )");
                // ── END RETURNED ITEM STATUS TRACKING ───────────────────────────

                log_activity($_SESSION['user_id'], 'return_items', "Returned $return_quantity items - Transaction: $return_code");
                $_SESSION['success'] = "Items returned successfully! Transaction Code: $return_code";
                header('Location: return_items.php');
                exit();
            } else {
                $error = "Failed to record return transaction.";
            }
            $stmt->close();
        }
    }
}
}

require_once 'includes/header.php';
?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="return-card-body">
        <h2 class="return-card-title"><i class="fas fa-undo-alt"></i> Return Borrowed Items</h2>
        <p class="return-card-desc">Record the return of borrowed inventory items.</p>

        <div class="return-search-wrap">
            <form method="GET" action="" class="return-search-form">
                <div class="return-search-input-wrap">
                    <i class="fas fa-search return-search-icon"></i>
                    <input type="text" name="search" class="form-control return-search-input-pad"
                        placeholder="Search by transaction code, item, or borrower name..."
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                <?php if ($search): ?>
                    <a href="return_items.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
            <?php if ($search): ?>
                <p class="return-filter-label"><i class="fas fa-filter"></i> Searching for: <strong><?php echo htmlspecialchars($search); ?></strong></p>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="return-table-wrap">
            <table class="return-table">
                <thead>
                    <tr>
                        <th>Transaction Code</th>
                        <th>Item</th>
                        <th>Borrower</th>
                        <th class="center">Borrowed</th>
                        <th class="center">Returned</th>
                        <th class="center">Remaining</th>
                        <th class="center">Date</th>
                        <th class="center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $borrowed_items->data_seek(0);
                    $has_items = false;
                    $total_count = 0;
                    while ($item = $borrowed_items->fetch_assoc()):
                        $remaining = $item['quantity'] - $item['returned_quantity'];
                        if ($remaining <= 0) continue;
                        $has_items = true;
                        $total_count++;
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($item['transaction_code']); ?></strong></td>
                            <td>
                                <div class="return-item-code"><?php echo htmlspecialchars($item['item_code']); ?></div>
                                <div class="return-item-desc"><?php echo htmlspecialchars($item['item_description']); ?></div>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($item['recipient_name']); ?>
                                <?php if ($item['recipient_organization']): ?>
                                    <br><small class="return-borrower-org"><?php echo htmlspecialchars($item['recipient_organization']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="center"><strong><?php echo number_format($item['quantity']); ?></strong> <?php echo $item['unit']; ?></td>
                            <td class="center"><strong class="return-qty-returned"><?php echo number_format($item['returned_quantity']); ?></strong> <?php echo $item['unit']; ?></td>
                            <td class="center"><strong class="return-qty-remaining"><?php echo number_format($remaining); ?></strong> <?php echo $item['unit']; ?></td>
                            <td class="center"><?php echo date('M d, Y', strtotime($item['transaction_date'])); ?></td>
                            <td class="center">
                                <button onclick="openReturnModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['item_description'])); ?>', <?php echo $remaining; ?>, '<?php echo $item['unit']; ?>')"
                                    class="btn btn-primary btn-sm"><i class="fas fa-undo"></i> Return</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if (!$has_items): ?>
                        <tr>
                            <td colspan="8" class="return-empty-cell">
                                <?php if ($search): ?>
                                    <i class="fas fa-search return-empty-icon"></i>
                                    <p style="margin:0;">No borrowed items found matching "<?php echo htmlspecialchars($search); ?>"</p>
                                    <a href="return_items.php" class="return-empty-clear">Clear search</a>
                                <?php else: ?>
                                    <i class="fas fa-inbox return-empty-icon"></i>
                                    <p style="margin:0;">No borrowed items pending return</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($has_items): ?>
                <div class="return-summary"><strong>Total Items Pending Return:</strong> <?php echo $total_count; ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Return Modal -->
<div id="returnModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-undo-alt"></i> Return Borrowed Items</h3>
            <button class="modal-close" onclick="closeReturnModal()">&times;</button>
        </div>
        <form method="POST" id="returnForm">
            <div class="modal-body">
                <input type="hidden" name="transaction_id" id="return_transaction_id">
                <div class="form-group">
                    <label class="form-label">Item</label>
                    <div class="return-modal-item-display"><span id="return_item_name"></span></div>
                </div>
                <div class="return-modal-grid">
                    <div class="form-group">
                        <label class="form-label">Quantity to Return *</label>
                        <input type="number" name="return_quantity" id="return_quantity" class="form-control" min="1" required>
                        <small class="return-max-hint">Maximum: <strong id="max_return">0</strong></small>
                        <small class="return-max-hint" id="return_qty_auto_note" style="display:none; color: var(--primary, #2563eb);">Only 1 item was borrowed, so quantity is set automatically.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Return Date *</label>
                        <input type="date" name="return_date" class="form-control" required
                            value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Condition *</label>
                    <select name="return_condition" class="form-control" onchange="updateAssessmentRequirement()" required>
                        <option value="">-- Select condition --</option>
                        <option value="Good">Good - No damage</option>
                        <option value="Fair">Fair - Minor wear and tear</option>
                        <option value="Damaged">Damaged - Needs repair/replacement</option>
                    </select>
                </div>
                <div class="form-group" id="assessmentGroup" style="display: none;">
                    <label class="form-label">Assessment Status <span id="assessmentRequired" style="color: #ef4444;">*</span></label>
                    <select name="assessment_status" id="assessment_status" class="form-control" onchange="toggleAssessmentNotes()">
                        <option value="">-- Select assessment --</option>
                        <option value="Serviceable">✓ Serviceable - Repaired/good condition, ready for reuse</option>
                        <option value="Unserviceable">✗ Unserviceable - Beyond repair, unsafe, or obsolete</option>
                    </select>
                    <small class="password-hint" style="color: #ef4444;">
                        Note: Unserviceable items will be removed from inventory
                    </small>
                </div>
                <div class="form-group" id="assessmentNotesGroup" style="display: none;">
                    <label class="form-label">Assessment Notes</label>
                    <textarea name="assessment_notes" class="form-control" rows="2" placeholder="Document findings from your assessment..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Return Notes</label>
                    <textarea name="return_notes" class="form-control" rows="3" placeholder="Any notes about the returned items"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Record Return</button>
                <button type="button" class="btn btn-secondary" onclick="closeReturnModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openReturnModal(transactionId, itemName, maxQuantity, unit) {
    document.getElementById('return_transaction_id').value = transactionId;
    document.getElementById('return_item_name').textContent = itemName;

    const qtyInput = document.getElementById('return_quantity');
    const autoNote = document.getElementById('return_qty_auto_note');
    qtyInput.max = maxQuantity;
    document.getElementById('max_return').textContent = maxQuantity + ' ' + unit;

    if (maxQuantity == 1) {
        // Only one item was borrowed - set quantity automatically, no need for the user to pick it
        qtyInput.value = 1;
        qtyInput.readOnly = true;
        qtyInput.classList.add('return-qty-locked');
        autoNote.style.display = 'block';
    } else {
        qtyInput.value = '';
        qtyInput.readOnly = false;
        qtyInput.classList.remove('return-qty-locked');
        autoNote.style.display = 'none';
    }

    const modal = document.getElementById('returnModal');
    modal.classList.add('show');
    modal.style.display = 'flex';
}
function closeReturnModal() {
    const modal = document.getElementById('returnModal');
    modal.classList.remove('show');
    setTimeout(() => { 
        modal.style.display = 'none'; 
        document.getElementById('returnForm').reset(); 
        document.getElementById('assessmentGroup').style.display = 'none';
        document.getElementById('assessmentNotesGroup').style.display = 'none';
        document.getElementById('return_quantity').readOnly = false;
        document.getElementById('return_quantity').classList.remove('return-qty-locked');
        document.getElementById('return_qty_auto_note').style.display = 'none';
    }, 200);
}

function updateAssessmentRequirement() {
    const condition = document.querySelector('select[name="return_condition"]').value;
    const assessmentGroup = document.getElementById('assessmentGroup');
    const assessmentField = document.getElementById('assessment_status');
    
    if (condition === 'Good') {
        // Good condition - assessment not required
        assessmentGroup.style.display = 'none';
        assessmentField.removeAttribute('required');
        assessmentField.value = '';
        document.getElementById('assessmentNotesGroup').style.display = 'none';
    } else if (condition === 'Fair' || condition === 'Damaged') {
        // Fair or Damaged - assessment IS required
        assessmentGroup.style.display = 'block';
        assessmentField.setAttribute('required', 'required');
    }
}

function toggleAssessmentNotes() {
    const assessmentStatus = document.querySelector('select[name="assessment_status"]').value;
    const notesGroup = document.getElementById('assessmentNotesGroup');
    if (assessmentStatus) {
        notesGroup.style.display = 'block';
    } else {
        notesGroup.style.display = 'none';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
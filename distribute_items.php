<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Distribute Items';

$success = '';
$error = '';

// Get items with available stock
$items = $conn->query("
    SELECT i.id, i.item_code, i.item_description, c.category_name, i.unit, i.items_on_hand
    FROM inventory_items i
    JOIN categories c ON i.category_id = c.id
    WHERE i.is_active = 1 AND i.items_on_hand > 0
    ORDER BY i.item_code
");

// Pre-select item if coming from item page
$selected_item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = (int)$_POST['item_id'];
    $quantity = (int)$_POST['quantity'];
    $transaction_date = sanitize_input($_POST['transaction_date']);
    $recipient_name = sanitize_input($_POST['recipient_name']);
    $recipient_organization = sanitize_input($_POST['recipient_organization']);
    $purpose = sanitize_input($_POST['purpose']);
    $reference_number = sanitize_input($_POST['reference_number']);
    $notes = sanitize_input($_POST['notes']);
    $is_borrowed = isset($_POST['is_borrowed']) ? 1 : 0;
    
    // Check available stock
    $stock_check = $conn->prepare("SELECT items_on_hand, unit_cost FROM inventory_items WHERE id = ?");
    $stock_check->bind_param("i", $item_id);
    $stock_check->execute();
    $stock_result = $stock_check->get_result();
    $item_data = $stock_result->fetch_assoc();
    $stock_check->close();
    
    if ($quantity <= 0) {
        $error = "Quantity must be greater than 0";
    } elseif ($quantity > $item_data['items_on_hand']) {
        $error = "Insufficient stock. Available: " . $item_data['items_on_hand'];
    } else {
        // Generate transaction code
        $year = date('Y', strtotime($transaction_date));
        $count_result = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE YEAR(transaction_date) = $year");
        $count = $count_result->fetch_assoc()['count'] + 1;
        $transaction_code = "DIS-$year-" . str_pad($count, 4, '0', STR_PAD_LEFT);
        
        $unit_cost = $item_data['unit_cost'];
        
        // Insert distribution transaction
        $stmt = $conn->prepare("
            INSERT INTO transactions (
                transaction_code, item_id, transaction_type, quantity, unit_cost,
                transaction_date, recipient_name, recipient_organization, purpose,
                reference_number, notes, is_borrowed, created_by
            ) VALUES (?, ?, 'distributed', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "siidssssssii",
            $transaction_code, $item_id, $quantity, $unit_cost, $transaction_date,
            $recipient_name, $recipient_organization, $purpose, $reference_number,
            $notes, $is_borrowed, $_SESSION['user_id']
        );
        
        if ($stmt->execute()) {
            $transaction_id = $conn->insert_id;

            // ── FIFO BATCH DEDUCTION ──────────────────────────────────────────
            // Fetch batches for this item ordered by earliest expiration first (FEFO).
            // Batches without expiration date come last.
            $batches_result = $conn->prepare("
                SELECT id, quantity_on_hand
                FROM item_batches
                WHERE item_id = ? AND quantity_on_hand > 0
                ORDER BY
                    CASE WHEN expiration_date IS NULL THEN 1 ELSE 0 END ASC,
                    expiration_date ASC,
                    received_date ASC
            ");
            $batches_result->bind_param("i", $item_id);
            $batches_result->execute();
            $batches = $batches_result->get_result();
            $batches_result->close();

            $remaining_to_deduct = $quantity;

            $deduct_batch_stmt = $conn->prepare(
                "UPDATE item_batches SET quantity_on_hand = quantity_on_hand - ? WHERE id = ?"
            );
            $record_dist_stmt = $conn->prepare(
                "INSERT INTO distribution_batches (transaction_id, batch_id, quantity_taken) VALUES (?, ?, ?)"
            );

            while ($batch = $batches->fetch_assoc()) {
                if ($remaining_to_deduct <= 0) break;

                $take = min($remaining_to_deduct, $batch['quantity_on_hand']);

                // Deduct from this batch
                $deduct_batch_stmt->bind_param("ii", $take, $batch['id']);
                $deduct_batch_stmt->execute();

                // Record which batch was used
                $record_dist_stmt->bind_param("iii", $transaction_id, $batch['id'], $take);
                $record_dist_stmt->execute();

                $remaining_to_deduct -= $take;
            }

            $deduct_batch_stmt->close();
            $record_dist_stmt->close();
            // ── END FIFO BATCH DEDUCTION ──────────────────────────────────────

            // Update inventory totals
            $update_stmt = $conn->prepare("
                UPDATE inventory_items 
                SET items_distributed = items_distributed + ?,
                    items_on_hand = items_on_hand - ?,
                    updated_by = ?
                WHERE id = ?
            ");
            $update_stmt->bind_param("iiii", $quantity, $quantity, $_SESSION['user_id'], $item_id);
            $update_stmt->execute();
            $update_stmt->close();

            // ── BORROWED ITEM STATUS TRACKING ────────────────────────────────
            if ($is_borrowed) {
                // Mark item as "In-Use" if borrowed
                $current_status_result = $conn->query("SELECT item_status FROM inventory_items WHERE id = $item_id");
                $current_status_row = $current_status_result->fetch_assoc();
                $previous_status = $current_status_row['item_status'];

                $status_reason = "Item borrowed and in use";
                $status_notes = "Borrowed by: $recipient_name" . ($recipient_organization ? " ($recipient_organization)" : "");

                $conn->query("CALL log_item_status_change(
                    $item_id,
                    $transaction_id,
                    '$previous_status',
                    'In-Use',
                    '$status_reason',
                    '$status_notes',
                    NULL,
                    NULL,
                    {$_SESSION['user_id']}
                )");
            }
            // ── END BORROWED ITEM STATUS TRACKING ────────────────────────────
            
            log_activity($_SESSION['user_id'], 'distribute_items', "Distributed $quantity items - Transaction: $transaction_code");
            
            $_SESSION['success'] = "Items distributed successfully! Transaction Code: $transaction_code";
            header('Location: distribute_items.php');
            exit();
        } else {
            $error = "Failed to record transaction.";
        }
        $stmt->close();
    }
}

require_once 'includes/header.php';
?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<div class="card txn-card-wrap">
    <div class="txn-card-body">
        <h2 class="txn-card-title">
            <i class="fas fa-minus-circle"></i> Distribute Items
        </h2>
        
        <p class="txn-card-desc">
            Record outgoing inventory items distributed to beneficiaries or other departments.
        </p>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="distributeForm">
            <div class="form-group">
                <label class="form-label">Select Item *</label>
                <div class="txn-autocomplete-wrap" id="itemComboWrapper">
                    <i class="fas fa-search" style="position:absolute; left:2px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:13px; pointer-events:none; z-index:1;"></i>
                    <input
                        type="text"
                        id="itemSearch"
                        class="form-control"
                        placeholder="Type item code or name to search..."
                        autocomplete="off"
                        class="item-search-padded"
                        required
                    >
                    <i class="fas fa-times" id="itemClearBtn" onclick="clearItemSearch()" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:13px; cursor:pointer; display:none;"></i>
                    <input type="hidden" name="item_id" id="item_id" required>
                    <!-- Suggestions dropdown -->
                    <ul id="itemDropdown" style="
                        display:none;
                        position:absolute;
                        top:calc(100% + 4px);
                        left:0; right:0;
                        background:#fff;
                        border:1px solid #e5e7eb;
                        border-radius:8px;
                        box-shadow:0 8px 24px rgba(0,0,0,0.12);
                        list-style:none;
                        margin:0; padding:4px 0;
                        z-index:9999;
                        max-height:260px;
                        overflow-y:auto;
                    "></ul>
                </div>
            </div>

            
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Quantity to Distribute *</label>
                    <input type="number" name="quantity" id="quantity" class="form-control" min="1" required 
                        placeholder="Enter quantity">
                    <small class="password-hint">
                        Available: <strong id="availableStock">-</strong>
                    </small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Unit</label>
                    <div class="return-modal-item-display">
                        <span id="unitText">-</span>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Transaction Date *</label>
                <input type="date" name="transaction_date" class="form-control" required 
                    value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="info-box" style="margin:20px 0;">
                <h3 class="txn-card-title">
                    <i class="fas fa-user"></i> Recipient Information
                </h3>
                
                <div class="form-group">
                    <label class="form-label">Recipient Name *</label>
                    <input type="text" name="recipient_name" class="form-control" required 
                        placeholder="Name of person receiving items">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Organization/Department</label>
                    <input type="text" name="recipient_organization" class="form-control" 
                        placeholder="Organization, department, or barangay">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Purpose of Distribution *</label>
                    <textarea name="purpose" class="form-control" rows="2" required 
                        placeholder="Reason or purpose for distributing these items"></textarea>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Requisition and Issue Slip (RIS) No. *</label>
                <input type="text" name="reference_number" class="form-control" required
                    placeholder="Distribution slip or reference number">
            </div>
            
            <div class="form-group">
                <label class="checkbox-label-row">
                    <input type="checkbox" name="is_borrowed" id="is_borrowed" value="1" class="checkbox-icon">
                    <span class="checkbox-label-text">
                        <i class="fas fa-exchange-alt"></i> This is a borrowed item (will be returned later)
                    </span>
                </label>
                <small class="checkbox-hint">
                    Check this box if the item will be returned. This allows tracking of borrowed items.
                </small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Additional Notes</label>
                <textarea name="notes" class="form-control" rows="3" 
                    placeholder="Additional notes or comments about this distribution"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Distribute Items
                </button>
                <button type="reset" class="btn btn-secondary" onclick="resetForm()">
                    <i class="fas fa-redo"></i> Reset Form
                </button>
                <a href="inventory.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// ── Item data from PHP ─────────────────────────────────────────
const ITEMS = [
<?php 
$items->data_seek(0);
while ($item = $items->fetch_assoc()):
    $label = $item['item_code'] . ' - ' . substr($item['item_description'], 0, 50) . ' (Stock: ' . $item['items_on_hand'] . ' ' . $item['unit'] . ')';
?>
  { id: <?php echo (int)$item['id']; ?>,
    label: <?php echo json_encode($label); ?>,
    unit: <?php echo json_encode($item['unit'] ?? 'pcs'); ?>,
    stock: <?php echo (int)$item['items_on_hand']; ?>,
    search: <?php echo json_encode(strtolower($item['item_code'] . ' ' . $item['item_description'] . ' ' . $item['category_name'])); ?> },
<?php endwhile; ?>
];

// ── Combobox logic ─────────────────────────────────────────────
(function(){
    const input   = document.getElementById('itemSearch');
    const hidden  = document.getElementById('item_id');
    const dropdown= document.getElementById('itemDropdown');
    const clearBtn= document.getElementById('itemClearBtn');
    let activeIdx = -1;
    let filtered  = [];

    // Pre-select if coming from item page
    <?php if ($selected_item_id): ?>
    const preItem = ITEMS.find(i => i.id === <?php echo (int)$selected_item_id; ?>);
    if (preItem) selectItem(preItem);
    <?php endif; ?>

    function esc(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function highlight(text, q){
        if(!q) return esc(text);
        const i = text.toLowerCase().indexOf(q.toLowerCase());
        if(i === -1) return esc(text);
        return esc(text.slice(0,i)) + '<mark class="highlight-match">' + esc(text.slice(i,i+q.length)) + '</mark>' + esc(text.slice(i+q.length));
    }

    function openDropdown(q){
        filtered = q ? ITEMS.filter(it => it.search.includes(q.toLowerCase())) : ITEMS;
        activeIdx = -1;
        dropdown.innerHTML = '';
        if(filtered.length === 0){
            dropdown.innerHTML = '<li class="item-dropdown-empty">No items found.</li>';
        } else {
            filtered.forEach(function(it, i){
                const li = document.createElement('li');
                li.style.cssText = 'padding:9px 14px;cursor:pointer;font-size:13.5px;border-bottom:1px solid #f3f4f6;transition:background 0.1s;';
                li.innerHTML = highlight(it.label, q);
                li.addEventListener('mouseenter', ()=> setActive(i));
                li.addEventListener('mousedown', (e)=>{ e.preventDefault(); selectItem(it); });
                dropdown.appendChild(li);
            });
        }
        dropdown.style.display = 'block';
    }

    function setActive(i){
        const lis = dropdown.querySelectorAll('li');
        lis.forEach(l => l.style.background = '');
        activeIdx = i;
        if(i >= 0 && lis[i]) { lis[i].style.background = '#f0f9ff'; lis[i].scrollIntoView({block:'nearest'}); }
    }

    function closeDropdown(){ dropdown.style.display = 'none'; activeIdx = -1; }

    function selectItem(it){
        input.value  = it.label;
        hidden.value = it.id;
        clearBtn.style.display = 'inline';
        closeDropdown();
        // Update stock/unit display
        document.getElementById('unitText').textContent = it.unit;
        document.getElementById('availableStock').textContent = it.stock.toLocaleString() + ' ' + it.unit;
        const quantityInput = document.getElementById('quantity');
        quantityInput.max = it.stock;
    }

    window.clearItemSearch = function(){
        input.value  = '';
        hidden.value = '';
        clearBtn.style.display = 'none';
        document.getElementById('unitText').textContent = '-';
        document.getElementById('availableStock').textContent = '-';
        document.getElementById('quantity').removeAttribute('max');
        input.focus();
    };

    input.addEventListener('input', function(){
        clearBtn.style.display = this.value ? 'inline' : 'none';
        hidden.value = '';
        openDropdown(this.value.trim());
    });
    input.addEventListener('focus', function(){
        if(!hidden.value) openDropdown(this.value.trim());
    });
    input.addEventListener('keydown', function(e){
        const lis = dropdown.querySelectorAll('li');
        if(dropdown.style.display === 'none'){
            if(e.key === 'ArrowDown'){ openDropdown(this.value.trim()); } return;
        }
        if(e.key === 'ArrowDown'){ e.preventDefault(); setActive(Math.min(activeIdx+1, lis.length-1)); }
        else if(e.key === 'ArrowUp'){ e.preventDefault(); setActive(Math.max(activeIdx-1, 0)); }
        else if(e.key === 'Enter' && activeIdx >= 0 && filtered[activeIdx]){
            e.preventDefault(); selectItem(filtered[activeIdx]);
        } else if(e.key === 'Escape'){ closeDropdown(); }
    });
    document.addEventListener('click', function(e){
        if(!input.contains(e.target) && !dropdown.contains(e.target)) closeDropdown();
    });
})();

function updateItemDetails(){} // kept for compatibility

function resetForm() {
    document.getElementById('distributeForm').reset();
    document.getElementById('unitText').textContent = '-';
    document.getElementById('availableStock').textContent = '-';
    document.getElementById('itemClearBtn').style.display = 'none';
}
</script>

<?php require_once 'includes/footer.php'; ?>
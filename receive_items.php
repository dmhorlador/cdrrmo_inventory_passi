<?php
require_once 'includes/config.php';
check_login();

$page_title = 'Receive Items';

$success = '';
$error = '';

// Get items
$items = $conn->query("
    SELECT i.id, i.item_code, i.item_description, c.category_name, i.unit, i.unit_cost
    FROM inventory_items i
    JOIN categories c ON i.category_id = c.id
    WHERE i.is_active = 1
    ORDER BY i.item_code
");

// Get suppliers
$suppliers = $conn->query("SELECT id, supplier_name FROM suppliers WHERE is_active = 1 ORDER BY supplier_name");

// Pre-select item if coming from item page
$selected_item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = (int)$_POST['item_id'];
    $quantity = (int)$_POST['quantity'];
    $unit_cost = isset($_POST['unit_cost']) ? (float)$_POST['unit_cost'] : 0.00;
    $transaction_date = sanitize_input($_POST['transaction_date']);
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $reference_number = sanitize_input($_POST['reference_number']);
    $batch_number = sanitize_input($_POST['batch_number']);
    $expiration_date = !empty($_POST['expiration_date']) ? sanitize_input($_POST['expiration_date']) : null;
    $notes = sanitize_input($_POST['notes']);
    
    if ($quantity <= 0) {
        $error = "Quantity must be greater than 0";
    } else {
        // Generate transaction code
        $year = date('Y', strtotime($transaction_date));
        $count_result = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE YEAR(transaction_date) = $year");
        $count = $count_result->fetch_assoc()['count'] + 1;
        $transaction_code = "REC-$year-" . str_pad($count, 4, '0', STR_PAD_LEFT);
        
        // Insert transaction (total_cost is auto-generated)
        $stmt = $conn->prepare("
            INSERT INTO transactions (
                transaction_code, item_id, transaction_type, quantity, unit_cost,
                transaction_date, supplier_id, reference_number, batch_number,
                expiration_date, notes, created_by
            ) VALUES (?, ?, 'received', ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "siidssssssi",
            $transaction_code, $item_id, $quantity, $unit_cost,
            $transaction_date, $supplier_id, $reference_number, $batch_number, 
            $expiration_date, $notes, $_SESSION['user_id']
        );
        
        if ($stmt->execute()) {
            $transaction_id = $conn->insert_id;

            // ── BATCH RECORD: Insert a new row into item_batches ──────────────
            // Each receive creates a SEPARATE batch so expiration dates are preserved.
            $batch_stmt = $conn->prepare("
                INSERT INTO item_batches (
                    item_id, transaction_id, batch_number, ris_no,
                    quantity_received, quantity_on_hand, unit_cost,
                    expiration_date, received_date, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $batch_stmt->bind_param(
                "iissiidsss",
                $item_id, $transaction_id, $batch_number, $reference_number,
                $quantity, $quantity, $unit_cost,
                $expiration_date, $transaction_date, $notes
            );
            $batch_stmt->execute();
            $batch_stmt->close();
            // ── END BATCH RECORD ──────────────────────────────────────────────

            // Update inventory totals (unchanged — items table stays consistent)
            $update_stmt = $conn->prepare("
                UPDATE inventory_items 
                SET items_received = items_received + ?,
                    items_on_hand = items_on_hand + ?,
                    unit_cost = ?,
                    updated_by = ?
                WHERE id = ?
            ");
            $update_stmt->bind_param("iidii", $quantity, $quantity, $unit_cost, $_SESSION['user_id'], $item_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            log_activity($_SESSION['user_id'], 'receive_items', "Received $quantity items - Transaction: $transaction_code");
            
            $_SESSION['success'] = "Items received successfully! Transaction Code: $transaction_code";
            header('Location: receive_items.php');
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
            <i class="fas fa-plus-circle"></i> Receive Items
        </h2>
        
        <p class="txn-card-desc">
            Record incoming inventory items from suppliers or other sources.
        </p>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="receiveForm">
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
                    <label class="form-label">Quantity Received *</label>
                    <input type="number" name="quantity" class="form-control" min="1" required 
                        placeholder="Enter quantity" onchange="calculateTotal()">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Unit <span id="unitDisplay"></span></label>
                    <div class="return-modal-item-display">
                        <span id="unitText">-</span>
                    </div>
                </div>
                
                <!-- <div class="form-group">
                    <label class="form-label">Unit Cost (₱) *</label>
                    <input type="number" name="unit_cost" class="form-control" step="0.01" min="0" required 
                        placeholder="0.00" onchange="calculateTotal()">
                </div>
                 -->
                <div class="form-group">
                    <label class="form-label">Total Cost (₱)</label>
                    <div style="background: var(--primary-50); padding: 12px 15px; border-radius: 6px; font-weight: 700; color: var(--primary); font-size: 18px;">
                        ₱<span id="totalCost">0.00</span>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Transaction Date *</label>
                <input type="date" name="transaction_date" class="form-control" required 
                    value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <!-- <div class="form-group">
                <label class="form-label">Supplier</label>
                <select name="supplier_id" class="form-control">
                    <option value="">-- Select supplier (optional) --</option>
                    <?php 
                    $suppliers->data_seek(0);
                    while ($supplier = $suppliers->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $supplier['id']; ?>">
                            <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div> -->
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">RIS No. *</label>
                    <input type="text" name="reference_number" class="form-control" required 
                        placeholder="PO/DR/Invoice number">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Batch Number *</label>
                    <input type="text" name="batch_number" class="form-control" required
                        placeholder="Batch/Lot number">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Expiration Date</label>
                    <input type="date" name="expiration_date" class="form-control">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="3" 
                    placeholder="Additional notes or comments about this transaction"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check"></i> Receive Items
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
    $label = $item['item_code'] . ' - ' . substr($item['item_description'], 0, 60) . ' (' . $item['category_name'] . ')';
?>
  { id: <?php echo (int)$item['id']; ?>,
    label: <?php echo json_encode($label); ?>,
    unit: <?php echo json_encode($item['unit'] ?? 'pcs'); ?>,
    unitCost: <?php echo (float)$item['unit_cost']; ?>,
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
        // Trigger details update
        document.getElementById('unitText').textContent = it.unit;
        const unitCostInput = document.querySelector('input[name="unit_cost"]');
        unitCostInput.value = parseFloat(it.unitCost).toFixed(2);
        calculateTotal();
    }

    window.clearItemSearch = function(){
        input.value  = '';
        hidden.value = '';
        clearBtn.style.display = 'none';
        document.getElementById('unitText').textContent = '-';
        document.querySelector('input[name="unit_cost"]').value = '';
        document.getElementById('totalCost').textContent = '0.00';
        input.focus();
    };

    input.addEventListener('input', function(){
        clearBtn.style.display = this.value ? 'inline' : 'none';
        hidden.value = ''; // clear selection when user edits
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

function updateItemDetails(){} // kept for compatibility (no longer used)

function calculateTotal() {
    const quantity = parseFloat(document.querySelector('input[name="quantity"]').value) || 0;
    const unitCost = parseFloat(document.querySelector('input[name="unit_cost"]').value) || 0;
    document.getElementById('totalCost').textContent = (quantity * unitCost).toFixed(2);
}

function resetForm() {
    document.getElementById('receiveForm').reset();
    document.getElementById('unitText').textContent = '-';
    document.getElementById('totalCost').textContent = '0.00';
    document.getElementById('itemClearBtn').style.display = 'none';
}

// Add loading indicator to form submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('receiveForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                showFormLoading('Processing item receipt...');
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
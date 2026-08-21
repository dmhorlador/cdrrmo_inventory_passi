<?php
require_once 'includes/config.php';
check_login(); // Both admin and staff can delete

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Invalid item ID";
    header('Location: inventory.php');
    exit();
}

$item_id = (int)$_GET['id'];

// Get item data before deletion for logging
$stmt = $conn->prepare("
    SELECT i.*, c.category_name 
    FROM inventory_items i
    JOIN categories c ON i.category_id = c.id
    WHERE i.id = ?
");
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

// Perform soft delete (set is_active = 0) to preserve data
$stmt = $conn->prepare("UPDATE inventory_items SET is_active = 0, updated_by = ? WHERE id = ?");
$stmt->bind_param("ii", $_SESSION['user_id'], $item_id);

if ($stmt->execute()) {
    // Log the deletion with full details
    $log_details = sprintf(
        "Deleted item: %s - %s (Category: %s, Stock: %d)",
        $item['item_code'],
        $item['item_description'],
        $item['category_name'],
        $item['items_on_hand']
    );
    
    log_activity($_SESSION['user_id'], 'delete_item', $log_details);
    
    $_SESSION['success'] = "Item deleted successfully!";
} else {
    $_SESSION['error'] = "Failed to delete item. Please try again.";
}

$stmt->close();

// Preserve search/filter parameters
$redirect = 'inventory.php';
if (isset($_GET['search'])) $redirect .= '?search=' . urlencode($_GET['search']);
if (isset($_GET['category'])) $redirect .= (strpos($redirect, '?') ? '&' : '?') . 'category=' . urlencode($_GET['category']);
if (isset($_GET['status'])) $redirect .= (strpos($redirect, '?') ? '&' : '?') . 'status=' . urlencode($_GET['status']);

header('Location: ' . $redirect);
exit();
?>
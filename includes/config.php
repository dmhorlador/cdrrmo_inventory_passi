<?php
// Database Configuration
define('DB_HOST', 'localhost');
// define('DB_HOST', '192.168.0.3');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cdrrmo_inventory');

// Site Configuration
define('SITE_NAME', 'CDRRMO Inventory System');
define('SITE_URL', 'http://localhost/cdrrmo_inventory_system');

// Link used by the "Open Other System" option in the user menu (top-right dropdown)
define('OTHER_SYSTEM_URL', 'https://example.com/replace-with-actual-link');

// File Upload Configuration
define('UPLOAD_DIR_ITEMS', __DIR__ . '/../uploads/items/');
define('UPLOAD_DIR_DOCS', __DIR__ . '/../uploads/documents/');
define('MAX_FILE_SIZE', 5242880); // 5MB

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS

ini_set('session.gc_maxlifetime', 3600); // 1 hour
ini_set('session.cookie_lifetime', 3600); // 1 hour
session_start();

// Database Connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Database connection error. Please contact the administrator.");
}

/**
 * Main audit logging function
 * Logs any action to the audit_log table
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User performing the action
 * @param string $action Type of action (create, update, delete, login, logout)
 * @param string $table_name Affected table name (optional)
 * @param int $record_id Affected record ID (optional)
 * @param array $old_values Old values before change (optional)
 * @param array $new_values New values after change (optional)
 * @return bool Success status
 */

// Timezone
date_default_timezone_set('Asia/Manila');

// Helper Functions

function log_audit($conn, $user_id, $action, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
    // Get IP address
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    
    // Convert IPv6 localhost to IPv4 for consistency
    if ($ip_address == '::1') {
        $ip_address = '127.0.0.1';
    }
    
    // Get user agent (browser info)
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    
    // Convert arrays to JSON format
    $old_json = $old_values ? json_encode($old_values, JSON_UNESCAPED_UNICODE) : null;
    $new_json = $new_values ? json_encode($new_values, JSON_UNESCAPED_UNICODE) : null;
    
    // Prepare values for SQL (escape to prevent SQL injection)
    $user_id = intval($user_id);
    $action = $conn->real_escape_string($action);
    $table_name = $table_name ? "'" . $conn->real_escape_string($table_name) . "'" : "NULL";
    $record_id = $record_id ? intval($record_id) : "NULL";
    $old_json = $old_json ? "'" . $conn->real_escape_string($old_json) . "'" : "NULL";
    $new_json = $new_json ? "'" . $conn->real_escape_string($new_json) . "'" : "NULL";
    $ip_address = $conn->real_escape_string($ip_address);
    $user_agent = $conn->real_escape_string($user_agent);
    
    // Build and execute SQL
    $sql = "INSERT INTO audit_log 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
            VALUES 
            ($user_id, '$action', $table_name, $record_id, $old_json, $new_json, '$ip_address', '$user_agent')";
    
    $result = $conn->query($sql);
    
    // Optional: Log errors for debugging
    if (!$result) {
        error_log("Audit log error: " . $conn->error);
    }
    
    return $result;
}
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

function generate_token() {
    return bin2hex(random_bytes(32));
}

function check_login() {
    // Prevent browser from caching protected pages
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        header('Location: login.php');
        exit();
    }
}

function check_admin() {
    check_login();
    if ($_SESSION['user_role'] !== 'admin') {
        header('Location: dashboard.php');
        exit();
    }
}

function log_logout($conn, $user_id) {
    return log_audit($conn, $user_id, 'logout', 'users', $user_id, null, [
        'logout_time' => date('Y-m-d H:i:s')
    ]);
}

function log_login($conn, $user_id) {
    return log_audit($conn, $user_id, 'login', 'users', $user_id, null, [
        'login_time' => date('Y-m-d H:i:s'),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'
    ]);
}

function log_password_change($conn, $user_id, $success = true) {
    $status = $success ? 'success' : 'failed';
    return log_audit($conn, $user_id, 'password_change', 'users', $user_id, null, [
        'status' => $status,
        'change_time' => date('Y-m-d H:i:s'),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'
    ]);
}

function log_activity($user_id, $action, $description) {
    global $conn;
    return log_audit($conn, $user_id, $action, null, null, null, [
        'details' => $description,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
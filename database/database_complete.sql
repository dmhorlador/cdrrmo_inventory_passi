-- ============================================================================
-- CDRRMO INVENTORY MANAGEMENT SYSTEM - COMPLETE DATABASE SCHEMA
-- Includes: Item Status Workflow Migration + Batch Tracking Migration
-- ============================================================================

-- Create database
CREATE DATABASE IF NOT EXISTS cdrrmo_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cdrrmo_inventory;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- 1. USERS TABLE - For authentication and user management
-- ============================================================================
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL COMMENT 'Hashed password using password_hash()',
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('admin', 'staff', 'viewer') DEFAULT 'staff' COMMENT 'admin: full access, staff: limited access, viewer: read-only',
    is_active TINYINT(1) DEFAULT 1 COMMENT '1=active, 0=inactive/disabled',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB COMMENT='System users and authentication';

-- ============================================================================
-- 2. USER SESSIONS TABLE - Track active sessions
-- ============================================================================
DROP TABLE IF EXISTS user_sessions;
CREATE TABLE user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    logout_time DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_session_token (session_token)
) ENGINE=InnoDB COMMENT='Track user login sessions';

-- ============================================================================
-- 3. CATEGORIES TABLE - Inventory categories
-- ============================================================================
DROP TABLE IF EXISTS categories;
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(50) NOT NULL UNIQUE,
    category_code VARCHAR(10) UNIQUE COMMENT 'Short code for category (e.g., OFC, MED, SRR, FOOD)',
    description TEXT,
    icon VARCHAR(50) COMMENT 'Icon identifier for UI',
    color VARCHAR(7) COMMENT 'Hex color code for category badge',
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category_name (category_name),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB COMMENT='Inventory categories';

-- ============================================================================
-- 4. SUPPLIERS TABLE - Track suppliers/sources
-- ============================================================================
DROP TABLE IF EXISTS suppliers;
CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supplier_name (supplier_name)
) ENGINE=InnoDB COMMENT='Suppliers and sources of inventory';

-- ============================================================================
-- 5. STORAGE LOCATIONS TABLE - Where items are stored
-- ============================================================================
DROP TABLE IF EXISTS storage_locations;
CREATE TABLE storage_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location_name VARCHAR(100) NOT NULL,
    location_code VARCHAR(20) UNIQUE,
    description TEXT,
    capacity INT COMMENT 'Maximum capacity if applicable',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_location_name (location_name)
) ENGINE=InnoDB COMMENT='Storage locations for inventory items';

-- ============================================================================
-- 6. INVENTORY ITEMS TABLE - Main inventory items
-- ============================================================================
DROP TABLE IF EXISTS inventory_items;
CREATE TABLE inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(50) UNIQUE COMMENT 'Unique item identifier/SKU',
    category_id INT NOT NULL,
    item_description TEXT NOT NULL,
    brand VARCHAR(100),
    model VARCHAR(100),
    specifications TEXT COMMENT 'Technical specifications or details',
    unit VARCHAR(50) COMMENT 'Unit of measurement (pcs, boxes, bottles, kg, etc.)',
    unit_cost DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Cost per unit',

    -- Stock tracking
    items_received INT DEFAULT 0 COMMENT 'Total items received (cumulative)',
    items_distributed INT DEFAULT 0 COMMENT 'Total items distributed (cumulative)',
    items_on_hand INT DEFAULT 0 COMMENT 'Current stock = received - distributed + adjustments',
    minimum_stock_level INT DEFAULT 5 COMMENT 'Alert when stock falls below this',
    maximum_stock_level INT DEFAULT NULL COMMENT 'Maximum stock capacity',
    reorder_point INT DEFAULT 10 COMMENT 'Trigger reorder when stock reaches this level',

    -- Additional tracking
    storage_location_id INT NULL,
    expiration_date DATE NULL COMMENT 'For perishable items',
    batch_number VARCHAR(50),
    serial_number VARCHAR(50),

    -- Status and metadata
    condition_status ENUM('new', 'good', 'fair', 'poor', 'damaged') DEFAULT 'new',
    item_status ENUM(
        'Available',
        'In-Use',
        'Pending Repair',
        'Serviceable',
        'Unserviceable'
    ) DEFAULT 'Available' COMMENT 'Current status of the item: Available, In-Use, Pending Repair, Serviceable, Unserviceable',
    is_active TINYINT(1) DEFAULT 1,
    notes TEXT,

    -- Image/Document
    image_path VARCHAR(255),
    document_path VARCHAR(255),

    -- Audit fields
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    updated_by INT,

    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (storage_location_id) REFERENCES storage_locations(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_item_code (item_code),
    INDEX idx_category_id (category_id),
    INDEX idx_items_on_hand (items_on_hand),
    INDEX idx_expiration_date (expiration_date),
    INDEX idx_is_active (is_active),
    INDEX idx_item_status (item_status),
    FULLTEXT idx_item_description (item_description)
) ENGINE=InnoDB COMMENT='Main inventory items table';

-- ============================================================================
-- 7. TRANSACTIONS TABLE - All inventory movements
-- ============================================================================
DROP TABLE IF EXISTS transactions;
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_code VARCHAR(50) UNIQUE COMMENT 'Unique transaction reference number',
    item_id INT NOT NULL,
    transaction_type ENUM('received', 'distributed', 'returned', 'adjustment', 'return', 'damaged', 'expired') NOT NULL,

    -- Quantity tracking
    quantity INT NOT NULL COMMENT 'Positive for additions, negative for deductions',
    unit_cost DECIMAL(10,2) DEFAULT 0.00,
    total_cost DECIMAL(10,2) AS (quantity * unit_cost) STORED,

    -- Transaction details
    transaction_date DATE NOT NULL,
    supplier_id INT NULL COMMENT 'For received items',
    recipient_name VARCHAR(100) COMMENT 'For distributed items',
    recipient_organization VARCHAR(100),
    purpose TEXT COMMENT 'Purpose of distribution or reason for adjustment',

    -- Reference documents
    reference_number VARCHAR(50) COMMENT 'PO number, DR number, etc.',
    document_path VARCHAR(255),

    -- Batch/Serial tracking
    batch_number VARCHAR(50),
    serial_number VARCHAR(50),
    expiration_date DATE,

    -- Additional info
    notes TEXT,
    approved_by INT COMMENT 'User who approved the transaction',
    approved_at DATETIME,

    -- Borrow/Return functionality
    is_borrowed TINYINT(1) DEFAULT 0 COMMENT 'Is this a borrowed item (1=yes, 0=no)',
    parent_transaction_id INT NULL COMMENT 'References original transaction for returns',
    return_condition VARCHAR(50) NULL COMMENT 'Condition of returned items (Good/Fair/Damaged)',
    status_change_id INT NULL COMMENT 'References the status history record',

    -- Audit fields
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,

    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,

    INDEX idx_transaction_code (transaction_code),
    INDEX idx_item_id (item_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_transaction_date (transaction_date),
    INDEX idx_supplier_id (supplier_id),
    INDEX idx_is_borrowed (is_borrowed),
    INDEX idx_parent_transaction (parent_transaction_id)
) ENGINE=InnoDB COMMENT='All inventory transactions and movements';

-- ============================================================================
-- 8. ITEM STATUS HISTORY TABLE - Audit trail for item status changes
-- ============================================================================
DROP TABLE IF EXISTS item_status_history;
CREATE TABLE item_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    transaction_id INT NULL COMMENT 'Reference to the transaction that caused the status change',
    previous_status ENUM(
        'Available',
        'In-Use',
        'Pending Repair',
        'Serviceable',
        'Unserviceable'
    ) COMMENT 'Status before the change',
    new_status ENUM(
        'Available',
        'In-Use',
        'Pending Repair',
        'Serviceable',
        'Unserviceable'
    ) NOT NULL COMMENT 'Status after the change',
    status_reason VARCHAR(255) COMMENT 'Reason for status change',
    notes TEXT COMMENT 'Additional notes about the status change',
    return_condition VARCHAR(50) COMMENT 'Condition upon return (Good/Fair/Damaged)',
    assessment_notes TEXT COMMENT 'Maintenance team assessment notes',
    created_by INT NOT NULL COMMENT 'User who made the status change',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,

    INDEX idx_item_id (item_id),
    INDEX idx_new_status (new_status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB COMMENT='Audit trail for item status changes';

-- Add FK from transactions to item_status_history (after both tables exist)
ALTER TABLE transactions
    ADD CONSTRAINT fk_transactions_status_change
    FOREIGN KEY (status_change_id) REFERENCES item_status_history(id) ON DELETE SET NULL;

-- ============================================================================
-- 9. STOCK ALERTS TABLE - Automated stock alerts
-- ============================================================================
DROP TABLE IF EXISTS stock_alerts;
CREATE TABLE stock_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    alert_type ENUM('low_stock', 'out_of_stock', 'expiring_soon', 'expired', 'overstocked') NOT NULL,
    alert_message TEXT,
    is_acknowledged TINYINT(1) DEFAULT 0,
    acknowledged_by INT NULL,
    acknowledged_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (acknowledged_by) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_item_id (item_id),
    INDEX idx_alert_type (alert_type),
    INDEX idx_is_acknowledged (is_acknowledged)
) ENGINE=InnoDB COMMENT='Stock alerts and notifications';

-- ============================================================================
-- 10. AUDIT LOG TABLE - Track all system changes
-- ============================================================================
DROP TABLE IF EXISTS audit_log;
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(50) COMMENT 'create, update, delete, login, logout, etc.',
    table_name VARCHAR(50),
    record_id INT,
    old_values TEXT COMMENT 'JSON of old values',
    new_values TEXT COMMENT 'JSON of new values',
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_table_name (table_name),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB COMMENT='Audit trail for all system actions';

-- ============================================================================
-- 11. SYSTEM SETTINGS TABLE - Application configuration
-- ============================================================================
DROP TABLE IF EXISTS system_settings;
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_editable TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,

    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB COMMENT='System configuration settings';

-- ============================================================================
-- 12. USER FEEDBACK TABLE - User feedback and support tickets
-- ============================================================================
DROP TABLE IF EXISTS user_feedback;
CREATE TABLE user_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    feedback_type ENUM('bug', 'suggestion', 'complaint', 'praise', 'other') DEFAULT 'suggestion',
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('pending', 'reviewing', 'resolved', 'closed') DEFAULT 'pending',
    admin_response TEXT NULL,
    responded_by INT NULL,
    responded_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (responded_by) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_feedback_type (feedback_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB COMMENT='User feedback and support tickets';

-- ============================================================================
-- 13. ITEM BATCHES TABLE - Batch-Based Expiration & Distribution Tracking
-- ============================================================================
CREATE TABLE IF NOT EXISTS item_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    transaction_id INT NULL COMMENT 'Links to the transactions table entry',
    batch_number VARCHAR(50),
    ris_no VARCHAR(50) COMMENT 'Reference/RIS number from receive transaction',
    quantity_received INT NOT NULL DEFAULT 0,
    quantity_on_hand INT NOT NULL DEFAULT 0 COMMENT 'Remaining after distributions',
    unit_cost DECIMAL(10,2) DEFAULT 0.00,
    expiration_date DATE NULL,
    received_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    INDEX idx_item_batches_item_id (item_id),
    INDEX idx_item_batches_expiration (expiration_date),
    INDEX idx_item_batches_qty (quantity_on_hand)
) ENGINE=InnoDB COMMENT='Stores individual receive batches for FIFO expiration tracking';

-- ============================================================================
-- 14. DISTRIBUTION BATCHES TABLE - Tracks which batches used per distribution
-- ============================================================================
CREATE TABLE IF NOT EXISTS distribution_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL COMMENT 'The distribution transaction',
    batch_id INT NOT NULL COMMENT 'The item_batch used',
    quantity_taken INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES item_batches(id) ON DELETE CASCADE,
    INDEX idx_dist_batches_txn (transaction_id),
    INDEX idx_dist_batches_batch (batch_id)
) ENGINE=InnoDB COMMENT='Records which batches were consumed in each distribution (FIFO)';

-- ============================================================================
-- INSERT DEFAULT DATA
-- ============================================================================

-- Insert default categories
INSERT INTO categories (category_name, category_code, description, icon, color, display_order) VALUES
('OFFICE', 'OFC', 'Office Supplies and Equipment', '📄', '#3498db', 1),
('MEDICAL', 'MED', 'Medical Supplies and Equipment', '🏥', '#e74c3c', 2),
('SRR', 'SRR', 'Search, Rescue and Relief Equipment', '🚨', '#f39c12', 3),
('FOOD', 'FOOD', 'Food and Beverage Supplies', '🍱', '#2ecc71', 4);

-- Insert default storage locations
INSERT INTO storage_locations (location_name, location_code, description) VALUES
('Office Storage', 'OFFICE', 'CDRRMO Office storage'),
('Gabaldon Storage', 'GABALDON', 'Gabaldon storage facility'),
('City Hall Storage', 'CITYHALL', 'City Hall storage area');

-- Insert default admin user (username: admin, password: admin123)
INSERT INTO users (username, password, full_name, email, role, is_active, created_by) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@cdrrmo.gov.ph', 'admin', 1, NULL),
('staff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CDRRMO Staff', 'staff@cdrrmo.gov.ph', 'staff', 1, 1);
-- Note: Both users have password 'admin123' for initial setup. CHANGE THESE IMMEDIATELY!

-- Insert sample suppliers
INSERT INTO suppliers (supplier_name, contact_person, phone, email, address) VALUES
('Medical Supplies Inc.', 'John Doe', '09123456789', 'john@medsupply.com', 'Iloilo City'),
('Office Depot Philippines', 'Jane Smith', '09198765432', 'jane@officedepot.ph', 'Manila'),
('Safety Equipment Corp.', 'Bob Johnson', '09187654321', 'bob@safetyequip.com', 'Cebu City'),
('Food Distributors Ltd.', 'Alice Brown', '09176543210', 'alice@fooddist.ph', 'Davao City');

-- Insert system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_editable) VALUES
('system_name', 'CDRRMO Inventory System', 'string', 'System name displayed in the application', 1),
('organization_name', 'Passi City CDRRMO', 'string', 'Organization name', 1),
('low_stock_threshold', '5', 'number', 'Default minimum stock level for alerts', 1),
('expiry_alert_days', '30', 'number', 'Days before expiration to trigger alert', 1),
('auto_generate_item_code', 'true', 'boolean', 'Automatically generate item codes', 1),
('require_approval', 'false', 'boolean', 'Require approval for transactions', 1);

-- ============================================================================
-- INSERT SAMPLE INVENTORY DATA
-- ============================================================================

-- OFFICE SUPPLIES
INSERT INTO inventory_items (item_code, category_id, item_description, unit, items_received, items_distributed, items_on_hand, minimum_stock_level, storage_location_id, created_by) VALUES
('OFC-001', 1, 'Book paper 70 GSM 8.5 inch x 13 inch, sub', 'reams', 50, 30, 20, 10, 1, 1),
('OFC-002', 1, 'Book paper 70 GSM A4 8.3 inches x 11.7 inch sub 20', 'reams', 50, 30, 20, 10, 1, 1),
('OFC-003', 1, 'Book paper 70 GSM A4 8.5 inches x 11 inch sub 20', 'reams', 45, 30, 15, 10, 1, 1),
('OFC-004', 1, 'Printer Ink Bottle Refill 003 "Black" 65ml', 'bottles', 15, 15, 0, 5, 1, 1),
('OFC-005', 1, 'Printer Ink Bottle Refill 003 "Cyan" 65ml', 'bottles', 6, 6, 0, 5, 1, 1),
('OFC-006', 1, 'Printer Ink Bottle Refill 003 "Yellow" 65ml', 'bottles', 6, 6, 0, 5, 1, 1),
('OFC-007', 1, 'Printer Ink Bottle Refill T664 "Black" 70ml', 'bottles', 5, 5, 0, 5, 1, 1),
('OFC-008', 1, 'Printer Ink Bottle Refill T664 "Cyan" 70ml', 'bottles', 3, 3, 0, 5, 1, 1),
('OFC-009', 1, 'Printer Ink Bottle Refill T664 "Yellow" 70ml', 'bottles', 3, 3, 0, 5, 1, 1);

-- MEDICAL SUPPLIES
INSERT INTO inventory_items (item_code, category_id, item_description, unit, items_received, items_distributed, items_on_hand, minimum_stock_level, storage_location_id, created_by) VALUES
('MED-001', 2, 'Arm Sling (Large)', 'pcs', 20, 15, 5, 10, 1, 1),
('MED-002', 2, 'Arm Sling (Small)', 'pcs', 20, 15, 5, 10, 1, 1),
('MED-003', 2, 'Blood Glucose Tester Strips', 'pcs', 2, 2, 0, 5, 1, 1),
('MED-004', 2, 'Extra Large Garbage Bag', 'pack', 10, 3, 7, 5, 1, 1),
('MED-005', 2, '70% Alcohol', 'bottles', 25, 15, 10, 15, 1, 1),
('MED-006', 2, 'Non-Rebreather Mask (Adult)', 'pcs', 100, 75, 25, 20, 1, 1),
('MED-007', 2, 'Nasal Cannula (Adult)', 'pcs', 100, 75, 25, 20, 1, 1),
('MED-008', 2, 'Surgical Gloves (Medium)', 'boxes', 50, 30, 20, 15, 1, 1),
('MED-009', 2, 'Face Mask (3-ply)', 'boxes', 100, 60, 40, 25, 1, 1),
('MED-010', 2, 'Bandage Roll 2 inches', 'rolls', 80, 50, 30, 20, 1, 1);

-- SRR EQUIPMENT
INSERT INTO inventory_items (item_code, category_id, item_description, unit, items_received, items_distributed, items_on_hand, minimum_stock_level, storage_location_id, created_by) VALUES
('SRR-001', 3, 'Rechargeable outdoor LED flood light', 'pcs', 13, 1, 12, 5, 1, 1),
('SRR-002', 3, 'Collapsible telescope floodlight tripod', 'pcs', 4, 0, 4, 2, 1, 1),
('SRR-003', 3, 'Industrial waterproof rechargeable emergency flashlight', 'pcs', 10, 3, 7, 5, 1, 1),
('SRR-004', 3, 'Rechargeable searchlight', 'pcs', 14, 1, 13, 5, 1, 1),
('SRR-005', 3, 'Storage box 155L storage capacity', 'pcs', 2, 0, 2, 1, 1, 1),
('SRR-006', 3, 'Folding Table 6ft. Stands 74cm high', 'pcs', 2, 0, 2, 1, 1, 1),
('SRR-007', 3, 'Folding table 4ft. Stands 74cm high', 'pcs', 2, 1, 1, 1, 1, 1),
('SRR-008', 3, 'Emergency Rescue Kit', 'sets', 5, 2, 3, 2, 1, 1),
('SRR-009', 3, 'Life Jacket (Adult)', 'pcs', 20, 5, 15, 10, 1, 1),
('SRR-010', 3, 'Fire Extinguisher 5kg', 'pcs', 10, 3, 7, 5, 1, 1);

-- FOOD SUPPLIES
INSERT INTO inventory_items (item_code, category_id, item_description, unit, items_received, items_distributed, items_on_hand, minimum_stock_level, expiration_date, storage_location_id, created_by) VALUES
('FOOD-001', 4, 'Bearbrand 33g', 'boxes', 39, 12, 27, 20, NULL, 1, 1),
('FOOD-002', 4, 'HOMI BEEF', 'boxes', 30, 23, 7, 15, '2026-03-01', 1, 1),
('FOOD-003', 4, 'HOMI CHICKEN', 'boxes', 5, 5, 0, 10, NULL, 1, 1),
('FOOD-004', 4, 'LUCKY ME BEEF', 'boxes', 5, 4, 1, 10, NULL, 1, 1),
('FOOD-005', 4, 'Nescafe 3n1 Original', 'boxes', 6, 1, 5, 5, NULL, 1, 1),
('FOOD-006', 4, 'Nescafe 3n1 Creamy White', 'boxes', 3, 2, 1, 5, NULL, 1, 1),
('FOOD-007', 4, 'Nescafe 3n1 Creamy Latte', 'boxes', 6, 2, 4, 5, NULL, 1, 1),
('FOOD-008', 4, 'Canned Sardines 155g', 'boxes', 50, 25, 25, 20, '2026-06-30', 1, 1),
('FOOD-009', 4, 'Rice (25kg sack)', 'sacks', 20, 10, 10, 8, NULL, 1, 1),
('FOOD-010', 4, 'Bottled Water 500ml (24 bottles/case)', 'cases', 30, 15, 15, 10, '2026-12-31', 1, 1);

-- ============================================================================
-- INSERT SAMPLE TRANSACTIONS
-- ============================================================================

-- Sample received transactions
INSERT INTO transactions (transaction_code, item_id, transaction_type, quantity, unit_cost, transaction_date, supplier_id, reference_number, notes, created_by) VALUES
('RCV-2026-001', 1, 'received', 50, 150.00, '2026-01-15', 2, 'PO-2026-001', 'Initial stock order', 1),
('RCV-2026-002', 11, 'received', 20, 85.00, '2026-01-20', 1, 'PO-2026-002', 'Medical supplies restocking', 1),
('RCV-2026-003', 21, 'received', 13, 2500.00, '2026-01-22', 3, 'PO-2026-003', 'Emergency equipment purchase', 1);

-- Sample distributed transactions
INSERT INTO transactions (transaction_code, item_id, transaction_type, quantity, transaction_date, recipient_name, recipient_organization, purpose, notes, created_by) VALUES
('DST-2026-001', 1, 'distributed', 30, '2026-01-25', 'Maria Santos', 'Barangay San Jose', 'Relief operations', 'Distributed for typhoon relief', 1),
('DST-2026-002', 11, 'distributed', 15, '2026-01-26', 'Jose Cruz', 'Barangay Emergency Response Team', 'Emergency medical supplies', 'For barangay health center', 1),
('DST-2026-003', 31, 'distributed', 12, '2026-01-28', 'Pedro Reyes', 'Relief Operations Team', 'Relief goods distribution', 'Food relief for flood victims', 1);

-- ============================================================================
-- CREATE VIEWS FOR REPORTING
-- ============================================================================

-- View: Current inventory status with category info
CREATE OR REPLACE VIEW v_inventory_status AS
SELECT
    i.id,
    i.item_code,
    c.category_name,
    c.category_code,
    i.item_description,
    i.unit,
    i.items_received,
    i.items_distributed,
    i.items_on_hand,
    i.minimum_stock_level,
    i.unit_cost,
    (i.items_on_hand * i.unit_cost) AS total_value,
    i.expiration_date,
    DATEDIFF(i.expiration_date, CURDATE()) AS days_until_expiry,
    sl.location_name,
    i.item_status,
    CASE
        WHEN i.items_on_hand = 0 THEN 'Out of Stock'
        WHEN i.items_on_hand <= i.minimum_stock_level THEN 'Stock Alert'
        WHEN i.expiration_date IS NOT NULL AND DATEDIFF(i.expiration_date, CURDATE()) <= 30 THEN 'Expiring Soon'
        ELSE 'Normal'
    END AS stock_status,
    i.is_active
FROM inventory_items i
JOIN categories c ON i.category_id = c.id
LEFT JOIN storage_locations sl ON i.storage_location_id = sl.id;

-- View: Transaction summary
CREATE OR REPLACE VIEW v_transaction_summary AS
SELECT
    t.id,
    t.transaction_code,
    t.transaction_type,
    t.transaction_date,
    i.item_code,
    i.item_description,
    c.category_name,
    t.quantity,
    t.unit_cost,
    t.total_cost,
    s.supplier_name,
    t.recipient_name,
    t.recipient_organization,
    u.full_name AS created_by_name,
    t.created_at
FROM transactions t
JOIN inventory_items i ON t.item_id = i.id
JOIN categories c ON i.category_id = c.id
LEFT JOIN suppliers s ON t.supplier_id = s.id
LEFT JOIN users u ON t.created_by = u.id;

-- View: Stock alert items
CREATE OR REPLACE VIEW v_low_stock_items AS
SELECT
    i.id,
    i.item_code,
    c.category_name,
    i.item_description,
    i.items_on_hand,
    i.minimum_stock_level,
    (i.minimum_stock_level - i.items_on_hand) AS shortage
FROM inventory_items i
JOIN categories c ON i.category_id = c.id
WHERE i.items_on_hand <= i.minimum_stock_level
AND i.is_active = 1
ORDER BY i.items_on_hand ASC;

-- View: Expiring items
CREATE OR REPLACE VIEW v_expiring_items AS
SELECT
    i.id,
    i.item_code,
    c.category_name,
    i.item_description,
    i.items_on_hand,
    i.expiration_date,
    DATEDIFF(i.expiration_date, CURDATE()) AS days_until_expiry
FROM inventory_items i
JOIN categories c ON i.category_id = c.id
WHERE i.expiration_date IS NOT NULL
AND i.expiration_date >= CURDATE()
AND DATEDIFF(i.expiration_date, CURDATE()) <= 30
AND i.is_active = 1
ORDER BY i.expiration_date ASC;

-- View: Borrowed items with return status
CREATE OR REPLACE VIEW v_borrowed_items AS
SELECT
    t.id,
    t.transaction_code,
    t.transaction_date,
    t.item_id,
    i.item_code,
    i.item_description,
    i.unit,
    i.item_status,
    t.quantity AS borrowed_quantity,
    t.recipient_name,
    t.recipient_organization,
    t.purpose,
    COALESCE(
        (SELECT SUM(quantity)
         FROM transactions
         WHERE parent_transaction_id = t.id
         AND transaction_type = 'returned'),
        0
    ) AS returned_quantity,
    (t.quantity - COALESCE(
        (SELECT SUM(quantity)
         FROM transactions
         WHERE parent_transaction_id = t.id
         AND transaction_type = 'returned'),
        0
    )) AS remaining_quantity,
    CASE
        WHEN (t.quantity - COALESCE(
            (SELECT SUM(quantity)
             FROM transactions
             WHERE parent_transaction_id = t.id
             AND transaction_type = 'returned'),
            0
        )) = 0 THEN 'Fully Returned'
        WHEN (SELECT SUM(quantity)
              FROM transactions
              WHERE parent_transaction_id = t.id
              AND transaction_type = 'returned') > 0 THEN 'Partially Returned'
        ELSE 'Not Returned'
    END AS return_status
FROM transactions t
JOIN inventory_items i ON t.item_id = i.id
WHERE t.transaction_type = 'distributed'
AND t.is_borrowed = 1;

-- ============================================================================
-- CREATE TRIGGERS FOR AUTOMATION
-- ============================================================================

DELIMITER $$

-- Trigger: Auto-generate stock alerts after transaction
CREATE TRIGGER tr_check_low_stock_after_transaction
AFTER INSERT ON transactions
FOR EACH ROW
BEGIN
    DECLARE current_stock INT;
    DECLARE min_stock INT;
    DECLARE item_desc TEXT;

    SELECT items_on_hand, minimum_stock_level, item_description
    INTO current_stock, min_stock, item_desc
    FROM inventory_items
    WHERE id = NEW.item_id;

    IF current_stock = 0 THEN
        INSERT INTO stock_alerts (item_id, alert_type, alert_message)
        VALUES (NEW.item_id, 'out_of_stock', CONCAT('Item "', item_desc, '" is out of stock'));
    ELSEIF current_stock <= min_stock THEN
        INSERT INTO stock_alerts (item_id, alert_type, alert_message)
        VALUES (NEW.item_id, 'low_stock', CONCAT('Item "', item_desc, '" is running low. Current stock: ', current_stock));
    END IF;
END$$

-- Trigger: Check expiring items after update
CREATE TRIGGER tr_check_expiring_items_after_update
AFTER UPDATE ON inventory_items
FOR EACH ROW
BEGIN
    DECLARE days_left INT;

    IF NEW.expiration_date IS NOT NULL THEN
        SET days_left = DATEDIFF(NEW.expiration_date, CURDATE());

        IF days_left <= 0 THEN
            INSERT INTO stock_alerts (item_id, alert_type, alert_message)
            VALUES (NEW.id, 'expired', CONCAT('Item "', NEW.item_description, '" has expired'));
        ELSEIF days_left <= 30 THEN
            INSERT INTO stock_alerts (item_id, alert_type, alert_message)
            VALUES (NEW.id, 'expiring_soon', CONCAT('Item "', NEW.item_description, '" expires in ', days_left, ' days'));
        END IF;
    END IF;
END$$

DELIMITER ;

-- ============================================================================
-- STORED PROCEDURES
-- ============================================================================

DELIMITER $$

-- Procedure: Generate item code
CREATE PROCEDURE sp_generate_item_code(
    IN p_category_id INT,
    OUT p_item_code VARCHAR(50)
)
BEGIN
    DECLARE cat_code VARCHAR(10);
    DECLARE next_num INT;

    SELECT category_code INTO cat_code FROM categories WHERE id = p_category_id;

    SELECT COALESCE(MAX(CAST(SUBSTRING(item_code, LENGTH(cat_code) + 2) AS UNSIGNED)), 0) + 1
    INTO next_num
    FROM inventory_items
    WHERE category_id = p_category_id;

    SET p_item_code = CONCAT(cat_code, '-', LPAD(next_num, 3, '0'));
END$$

-- Procedure: Record transaction and update inventory
CREATE PROCEDURE sp_record_transaction(
    IN p_item_id INT,
    IN p_transaction_type VARCHAR(20),
    IN p_quantity INT,
    IN p_transaction_date DATE,
    IN p_user_id INT,
    IN p_notes TEXT,
    OUT p_transaction_id INT
)
BEGIN
    DECLARE trans_code VARCHAR(50);
    DECLARE qty_adjustment INT;

    START TRANSACTION;

    SET trans_code = CONCAT(
        UPPER(LEFT(p_transaction_type, 3)),
        '-',
        DATE_FORMAT(p_transaction_date, '%Y'),
        '-',
        LPAD((SELECT COUNT(*) + 1 FROM transactions WHERE YEAR(transaction_date) = YEAR(p_transaction_date)), 4, '0')
    );

    IF p_transaction_type IN ('received', 'return') THEN
        SET qty_adjustment = p_quantity;
    ELSE
        SET qty_adjustment = -p_quantity;
    END IF;

    INSERT INTO transactions (transaction_code, item_id, transaction_type, quantity, transaction_date, notes, created_by)
    VALUES (trans_code, p_item_id, p_transaction_type, p_quantity, p_transaction_date, p_notes, p_user_id);

    SET p_transaction_id = LAST_INSERT_ID();

    UPDATE inventory_items
    SET
        items_received = items_received + IF(p_transaction_type = 'received', p_quantity, 0),
        items_distributed = items_distributed + IF(p_transaction_type = 'distributed', p_quantity, 0),
        items_on_hand = items_on_hand + qty_adjustment,
        updated_by = p_user_id
    WHERE id = p_item_id;

    COMMIT;
END$$

-- Procedure: Log item status change
CREATE PROCEDURE log_item_status_change(
    IN p_item_id INT,
    IN p_transaction_id INT,
    IN p_previous_status VARCHAR(50),
    IN p_new_status VARCHAR(50),
    IN p_reason VARCHAR(255),
    IN p_notes TEXT,
    IN p_return_condition VARCHAR(50),
    IN p_assessment_notes TEXT,
    IN p_created_by INT
)
BEGIN
    INSERT INTO item_status_history (
        item_id,
        transaction_id,
        previous_status,
        new_status,
        status_reason,
        notes,
        return_condition,
        assessment_notes,
        created_by
    ) VALUES (
        p_item_id,
        p_transaction_id,
        p_previous_status,
        p_new_status,
        p_reason,
        p_notes,
        p_return_condition,
        p_assessment_notes,
        p_created_by
    );

    UPDATE inventory_items SET item_status = p_new_status WHERE id = p_item_id;
END$$

DELIMITER ;

-- ============================================================================
-- GRANT PERMISSIONS (Adjust based on your setup)
-- ============================================================================
-- GRANT ALL PRIVILEGES ON cdrrmo_inventory.* TO 'cdrrmo_user'@'localhost' IDENTIFIED BY 'your_secure_password';
-- FLUSH PRIVILEGES;

-- ============================================================================
-- VERIFICATION
-- ============================================================================
SELECT 'Database setup completed successfully!' AS Status;
SELECT COUNT(*) AS total_users FROM users;
SELECT COUNT(*) AS total_categories FROM categories;
SELECT COUNT(*) AS total_items FROM inventory_items;
SELECT COUNT(*) AS total_transactions FROM transactions;
SELECT COUNT(*) AS total_feedback FROM user_feedback;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- END OF DATABASE SETUP
-- ============================================================================

-- ============================================================================
-- STATUS WORKFLOW REFERENCE
-- ============================================================================
-- 1. Available (default) - Item is in stock and available for distribution
-- 2. In-Use             - Item has been borrowed, awaiting return
-- 3. Pending Repair     - Item was returned but found to be damaged
-- 4. Serviceable        - Maintenance assessed item as repaired/good, ready for reuse
-- 5. Unserviceable      - Maintenance assessed item as beyond repair, flagged for disposal
-- ============================================================================
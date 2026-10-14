-- ============================================================================
-- COMBINED MIGRATION: Item Status Workflow + Batch Tracking
-- ============================================================================
-- Safe to run against an EXISTING cdrrmo_inventory database that already has
-- the base tables (users, categories, suppliers, storage_locations,
-- inventory_items, transactions, etc.) from the original schema.
--
-- This does NOT drop or recreate any existing data tables (unlike
-- database_complete.sql, which is a from-scratch build script).
-- ============================================================================

USE cdrrmo_inventory;

-- ============================================================================
-- PART 1: ITEM STATUS WORKFLOW
-- Available → In-Use → (Returned) → Pending Repair → (Assessed) → Serviceable/Unserviceable
-- ============================================================================

-- 1.1 Add item_status to inventory_items
ALTER TABLE inventory_items ADD COLUMN IF NOT EXISTS item_status ENUM(
    'Available',
    'In-Use',
    'Pending Repair',
    'Serviceable',
    'Unserviceable'
) DEFAULT 'Available' COMMENT 'Current status of the item: Available, In-Use, Pending Repair, Serviceable, Unserviceable';

SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'inventory_items'
      AND INDEX_NAME = 'idx_item_status'
);
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE inventory_items ADD INDEX idx_item_status (item_status)',
    'SELECT "idx_item_status already exists, skipping"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 1.2 Item status history (audit trail)
CREATE TABLE IF NOT EXISTS item_status_history (
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
    status_reason VARCHAR(255) COMMENT 'Reason for status change (e.g., Item borrowed, Item returned - Good condition, Item assessed as serviceable)',
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

-- 1.3 Link transactions to the status history record that resulted from them
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS status_change_id INT NULL COMMENT 'References the status history record' AFTER return_condition;

SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'transactions'
      AND CONSTRAINT_NAME = 'fk_transactions_status_change'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE transactions ADD CONSTRAINT fk_transactions_status_change FOREIGN KEY (status_change_id) REFERENCES item_status_history(id) ON DELETE SET NULL',
    'SELECT "fk_transactions_status_change already exists, skipping"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 1.4 Procedure to log a status change and update the item in one call
DELIMITER $$

DROP PROCEDURE IF EXISTS log_item_status_change$$

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
-- PART 2: BATCH TRACKING (FIFO expiration & distribution)
-- ============================================================================

-- 2.1 Item batches: each individual "receive" event creates one batch row
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

-- 2.1a Fix: database_complete.sql omits the FK on item_batches.transaction_id
-- even though the column is meant to reference transactions(id). Add it here.
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'item_batches'
      AND CONSTRAINT_NAME = 'fk_item_batches_transaction'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE item_batches ADD CONSTRAINT fk_item_batches_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL',
    'SELECT "fk_item_batches_transaction already exists, skipping"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2.2 Distribution batches: which batch(es) were drawn down for a distribution
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
-- NOTES
-- ============================================================================
-- Item Status Workflow:
-- 1. Available (default) - Item is in stock and available for distribution
-- 2. In-Use - Item has been borrowed, awaiting return
-- 3. Pending Repair - Item was returned but found to be damaged
-- 4. Serviceable - Maintenance team assessed item as repaired/good condition
-- 5. Unserviceable - Maintenance team assessed item as beyond repair/unsafe
--
-- Batch Tracking:
-- Each "received" transaction should create a row in item_batches.
-- Each "distributed" transaction should draw down quantity_on_hand across
-- one or more batches (oldest expiration_date / received_date first = FIFO),
-- recording each draw in distribution_batches.
-- ============================================================================
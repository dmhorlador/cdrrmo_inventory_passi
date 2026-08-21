-- ============================================================================
-- MIGRATION: Item Status Workflow System
-- ============================================================================
-- This migration adds item status tracking for borrowed items:
-- In-Use → (Returned) → Available/Pending Repair → (Assessment) → Serviceable/Unserviceable

-- ============================================================================
-- 1. Add item status field to inventory_items if not exists
-- ============================================================================
ALTER TABLE inventory_items ADD COLUMN IF NOT EXISTS item_status ENUM(
    'Available', 
    'In-Use', 
    'Pending Repair', 
    'Serviceable', 
    'Unserviceable'
) DEFAULT 'Available' COMMENT 'Current status of the item: Available, In-Use, Pending Repair, Serviceable, Unserviceable';

-- ============================================================================
-- 2. Create item_status_history table for audit trail
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

-- ============================================================================
-- 3. Add status assessment fields to transactions table
-- ============================================================================
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS status_change_id INT NULL COMMENT 'References the status history record' AFTER return_condition;
ALTER TABLE transactions ADD CONSTRAINT FOREIGN KEY (status_change_id) REFERENCES item_status_history(id) ON DELETE SET NULL;

-- ============================================================================
-- 4. Create function to log status changes
-- ============================================================================
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
-- NOTES
-- ============================================================================
-- Status Workflow:
-- 1. Available (default) - Item is in stock and available for distribution
-- 2. In-Use - Item has been borrowed, awaiting return
-- 3. Pending Repair - Item was returned but found to be damaged
-- 4. Serviceable - Maintenance team assessed item as repaired/good condition, ready for reuse
-- 5. Unserviceable - Maintenance team assessed item as beyond repair/unsafe/obsolete, flagged for disposal

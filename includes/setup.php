<?php
/**
 * Database setup and verification script
 * 
 * This file checks for required tables and columns in the database and
 * creates them if they don't exist to prevent errors.
 */

// Include database connection
require_once __DIR__ . '/../config/db.php';

/**
 * Check if a table exists in the database
 * @param string $tableName The name of the table to check
 * @return bool True if table exists, false otherwise
 */
function tableExists($tableName) {
    try {
        $sql = "SHOW TABLES LIKE '$tableName'";
        $result = db()->query($sql);
        return $result && $result->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error checking if table $tableName exists: " . $e->getMessage());
        return false;
    }
}

// Check and create approval_actions table if it doesn't exist
if (!tableExists('approval_actions')) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS `approval_actions` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `document_id` INT(11) NOT NULL,
            `user_id` INT(11) NOT NULL,
            `action` ENUM('approve', 'reject', 'pay', 'complete') NOT NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `document_id` (`document_id`),
            KEY `user_id` (`user_id`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
        
        db()->exec($sql);
        
        // Add foreign key constraints if documents table exists
        if (tableExists('documents') && tableExists('users')) {
            $sql = "ALTER TABLE `approval_actions` 
                    ADD CONSTRAINT `approval_actions_ibfk_1` 
                    FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) 
                    ON DELETE CASCADE,
                    ADD CONSTRAINT `approval_actions_ibfk_2` 
                    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)";
            db()->exec($sql);
        }
        
        error_log("Created approval_actions table successfully");
    } catch (PDOException $e) {
        error_log("Error creating approval_actions table: " . $e->getMessage());
    }
}

// Additional table checks can be added here as needed

?> 
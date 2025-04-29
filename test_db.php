<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'config/db.php';

try {
    // Test database connection
    echo "Testing database connection...\n";
    $pdo = db();
    
    // Check if documents table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'documents'");
    if ($checkTable->rowCount() === 0) {
        echo "Documents table does not exist. Creating it...\n";
        
        // Create documents table
        $pdo->exec("CREATE TABLE `documents` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `doc_unique_id` varchar(50) DEFAULT NULL,
            `file_name` varchar(255) NOT NULL,
            `file_path` varchar(255) NOT NULL,
            `title` varchar(255) NOT NULL,
            `type` varchar(100) NOT NULL,
            `department` varchar(100) NOT NULL,
            `status` varchar(50) DEFAULT 'pending',
            `uploaded_by` int(11) DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        echo "Documents table created successfully.\n";
    } else {
        echo "Documents table already exists.\n";
    }
    
    // Check if users table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($checkTable->rowCount() === 0) {
        echo "Users table does not exist. Creating it...\n";
        
        // Create users table
        $pdo->exec("CREATE TABLE `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `email` varchar(100) NOT NULL,
            `password` varchar(255) NOT NULL,
            `role` varchar(50) NOT NULL,
            `department` varchar(100) DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        echo "Users table created successfully.\n";
        
        // Create admin user if no users exist
        $pdo->exec("INSERT INTO `users` (`name`, `email`, `password`, `role`, `department`, `created_at`) 
                   VALUES ('Admin', 'admin@example.com', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'admin', 'Administration', NOW())");
        
        echo "Admin user created successfully.\n";
    } else {
        echo "Users table already exists.\n";
    }
    
    // Check if document_movements table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'document_movements'");
    if ($checkTable->rowCount() === 0) {
        echo "Document movements table does not exist. Creating it...\n";
        
        // Create document_movements table
        $pdo->exec("CREATE TABLE `document_movements` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `document_id` int(11) NOT NULL,
            `from_department` varchar(100) NOT NULL,
            `to_department` varchar(100) NOT NULL,
            `moved_by` int(11) NOT NULL,
            `moved_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `document_id` (`document_id`),
            KEY `moved_by` (`moved_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        echo "Document movements table created successfully.\n";
    } else {
        echo "Document movements table already exists.\n";
    }
    
    echo "Database verification completed successfully.\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "General error: " . $e->getMessage() . "\n";
} 
<?php
// Database connection parameters - adjust these to match your config
require_once 'config/db.php';

try {
    // Read the SQL file
    $sql = file_get_contents(__DIR__ . '/sql/fix_approval_workflow.sql');
    
    // Execute the SQL
    $result = db()->exec($sql);
    
    echo "SQL script executed successfully.\n";
    echo "Result: " . $result . "\n";
    
} catch (PDOException $e) {
    echo "Error executing SQL: " . $e->getMessage() . "\n";
}
?> 
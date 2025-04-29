<?php
/**
 * Get Trail Final Step
 * 
 * AJAX handler to retrieve the final step of a document trail
 */

// Include database connection
require_once '../../config/db.php';
require_once '../auth.php';

// Ensure this is accessed via AJAX
header('Content-Type: application/json');

// Check if trail_id is provided
if (!isset($_GET['trail_id']) || empty($_GET['trail_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Trail ID is required'
    ]);
    exit;
}

// Validate and sanitize the trail ID
$trailId = filter_var($_GET['trail_id'], FILTER_VALIDATE_INT);
if ($trailId === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid Trail ID'
    ]);
    exit;
}

try {
    // Get the final step (highest step_order) for the trail
    $sql = "SELECT department 
            FROM document_trail_steps 
            WHERE trail_id = :trail_id 
            ORDER BY step_order DESC 
            LIMIT 1";
    
    $stmt = db()->prepare($sql);
    $stmt->execute(['trail_id' => $trailId]);
    
    if ($stmt->rowCount() > 0) {
        $finalStep = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'department' => $finalStep['department']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No steps found for this trail'
        ]);
    }
} catch (PDOException $e) {
    error_log("Error getting trail final step: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request'
    ]);
}
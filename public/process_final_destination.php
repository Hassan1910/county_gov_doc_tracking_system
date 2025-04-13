<?php
/**
 * Process Final Destination Change
 * 
 * This file handles admin requests to change a document's final destination
 */

// Include authentication utilities
require_once '../includes/auth.php';

// Require login and admin role
requireLogin();
requireRole('admin');

// Get current user data
$user = getCurrentUser();

// Include database connection
require_once '../config/db.php';

// Verify POST request with document_id and new_final_destination
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['document_id'], $_POST['new_final_destination'])) {
    $_SESSION['error'] = "Invalid request. Missing required parameters.";
    header('Location: dashboard.php');
    exit;
}

// Sanitize input
$documentId = filter_var($_POST['document_id'], FILTER_VALIDATE_INT);
$newFinalDestination = filter_var($_POST['new_final_destination'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if ($documentId === false || empty($newFinalDestination)) {
    $_SESSION['error'] = "Invalid document ID or destination department.";
    header('Location: dashboard.php');
    exit;
}

try {
    // Begin transaction
    db()->beginTransaction();
    
    // Get current document data
    $sql = "SELECT id, doc_unique_id, title, final_destination, department, submitter_id 
            FROM documents 
            WHERE id = :id";
    $stmt = db()->prepare($sql);
    $stmt->execute(['id' => $documentId]);
    $document = $stmt->fetch();
    
    if (!$document) {
        throw new Exception("Document not found.");
    }
    
    // Update final destination
    $sql = "UPDATE documents 
            SET final_destination = :final_destination 
            WHERE id = :id";
    $stmt = db()->prepare($sql);
    $result = $stmt->execute([
        'final_destination' => $newFinalDestination,
        'id' => $documentId
    ]);
    
    if (!$result) {
        throw new Exception("Failed to update final destination.");
    }
    
    // Check if document was already at final destination and now it's not
    if ($document['department'] == $document['final_destination'] && $document['department'] != $newFinalDestination) {
        // Document was at final destination but now has a new one that doesn't match current department
        $sql = "UPDATE documents 
                SET status = 'in_movement' 
                WHERE id = :id AND status IN ('pending_approval', 'pending')";
        $stmt = db()->prepare($sql);
        $stmt->execute(['id' => $documentId]);
    }
    
    // Check if the new final destination matches the current department
    if ($document['department'] == $newFinalDestination) {
        // Document has reached its final destination now
        $sql = "UPDATE documents 
                SET status = 'pending_approval' 
                WHERE id = :id AND status IN ('in_movement', 'pending')";
        $stmt = db()->prepare($sql);
        $stmt->execute(['id' => $documentId]);
        
        // Notify client if there is one
        if (!empty($document['submitter_id'])) {
            $sql = "INSERT INTO client_notifications (client_id, document_id, message, is_read, created_at)
                    VALUES (:client_id, :document_id, :message, 0, NOW())";
            $stmt = db()->prepare($sql);
            $stmt->execute([
                'client_id' => $document['submitter_id'],
                'document_id' => $documentId,
                'message' => "Your document \"{$document['title']}\" has reached its final destination and is now awaiting approval. You can download the PDF from your dashboard or visit our office to collect the physical document."
            ]);
        }
    }
    
    // Commit the transaction
    db()->commit();
    
    $_SESSION['success'] = "Document final destination updated successfully.";
    header('Location: view_document.php?id=' . $documentId);
    exit;
    
} catch (Exception $e) {
    // Rollback the transaction on error
    try {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
    } catch (Exception $rollbackException) {
        error_log("Error rolling back transaction: " . $rollbackException->getMessage());
    }
    
    error_log("Error updating final destination: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred: " . $e->getMessage();
    header('Location: view_document.php?id=' . $documentId);
    exit;
} 
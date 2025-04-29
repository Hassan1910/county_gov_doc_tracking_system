<?php
/**
 * Mark Document as Done Process
 * 
 * This script marks a document as done, indicating it has reached its final stage
 * and is ready for collection or download.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once '../config/db.php';
require_once '../includes/auth.php';

// Require login to access this functionality
requireLogin();

// Get current user data
$user = getCurrentUser();

// Only admin and supervisor can mark documents as done
if (!hasRole(['admin', 'supervisor'])) {
    $_SESSION['error'] = "You don't have permission to mark documents as done.";
    header('Location: ../public/dashboard.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['document_id'])) {
    $document_id = intval($_POST['document_id']);
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    
    try {
        // Get a stable database connection for the entire transaction
        $dbConnection = db();
        
        // Get document info
        $sql = "SELECT d.id, d.doc_unique_id, d.title, d.department, d.submitter_id
                FROM documents d 
                WHERE d.id = :id";
        $stmt = $dbConnection->prepare($sql);
        $stmt->execute(['id' => $document_id]);
        $document = $stmt->fetch();
        
        if (!$document) {
            $_SESSION['error'] = "Document not found.";
            header('Location: ../public/view_document.php?id=' . $document_id);
            exit;
        }
        
        // Begin transaction
        $dbConnection->beginTransaction();
        
        // Update document status
        $sql = "UPDATE documents SET status = 'done' WHERE id = :id";
        $stmt = $dbConnection->prepare($sql);
        $stmt->execute(['id' => $document_id]);
        
        // Check if document has a client submitter
        if (!empty($document['submitter_id'])) {
            // Create notification message
            $message = "Your document \"" . $document['title'] . "\" has been marked as COMPLETE and is ready for collection or download.";
            
            if (!empty($comment)) {
                $message .= " Note: \"" . $comment . "\"";
            }
            
            // Send notification to the client
            $sql = "INSERT INTO client_notifications (client_id, document_id, message, is_read, created_at) 
                    VALUES (:client_id, :document_id, :message, 0, NOW())";
            $stmt = $dbConnection->prepare($sql);
            $stmt->execute([
                'client_id' => $document['submitter_id'],
                'document_id' => $document['id'],
                'message' => $message
            ]);
        }
        
        // Log the activity (if function exists)
        if (function_exists('logActivity')) {
            $details = "Document ID: " . $document['doc_unique_id'] . " marked as COMPLETE" . 
                      (!empty($comment) ? " with note: " . $comment : "");
            logActivity('document_marked_done', $details);
        }
        
        // Commit transaction
        $dbConnection->commit();
        
        // Set success message
        $_SESSION['success'] = "Document has been marked as complete successfully.";
        
        // Redirect
        header('Location: ../public/view_document.php?id=' . $document_id);
        exit;
        
    } catch (PDOException $e) {
        // Only rollback if we have an active transaction
        if (isset($dbConnection) && $dbConnection->inTransaction()) {
            $dbConnection->rollBack();
        }
        error_log("Error marking document as done: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while marking the document as done.";
        header('Location: ../public/view_document.php?id=' . $document_id);
        exit;
    }
} else {
    // If not a POST request, redirect to dashboard
    header('Location: ../public/dashboard.php');
    exit;
} 
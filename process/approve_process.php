<?php
/**
 * Document Approval Process
 * 
 * This script handles document approval/rejection and notifies the client.
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

// Only admin and supervisor can approve documents
if (!hasRole(['admin', 'supervisor', 'manager'])) {
    $_SESSION['error'] = "You don't have permission to approve documents.";
    header('Location: ../public/dashboard.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['document_id'], $_POST['action'])) {
    // Validate action
    if ($_POST['action'] !== 'approve' && $_POST['action'] !== 'reject') {
        $_SESSION['error'] = "Invalid action specified.";
        header('Location: ../public/approve.php');
        exit;
    }
    
    // Process the approval/rejection
    $document_id = intval($_POST['document_id']);
    $action = $_POST['action'];
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    
    try {
        // Get document info
        $sql = "SELECT d.id, d.doc_unique_id, d.title, d.department, d.submitter_id
                FROM documents d 
                WHERE d.id = :id";
        $stmt = db()->prepare($sql);
        $stmt->execute(['id' => $document_id]);
        $document = $stmt->fetch();
        
        if (!$document) {
            $_SESSION['error'] = "Document not found.";
            header('Location: ../public/approve.php');
            exit;
        }
        
        // Begin transaction
        db()->beginTransaction();
        
        // Insert approval record
        $sql = "INSERT INTO approvals (document_id, approved_by, status, comment, approved_at)
                VALUES (:document_id, :approved_by, :status, :comment, NOW())";
        $stmt = db()->prepare($sql);
        $stmt->execute([
            'document_id' => $document_id,
            'approved_by' => $user['id'],
            'status' => $action,
            'comment' => $comment
        ]);
        
        // Update document status
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        $sql = "UPDATE documents SET status = :status WHERE id = :id";
        $stmt = db()->prepare($sql);
        $stmt->execute([
            'status' => $status,
            'id' => $document_id
        ]);
        
        // Check if document has a client submitter
        if (!empty($document['submitter_id'])) {
            // Create notification message
            $message = "Your document \"" . $document['title'] . "\" has been " . 
                      ($action === 'approve' ? 'approved' : 'rejected') . " by the " . 
                      $user['department'] . " department.";
            
            if (!empty($comment)) {
                $message .= " Comment: \"" . $comment . "\"";
            }
            
            // Send notification to the client
            $sql = "INSERT INTO client_notifications (client_id, document_id, message, is_read, created_at) 
                    VALUES (:client_id, :document_id, :message, 0, NOW())";
            $stmt = db()->prepare($sql);
            $stmt->execute([
                'client_id' => $document['submitter_id'],
                'document_id' => $document['id'],
                'message' => $message
            ]);
        }
        
        // Log the activity (if function exists)
        if (function_exists('logActivity')) {
            $details = "Document ID: " . $document['doc_unique_id'] . " " . 
                      ($action === 'approve' ? 'approved' : 'rejected') . 
                      (!empty($comment) ? " with comment: " . $comment : "");
            logActivity('document_' . $action . 'd', $details);
        }
        
        // Commit transaction
        db()->commit();
        
        // Set success message
        $_SESSION['success'] = "Document has been " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully.";
        
        // Redirect
        header('Location: ../public/approve.php');
        exit;
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        db()->rollBack();
        error_log("Error processing document approval: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while processing the document approval.";
        header('Location: ../public/approve.php');
        exit;
    }
} else {
    // If not a POST request, redirect to approve page
    header('Location: ../public/approve.php');
    exit;
} 
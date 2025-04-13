<?php
/**
 * Document Movement Process
 * 
 * This script handles the movement of documents between departments
 * and sends notifications to client users.
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

// Only admin, clerk, and supervisor can move documents
if (!hasRole(['admin', 'clerk', 'supervisor'])) {
    $_SESSION['error'] = "You don't have permission to move documents.";
    header('Location: ../public/dashboard.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['document_id'], $_POST['to_department'])) {
    // Validate the destination department
    if (empty($_POST['to_department'])) {
        $_SESSION['error'] = "Destination department is required.";
        header('Location: ../public/move.php' . (isset($_POST['document_id']) ? '?id=' . $_POST['document_id'] : ''));
        exit;
    }
    
    try {
        // Get current document info
        $sql = "SELECT d.id, d.doc_unique_id, d.title, d.department, d.submitter_id
                FROM documents d
                WHERE d.id = :id";
        $stmt = db()->prepare($sql);
        $stmt->execute(['id' => $_POST['document_id']]);
        $document = $stmt->fetch();
        
        if (!$document) {
            $_SESSION['error'] = "Document not found.";
            header('Location: ../public/move.php');
            exit;
        }
        
        // Check if document is being moved to the same department
        if ($document['department'] === $_POST['to_department']) {
            $_SESSION['error'] = "Document is already in the selected department.";
            header('Location: ../public/move.php?id=' . $_POST['document_id']);
            exit;
        }
        
        // Begin transaction
        db()->beginTransaction();
        
        // Record the movement
        $sql = "INSERT INTO document_movements (document_id, from_department, to_department, moved_by, note, moved_at) 
                VALUES (:document_id, :from_department, :to_department, :moved_by, :note, NOW())";
        $stmt = db()->prepare($sql);
        $stmt->execute([
            'document_id' => $_POST['document_id'],
            'from_department' => $document['department'],
            'to_department' => $_POST['to_department'],
            'moved_by' => $user['id'],
            'note' => $_POST['note'] ?? null
        ]);
        
        // Update document's department and status
        $sql = "UPDATE documents SET 
                department = :department, 
                status = 'in_movement' 
                WHERE id = :id";
        $stmt = db()->prepare($sql);
        $stmt->execute([
            'department' => $_POST['to_department'],
            'id' => $_POST['document_id']
        ]);
        
        // Check if document has a client submitter
        if (!empty($document['submitter_id'])) {
            // Send notification to the client
            $message = "Your document \"" . $document['title'] . "\" has been moved from " . 
                      $document['department'] . " to " . $_POST['to_department'] . " department.";
            
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
            $details = "Document ID: " . $document['doc_unique_id'] . " moved from " . 
                      $document['department'] . " to " . $_POST['to_department'];
            logActivity('document_moved', $details);
        }
        
        // Commit transaction
        db()->commit();
        
        $_SESSION['success'] = "Document has been moved to " . $_POST['to_department'] . " department.";
        
        // Redirect to move documents list
        header('Location: ../public/move.php');
        exit;
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        db()->rollBack();
        error_log("Error processing document movement: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while processing the document movement.";
        header('Location: ../public/move.php' . (isset($_POST['document_id']) ? '?id=' . $_POST['document_id'] : ''));
        exit;
    }
} else {
    // If not a POST request, redirect to move page
    header('Location: ../public/move.php');
    exit;
} 
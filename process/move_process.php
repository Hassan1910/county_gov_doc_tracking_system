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

// Allow document movement by relevant roles
if (!hasRole(['admin', 'clerk', 'assistant_manager', 'senior_manager'])) {
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
        $sql = "SELECT d.id, d.doc_unique_id, d.title, d.department, d.submitter_id, d.trail_id, d.current_trail_step, d.final_destination
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
        
        // Special handling for manager workflow
        if (hasRole('assistant_manager')) {
            // Assistant managers can only move to senior managers in their department
            $sql = "SELECT id, department FROM users 
                    WHERE role = 'senior_manager' 
                    AND department = :department";
            $stmt = db()->prepare($sql);
            $stmt->execute(['department' => $user['department']]);
            $seniorManager = $stmt->fetch();
            
            // Check if there's a senior manager in this department
            if (!$seniorManager) {
                $_SESSION['error'] = "Cannot proceed with approval flow - no senior manager found in this department.";
                header('Location: ../public/move.php?id=' . $_POST['document_id']);
                exit;
            }
            
            // Force assistant managers to only move to their senior manager
            $_POST['to_department'] = $user['department']; // Keep in same department
            $moveType = 'internal_approval';
            
            // Add approval record
            $sql = "INSERT INTO approval_actions (document_id, user_id, action, notes, created_at)
                    VALUES (:document_id, :user_id, :action, :notes, NOW())";
            $stmt = db()->prepare($sql);
            $stmt->execute([
                'document_id' => $_POST['document_id'],
                'user_id' => $user['id'],
                'action' => 'approve',
                'notes' => 'Approved by assistant manager, forwarded to senior manager for review.'
            ]);
            
            // Add notification for senior manager
            $sql = "INSERT INTO notifications (user_id, message, sent_at)
                    VALUES (:user_id, :message, NOW())";
            $stmt = db()->prepare($sql);
            $stmt->execute([
                'user_id' => $seniorManager['id'],
                'message' => "Document \"{$document['title']}\" (ID: {$document['doc_unique_id']}) has been approved by assistant manager and requires your review."
            ]);
            
            // Set note addition for document movement
            $noteAddition = ' (Approved by assistant manager, forwarded to senior manager)';
        } elseif (hasRole('senior_manager')) {
            // Senior managers should send completed documents back to the clerk
            $sql = "SELECT id FROM users WHERE role = 'clerk' LIMIT 1";
            $stmt = db()->prepare($sql);
            $stmt->execute();
            $clerk = $stmt->fetch();
            
            if (!$clerk) {
                $_SESSION['error'] = "Cannot complete approval flow - no clerk found in the system.";
                header('Location: ../public/move.php?id=' . $_POST['document_id']);
                exit;
            }
            
            $moveType = 'completion';
        } else {
            $moveType = 'standard';
        }
        
        // Check if document is following a trail
        $following_trail = false;
        $next_department = null;

        if (!empty($document['trail_id']) && $document['trail_id'] > 0 && !hasRole('assistant_manager') && !hasRole('senior_manager')) {
            try {
                // Get current step
                $current_step = $document['current_trail_step'] ?? 0;
                
                // Get next step in the trail
                $sql = "SELECT * FROM document_trail_steps 
                        WHERE trail_id = :trail_id AND step_order = :step_order";
                $stmt = db()->prepare($sql);
                $stmt->execute([
                    'trail_id' => $document['trail_id'],
                    'step_order' => $current_step + 1
                ]);
                
                $next_step = $stmt->fetch();
                
                if ($next_step) {
                    $following_trail = true;
                    $next_department = $next_step['department'];
                    
                    // If the user is trying to move to a different department than the next one in the trail, check if they're admin
                    if ($_POST['to_department'] != $next_department && !hasRole('admin')) {
                        $_SESSION['error'] = "This document is following a predefined trail. The next department must be " . $next_department;
                        header('Location: ../public/move.php' . (isset($_POST['document_id']) ? '?id=' . $_POST['document_id'] : ''));
                        exit;
                    } else if ($_POST['to_department'] != $next_department && hasRole('admin')) {
                        // Admin is overriding the trail - log this
                        logActivity('trail_override', "Admin overrode document trail for {$document['doc_unique_id']}, moving to {$_POST['to_department']} instead of {$next_department}");
                    }
                }
            } catch (PDOException $e) {
                error_log("Error checking document trail: " . $e->getMessage());
                // Continue with normal process if there's an error with the trail
            }
        }
        
        // Begin transaction
        db()->beginTransaction();
        
        // Special handling for managers
        $statusUpdate = 'in_movement';
        
        if (hasRole('assistant_manager')) {
            $statusUpdate = 'assistant_approved';
        } elseif (hasRole('senior_manager')) {
            $statusUpdate = 'approved';
        }
        
        // Record the movement
        $sql = "INSERT INTO document_movements (document_id, from_department, to_department, moved_by, note, moved_at) 
                VALUES (:document_id, :from_department, :to_department, :moved_by, :note, NOW())";
        $stmt = db()->prepare($sql);
        $stmt->execute([
            'document_id' => $_POST['document_id'],
            'from_department' => $document['department'],
            'to_department' => $_POST['to_department'],
            'moved_by' => $user['id'],
            'note' => ($_POST['note'] ?? '') . $noteAddition
        ]);
        
        // Update document's department and status
        $sql = "UPDATE documents SET 
                department = :department, 
                status = :status 
                WHERE id = :id";
        $stmt = db()->prepare($sql);
        $stmt->execute([
            'department' => $_POST['to_department'],
            'status' => $statusUpdate,
            'id' => $_POST['document_id']
        ]);
        
        // If this is an assistant manager approval, set a special flag for senior manager
        if (hasRole('assistant_manager')) {
            $sql = "UPDATE documents SET 
                    needs_senior_approval = 1 
                    WHERE id = :id";
            $stmt = db()->prepare($sql);
            $stmt->execute(['id' => $_POST['document_id']]);
        }
        
        // Update current trail step if following a trail
        if ($following_trail) {
            $sql = "UPDATE documents 
                    SET current_trail_step = current_trail_step + 1 
                    WHERE id = :id";
            $stmt = db()->prepare($sql);
            $stmt->execute(['id' => $_POST['document_id']]);
        }
        
        // Check if document has a client submitter
        if (!empty($document['submitter_id'])) {
            // Send notification to the contractor
            $message = "Your document \"" . $document['title'] . "\" has been moved from " . 
                      $document['department'] . " to " . $_POST['to_department'] . " department.";
            
            if (hasRole('senior_manager')) {
                $message = "Your document \"" . $document['title'] . "\" has been approved by the senior manager and is being processed for completion.";
            }
            
            $sql = "INSERT INTO client_notifications (client_id, document_id, message, is_read, created_at) 
                    VALUES (:client_id, :document_id, :message, 0, NOW())";
            $stmt = db()->prepare($sql);
            $stmt->execute([
                'client_id' => $document['submitter_id'],
                'document_id' => $document['id'],
                'message' => $message
            ]);
        }
        
        // Log the activity
        $details = "Document ID: " . $document['doc_unique_id'] . " moved from " . 
                  $document['department'] . " to " . $_POST['to_department'];
        if (hasRole('assistant_manager')) {
            $details .= " after assistant manager approval";
        } else if (hasRole('senior_manager')) {
            $details .= " after senior manager approval";
        }
        logActivity('document_moved', $details);
        
        // Check if this is the final department
        if ($document['final_destination'] === $_POST['to_department']) {
            // Document has reached its final destination, update status if needed
            if (!hasRole('senior_manager')) {
                $sql = "UPDATE documents 
                        SET status = 'pending_approval' 
                        WHERE id = :id AND status = 'in_movement'";
                $stmt = db()->prepare($sql);
                $stmt->execute(['id' => $_POST['document_id']]);
                
                // Check if we need to send notification to managers
                try {
                    // Get assistant managers in the department
                    $sql = "SELECT id, name FROM users 
                            WHERE department = :department 
                            AND role = 'assistant_manager'";
                    $stmt = db()->prepare($sql);
                    $stmt->execute(['department' => $_POST['to_department']]);
                    $assistantManagers = $stmt->fetchAll();
                    
                    if (!empty($assistantManagers)) {
                        foreach ($assistantManagers as $manager) {
                            // Send notification to assistant manager
                            $message = "Document \"{$document['title']}\" ({$document['doc_unique_id']}) has arrived at your department for review.";
                            
                            // Insert into notifications table
                            $sql = "INSERT INTO notifications (user_id, message, sent_at) 
                                    VALUES (:user_id, :message, NOW())";
                            $stmt = db()->prepare($sql);
                            $stmt->execute([
                                'user_id' => $manager['id'],
                                'message' => $message
                            ]);
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Error notifying managers: " . $e->getMessage());
                    // Continue with process - notification is not critical
                }
            }
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
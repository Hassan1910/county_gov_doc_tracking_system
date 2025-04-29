<?php
/**
 * Mark Notification Read Process
 * 
 * This script handles marking a single notification as read.
 */

// Start session if not already started
session_start();

// Include authentication utilities
require_once '../includes/auth.php';

// Require login to access this functionality
requireLogin();

// Get current user data
$user = getCurrentUser();

// Redirect non-authorized users
if (!in_array($user['role'], ['client', 'contractor', 'viewer'])) {
    $_SESSION['error'] = "Unauthorized access.";
    header('Location: ../public/dashboard.php');
    exit;
}

// Include database connection
require_once '../config/db.php';

// Check if notification ID is provided
if (!isset($_POST['notification_id']) || empty($_POST['notification_id'])) {
    $_SESSION['error'] = "No notification specified.";
    header('Location: ' . (isset($_POST['redirect']) ? $_POST['redirect'] : '../public/client_dashboard.php'));
    exit;
}

$notification_id = (int)$_POST['notification_id'];
$redirect = isset($_POST['redirect']) ? $_POST['redirect'] : '../public/client_dashboard.php';

try {
    // First check if the notification belongs to the current user
    $checkSql = "SELECT id FROM client_notifications 
                 WHERE id = :notification_id AND client_id = :client_id";
    $checkStmt = db()->prepare($checkSql);
    $checkStmt->execute([
        'notification_id' => $notification_id,
        'client_id' => $user['id']
    ]);
    
    if ($checkStmt->rowCount() === 0) {
        // Notification doesn't exist or doesn't belong to this user
        $_SESSION['error'] = "Notification not found.";
        header('Location: ' . $redirect);
        exit;
    }
    
    // Mark the notification as read
    $updateSql = "UPDATE client_notifications SET is_read = 1 
                  WHERE id = :notification_id AND client_id = :client_id";
    $updateStmt = db()->prepare($updateSql);
    $updateStmt->execute([
        'notification_id' => $notification_id,
        'client_id' => $user['id']
    ]);
    
    if ($updateStmt->rowCount() > 0) {
        $_SESSION['success'] = "Notification marked as read.";
    } else {
        $_SESSION['info'] = "Notification already marked as read.";
    }
    
} catch (PDOException $e) {
    error_log("Error marking notification as read: " . $e->getMessage());
    $_SESSION['error'] = "Something went wrong. Please try again.";
}

// Redirect back to the appropriate page
header('Location: ' . $redirect);
exit; 
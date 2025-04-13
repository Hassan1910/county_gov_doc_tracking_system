<?php
/**
 * Mark All Notifications Read Process
 * 
 * This script handles marking all notifications as read for a client.
 */

// Start session if not already started
session_start();

// Include authentication utilities
require_once '../includes/auth.php';

// Require login to access this functionality
requireLogin();

// Get current user data
$user = getCurrentUser();

// Redirect non-client users
if ($user['role'] !== 'client') {
    $_SESSION['error'] = "Unauthorized access.";
    header('Location: ../public/dashboard.php');
    exit;
}

// Include database connection
require_once '../config/db.php';

// Get redirect URL (default to notifications page)
$redirect = isset($_POST['redirect']) ? $_POST['redirect'] : '../public/notifications.php';

try {
    // Mark all notifications as read for this client
    $sql = "UPDATE client_notifications SET is_read = 1 
            WHERE client_id = :client_id AND is_read = 0";
    $stmt = db()->prepare($sql);
    $stmt->execute([
        'client_id' => $user['id']
    ]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "All notifications marked as read.";
    } else {
        $_SESSION['info'] = "No unread notifications to mark.";
    }
    
} catch (PDOException $e) {
    error_log("Error marking all notifications as read: " . $e->getMessage());
    $_SESSION['error'] = "Something went wrong. Please try again.";
}

// Redirect back to the appropriate page
header('Location: ' . $redirect);
exit; 
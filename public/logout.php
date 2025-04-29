<?php
/**
 * Logout
 * 
 * This file handles user logout by destroying the session
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include auth utilities
require_once '../includes/auth.php';

// Log the logout activity if user was logged in
if (isLoggedIn()) {
    logActivity('user_logout', 'User logged out');
    
    // Clear remember me cookie if exists
    if (isset($_COOKIE['remember_token']) && isset($_COOKIE['remember_user'])) {
        // Delete token from database
        try {
            require_once '../config/db.php';
            $sql = "DELETE FROM remember_tokens WHERE user_id = :user_id";
            $stmt = db()->prepare($sql);
            $stmt->execute(['user_id' => $_SESSION['user_id']]);
        } catch (PDOException $e) {
            error_log("Error removing remember token: " . $e->getMessage());
        }
        
        // Expire cookies
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        setcookie('remember_user', '', time() - 3600, '/', '', false, true);
    }
}

// Destroy the session
$_SESSION = array();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Set success message in a new session
session_start();
$_SESSION['success'] = "You have been successfully logged out.";

// Redirect to login page
header('Location: login.php');
exit; 
<?php
/**
 * Authentication Utility
 * 
 * This file handles user authentication, session management, and 
 * role-based access control for the document tracking system.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user has required role
 * 
 * @param string|array $roles Role or array of roles to check against
 * @return bool True if user has the required role, false otherwise
 */
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Convert single role to array for consistent handling
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    return in_array($_SESSION['user_role'], $roles);
}

/**
 * Require user to be logged in
 * Redirects to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        // Store the requested URL to redirect back after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        // Redirect to login page
        header('Location: ../public/login.php');
        exit;
    }
}

/**
 * Require user to have specific role(s)
 * Redirects to dashboard with error message if not authorized
 * 
 * @param string|array $roles Role or array of roles allowed to access
 */
function requireRole($roles) {
    requireLogin();
    
    if (!hasRole($roles)) {
        $_SESSION['error'] = "You don't have permission to access this page.";
        header('Location: ../public/dashboard.php');
        exit;
    }
}

/**
 * Get the current user's information
 * 
 * @return array|null User information or null if not logged in
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    require_once __DIR__ . '/../config/db.php';
    
    $sql = "SELECT id, name, email, role, department FROM users WHERE id = :id";
    $stmt = db()->prepare($sql);
    $stmt->execute(['id' => $_SESSION['user_id']]);
    
    return $stmt->fetch();
}

/**
 * Creates a CSRF token and stores it in the session
 * 
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates a CSRF token against the one stored in session
 * 
 * @param string $token The token to validate
 * @return bool True if token is valid, false otherwise
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if user can approve documents
 * Only supervisors and admins can approve documents
 * 
 * @return bool True if user can approve documents, false otherwise
 */
function canApproveDocuments() {
    return hasRole(['admin', 'supervisor']);
}

/**
 * Log user activity
 * 
 * @param string $action The action performed
 * @param string $details Additional details about the action
 * @param int|null $user_id Optional user ID (for cases when user is not logged in)
 */
function logActivity($action, $details = '', $user_id = null) {
    // If user_id is not provided, try to get it from session
    if ($user_id === null) {
        if (!isLoggedIn()) {
            return;
        }
        $user_id = $_SESSION['user_id'];
    }
    
    require_once __DIR__ . '/../config/db.php';
    
    $sql = "INSERT INTO activity_logs (user_id, action, details, ip_address) 
            VALUES (:user_id, :action, :details, :ip)";
    
    $stmt = db()->prepare($sql);
    $stmt->execute([
        'user_id' => $user_id,
        'action' => $action,
        'details' => $details,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
} 
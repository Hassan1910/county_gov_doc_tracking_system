<?php
/**
 * Reset Password Process
 * 
 * Processes password reset form submission and updates the user's password.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ../public/dashboard.php');
    exit;
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid form submission. Please try again.";
        header('Location: ../public/forgot_password.php');
        exit;
    }
    
    // Get form data
    $token = $_POST['token'] ?? '';
    $email = $_POST['email'] ?? '';
    $userId = intval($_POST['user_id'] ?? 0);
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Validate input
    if (empty($token) || empty($email) || empty($userId) || empty($password) || empty($password_confirm)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: ../public/reset_password.php?token={$token}&email=" . urlencode($email));
        exit;
    }
    
    // Check if passwords match
    if ($password !== $password_confirm) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: ../public/reset_password.php?token={$token}&email=" . urlencode($email));
        exit;
    }
    
    // Validate password complexity
    $password_regex = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/";
    if (!preg_match($password_regex, $password)) {
        $_SESSION['error'] = "Password must be at least 8 characters long and include uppercase and lowercase letters, numbers, and special characters.";
        header("Location: ../public/reset_password.php?token={$token}&email=" . urlencode($email));
        exit;
    }
    
    try {
        // Verify user exists
        $sql = "SELECT id FROM users WHERE id = :id AND email = :email";
        $stmt = db()->prepare($sql);
        $stmt->execute([
            'id' => $userId,
            'email' => $email
        ]);
        
        if ($stmt->rowCount() === 0) {
            $_SESSION['error'] = "Invalid user or email. Please request a new password reset.";
            header('Location: ../public/forgot_password.php');
            exit;
        }
        
        // Verify token is valid
        $sql = "SELECT token, expires_at FROM password_resets 
                WHERE user_id = :user_id AND expires_at > NOW()
                ORDER BY created_at DESC LIMIT 1";
        $stmt = db()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        if ($stmt->rowCount() === 0) {
            $_SESSION['error'] = "Password reset link has expired. Please request a new one.";
            header('Location: ../public/forgot_password.php');
            exit;
        }
        
        $resetData = $stmt->fetch();
        
        // Verify token
        if (!password_verify($token, $resetData['token'])) {
            $_SESSION['error'] = "Invalid reset token. Please request a new password reset.";
            header('Location: ../public/forgot_password.php');
            exit;
        }
        
        // All validations passed, update password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "UPDATE users SET password = :password WHERE id = :id";
        $stmt = db()->prepare($sql);
        $stmt->execute([
            'password' => $hashed_password,
            'id' => $userId
        ]);
        
        // Delete used token
        $sql = "DELETE FROM password_resets WHERE user_id = :user_id";
        $stmt = db()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        // Log the activity
        logActivity('password_reset', "Password reset completed for user ID: {$userId}");
        
        // Set success message
        $_SESSION['success'] = "Your password has been successfully reset. You can now log in with your new password.";
        
        // Redirect to login page
        header('Location: ../public/login.php');
        exit;
        
    } catch (PDOException $e) {
        // Log error and show generic error message
        error_log("Reset password process error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while resetting your password. Please try again.";
        header("Location: ../public/reset_password.php?token={$token}&email=" . urlencode($email));
        exit;
    }
    
} else {
    // If not a POST request, redirect to forgot password page
    header('Location: ../public/forgot_password.php');
    exit;
} 
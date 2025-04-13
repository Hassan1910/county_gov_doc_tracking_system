<?php
/**
 * Login Process
 * 
 * Handles user authentication and session creation
 */

// Enable detailed error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once '../config/db.php';
require_once '../includes/auth.php';

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
        header('Location: ../public/login.php');
        exit;
    }
    
    // Get and sanitize user input
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Basic validation
    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Email and password are required.";
        header('Location: ../public/login.php?email=' . urlencode($email));
        exit;
    }
    
    try {
        // Prepare database query
        $sql = "SELECT id, name, email, password, role, department FROM users WHERE email = :email";
        $stmt = db()->prepare($sql);
        $stmt->execute(['email' => $email]);
        
        // Check if user exists
        if ($stmt->rowCount() === 0) {
            $_SESSION['error'] = "Invalid email or password.";
            header('Location: ../public/login.php?email=' . urlencode($email));
            exit;
        }
        
        // Get user data
        $user = $stmt->fetch();
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            $_SESSION['error'] = "Invalid email or password.";
            header('Location: ../public/login.php?email=' . urlencode($email));
            exit;
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_department'] = $user['department'];
        
        // Set remember me cookie if requested (30 days)
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expires = time() + (30 * 24 * 60 * 60); // 30 days
            
            // Store token in database
            $sql = "INSERT INTO remember_tokens (user_id, token, expires_at) 
                    VALUES (:user_id, :token, :expires_at)";
            $stmt = db()->prepare($sql);
            $stmt->execute([
                'user_id' => $user['id'],
                'token' => password_hash($token, PASSWORD_DEFAULT),
                'expires_at' => date('Y-m-d H:i:s', $expires)
            ]);
            
            // Set cookie with token
            setcookie('remember_token', $token, $expires, '/', '', false, true);
            setcookie('remember_user', $user['id'], $expires, '/', '', false, true);
        }
        
        // Log the successful login
        logActivity('user_login', 'User logged in successfully');
        
        // Redirect based on user role
        if ($user['role'] === 'client') {
            $redirect = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : '../public/client_dashboard.php';
        } else {
            $redirect = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : '../public/dashboard.php';
        }
        unset($_SESSION['redirect_after_login']);
        
        header('Location: ' . $redirect);
        exit;
        
    } catch (PDOException $e) {
        // Log error and show generic error message
        error_log("Login error: " . $e->getMessage());
        $_SESSION['error'] = "A system error occurred. Please try again later.";
        header('Location: ../public/login.php?email=' . urlencode($email));
        exit;
    }
    
} else {
    // If not a POST request, redirect to login page
    header('Location: ../public/login.php');
    exit;
} 
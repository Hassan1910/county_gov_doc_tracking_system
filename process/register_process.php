<?php
/**
 * Registration Process
 * 
 * Handles new user registration, validation, and database insertion
 */

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
        header('Location: ../public/register.php');
        exit;
    }
    
    // Get and sanitize user input
    $name = filter_var($_POST['name'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $department = filter_var($_POST['department'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $role = filter_var($_POST['role'] ?? 'clerk', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $terms = isset($_POST['terms']);
    
    // Initialize errors array
    $errors = [];
    
    // Basic validation
    if (empty($name)) {
        $errors[] = "Full name is required.";
    }
    
    if (empty($email)) {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    if (empty($department)) {
        $errors[] = "Department is required.";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.";
    }
    
    if (empty($password_confirm)) {
        $errors[] = "Please confirm your password.";
    } elseif ($password !== $password_confirm) {
        $errors[] = "Passwords do not match.";
    }
    
    if (!$terms) {
        $errors[] = "You must agree to the Terms of Service and Privacy Policy.";
    }
    
    // If validation passes, check if email already exists
    if (empty($errors)) {
        try {
            $sql = "SELECT COUNT(*) FROM users WHERE email = :email";
            $stmt = db()->prepare($sql);
            $stmt->execute(['email' => $email]);
            
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Email address is already registered. Please use a different email.";
            }
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $errors[] = "A system error occurred. Please try again later.";
        }
    }
    
    // If there are errors, redirect back to registration form with error messages
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        
        // Keep form data for repopulating fields
        $query_params = http_build_query([
            'name' => $name,
            'email' => $email,
            'department' => $department
        ]);
        
        header('Location: ../public/register.php?' . $query_params);
        exit;
    }
    
    // All validation passed, create new user
    try {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Prepare insert statement
        $sql = "INSERT INTO users (name, email, password, role, department, created_at) 
                VALUES (:name, :email, :password, :role, :department, NOW())";
        
        $stmt = db()->prepare($sql);
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password' => $hashed_password,
            'role' => $role,
            'department' => $department
        ]);
        
        // Set success message
        $_SESSION['success'] = "Registration successful! You can now log in.";
        
        // Redirect to login page
        header('Location: ../public/login.php?email=' . urlencode($email));
        exit;
        
    } catch (PDOException $e) {
        // Log error and show generic error message
        error_log("Registration error: " . $e->getMessage());
        $_SESSION['error'] = "A system error occurred during registration. Please try again later.";
        
        // Keep form data for repopulating fields
        $query_params = http_build_query([
            'name' => $name,
            'email' => $email,
            'department' => $department
        ]);
        
        header('Location: ../public/register.php?' . $query_params);
        exit;
    }
    
} else {
    // If not a POST request, redirect to registration page
    header('Location: ../public/register.php');
    exit;
} 
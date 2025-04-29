<?php
/**
 * Register Client Process
 * 
 * Process form submission for clerk-initiated client registration
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection and utility functions
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Redirect if not logged in or not authorized (admin or clerk)
if (!isLoggedIn() || !hasRole(['admin', 'clerk'])) {
    $_SESSION['error'] = "You are not authorized to register clients.";
    header('Location: ../public/login.php');
    exit;
}

// Validate CSRF token
validateCSRFToken($_POST['csrf_token']);

// Validate form data
if (
    !isset($_POST['name']) || empty(trim($_POST['name'])) ||
    !isset($_POST['email']) || empty(trim($_POST['email'])) ||
    !isset($_POST['phone']) || empty(trim($_POST['phone'])) ||
    !isset($_POST['password']) || empty($_POST['password']) ||
    !isset($_POST['password_confirm']) || empty($_POST['password_confirm'])
) {
    $_SESSION['error'] = "All fields are required.";
    redirectWithInputs('../public/register_client.php', $_POST);
}

// Extract and sanitize form data
$name = sanitizeInput($_POST['name']);
$email = sanitizeInput($_POST['email']);
$phone = sanitizeInput($_POST['phone']);
$password = $_POST['password'];
$password_confirm = $_POST['password_confirm'];
$role = 'client'; // Force role to be client

// Check if passwords match
if ($password !== $password_confirm) {
    $_SESSION['error'] = "Passwords do not match.";
    redirectWithInputs('../public/register_client.php', $_POST);
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Invalid email format.";
    redirectWithInputs('../public/register_client.php', $_POST);
}

// Check password complexity (8+ chars, uppercase, lowercase, number, special char)
$password_regex = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/";
if (!preg_match($password_regex, $password)) {
    $_SESSION['error'] = "Password must be at least 8 characters long and include uppercase and lowercase letters, numbers, and special characters.";
    redirectWithInputs('../public/register_client.php', $_POST);
}

// Check if email already exists
$sql = "SELECT COUNT(*) FROM users WHERE email = :email";
$stmt = db()->prepare($sql);
$stmt->execute(['email' => $email]);

if ($stmt->fetchColumn() > 0) {
    $_SESSION['error'] = "Email address already in use. Please choose another.";
    redirectWithInputs('../public/register_client.php', $_POST);
}

// Hash password for storage
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Get current user (clerk/admin) who is registering the client
$registrar_id = getCurrentUserId();

// Create user account with client role and registered_by field
$sql = "INSERT INTO users (name, email, role, password, created_at) 
        VALUES (:name, :email, :role, :password, NOW())";
$stmt = db()->prepare($sql);
$result = $stmt->execute([
    'name' => $name,
    'email' => $email,
    'role' => $role,
    'password' => $password_hash
]);

if ($result) {
    $user_id = db()->lastInsertId();
    
    // Log the client registration
    logActivity('register_client', "Registered new client: $name ($email)");
    
    // Success message
    $_SESSION['success'] = "Client account created successfully! The client can now login with their email and password.";
    header('Location: ../public/dashboard.php');
    exit;
} else {
    // Database error
    $_SESSION['error'] = "Error creating account. Please try again.";
    redirectWithInputs('../public/register_client.php', $_POST);
}

// Function to redirect with form data
function redirectWithInputs($page, $inputs) {
    $query = [];
    
    // Add relevant inputs to query string
    if (isset($inputs['name'])) $query['name'] = urlencode($inputs['name']);
    if (isset($inputs['email'])) $query['email'] = urlencode($inputs['email']);
    if (isset($inputs['phone'])) $query['phone'] = urlencode($inputs['phone']);
    
    // Build query string
    $query_string = http_build_query($query);
    
    // Redirect with inputs
    header("Location: $page" . ($query_string ? "?$query_string" : ""));
    exit;
} 
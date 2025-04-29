<?php
/**
 * Forgot Password Page
 * 
 * This page allows users to reset their password by entering their email address.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include auth utilities
require_once '../includes/auth.php';
require_once '../config/db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Initialize variables
$email_verified = false;
$user_id = 0;
$email = '';

// Check if email verification form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_email'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid form submission. Please try again.";
        header('Location: forgot_password.php');
        exit;
    }
    
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email address.";
    } else {
        try {
            // Check if email exists in the database
            $sql = "SELECT id, email FROM users WHERE email = :email";
            $stmt = db()->prepare($sql);
            $stmt->execute(['email' => $email]);
            
            if ($stmt->rowCount() === 0) {
                $_SESSION['error'] = "Email address not found in our records.";
            } else {
                // Email verified, allow password reset
                $user = $stmt->fetch();
                $email_verified = true;
                $user_id = $user['id'];
            }
        } catch (PDOException $e) {
            error_log("Email verification error: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred. Please try again later.";
        }
    }
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid form submission. Please try again.";
        header('Location: forgot_password.php');
        exit;
    }
    
    $user_id = (int)($_POST['user_id'] ?? 0);
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if ($user_id === 0) {
        $_SESSION['error'] = "Invalid user information. Please try again.";
    } elseif (empty($new_password)) {
        $_SESSION['error'] = "New password is required.";
        $email_verified = true; // Keep the form in password reset mode
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        $email_verified = true; // Keep the form in password reset mode
    } elseif (strlen($new_password) < 8) {
        $_SESSION['error'] = "Password must be at least 8 characters long.";
        $email_verified = true; // Keep the form in password reset mode
    } else {
        try {
            // Verify that the user exists
            $sql = "SELECT id FROM users WHERE id = :id AND email = :email";
            $stmt = db()->prepare($sql);
            $stmt->execute(['id' => $user_id, 'email' => $email]);
            
            if ($stmt->rowCount() === 0) {
                $_SESSION['error'] = "Invalid user information. Please try again.";
                header('Location: forgot_password.php');
                exit;
            }
            
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update user's password
            $sql = "UPDATE users SET password = :password WHERE id = :id";
            $stmt = db()->prepare($sql);
            $stmt->execute([
                'password' => $hashed_password,
                'id' => $user_id
            ]);
            
            // Log the activity
            logActivity('password_reset', "Password reset for user ID: {$user_id}", $user_id);
            
            // Set success message
            $_SESSION['success'] = "Your password has been reset successfully. You can now log in with your new password.";
            
            // Redirect to login page
            header('Location: login.php');
            exit;
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred. Please try again later.";
            $email_verified = true; // Keep the form in password reset mode
        }
    }
}

// Set page title
$page_title = 'Forgot Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - County Government Document Tracker</title>
    
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom Tailwind Configuration -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            300: '#86efac',
                            400: '#4ade80',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532d',
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <!-- Header Strip -->
    <div class="bg-primary-700 text-white py-4 shadow-lg">
        <div class="container mx-auto px-4 flex items-center">
            <i class="fas fa-file-alt text-2xl mr-2"></i>
            <span class="font-bold text-xl">County Gov Document Tracker</span>
        </div>
    </div>
    
    <!-- Flash Messages -->
    <?php if(isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mt-4 mx-auto max-w-md" role="alert">
            <span class="block sm:inline"><?= $_SESSION['success']; ?></span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg onclick="this.parentElement.parentElement.style.display='none'" class="fill-current h-6 w-6 text-green-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
            </span>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mt-4 mx-auto max-w-md" role="alert">
            <span class="block sm:inline"><?= $_SESSION['error']; ?></span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg onclick="this.parentElement.parentElement.style.display='none'" class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
            </span>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Page Content -->
    <div class="flex-grow flex items-center justify-center p-4">
        <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
            <?php if (!$email_verified): ?>
            <!-- Step 1: Verify Email Form -->
            <div class="text-center mb-8">
                <i class="fas fa-lock-open text-primary-500 text-4xl mb-4"></i>
                <h1 class="text-2xl font-bold text-gray-800 mb-2">Forgot Your Password?</h1>
                <p class="text-gray-600">Enter your email address to reset your password</p>
            </div>
            
            <form action="forgot_password.php" method="POST" class="space-y-6">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                <input type="hidden" name="verify_email" value="1">
                
                <!-- Email Field -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                        <input type="email" id="email" name="email" 
                            class="pl-10 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm py-2 px-3 border" 
                            placeholder="your.email@example.com" 
                            required 
                            autofocus>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <div>
                    <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        <i class="fas fa-search mr-2"></i> Verify Email
                    </button>
                </div>
            </form>
            <?php else: ?>
            <!-- Step 2: Reset Password Form -->
            <div class="text-center mb-8">
                <i class="fas fa-key text-primary-500 text-4xl mb-4"></i>
                <h1 class="text-2xl font-bold text-gray-800 mb-2">Reset Your Password</h1>
                <p class="text-gray-600">Enter a new password for your account</p>
            </div>
            
            <form action="forgot_password.php" method="POST" class="space-y-6" id="resetPasswordForm">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                <input type="hidden" name="reset_password" value="1">
                <input type="hidden" name="user_id" value="<?= $user_id; ?>">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email); ?>">
                
                <!-- Email Display (non-editable) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                        <input type="text" 
                            class="pl-10 block w-full bg-gray-50 border-gray-300 rounded-md shadow-sm sm:text-sm py-2 px-3 border" 
                            value="<?= htmlspecialchars($email); ?>"
                            disabled>
                    </div>
                </div>
                
                <!-- New Password Field -->
                <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input type="password" id="new_password" name="new_password" 
                            class="pl-10 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm py-2 px-3 border" 
                            placeholder="••••••••" 
                            required 
                            minlength="8">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer">
                            <i class="fas fa-eye text-gray-400 toggle-password" onclick="togglePasswordVisibility('new_password')"></i>
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        At least 8 characters, including uppercase, lowercase letters, numbers and special characters
                    </p>
                </div>
                
                <!-- Confirm Password Field -->
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input type="password" id="confirm_password" name="confirm_password" 
                            class="pl-10 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm py-2 px-3 border" 
                            placeholder="••••••••" 
                            required>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer">
                            <i class="fas fa-eye text-gray-400 toggle-password" onclick="togglePasswordVisibility('confirm_password')"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <div>
                    <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        <i class="fas fa-save mr-2"></i> Reset Password
                    </button>
                </div>
            </form>
            <?php endif; ?>
            
            <!-- Back to Login Link -->
            <div class="text-center mt-4">
                <p class="text-sm text-gray-600">
                    <a href="login.php" class="font-medium text-primary-600 hover:text-primary-500">
                        <i class="fas fa-arrow-left mr-1"></i> Back to login
                    </a>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-primary-800 text-white py-4 mt-auto">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; <?= date('Y'); ?> County Government Document Tracking System</p>
        </div>
    </footer>
    
    <!-- JavaScript for Password Toggle and Validation -->
    <script>
        // Toggle password visibility
        function togglePasswordVisibility(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const eyeIcon = document.querySelector(`#${fieldId} + div > .toggle-password`);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }
        
        // Form validation for password reset form
        if (document.getElementById('resetPasswordForm')) {
            document.getElementById('resetPasswordForm').addEventListener('submit', function(event) {
                const password = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                // Check if passwords match
                if (password !== confirmPassword) {
                    event.preventDefault();
                    alert('The passwords do not match. Please try again.');
                }
            });
        }
        
        // Auto dismiss flash messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.bg-green-100, .bg-red-100');
                alerts.forEach(function(alert) {
                    alert.style.display = 'none';
                });
            }, 5000);
        });
    </script>
</body>
</html> 
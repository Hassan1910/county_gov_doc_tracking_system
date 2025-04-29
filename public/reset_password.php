<?php
/**
 * Password Reset Page
 * 
 * This page allows users to reset their password using a valid reset token.
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
    header('Location: dashboard.php');
    exit;
}

// Initialize variables
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';
$tokenValid = false;
$userId = null;

// Validate token and email
if (empty($token) || empty($email)) {
    $_SESSION['error'] = "Invalid password reset link. Please request a new one.";
    header('Location: forgot_password.php');
    exit;
}

// Check if the token is valid
try {
    // Get user by email
    $sql = "SELECT id FROM users WHERE email = :email";
    $stmt = db()->prepare($sql);
    $stmt->execute(['email' => $email]);
    
    if ($stmt->rowCount() === 0) {
        $_SESSION['error'] = "Invalid password reset link. Please request a new one.";
        header('Location: forgot_password.php');
        exit;
    }
    
    $userId = $stmt->fetchColumn();
    
    // Check if there's a valid reset token for this user
    $sql = "SELECT token, expires_at FROM password_resets 
            WHERE user_id = :user_id AND expires_at > NOW()
            ORDER BY created_at DESC LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute(['user_id' => $userId]);
    
    if ($stmt->rowCount() === 0) {
        $_SESSION['error'] = "Password reset link has expired. Please request a new one.";
        header('Location: forgot_password.php');
        exit;
    }
    
    $resetData = $stmt->fetch();
    
    // Verify the token
    if (!password_verify($token, $resetData['token'])) {
        $_SESSION['error'] = "Invalid password reset token. Please request a new one.";
        header('Location: forgot_password.php');
        exit;
    }
    
    // Token is valid
    $tokenValid = true;
    
} catch (PDOException $e) {
    error_log("Reset password error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred. Please try again later.";
    header('Location: forgot_password.php');
    exit;
}

// Generate CSRF token for the form
$csrf_token = generateCSRFToken();

// Set page title
$page_title = 'Reset Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - County Government Document Tracker</title>
    
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
    <?php if(isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mt-4 mx-auto max-w-md" role="alert">
            <span class="block sm:inline"><?= $_SESSION['error']; ?></span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg onclick="this.parentElement.parentElement.style.display='none'" class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
            </span>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Reset Password Form -->
    <div class="flex-grow flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
            <div class="text-center mb-8">
                <i class="fas fa-lock-open text-primary-500 text-4xl mb-4"></i>
                <h1 class="text-2xl font-bold text-gray-800 mb-2">Reset Your Password</h1>
                <p class="text-gray-600">Create a new password for your account</p>
            </div>
            
            <form action="../process/reset_password_process.php" method="POST" class="space-y-6" id="resetPasswordForm">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token); ?>">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email); ?>">
                <input type="hidden" name="user_id" value="<?= $userId; ?>">
                
                <!-- Password Field -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input type="password" id="password" name="password" 
                            class="pl-10 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm py-2 px-3 border" 
                            placeholder="••••••••" 
                            required 
                            minlength="8">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer">
                            <i class="fas fa-eye text-gray-400 toggle-password" onclick="togglePasswordVisibility('password')"></i>
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        At least 8 characters, including uppercase and lowercase letters, numbers, and special characters
                    </p>
                </div>
                
                <!-- Confirm Password Field -->
                <div>
                    <label for="password_confirm" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input type="password" id="password_confirm" name="password_confirm" 
                            class="pl-10 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm py-2 px-3 border" 
                            placeholder="••••••••" 
                            required>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer">
                            <i class="fas fa-eye text-gray-400 toggle-password" onclick="togglePasswordVisibility('password_confirm')"></i>
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
        
        // Form validation
        document.getElementById('resetPasswordForm').addEventListener('submit', function(event) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('password_confirm').value;
            
            // Check if passwords match
            if (password !== confirmPassword) {
                event.preventDefault();
                alert('Passwords do not match. Please try again.');
                return false;
            }
            
            // Check password complexity
            const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
            if (!passwordRegex.test(password)) {
                event.preventDefault();
                alert('Password must be at least 8 characters long and include uppercase and lowercase letters, numbers, and special characters.');
                return false;
            }
            
            return true;
        });
        
        // Auto dismiss flash messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.bg-red-100');
                alerts.forEach(function(alert) {
                    alert.style.display = 'none';
                });
            }, 5000);
        });
    </script>
</body>
</html> 
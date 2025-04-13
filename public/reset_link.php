<?php
/**
 * Reset Link Display Page
 * 
 * This page displays the reset link for demonstration purposes.
 * In a production environment, this would be sent via email.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if no reset link is in session
if (!isset($_SESSION['reset_link'])) {
    header('Location: forgot_password.php');
    exit;
}

// Get the reset link from session
$resetLink = $_SESSION['reset_link'];

// Set page title
$page_title = 'Password Reset Link';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Link - County Government Document Tracker</title>
    
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
    
    <!-- Reset Link Information -->
    <div class="flex-grow flex items-center justify-center p-4">
        <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-lg">
            <div class="text-center mb-8">
                <i class="fas fa-envelope-open-text text-primary-500 text-5xl mb-4"></i>
                <h1 class="text-2xl font-bold text-gray-800 mb-2">Password Reset Link Generated</h1>
                <p class="text-gray-600">
                    In a real application, this link would be sent to your email. 
                    For demonstration purposes, you can use the link below to reset your password.
                </p>
            </div>
            
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            <strong>Note:</strong> This is for demonstration only. In a production environment, 
                            this link would be sent via email for security.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-50 p-4 rounded-md border border-gray-200 mb-6 overflow-x-auto">
                <p class="text-sm text-gray-600 mb-2">Your password reset link:</p>
                <a href="<?= htmlspecialchars($resetLink); ?>" class="text-sm font-mono text-blue-600 hover:text-blue-800 break-all">
                    <?= htmlspecialchars($resetLink); ?>
                </a>
            </div>
            
            <div class="text-center space-y-4">
                <a href="<?= htmlspecialchars($resetLink); ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <i class="fas fa-key mr-2"></i> Reset Password
                </a>
                
                <p class="text-sm text-gray-500">
                    This link will expire in 1 hour for security reasons.
                </p>
                
                <div class="border-t border-gray-200 pt-4">
                    <a href="login.php" class="text-sm text-primary-600 hover:text-primary-500">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-primary-800 text-white py-4 mt-auto">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; <?= date('Y'); ?> County Government Document Tracking System</p>
        </div>
    </footer>
</body>
</html>

<?php
// Clear the reset link from session after displaying it once
unset($_SESSION['reset_link']);
?> 
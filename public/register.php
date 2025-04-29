<?php
/**
 * Registration Page
 * 
 * This page allows new users to register for the document tracking system.
 */

// Include authentication utilities
include_once '../includes/auth.php';

// Redirect if already logged in, but allow admin/supervisor to register manager
if (isLoggedIn()) {
    // Allow admin (and supervisor if desired) to access manager registration
    if (
        isset($_SESSION['user_role']) &&
        in_array($_SESSION['user_role'], ['admin', 'supervisor']) &&
        isset($_GET['role']) && $_GET['role'] === 'manager'
    ) {
        // Allow access
    } else {
        header('Location: dashboard.php');
        exit;
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Set page title
$page_title = 'Register';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - County Government Document Tracker</title>
    
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
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mt-4 mx-auto max-w-lg" role="alert">
            <span class="block sm:inline"><?= $_SESSION['success']; ?></span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg onclick="this.parentElement.parentElement.style.display='none'" class="fill-current h-6 w-6 text-green-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
            </span>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mt-4 mx-auto max-w-lg" role="alert">
            <span class="block sm:inline"><?= $_SESSION['error']; ?></span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg onclick="this.parentElement.parentElement.style.display='none'" class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
            </span>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Registration Form -->
    <div class="flex-grow flex items-center justify-center py-8">
        <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-lg">
            <div class="text-center mb-8">
                <?php if (isset($_GET['role']) && $_GET['role'] === 'manager'): ?>
                    <h1 class="text-2xl font-bold text-orange-700 mb-2">Register a Manager</h1>
                    <p class="text-gray-600">Fill out the form below to add a new manager to the system.</p>
                <?php else: ?>
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">Create an Account</h1>
                    <p class="text-gray-600">Register to use the county government document tracking system</p>
                <?php endif; ?>
            </div>
            
            <form action="../process/register_process.php" method="POST" class="space-y-6" id="registrationForm">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                
                <!-- Name Field -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user text-gray-400"></i>
                        </div>
                        <input type="text" id="name" name="name" 
                            class="pl-10 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm py-2 px-3 border" 
                            placeholder="Hassan Adan" 
                            required 
                            autofocus
                            value="<?= isset($_GET['name']) ? htmlspecialchars($_GET['name']) : ''; ?>">
                    </div>
                </div>
                
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
                            value="<?= isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>">
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Use your official county government email address if possible
                    </p>
                </div>
                
                <!-- Department Field -->
                <div>
                    <label for="department" class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-building text-gray-400"></i>
                        </div>
                        <select id="department" name="department" 
                            class="pl-10 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm py-2 px-3 border"
                            required>
                            <option value="">Select Department</option>
                            <option value="Finance" <?= (isset($_GET['department']) && $_GET['department'] === 'Finance') ? 'selected' : ''; ?>>Finance</option>
                            <option value="Health" <?= (isset($_GET['department']) && $_GET['department'] === 'Health') ? 'selected' : ''; ?>>Health</option>
                            <option value="Agriculture" <?= (isset($_GET['department']) && $_GET['department'] === 'Agriculture') ? 'selected' : ''; ?>>Agriculture</option>
                            <option value="Education" <?= (isset($_GET['department']) && $_GET['department'] === 'Education') ? 'selected' : ''; ?>>Education</option>
                            <option value="Infrastructure" <?= (isset($_GET['department']) && $_GET['department'] === 'Infrastructure') ? 'selected' : ''; ?>>Infrastructure</option>
                            <option value="IT" <?= (isset($_GET['department']) && $_GET['department'] === 'IT') ? 'selected' : ''; ?>>IT</option>
                            <option value="Administration" <?= (isset($_GET['department']) && $_GET['department'] === 'Administration') ? 'selected' : ''; ?>>Administration</option>
                            <option value="Legal" <?= (isset($_GET['department']) && $_GET['department'] === 'Legal') ? 'selected' : ''; ?>>Legal</option>
                        </select>
                    </div>
                </div>
                
                <!-- Role Field (Default to clerk, admin will change later) -->
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Account Type</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user-tag text-gray-400"></i>
                        </div>
                        <?php if (isset($_GET['role']) && $_GET['role'] === 'manager'): ?>
                        <input type="hidden" name="role" value="manager">
                        <select id="role" name="role" class="pl-10 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm py-2 px-3 border bg-gray-100 cursor-not-allowed" readonly tabindex="-1">
                            <option value="manager" selected>Manager</option>
                        </select>
                        <?php else: ?>
                        <select id="role" name="role" class="pl-10 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm py-2 px-3 border" required>
                            <option value="clerk" selected>County Staff</option>
                        </select>
                        <?php endif; ?>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        <?php if (isset($_GET['role']) && $_GET['role'] === 'manager'): ?>
                            Registering a new manager account.
                        <?php else: ?>
                            Only county staff can register here. Client accounts must be created by staff members.
                        <?php endif; ?>
                    </p>
                </div>
                
                <!-- Password Field -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
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
                        At least 8 characters, including a mix of uppercase and lowercase letters, numbers, and special characters
                    </p>
                </div>
                
                <!-- Confirm Password Field -->
                <div>
                    <label for="password_confirm" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
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
                
                <!-- Terms and Conditions -->
                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <input id="terms" name="terms" type="checkbox" 
                            class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                            required>
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="terms" class="font-medium text-gray-700">
                            I agree to the <a href="#" class="text-primary-600 hover:text-primary-500">Terms of Service</a> and <a href="#" class="text-primary-600 hover:text-primary-500">Privacy Policy</a>
                        </label>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <div>
                    <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        <i class="fas fa-user-plus mr-2"></i> Create Account
                    </button>
                </div>
            </form>
            
            <!-- Login Link -->
            <div class="text-center mt-4">
                <p class="text-sm text-gray-600">
                    Already have an account? 
                    <a href="login.php" class="font-medium text-primary-600 hover:text-primary-500">
                        Log in
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
    
    <!-- JavaScript for Password Toggle and Form Validation -->
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
        document.getElementById('registrationForm').addEventListener('submit', function(event) {
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
                const alerts = document.querySelectorAll('.bg-green-100, .bg-red-100');
                alerts.forEach(function(alert) {
                    alert.style.display = 'none';
                });
            }, 5000);
        });
    </script>
</body>
</html>
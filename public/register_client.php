<?php
/**
 * Client Registration Page
 * 
 * This page allows clerks and admins to register new clients for the document tracking system.
 */

// Include authentication utilities
include_once '../includes/auth.php';

// Redirect if not logged in or not authorized
if (!isLoggedIn() || !hasRole(['admin', 'clerk'])) {
    $_SESSION['error'] = "You are not authorized to access this page.";
    header('Location: login.php');
    exit;
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Set page title and include header
$page_title = 'Register Client';
include_once '../includes/header.php';
?>

<!-- Client Registration Form -->
<div class="container mx-auto px-4 py-8">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-lg mx-auto">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Register New Client</h1>
            <p class="text-gray-600">Create an account for a new client in the system</p>
        </div>
        
        <form action="../process/register_client_process.php" method="POST" class="space-y-6" id="registrationForm">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
            
            <!-- Name Field -->
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Client Full Name</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-user text-gray-400"></i>
                    </div>
                    <input type="text" id="name" name="name" 
                        class="pl-10 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm py-2 px-3 border" 
                        placeholder="John Doe" 
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
                        placeholder="client.email@example.com" 
                        required
                        value="<?= isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>">
                </div>
            </div>
            
            <!-- Phone Number Field -->
            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-phone text-gray-400"></i>
                    </div>
                    <input type="text" id="phone" name="phone" 
                        class="pl-10 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm py-2 px-3 border" 
                        placeholder="+254 7XX XXX XXX" 
                        required
                        value="<?= isset($_GET['phone']) ? htmlspecialchars($_GET['phone']) : ''; ?>">
                </div>
            </div>
            
            <!-- Hidden Role Field -->
            <input type="hidden" name="role" value="client">
            
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
            
            <!-- Submit Button -->
            <div>
                <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <i class="fas fa-user-plus mr-2"></i> Register Client
                </button>
            </div>
        </form>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>

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
</script> 
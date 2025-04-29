<?php
// (Removed debug output as deletion works correctly)

/**
 * User Management Page
 * 
 * This page allows administrators to manage system users.
 */

// Include authentication utilities
require_once '../includes/auth.php';

// Require login to access this page
requireLogin();

// Check if user has admin privileges
if (!hasRole('admin')) {
    $_SESSION['error'] = "You don't have permission to access the user management page.";
    header('Location: dashboard.php');
    exit;
}

// Include database connection
require_once '../config/db.php';

// Initialize variables
$users = [];
$departments = [];
$action = $_GET['action'] ?? '';
$userId = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
$userToEdit = null;
$errors = [];

// Get departments list
try {
    $sql = "SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department";
    $stmt = db()->prepare($sql);
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching departments: " . $e->getMessage());
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $errors[] = "Invalid request.";
    } else {
        // Determine form action
        $formAction = $_POST['form_action'] ?? '';
        
        if ($formAction === 'add_user' || $formAction === 'edit_user') {
            // Get and sanitize user input
            $name = filter_var($_POST['name'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $department = isset($_POST['department']) ? filter_var($_POST['department'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '';
            $role = filter_var($_POST['role'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $password = $_POST['password'] ?? '';
            $editUserId = isset($_POST['user_id']) ? filter_var($_POST['user_id'], FILTER_VALIDATE_INT) : null;
            
            // For debugging
            error_log("Form data - Role: $role, Department: " . (isset($_POST['department']) ? $_POST['department'] : 'NOT SET'));
            
            // Validate inputs
            if (empty($name)) {
                $errors[] = "Name is required.";
            }
            
            if (empty($email)) {
                $errors[] = "Email is required.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email format.";
            }
            
            // Department validation - only required for roles other than contractor and viewer
            if (!in_array($role, ['contractor', 'viewer', 'client'])) {
                if (empty($department)) {
                    $errors[] = "Department is required for this role.";
                }
            } else {
                // For contractor and viewer roles, department should be NULL
                $department = null;
                error_log("Setting department to NULL for role: $role");
            }
            
            // Role validation
            if (empty($role)) {
                $errors[] = "Role is required.";
            } elseif (!in_array($role, ['admin', 'clerk', 'assistant_manager', 'senior_manager', 'client', 'contractor', 'manager', 'supervisor', 'department_head', 'viewer'])) {
                $errors[] = "Invalid role selected.";
            }
            
            // Enforce max two managers per department
            if ($role === 'manager' && !empty($department)) {
                try {
                    $sql = "SELECT COUNT(*) FROM users WHERE role = 'manager' AND department = :dept";
                    if ($formAction === 'edit_user' && $editUserId) {
                        $sql .= " AND id != :id";
                    }
                    
                    $stmt = db()->prepare($sql);
                    $params = ['dept' => $department];
                    
                    if ($formAction === 'edit_user' && $editUserId) {
                        $params['id'] = $editUserId;
                    }
                    
                    error_log("Checking manager count with query: $sql and params: " . json_encode($params));
                    $stmt->execute($params);
                    $managerCount = $stmt->fetchColumn();
                    
                    error_log("Manager count for $department: $managerCount");
                    
                    if ($managerCount >= 2) {
                        $errors[] = "Each department can have a maximum of two managers.";
                    }
                } catch (PDOException $e) {
                    error_log("Manager count validation error: " . $e->getMessage());
                    $errors[] = "An error occurred while validating department managers.";
                }
            }
            
            // Check if email is already in use (except for current user when editing)
            try {
                $sql = "SELECT id FROM users WHERE email = :email";
                if ($formAction === 'edit_user' && $editUserId) {
                    $sql .= " AND id != :user_id";
                }
                
                $stmt = db()->prepare($sql);
                $params = ['email' => $email];
                
                if ($formAction === 'edit_user' && $editUserId) {
                    $params['user_id'] = $editUserId;
                }
                
                $stmt->execute($params);
                
                if ($stmt->rowCount() > 0) {
                    $errors[] = "Email address is already in use.";
                }
            } catch (PDOException $e) {
                error_log("Email validation error: " . $e->getMessage());
                $errors[] = "An error occurred while validating email.";
            }
            
            // If no errors, process the form
            if (empty($errors)) {
                try {
                    // Begin transaction
                    db()->beginTransaction();
                    
                    if ($formAction === 'add_user') {
                        // Adding a new user
                        if (empty($password)) {
                            $errors[] = "Password is required for new users.";
                        } else {
                            // For contractor or viewer roles, department should be NULL
                            if (in_array($role, ['contractor', 'viewer', 'client'])) {
                                $department = null;
                                error_log("Setting department to NULL for new $role user");
                            }
                            
                            // Hash password
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                            
                            // Insert new user
                            $sql = "INSERT INTO users (name, email, password, department, role, created_at) 
                                    VALUES (:name, :email, :password, :department, :role, NOW())";
                            $stmt = db()->prepare($sql);
                            $params = [
                                'name' => $name,
                                'email' => $email,
                                'password' => $hashedPassword,
                                'department' => $department,
                                'role' => $role
                            ];
                            error_log("Add user params: " . json_encode($params));
                            $stmt->execute($params);
                            
                            // Log activity
                            logActivity('user_add', "Added new user: {$name} ({$email})");
                            
                            // Set success message
                            $_SESSION['success'] = "User {$name} has been added successfully.";
                        }
                    } elseif ($formAction === 'edit_user' && $editUserId) {
                        // Editing existing user
                        try {
                            // For contractor or viewer roles, department should be NULL
                            if (in_array($role, ['contractor', 'viewer', 'client'])) {
                                $department = null;
                                error_log("Setting department to NULL for $role role");
                            }
                            
                            if (!empty($password)) {
                                // Update user with new password
                                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                                
                                $sql = "UPDATE users SET 
                                        name = :name, 
                                        email = :email, 
                                        password = :password, 
                                        department = :department, 
                                        role = :role
                                        WHERE id = :id";
                                $stmt = db()->prepare($sql);
                                $params = [
                                    'name' => $name,
                                    'email' => $email,
                                    'password' => $hashedPassword,
                                    'department' => $department,
                                    'role' => $role,
                                    'id' => $editUserId
                                ];
                                error_log("Edit user with password params: " . json_encode($params));
                                $stmt->execute($params);
                            } else {
                                // Update user without changing password
                                $sql = "UPDATE users SET 
                                        name = :name, 
                                        email = :email, 
                                        department = :department, 
                                        role = :role 
                                        WHERE id = :id";
                                $stmt = db()->prepare($sql);
                                $params = [
                                    'name' => $name,
                                    'email' => $email,
                                    'department' => $department,
                                    'role' => $role,
                                    'id' => $editUserId
                                ];
                                error_log("Edit user without password params: " . json_encode($params));
                                $stmt->execute($params);
                            }
                            
                            // Log activity
                            logActivity('user_edit', "Updated user: {$name} ({$email})");
                            
                            // Set success message
                            $_SESSION['success'] = "User {$name} has been updated successfully.";
                        } catch (PDOException $e) {
                            error_log("Detailed edit user error: " . $e->getMessage() . " SQL STATE: " . $e->getCode());
                            throw $e; // Re-throw to be caught by the outer catch block
                        }
                    }
                    
                    // Commit transaction
                    db()->commit();
                    
                    // Redirect to refresh page
                    header('Location: users.php');
                    exit;
                    
                } catch (PDOException $e) {
                    // Rollback transaction on error
                    if (db()->inTransaction()) {
                        db()->rollBack();
                    }
                    
                    error_log("User management error: " . $e->getMessage() . " - Code: " . $e->getCode() . " - Line: " . $e->getLine());
                    error_log("Error Trace: " . $e->getTraceAsString());
                    
                    $errors[] = "An error occurred while processing the request: " . $e->getMessage();
                }
            }
        } elseif ($formAction === 'delete_user' && isset($_POST['user_id'])) {
            $deleteUserId = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
            
            if ($deleteUserId) {
                try {
                    // Get user details before deleting
                    $stmt = db()->prepare("SELECT name, email FROM users WHERE id = :id");
                    $stmt->execute(['id' => $deleteUserId]);
                    $userToDelete = $stmt->fetch();
                    
                    if (!$userToDelete) {
                        $errors[] = "User not found or already deleted.";
                    } else {
                        // Prevent deleting your own account
                        if ($deleteUserId == getCurrentUser()['id']) {
                            $errors[] = "You cannot delete your own account.";
                        } else {
                            // Begin transaction
                            db()->beginTransaction();
                            
                            // Delete user
                            $sql = "DELETE FROM users WHERE id = :id";
                            $stmt = db()->prepare($sql);
                            $stmt->execute(['id' => $deleteUserId]);
                            
                            // Log activity
                            logActivity('user_delete', "Deleted user: {$userToDelete['name']} ({$userToDelete['email']})");
                            
                            // Commit transaction
                            db()->commit();
                            
                            // Set success message
                            $_SESSION['success'] = "User {$userToDelete['name']} has been deleted successfully.";
                            
                            // Redirect to refresh page
                            header('Location: users.php');
                            exit;
                        }
                    }
                } catch (PDOException $e) {
                    // Rollback transaction on error
                    if (db()->inTransaction()) {
                        db()->rollBack();
                    }
                    
                    error_log("User deletion error: " . $e->getMessage());
                    $errors[] = "An error occurred while deleting the user.";
                }
            }
        }
    }
}

// Handle edit user action
if ($action === 'edit' && $userId) {
    try {
        $stmt = db()->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        
        if ($stmt->rowCount() > 0) {
            $userToEdit = $stmt->fetch();
        } else {
            $_SESSION['error'] = "User not found.";
            header('Location: users.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error fetching user: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while retrieving user details.";
        header('Location: users.php');
        exit;
    }
}

// Get all users
try {
    $sql = "SELECT * FROM users ORDER BY name";
    $stmt = db()->prepare($sql);
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while retrieving users.";
}

// Set page title
$page_title = "User Management";

// Include header
include_once '../includes/header.php';
?>

<div class="py-6">
    <div class="mx-auto px-4 sm:px-6 md:px-8">
        <h1 class="text-2xl font-semibold text-gray-900">User Management</h1>
        <p class="mt-1 text-sm text-gray-600">Manage system users and their access privileges</p>
    </div>
    
    <div class="mx-auto px-4 sm:px-6 md:px-8">
        <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-50 border-l-4 border-green-400 p-4 mt-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700"><?= $_SESSION['success']; ?></p>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border-l-4 border-red-400 p-4 mt-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700"><?= $_SESSION['error']; ?></p>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border-l-4 border-red-400 p-4 mt-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-400"></i>
                </div>
                <div class="ml-3">
                    <?php foreach ($errors as $error): ?>
                    <p class="text-sm text-red-700"><?= $error; ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Add/Edit User Form -->
        <div class="bg-white shadow rounded-lg overflow-hidden mt-6">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">
                    <?= $userToEdit ? 'Edit User' : 'Add New User'; ?>
                </h3>
            </div>
            
            <form method="POST" action="users.php" class="p-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="form_action" value="<?= $userToEdit ? 'edit_user' : 'add_user'; ?>">
                
                <?php if ($userToEdit): ?>
                <input type="hidden" name="user_id" value="<?= $userToEdit['id']; ?>">
                <?php endif; ?>
                
                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                    <!-- Name -->
                    <div class="sm:col-span-3">
                        <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                        <div class="mt-1">
                            <input type="text" name="name" id="name" value="<?= $userToEdit ? htmlspecialchars($userToEdit['name']) : ''; ?>"
                                class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md" required>
                        </div>
                    </div>
                    
                    <!-- Email -->
                    <div class="sm:col-span-3">
                        <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                        <div class="mt-1">
                            <input type="email" name="email" id="email" value="<?= $userToEdit ? htmlspecialchars($userToEdit['email']) : ''; ?>"
                                class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md" required>
                        </div>
                    </div>
                    
                    <!-- Department -->
                    <div class="sm:col-span-2">
                        <label for="department" class="block text-sm font-medium text-gray-700">Department</label>
                        <div class="mt-1">
                            <select id="department" name="department" 
                                class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept); ?>" <?= ($userToEdit && $userToEdit['department'] === $dept) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($dept); ?>
                                </option>
                                <?php endforeach; ?>
                                <option value="other">Other (New Department)</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- New Department (shown when "Other" is selected) -->
                    <div id="new_department_container" class="sm:col-span-2 hidden">
                        <label for="new_department" class="block text-sm font-medium text-gray-700">New Department Name</label>
                        <div class="mt-1">
                            <input type="text" id="new_department" name="new_department"
                                class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>
                    
                    <!-- Role -->
                    <div class="sm:col-span-2">
                        <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                        <div class="mt-1">
                            <select id="role" name="role" 
                                class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md" required>
                                <option value="">Select Role</option>
                                <option value="admin" <?= ($userToEdit && $userToEdit['role'] === 'admin') ? 'selected' : ''; ?>>Administrator</option>
                                <option value="supervisor" <?= ($userToEdit && $userToEdit['role'] === 'supervisor') ? 'selected' : ''; ?>>Supervisor</option>
                                <option value="department_head" <?= ($userToEdit && $userToEdit['role'] === 'department_head') ? 'selected' : ''; ?>>Department Head</option>
                                <option value="clerk" <?= ($userToEdit && $userToEdit['role'] === 'clerk') ? 'selected' : ''; ?>>Clerk</option>
                                <option value="viewer" <?= ($userToEdit && $userToEdit['role'] === 'viewer') ? 'selected' : ''; ?>>Viewer</option>
                                <option value="manager" <?= ($userToEdit && $userToEdit['role'] === 'manager') ? 'selected' : ''; ?>>Manager</option>
                                <option value="assistant_manager" <?= ($userToEdit && $userToEdit['role'] === 'assistant_manager') ? 'selected' : ''; ?>>Assistant Manager</option>
                                <option value="senior_manager" <?= ($userToEdit && $userToEdit['role'] === 'senior_manager') ? 'selected' : ''; ?>>Senior Manager</option>
                                <option value="contractor" <?= ($userToEdit && $userToEdit['role'] === 'contractor') ? 'selected' : ''; ?>>Contractor</option>
                                <option value="client" <?= ($userToEdit && $userToEdit['role'] === 'client') ? 'selected' : ''; ?>>Client</option>
                            </select>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            Determines what actions the user can perform in the system
                        </p>
                    </div>
                    
                    <!-- Password -->
                    <div class="sm:col-span-3">
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            <?= $userToEdit ? 'New Password (leave blank to keep current)' : 'Password'; ?>
                        </label>
                        <div class="mt-1">
                            <input type="password" name="password" id="password" 
                                class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                <?= $userToEdit ? '' : 'required'; ?>>
                        </div>
                        <?php if ($userToEdit): ?>
                        <p class="mt-1 text-xs text-gray-500">
                            Only fill this if you want to change the user's password
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <?php if ($userToEdit): ?>
                    <a href="users.php" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        <i class="fas fa-save mr-2"></i> Update User
                    </button>
                    <?php else: ?>
                    <button type="reset" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        <i class="fas fa-eraser mr-2"></i> Clear Form
                    </button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        <i class="fas fa-user-plus mr-2"></i> Add User
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Users List -->
        <div class="bg-white shadow rounded-lg overflow-hidden mt-6">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">System Users</h3>
            </div>
            
            <?php if (empty($users)): ?>
            <div class="p-6 text-center text-gray-500">
                No users found in the system.
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Name
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Email
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Department
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Role
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Created
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($users as $user): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 rounded-full bg-primary-500 flex items-center justify-center text-white">
                                        <?= strtoupper(substr($user['name'], 0, 1)); ?>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($user['name']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($user['email']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <?= htmlspecialchars($user['department']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $roleLabels = [
                                    'admin' => ['bg-purple-100 text-purple-800', 'Administrator'],
                                    'supervisor' => ['bg-green-100 text-green-800', 'Supervisor'],
                                    'department_head' => ['bg-blue-100 text-blue-800', 'Department Head'],
                                    'clerk' => ['bg-yellow-100 text-yellow-800', 'Clerk'],
                                    'viewer' => ['bg-gray-100 text-gray-800', 'Viewer'],
                                    'manager' => ['bg-red-100 text-red-800', 'Manager'],
                                    'assistant_manager' => ['bg-pink-100 text-pink-800', 'Assistant Manager'],
                                    'senior_manager' => ['bg-indigo-100 text-indigo-800', 'Senior Manager'],
                                    'contractor' => ['bg-orange-100 text-orange-800', 'Contractor'],
                                    'client' => ['bg-teal-100 text-teal-800', 'Client'],
                                ];
                                $roleClass = $roleLabels[$user['role']][0] ?? 'bg-gray-100 text-gray-800';
                                $roleText = $roleLabels[$user['role']][1] ?? ucfirst(str_replace('_', ' ', $user['role']));
                                ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $roleClass; ?>">
                                    <?= $roleText; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= date('M d, Y', strtotime($user['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <!-- Edit user link -->
                                <a href="users.php?action=edit&id=<?= $user['id']; ?>" class="text-primary-600 hover:text-primary-900 mr-3">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                
                                <!-- Delete user link (only show for other users, not current user) -->
                                <?php if ($user['id'] != getCurrentUser()['id']): ?>
                                <button type="button" 
                                        onclick="confirmDelete(<?= $user['id']; ?>, '<?= htmlspecialchars($user['name']); ?>')"
                                        class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Delete User Form (hidden, used for POST submission) -->
        <form id="delete-form" method="POST" action="users.php" class="hidden">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="form_action" value="delete_user">
            <input type="hidden" name="user_id" id="delete-user-id">
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="fixed z-10 inset-0 overflow-y-auto hidden">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            Delete User
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500" id="modal-description">
                                Are you sure you want to delete this user? This action cannot be undone.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="confirm-delete-btn" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                    Delete
                </button>
                <button type="button" onclick="closeModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('User management JS loaded'); // Debug log

    // Department field show/hide based on role
    const roleSelect = document.getElementById('role');
    const departmentDiv = document.getElementById('department').closest('div').closest('.sm\\:col-span-2');
    const newDepartmentContainer = document.getElementById('new_department_container');
    const departmentSelect = document.getElementById('department');
    const newDepartmentInput = document.getElementById('new_department');
    
    // Keep track of the last selected department
    let lastSelectedDepartment = departmentSelect.value;
    
    function toggleDepartmentField() {
        const role = roleSelect.value;
        if (role === 'contractor' || role === 'viewer' || role === 'client') {
            departmentDiv.style.display = 'none';
            if (newDepartmentContainer) {
                newDepartmentContainer.classList.add('hidden');
            }
        } else {
            departmentDiv.style.display = '';
            // Restore previously selected department if available
            if (lastSelectedDepartment && lastSelectedDepartment !== '') {
                departmentSelect.value = lastSelectedDepartment;
            }
            
            // Only show the new department container if "other" is selected
            if (newDepartmentContainer && departmentSelect.value === 'other') {
                newDepartmentContainer.classList.remove('hidden');
            }
        }
    }
    
    roleSelect.addEventListener('change', toggleDepartmentField);
    toggleDepartmentField(); // Initial call on page load

    // Handle the department dropdown change to show/hide the new department field
    if (departmentSelect && newDepartmentContainer && newDepartmentInput) {
        departmentSelect.addEventListener('change', function() {
            // Save the last selected department
            lastSelectedDepartment = this.value;
            
            if (this.value === 'other') {
                newDepartmentContainer.classList.remove('hidden');
                newDepartmentInput.setAttribute('required', 'required');
                newDepartmentInput.focus();
            } else {
                newDepartmentContainer.classList.add('hidden');
                newDepartmentInput.removeAttribute('required');
                newDepartmentInput.value = ''; // Clear the input when not using "other"
            }
        });
        
        // Check if 'other' is selected on page load
        if (departmentSelect.value === 'other') {
            newDepartmentContainer.classList.remove('hidden');
            newDepartmentInput.setAttribute('required', 'required');
        }
        
        // Set up custom department handling on form submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const role = roleSelect.value;
            
            // For contractor or viewer roles, make sure there's no department
            if (role === 'contractor' || role === 'viewer' || role === 'client') {
                // Remove department select name to avoid submitting it
                departmentSelect.name = '';
                
                // Make sure there's no hidden department field
                const existingHidden = document.querySelector('input[type="hidden"][name="department"]');
                if (existingHidden) {
                    existingHidden.remove();
                }
            } else if (departmentSelect.value === 'other' && newDepartmentInput.value.trim()) {
                // For other roles with custom department
                const existingHidden = document.querySelector('input[type="hidden"][name="department"]');
                if (!existingHidden) {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'department';
                    hiddenInput.value = newDepartmentInput.value.trim();
                    this.appendChild(hiddenInput);
                } else {
                    existingHidden.value = newDepartmentInput.value.trim();
                }
                departmentSelect.name = ''; // Remove the select's name to avoid conflict
            } else {
                // For standard department selections, make sure it submits normally
                departmentSelect.name = 'department';
                
                // Remove any hidden department inputs
                const existingHidden = document.querySelector('input[type="hidden"][name="department"]');
                if (existingHidden) {
                    existingHidden.remove();
                }
            }
        });
    }
    
    // Delete user confirmation modal
    window.confirmDelete = function(userId, userName) {
        console.log('confirmDelete called with userId:', userId); // Debug log
        var input = document.getElementById('delete-user-id');
        console.log('delete-user-id input before:', input ? input.value : 'NOT FOUND');
        if(input) input.value = userId;
        console.log('delete-user-id input after:', input ? input.value : 'NOT FOUND');
        document.getElementById('modal-description').textContent = 
            `Are you sure you want to delete the user "${userName}"? This action cannot be undone.`;
        document.getElementById('confirm-delete-btn').onclick = function(event) {
            event.stopPropagation();
            console.log('Submitting delete form...'); // Debug log
            document.getElementById('delete-form').submit();
        };
        document.getElementById('delete-modal').classList.remove('hidden');
    }

    window.closeModal = function() {
        document.getElementById('delete-modal').classList.add('hidden');
    }

    // Close modal when clicking outside of it
    window.onclick = function(event) {
        const modal = document.getElementById('delete-modal');
        if (event.target === modal) {
            window.closeModal();
        }
    }
});
</script>

<?php include_once '../includes/footer.php'; ?>
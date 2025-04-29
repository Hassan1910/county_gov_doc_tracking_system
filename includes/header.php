<?php
/**
 * Header Template
 * 
 * This file contains the top part of the HTML document including
 * the navigation menu and Tailwind CSS integration.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include authentication utilities
require_once __DIR__ . '/auth.php';

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Get current user if logged in
$current_user = isLoggedIn() ? getCurrentUser() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>County Government Document Tracker</title>
    
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
<body class="bg-gray-100 min-h-screen">
    <!-- Flash Messages -->
    <?php if(isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?= $_SESSION['success']; ?></span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg onclick="this.parentElement.parentElement.style.display='none'" class="fill-current h-6 w-6 text-green-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
            </span>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?= $_SESSION['error']; ?></span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg onclick="this.parentElement.parentElement.style.display='none'" class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
            </span>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Navigation Bar -->
    <nav class="bg-primary-700 text-white shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <i class="fas fa-file-alt text-2xl mr-2"></i>
                    <span class="font-bold text-xl">County Gov Tracker</span>
                </div>
                
                <?php if(isLoggedIn()): ?>
                <div class="hidden md:flex space-x-4">
                    <?php if($current_user['role'] === 'client' || $current_user['role'] === 'contractor'): ?>
                    <a href="../public/client_dashboard.php" class="<?= $current_page === 'client_dashboard.php' ? 'border-b-2 border-white' : '' ?> hover:text-primary-200 px-3 py-2">
                        <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                    </a>
                    <a href="../public/track.php" class="<?= $current_page === 'track.php' ? 'border-b-2 border-white' : '' ?> hover:text-primary-200 px-3 py-2">
                        <i class="fas fa-search mr-1"></i> Track Documents
                    </a>
                    <?php else: ?>
                    <a href="../public/dashboard.php" class="<?= $current_page === 'dashboard.php' ? 'border-b-2 border-white' : '' ?> hover:text-primary-200 px-3 py-2">
                        <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                    </a>
                    <a href="#" class="invisible px-3 py-2">Upload</a>
                    <a href="../public/track.php" class="<?= $current_page === 'track.php' ? 'border-b-2 border-white' : '' ?> hover:text-primary-200 px-3 py-2">
                        <i class="fas fa-search mr-1"></i> Track
                    </a>
                    <?php if(hasRole(['admin', 'supervisor', 'manager'])): ?>
                    <a href="../public/approve.php" class="<?= $current_page === 'approve.php' ? 'border-b-2 border-white' : '' ?> hover:text-primary-200 px-3 py-2">
                        <i class="fas fa-check-circle mr-1"></i> Approve
                    </a>
                    <?php endif; ?>
                    <a href="../public/move.php" class="<?= $current_page === 'move.php' ? 'border-b-2 border-white' : '' ?> hover:text-primary-200 px-3 py-2">
                        <i class="fas fa-exchange-alt mr-1"></i> Move
                    </a>
                    <?php if(hasRole(['admin', 'clerk'])): ?>
                    <a href="../public/register_client.php" class="<?= $current_page === 'register_client.php' ? 'border-b-2 border-white' : '' ?> hover:text-primary-200 px-3 py-2">
                        <i class="fas fa-user-plus mr-1"></i> Register Client
                    </a>
                    <?php endif; ?>
                    <?php if(hasRole(['admin'])): ?>
                    <a href="../public/register.php?role=manager" class="<?= $current_page === 'register.php' && isset($_GET['role']) && $_GET['role'] === 'manager' ? 'border-b-2 border-white' : '' ?> hover:text-primary-200 px-3 py-2">
                        <i class="fas fa-user-tie mr-1"></i> Register Manager
                    </a>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <div class="flex items-center space-x-2">
                    <?php if(isLoggedIn() && ($current_user['role'] === 'client' || $current_user['role'] === 'contractor')): ?>
                    <!-- Notification Bell Dropdown -->
                    <div class="relative group mr-3">
                        <button class="flex items-center hover:text-primary-200 focus:outline-none relative p-1" aria-label="Notifications">
                            <i class="fas fa-bell text-xl"></i>
                            <?php 
                            // Get unread notifications count
                            $unreadCount = 0;
                            try {
                                $stmt = db()->prepare("SELECT COUNT(*) FROM client_notifications WHERE client_id = :user_id AND is_read = 0");
                                $stmt->execute(['user_id' => $current_user['id']]);
                                $unreadCount = $stmt->fetchColumn();
                            } catch (Exception $e) {
                                error_log("Error fetching notification count: " . $e->getMessage());
                            }
                            if($unreadCount > 0): 
                            ?>
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full text-xs w-5 h-5 flex items-center justify-center"><?= $unreadCount > 9 ? '9+' : $unreadCount; ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-md shadow-lg py-1 z-20 hidden dropdown-menu">
                            <div class="px-4 py-2 text-sm text-gray-700 border-b border-gray-200 bg-gray-50 flex justify-between sticky top-0">
                                <span class="font-semibold">Recent Notifications</span>
                                <?php if($unreadCount > 0): ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800"><?= $unreadCount; ?> new</span>
                                <?php endif; ?>
                            </div>
                            <?php
                            // Get latest 3 notifications only
                            $notifications = [];
                            try {
                                $stmt = db()->prepare("SELECT cn.*, d.title, d.doc_unique_id 
                                                    FROM client_notifications cn
                                                    JOIN documents d ON cn.document_id = d.id
                                                    WHERE cn.client_id = :user_id
                                                    ORDER BY cn.created_at DESC LIMIT 3");
                                $stmt->execute(['user_id' => $current_user['id']]);
                                $notifications = $stmt->fetchAll();
                            } catch (Exception $e) {
                                error_log("Error fetching notifications: " . $e->getMessage());
                            }
                            
                            if (empty($notifications)): ?>
                            <div class="text-center py-6 text-gray-500">
                                <i class="fas fa-bell-slash text-gray-300 text-2xl mb-2"></i>
                                <p class="text-sm">No notifications yet</p>
                            </div>
                            <?php else: ?>
                            <div>
                                <?php foreach ($notifications as $notification): ?>
                                <div class="px-4 py-3 hover:bg-gray-50 border-b border-gray-100 <?= $notification['is_read'] ? '' : 'bg-blue-50'; ?>">
                                    <div class="flex items-start">
                                        <div class="relative flex-shrink-0 mr-3">
                                            <div class="h-8 w-8 rounded-full bg-<?= $notification['is_read'] ? 'gray' : 'blue' ?>-100 flex items-center justify-center">
                                                <i class="fas fa-bell text-<?= $notification['is_read'] ? 'gray' : 'blue' ?>-500"></i>
                                            </div>
                                            <?php if (!$notification['is_read']): ?>
                                            <span class="absolute -top-1 -right-1 h-3 w-3 bg-red-500 rounded-full border-2 border-white"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-sm text-gray-800 font-medium notification-text"><?= htmlspecialchars($notification['message']); ?></p>
                                            <div class="flex justify-between items-center mt-1 flex-wrap">
                                                <div class="flex items-center flex-wrap">
                                                    <span class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded text-xs mr-2 mb-1">
                                                        <?= htmlspecialchars($notification['doc_unique_id']); ?>
                                                    </span>
                                                    <p class="text-xs text-gray-400">
                                                        <?= date('M j, Y g:i a', strtotime($notification['created_at'])); ?>
                                                    </p>
                                                </div>
                                                <?php if (!$notification['is_read']): ?>
                                                <form action="../process/mark_notification_read.php" method="post" class="mt-1 sm:mt-0">
                                                    <input type="hidden" name="notification_id" value="<?= $notification['id']; ?>">
                                                    <button type="submit" class="text-xs text-primary-600 hover:text-primary-500">
                                                        Mark as read
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                                <div class="px-4 py-3 text-center border-t border-gray-100 bg-gray-50">
                                    <a href="../public/notifications.php" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-primary-600 hover:text-primary-800">
                                        View all notifications <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="relative" id="userDropdownContainer">
                        <button id="userDropdownButton" class="flex items-center hover:text-primary-200 focus:outline-none">
                            <span class="mr-1"><?= htmlspecialchars($current_user['name']); ?></span>
                            <i class="fas fa-user-circle text-xl"></i>
                        </button>
                        <div id="userDropdownMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10 hidden">
                            <div class="px-4 py-2 text-sm text-gray-700">
                                <div><?= htmlspecialchars($current_user['email']); ?></div>
                                <div class="text-xs font-semibold mt-1 uppercase"><?= htmlspecialchars($current_user['role']); ?></div>
                            </div>
                            <hr class="my-1">
                            <a href="../public/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-1"></i> Sign out
                            </a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="space-x-2">
                    <a href="../public/login.php" class="bg-white text-primary-700 hover:bg-primary-50 px-3 py-2 rounded-md text-sm font-medium">
                        <i class="fas fa-sign-in-alt mr-1"></i> Log in
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <!-- Mobile Menu (hidden on desktop) -->
    <?php if(isLoggedIn()): ?>
    <div class="md:hidden bg-primary-800 text-white">
        <div class="container mx-auto px-4 py-2 flex flex-wrap justify-center space-x-2">
            <?php if($current_user['role'] === 'client' || $current_user['role'] === 'contractor'): ?>
            <a href="../public/client_dashboard.php" class="<?= $current_page === 'client_dashboard.php' ? 'bg-primary-600' : '' ?> hover:bg-primary-600 px-3 py-1 rounded my-1">
                <i class="fas fa-tachometer-alt"></i>
            </a>
            <a href="../public/track.php" class="<?= $current_page === 'track.php' ? 'bg-primary-600' : '' ?> hover:bg-primary-600 px-3 py-1 rounded my-1">
                <i class="fas fa-search"></i>
            </a>
            <a href="../public/notifications.php" class="<?= $current_page === 'notifications.php' ? 'bg-primary-600' : '' ?> hover:bg-primary-600 px-3 py-1 rounded my-1 relative">
                <i class="fas fa-bell"></i>
                <?php if($unreadCount > 0): ?>
                <span class="absolute top-0 right-0 transform translate-x-1/2 -translate-y-1/2 bg-red-500 text-white rounded-full text-xs w-4 h-4 flex items-center justify-center" style="font-size: 0.6rem;">
                    <?= $unreadCount > 9 ? '9+' : $unreadCount; ?>
                </span>
                <?php endif; ?>
            </a>
            <?php else: ?>
            <a href="../public/dashboard.php" class="<?= $current_page === 'dashboard.php' ? 'bg-primary-600' : '' ?> hover:bg-primary-600 px-3 py-1 rounded my-1">
                <i class="fas fa-tachometer-alt"></i>
            </a>
            <a href="#" class="invisible px-3 py-1">Upload</a>
            <a href="../public/track.php" class="<?= $current_page === 'track.php' ? 'bg-primary-600' : '' ?> hover:bg-primary-600 px-3 py-1 rounded my-1">
                <i class="fas fa-search"></i>
            </a>
            <?php if(hasRole(['admin', 'supervisor', 'manager'])): ?>
            <a href="../public/approve.php" class="<?= $current_page === 'approve.php' ? 'bg-primary-600' : '' ?> hover:bg-primary-600 px-3 py-1 rounded my-1">
                <i class="fas fa-check-circle"></i>
            </a>
            <?php endif; ?>
            <a href="../public/move.php" class="<?= $current_page === 'move.php' ? 'bg-primary-600' : '' ?> hover:bg-primary-600 px-3 py-1 rounded my-1">
                <i class="fas fa-exchange-alt"></i>
            </a>
            <?php if(hasRole(['admin', 'clerk'])): ?>
            <a href="../public/register_client.php" class="<?= $current_page === 'register_client.php' ? 'bg-primary-600' : '' ?> hover:bg-primary-600 px-3 py-1 rounded my-1">
                <i class="fas fa-user-plus"></i>
            </a>
            <?php endif; ?>
            <?php if(hasRole(['admin'])): ?>
            <a href="../public/register.php?role=manager" class="<?= $current_page === 'register.php' && isset($_GET['role']) && $_GET['role'] === 'manager' ? 'bg-primary-600' : '' ?> hover:bg-primary-600 px-3 py-1 rounded my-1">
                <i class="fas fa-user-tie"></i>
            </a>
            <?php endif; ?>
            <a href="../public/logout.php" class="hover:bg-primary-600 px-3 py-1 rounded my-1">
                <i class="fas fa-sign-out-alt"></i>
            </a>
            </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Main Content Container -->
    <div class="container mx-auto px-4 py-8">
    
<style>
/* Mobile notification styles */
@media (max-width: 640px) {
    .dropdown-menu {
        position: fixed !important;
        top: 4rem !important;
        left: 0 !important;
        right: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        border-radius: 0 !important;
        z-index: 9999 !important;
    }
    
    .notification-text {
        font-size: 0.85rem !important;
    }
}

/* Notification Bell Hover Effect */
.fas.fa-bell:hover {
    color: #2563eb !important; /* Tailwind blue-600 */
    transform: scale(1.15) rotate(-10deg);
    transition: color 0.2s, transform 0.2s;
    cursor: pointer;
}
.fas.fa-bell {
    transition: color 0.2s, transform 0.2s;
}

.dropdown-menu { display: none; }
.dropdown-menu.active { display: block !important; }
</style> 

<!-- End of header.php -->

<script>
// User Dropdown Toggle
const userDropdownButton = document.getElementById('userDropdownButton');
const userDropdownMenu = document.getElementById('userDropdownMenu');
const userDropdownContainer = document.getElementById('userDropdownContainer');

if (userDropdownButton && userDropdownMenu) {
    userDropdownButton.addEventListener('click', function(e) {
        e.stopPropagation();
        userDropdownMenu.classList.toggle('hidden');
    });
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!userDropdownContainer.contains(e.target)) {
            userDropdownMenu.classList.add('hidden');
        }
    });
}

// Toggle notification dropdown on click
document.addEventListener('DOMContentLoaded', function() {
    var bellBtn = document.querySelector('.fa-bell').closest('button');
    var dropdown = document.querySelector('.dropdown-menu');
    if(bellBtn && dropdown) {
        bellBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropdown.classList.toggle('active');
        });
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target) && !bellBtn.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });
    }
    // Improved handler for 'View all notifications' link
    var viewAll = document.querySelector('.dropdown-menu a[href$="notifications.php"]');
    if(viewAll) {
        viewAll.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropdown.classList.remove('active');
            window.location.href = this.href;
        });
    }
});
</script>
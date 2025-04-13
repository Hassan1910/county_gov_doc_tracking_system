<?php
/**
 * Notifications Page
 * 
 * This page displays all notifications for a client.
 */

require_once '../config/db.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get current user data
$current_user = getCurrentUser();

// Check if user is a client
if ($current_user['role'] !== 'client') {
    header('Location: dashboard.php');
    exit;
}

// Get all notifications for this client
$notifications = [];
try {
    $stmt = db()->prepare("SELECT cn.id, cn.client_id, cn.document_id, cn.message, cn.created_at, cn.is_read,
                      d.title, d.doc_unique_id
                      FROM client_notifications cn
                      JOIN documents d ON cn.document_id = d.id
                      WHERE cn.client_id = :client_id 
                      ORDER BY cn.created_at DESC");
    $stmt->execute(['client_id' => $current_user['id']]);
    $notifications = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
}

// Count unread notifications
$unreadCount = 0;
foreach ($notifications as $notification) {
    if (!$notification['is_read']) {
        $unreadCount++;
    }
}

// Process mark all as read if requested
if (isset($_POST['mark_all_read']) && $_POST['mark_all_read'] === '1') {
    try {
        $stmt = db()->prepare("UPDATE client_notifications SET is_read = 1 WHERE client_id = :client_id");
        $stmt->execute(['client_id' => $current_user['id']]);
        
        // Redirect to refresh the page
        header('Location: notifications.php');
        exit;
    } catch (Exception $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
    }
}

// Page title
$page_title = "Notifications";

// Include header
require_once '../includes/header.php';
?>

<div class="max-w-5xl mx-auto px-4">
    <div class="flex items-center justify-between mb-6 flex-wrap">
        <h1 class="text-2xl font-bold text-gray-800">Notifications</h1>
        
        <?php if ($unreadCount > 0): ?>
        <form action="" method="post" class="mt-2 sm:mt-0">
            <input type="hidden" name="mark_all_read" value="1">
            <button type="submit" class="px-4 py-2 bg-primary-500 text-white rounded-md hover:bg-primary-600 transition flex items-center text-sm">
                <i class="fas fa-check-double mr-2"></i> Mark all as read
            </button>
        </form>
        <?php endif; ?>
    </div>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <?php if (empty($notifications)): ?>
        <div class="p-8 text-center">
            <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-bell-slash text-gray-400 text-2xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-700 mb-1">No notifications</h3>
            <p class="text-gray-500">You don't have any notifications yet.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <div class="divide-y divide-gray-200">
                <?php foreach ($notifications as $notification): ?>
                <div class="px-4 py-5 sm:px-6 <?= $notification['is_read'] ? 'bg-white' : 'bg-blue-50' ?> hover:bg-gray-50 transition">
                    <div class="flex flex-col sm:flex-row sm:items-start">
                        <div class="flex-shrink-0 mr-4 mb-3 sm:mb-0">
                            <div class="h-10 w-10 rounded-full bg-<?= $notification['is_read'] ? 'gray' : 'blue' ?>-100 flex items-center justify-center">
                                <i class="fas fa-bell text-<?= $notification['is_read'] ? 'gray' : 'blue' ?>-500 text-lg"></i>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm sm:text-base font-medium text-gray-900">
                                <?= htmlspecialchars($notification['message']); ?>
                            </p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    Document #<?= htmlspecialchars($notification['doc_unique_id']); ?>
                                </span>
                                <span class="inline-flex items-center text-xs text-gray-500">
                                    <i class="far fa-clock mr-1"></i>
                                    <?= formatTimeAgo($notification['created_at']); ?>
                                </span>
                            </div>
                        </div>
                        <?php if (!$notification['is_read']): ?>
                        <div class="mt-4 sm:mt-0 sm:ml-4 flex-shrink-0">
                            <form action="../process/mark_notification_read.php" method="post">
                                <input type="hidden" name="notification_id" value="<?= $notification['id']; ?>">
                                <input type="hidden" name="redirect" value="../public/notifications.php">
                                <button type="submit" class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-primary-600 hover:text-primary-800 hover:underline">
                                    Mark as read
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php

/**
 * Format a timestamp into a human-readable "time ago" string
 */
function formatTimeAgo($timestamp) {
    $datetime = new DateTime($timestamp);
    $now = new DateTime();
    $interval = $now->diff($datetime);
    
    if ($interval->y > 0) {
        return $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
    }
    
    if ($interval->m > 0) {
        return $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
    }
    
    if ($interval->d > 0) {
        if ($interval->d == 1) {
            return 'Yesterday';
        }
        return $interval->d . ' days ago';
    }
    
    if ($interval->h > 0) {
        return $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
    }
    
    if ($interval->i > 0) {
        return $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
    }
    
    return 'Just now';
}

// Include footer
require_once '../includes/footer.php';
?> 
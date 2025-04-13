<?php
/**
 * Document Detail Page
 * 
 * This page displays detailed information about a specific document,
 * including its movement history and approval status.
 */

// Include authentication utilities
require_once '../includes/auth.php';

// Require login to access this page
requireLogin();

// Get current user data
$user = getCurrentUser();

// Include database connection
require_once '../config/db.php';

// Get document ID
$document_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$document_id) {
    $_SESSION['error'] = "Invalid document ID.";
    header('Location: ' . ($user['role'] === 'client' ? 'client_dashboard.php' : 'dashboard.php'));
    exit;
}

// Get document details
try {
    $sql = "SELECT d.*, u.name as uploader_name, u.department as uploader_department
            FROM documents d
            JOIN users u ON d.uploaded_by = u.id
            WHERE d.id = :id";
    
    $stmt = db()->prepare($sql);
    $stmt->execute(['id' => $document_id]);
    $document = $stmt->fetch();
    
    // If document doesn't exist, redirect
    if (!$document) {
        $_SESSION['error'] = "Document not found.";
        header('Location: ' . ($user['role'] === 'client' ? 'client_dashboard.php' : 'dashboard.php'));
        exit;
    }
    
    // Security check - only allow access to:
    // 1. Document submitter (client)
    // 2. Document uploader (clerk)
    // 3. Admin users
    // 4. Users from the current department where document is located
    // 5. Supervisors
    if ($user['role'] !== 'admin' && 
        $user['role'] !== 'supervisor' && 
        $user['id'] != $document['uploaded_by'] &&
        $user['id'] != $document['submitter_id'] &&
        $user['department'] != $document['department']) {
        
        $_SESSION['error'] = "You don't have permission to view this document.";
        header('Location: ' . ($user['role'] === 'client' ? 'client_dashboard.php' : 'dashboard.php'));
        exit;
    }
    
    // Get submitter details if exists
    $submitter = null;
    if ($document['submitter_id']) {
        $sql = "SELECT name, email, department FROM users WHERE id = :id";
        $stmt = db()->prepare($sql);
        $stmt->execute(['id' => $document['submitter_id']]);
        $submitter = $stmt->fetch();
    }
    
    // Get document movements
    $sql = "SELECT dm.*, 
                  u.name as moved_by_name,
                  u.department as moved_by_department
           FROM document_movements dm
           JOIN users u ON dm.moved_by = u.id
           WHERE dm.document_id = :document_id
           ORDER BY dm.moved_at ASC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute(['document_id' => $document_id]);
    $movements = $stmt->fetchAll();
    
    // Get approval history
    $sql = "SELECT a.*, 
                  u.name as approver_name,
                  u.department as approver_department
           FROM approvals a
           JOIN users u ON a.approved_by = u.id
           WHERE a.document_id = :document_id
           ORDER BY a.approved_at DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute(['document_id' => $document_id]);
    $approvals = $stmt->fetchAll();
    
    // If user is a client, log that they viewed the document
    if ($user['role'] === 'client' && $user['id'] == $document['submitter_id']) {
        try {
            $sql = "INSERT INTO client_document_views (document_id, client_id, viewed_at)
                    VALUES (:document_id, :client_id, NOW())";
            $stmt = db()->prepare($sql);
            $stmt->execute([
                'document_id' => $document_id,
                'client_id' => $user['id']
            ]);
        } catch (PDOException $e) {
            // Just log the error, don't disrupt the user experience
            error_log("Error logging document view: " . $e->getMessage());
        }
    }
    
} catch (PDOException $e) {
    error_log("Error fetching document details: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while fetching document details.";
    header('Location: ' . ($user['role'] === 'client' ? 'client_dashboard.php' : 'dashboard.php'));
    exit;
}

// Set page title
$page_title = "Document Details: " . $document['title'];

// Include header
include_once '../includes/header.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Document Details</h1>
                <p class="mt-1 text-sm text-gray-600">View information and track movement of this document</p>
            </div>
            <a href="<?= $user['role'] === 'client' ? 'client_dashboard.php' : 'dashboard.php'; ?>" class="flex items-center text-primary-600 hover:text-primary-800">
                <i class="fas fa-arrow-left mr-2"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>
    </div>
    
    <div class="max-w-7xl mx-auto mt-6 px-4 sm:px-6 md:px-8">
        <!-- Document Information Card -->
        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                <div class="flex items-center">
                    <i class="fas fa-file-alt text-primary-500 text-xl mr-3"></i>
                    <h3 class="text-lg font-semibold text-gray-800">Document Information</h3>
                </div>
                
                <?php 
                $statusColors = [
                    'pending' => 'bg-yellow-100 text-yellow-800',
                    'approved' => 'bg-green-100 text-green-800',
                    'rejected' => 'bg-red-100 text-red-800',
                    'in_movement' => 'bg-blue-100 text-blue-800',
                    'done' => 'bg-purple-100 text-purple-800'
                ];
                $statusColor = $statusColors[$document['status']] ?? 'bg-gray-100 text-gray-800';
                $statusLabel = ucfirst(str_replace('_', ' ', $document['status']));
                ?>
                
                <span class="px-3 py-1 text-sm font-semibold rounded-full <?= $statusColor; ?>">
                    <?= $statusLabel; ?>
                </span>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <div class="mb-4">
                            <h3 class="text-sm font-medium text-gray-500">Document ID</h3>
                            <p class="mt-1 text-base font-semibold text-gray-900"><?= htmlspecialchars($document['doc_unique_id']); ?></p>
                        </div>
                        
                        <div class="mb-4">
                            <h3 class="text-sm font-medium text-gray-500">Title</h3>
                            <p class="mt-1 text-base text-gray-900"><?= htmlspecialchars($document['title']); ?></p>
                        </div>
                        
                        <div class="mb-4">
                            <h3 class="text-sm font-medium text-gray-500">Type</h3>
                            <p class="mt-1 text-base text-gray-900"><?= htmlspecialchars($document['type']); ?></p>
                        </div>
                        
                        <div class="mb-4">
                            <h3 class="text-sm font-medium text-gray-500">Current Department</h3>
                            <p class="mt-1 text-base text-gray-900"><?= htmlspecialchars($document['department']); ?></p>
                        </div>
                    </div>
                    
                    <div>
                        <div class="mb-4">
                            <h3 class="text-sm font-medium text-gray-500">Date Submitted</h3>
                            <p class="mt-1 text-base text-gray-900"><?= date('F j, Y, g:i a', strtotime($document['created_at'])); ?></p>
                        </div>
                        
                        <div class="mb-4">
                            <h3 class="text-sm font-medium text-gray-500">Processed By</h3>
                            <p class="mt-1 text-base text-gray-900"><?= htmlspecialchars($document['uploader_name']); ?> (<?= htmlspecialchars($document['uploader_department']); ?>)</p>
                        </div>
                        
                        <?php if ($submitter): ?>
                        <div class="mb-4">
                            <h3 class="text-sm font-medium text-gray-500">Submitted By</h3>
                            <p class="mt-1 text-base text-gray-900"><?= htmlspecialchars($submitter['name']); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($document['notes'])): ?>
                        <div class="mb-4">
                            <h3 class="text-sm font-medium text-gray-500">Notes</h3>
                            <p class="mt-1 text-base text-gray-900"><?= nl2br(htmlspecialchars($document['notes'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Action Buttons (only for staff) -->
                <?php if ($user['role'] !== 'client' && $user['role'] !== 'viewer'): ?>
                <div class="mt-6 pt-6 border-t border-gray-200 flex flex-wrap gap-3">
                    <?php if (hasRole(['admin', 'supervisor']) && $document['status'] === 'pending'): ?>
                    <a href="approve.php?id=<?= $document['id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <i class="fas fa-check-circle mr-2"></i> Approve Document
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($user['role'] === 'admin' || $document['status'] === 'pending' || $document['status'] === 'in_movement'): ?>
                    <a href="move.php?id=<?= $document['id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-exchange-alt mr-2"></i> Move Document
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($document['file_path']): ?>
                    <a href="../<?= htmlspecialchars($document['file_path']); ?>" target="_blank" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        <i class="fas fa-file-pdf mr-2 text-primary-500"></i> View PDF
                    </a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <!-- Client View Button -->
                <?php if ($document['file_path']): ?>
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <a href="../<?= htmlspecialchars($document['file_path']); ?>" target="_blank" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        <i class="fas fa-file-pdf mr-2 text-primary-500"></i> View PDF
                    </a>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Document Movement Timeline -->
        <div class="mt-8 bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center">
                    <i class="fas fa-history text-primary-500 text-xl mr-3"></i>
                    <h3 class="text-lg font-semibold text-gray-800">Document Movement History</h3>
                </div>
            </div>
            
            <div class="p-6">
                <?php if (empty($movements)): ?>
                <div class="text-center py-6">
                    <i class="fas fa-inbox text-gray-400 text-3xl mb-3"></i>
                    <p class="text-gray-500">No movement records found. This document has not been moved between departments yet.</p>
                </div>
                <?php else: ?>
                <div class="flow-root">
                    <ul role="list" class="-mb-8">
                        <?php 
                        // Add initial document creation to the timeline
                        array_unshift($movements, [
                            'from_department' => 'N/A',
                            'to_department' => $document['uploader_department'],
                            'moved_by_name' => $document['uploader_name'],
                            'moved_at' => $document['created_at'],
                            'note' => 'Document was first registered in the system.',
                            'is_creation' => true
                        ]);
                        
                        foreach ($movements as $index => $movement): 
                            $isLast = $index === count($movements) - 1;
                        ?>
                        <li>
                            <div class="relative pb-8">
                                <?php if (!$isLast): ?>
                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                <?php endif; ?>
                                <div class="relative flex space-x-3">
                                    <div>
                                        <?php if (isset($movement['is_creation'])): ?>
                                        <span class="h-8 w-8 rounded-full bg-green-500 flex items-center justify-center ring-8 ring-white">
                                            <i class="fas fa-plus text-white"></i>
                                        </span>
                                        <?php else: ?>
                                        <span class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center ring-8 ring-white">
                                            <i class="fas fa-arrow-right text-white"></i>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                        <div>
                                            <?php if (isset($movement['is_creation'])): ?>
                                            <p class="text-sm text-gray-900">Document was registered in <span class="font-medium"><?= htmlspecialchars($movement['to_department']); ?></span> department</p>
                                            <?php else: ?>
                                            <p class="text-sm text-gray-900">Moved from <span class="font-medium"><?= htmlspecialchars($movement['from_department']); ?></span> to <span class="font-medium"><?= htmlspecialchars($movement['to_department']); ?></span></p>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($movement['note'])): ?>
                                            <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($movement['note']); ?></p>
                                            <?php endif; ?>
                                            
                                            <p class="text-xs text-gray-500 mt-1">
                                                By: <?= htmlspecialchars($movement['moved_by_name']); ?>
                                            </p>
                                        </div>
                                        <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                            <time datetime="<?= $movement['moved_at']; ?>"><?= date('M j, Y g:i a', strtotime($movement['moved_at'])); ?></time>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Approval History -->
        <?php if (!empty($approvals)): ?>
        <div class="mt-8 bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center">
                    <i class="fas fa-clipboard-check text-primary-500 text-xl mr-3"></i>
                    <h3 class="text-lg font-semibold text-gray-800">Approval History</h3>
                </div>
            </div>
            
            <div class="p-6">
                <div class="overflow-hidden">
                    <ul role="list" class="divide-y divide-gray-200">
                        <?php foreach ($approvals as $approval): ?>
                        <li class="py-4">
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0">
                                    <?php if ($approval['status'] === 'approved'): ?>
                                    <span class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-green-100">
                                        <i class="fas fa-check text-green-600"></i>
                                    </span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-red-100">
                                        <i class="fas fa-times text-red-600"></i>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900">
                                        <?= $approval['status'] === 'approved' ? 'Approved' : 'Rejected'; ?> by <?= htmlspecialchars($approval['approver_name']); ?>
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        <?= htmlspecialchars($approval['approver_department']); ?> Department
                                    </p>
                                    <?php if (!empty($approval['comment'])): ?>
                                    <p class="text-sm text-gray-500 mt-2 p-3 bg-gray-50 rounded-md">
                                        "<?= nl2br(htmlspecialchars($approval['comment'])); ?>"
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-shrink-0 text-sm text-gray-500">
                                    <?= date('M j, Y g:i a', strtotime($approval['approved_at'])); ?>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?> 
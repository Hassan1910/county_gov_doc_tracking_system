<?php
/**
 * Client Dashboard
 * 
 * This page serves as the main dashboard for external clients to track their documents.
 */

// Include authentication utilities
require_once '../includes/auth.php';

// Require login to access this page
requireLogin();

// Get current user data
$user = getCurrentUser();

// Redirect non-client users to the regular dashboard
if ($user['role'] !== 'client') {
    header('Location: dashboard.php');
    exit;
}

// Include database connection
require_once '../config/db.php';

// Fetch client's documents
try {
    // Simple direct query to avoid complexity
    $sql = "SELECT d.id, d.doc_unique_id, d.title, d.type, d.department, d.status, d.created_at,
            u.name as clerk_name
            FROM documents d
            JOIN users u ON d.uploaded_by = u.id
            WHERE d.submitter_id = :user_id
            ORDER BY d.created_at DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute(['user_id' => $user['id']]);
    $documents = $stmt->fetchAll();
    
    // If no documents found by submitter_id, try as the uploader
    if (empty($documents)) {
        $sql = "SELECT d.id, d.doc_unique_id, d.title, d.type, d.department, d.status, d.created_at,
                u.name as clerk_name
                FROM documents d
                JOIN users u ON d.uploaded_by = u.id
                WHERE d.uploaded_by = :user_id
                ORDER BY d.created_at DESC";
        
        $stmt = db()->prepare($sql);
        $stmt->execute(['user_id' => $user['id']]);
        $documents = $stmt->fetchAll();
    }
    
    // If still no documents, try using the document_clients table
    if (empty($documents)) {
        $sql = "SELECT d.id, d.doc_unique_id, d.title, d.type, d.department, d.status, d.created_at,
                u.name as clerk_name
                FROM documents d
                JOIN users u ON d.uploaded_by = u.id
                JOIN document_clients dc ON d.id = dc.document_id
                WHERE dc.client_id = :user_id
                ORDER BY d.created_at DESC";
                
        $stmt = db()->prepare($sql);
        $stmt->execute(['user_id' => $user['id']]);
        $documents = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Error fetching client documents: " . $e->getMessage());
    $documents = [];
}

// Count documents by status
$documentCounts = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'in_movement' => 0
];

// Simple direct query to verify counts
try {
    $sql = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN LOWER(status) = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN LOWER(status) = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN LOWER(status) = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN LOWER(status) = 'in_movement' THEN 1 ELSE 0 END) as in_movement
    FROM documents
    WHERE submitter_id = :user_id";
    
    $stmt = db()->prepare($sql);
    $stmt->execute(['user_id' => $user['id']]);
    $result = $stmt->fetch();
    
    if ($result) {
        $documentCounts = [
            'total' => (int)$result['total'],
            'pending' => (int)$result['pending'],
            'approved' => (int)$result['approved'],
            'rejected' => (int)$result['rejected'],
            'in_movement' => (int)$result['in_movement']
        ];
    }
} catch (PDOException $e) {
    error_log("Error counting client documents: " . $e->getMessage());
}

// Set page title
$page_title = "Client Dashboard";

// Include header
include_once '../includes/header.php';
?>

<!-- Toast Notification System -->
<div id="toast-container" class="fixed top-4 right-4 z-50 w-80 max-w-full"></div>

<script>
// Toast notification system
function showToast(message, type = 'info', duration = 5000) {
    const container = document.getElementById('toast-container');
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = 'flex items-center w-full p-4 mb-3 text-gray-500 bg-white rounded-lg shadow transition-all transform translate-x-full';
    toast.style.opacity = '0';
    
    // Set background and icon based on type
    let bgColor, icon;
    switch(type) {
        case 'success':
            bgColor = 'border-l-4 border-green-500';
            icon = '<div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 text-green-500 bg-green-100 rounded-lg"><i class="fas fa-check"></i></div>';
            break;
        case 'error':
            bgColor = 'border-l-4 border-red-500';
            icon = '<div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 text-red-500 bg-red-100 rounded-lg"><i class="fas fa-times"></i></div>';
            break;
        case 'warning':
            bgColor = 'border-l-4 border-yellow-500';
            icon = '<div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 text-yellow-500 bg-yellow-100 rounded-lg"><i class="fas fa-exclamation"></i></div>';
            break;
        default:
            bgColor = 'border-l-4 border-blue-500';
            icon = '<div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 text-blue-500 bg-blue-100 rounded-lg"><i class="fas fa-bell"></i></div>';
    }
    
    toast.classList.add(bgColor);
    
    // Set toast content
    toast.innerHTML = `
        ${icon}
        <div class="ml-3 text-sm font-normal flex-1">${message}</div>
        <button type="button" class="ml-auto -mx-1.5 -my-1.5 text-gray-400 hover:text-gray-900 focus:outline-none">
            <i class="fas fa-times text-lg"></i>
        </button>
    `;
    
    // Add toast to container
    container.appendChild(toast);
    
    // Animate toast entrance
    setTimeout(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateX(0)';
        toast.style.transition = 'all 0.5s ease-in-out';
    }, 10);
    
    // Close button functionality
    const closeButton = toast.querySelector('button');
    closeButton.addEventListener('click', () => removeToast(toast));
    
    // Auto-remove after duration
    setTimeout(() => removeToast(toast), duration);
}

function removeToast(toast) {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(full)';
    
    setTimeout(() => {
        toast.remove();
    }, 500);
}
</script>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
        <div class="flex items-center justify-between flex-wrap">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Welcome, <?= htmlspecialchars($user['name']); ?></h1>
                <p class="mt-1 text-sm text-gray-600">Track your documents and their movement through departments</p>
            </div>
            <div class="flex space-x-3">
                <a href="track.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <i class="fas fa-search mr-2"></i> Track Documents
                </a>
            </div>
        </div>
    </div>
    
    <div class="max-w-7xl mx-auto mt-6 px-4 sm:px-6 md:px-8">
        <!-- Document Status Overview -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 md:grid-cols-4">
            <!-- Total Documents -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-primary-100 rounded-md p-3">
                            <i class="fas fa-file-alt text-primary-600 text-xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Documents</dt>
                                <dd class="flex items-baseline">
                                    <div class="text-2xl font-semibold text-gray-900"><?= $documentCounts['total']; ?></div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pending Documents -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-yellow-100 rounded-md p-3">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Pending</dt>
                                <dd class="flex items-baseline">
                                    <div class="text-2xl font-semibold text-gray-900"><?= $documentCounts['pending']; ?></div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Approved Documents -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 rounded-md p-3">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Approved</dt>
                                <dd class="flex items-baseline">
                                    <div class="text-2xl font-semibold text-gray-900"><?= $documentCounts['approved']; ?></div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Rejected Documents -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-red-100 rounded-md p-3">
                            <i class="fas fa-times-circle text-red-600 text-xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Rejected</dt>
                                <dd class="flex items-baseline">
                                    <div class="text-2xl font-semibold text-gray-900"><?= $documentCounts['rejected']; ?></div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Document List and Movement Activity -->
        <div class="mt-8">
            <!-- My Documents -->
            <div class="bg-white shadow-lg rounded-lg">
                <div class="px-4 py-5 border-b border-gray-200 sm:px-6 flex justify-between items-center">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        My Documents
                    </h3>
                    <a href="client_documents.php" class="text-primary-600 hover:text-primary-700 text-sm">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <?php if (empty($documents)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-folder-open text-gray-400 text-4xl mb-3"></i>
                            <?php if ($documentCounts['total'] > 0): ?>
                                <p class="text-gray-500 text-lg">Error loading your documents. Please try refreshing the page.</p>
                                <?php
                                // Direct fallback query to ensure documents display
                                try {
                                    $sql = "SELECT d.id, d.doc_unique_id, d.title, d.type, d.department, d.status, d.created_at
                                            FROM documents d 
                                            WHERE d.submitter_id = :user_id 
                                            ORDER BY d.created_at DESC";
                                    $stmt = db()->prepare($sql);
                                    $stmt->execute(['user_id' => $user['id']]);
                                    $emergency_docs = $stmt->fetchAll();
                                    
                                    if (!empty($emergency_docs)) {
                                        $documents = $emergency_docs;
                                        // Force refresh the display
                                        echo '<script>window.location.reload();</script>';
                                    }
                                } catch (Exception $e) {
                                    error_log("Emergency document fetch failed: " . $e->getMessage());
                                }
                                ?>
                            <?php else: ?>
                                <p class="text-gray-500 text-lg">No documents submitted yet</p>
                                <p class="text-gray-400 text-sm mt-2">Visit your local county office to submit documents for processing</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Document</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="relative px-6 py-3">
                                            <span class="sr-only">View</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php 
                                    // Only show the latest 3 documents
                                    $latestDocuments = array_slice($documents, 0, 3);
                                    foreach ($latestDocuments as $document): 
                                    ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-full bg-gray-100">
                                                        <i class="fas fa-file-pdf text-primary-500"></i>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($document['title']); ?></div>
                                                        <div class="text-sm text-gray-500">ID: <?= htmlspecialchars($document['doc_unique_id']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?= htmlspecialchars($document['type']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?= htmlspecialchars($document['department']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php
                                                $statusClasses = [
                                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                                    'approved' => 'bg-green-100 text-green-800',
                                                    'rejected' => 'bg-red-100 text-red-800',
                                                    'in_movement' => 'bg-blue-100 text-blue-800'
                                                ];
                                                $statusClass = $statusClasses[$document['status']] ?? 'bg-gray-100 text-gray-800';
                                                $statusLabel = ucfirst(str_replace('_', ' ', $document['status']));
                                                ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass; ?>">
                                                    <?= $statusLabel; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('M j, Y', strtotime($document['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <a href="client_document_view.php?id=<?= $document['id']; ?>" class="text-primary-600 hover:text-primary-900">
                                                    <i class="fas fa-eye mr-1"></i> View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (count($documents) > 3): ?>
                                <div class="px-6 py-4 flex justify-center">
                                    <a href="client_documents.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-primary-700 bg-primary-100 hover:bg-primary-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                        View All Documents (<?= count($documents) ?>) <i class="fas fa-arrow-right ml-2"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?> 
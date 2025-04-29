<?php
/**
 * Client Dashboard
 * 
 * This page serves as the main dashboard for external clients to track their documents.
 */

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include authentication utilities
require_once '../includes/auth.php';

// Require login to access this page
requireLogin();

// Get current user data
$user = getCurrentUser();

// Check if we have a valid client/contractor role user
if (!in_array($user['role'], ['client', 'contractor'])) {
    header('Location: dashboard.php');
    exit;
}

// Include database connection
require_once '../config/db.php';

// Fetch client's documents
$documents = [];
try {
    // First try by submitter_id which works for both clients and contractors
    $sql = "SELECT d.id, d.doc_unique_id, d.title, d.type, d.department, d.status, d.created_at, 
                   u.name as clerk_name
            FROM documents d
            LEFT JOIN users u ON d.uploaded_by = u.id
            WHERE d.submitter_id = :user_id
            ORDER BY d.created_at DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute(['user_id' => $user['id']]);
    $documents = $stmt->fetchAll();
    
    // If no documents found, try checking document_clients table (for backwards compatibility)
    if (empty($documents)) {
        $sql = "SELECT d.id, d.doc_unique_id, d.title, d.type, d.department, d.status, d.created_at, 
                       u.name as clerk_name
                FROM documents d
                LEFT JOIN users u ON d.uploaded_by = u.id
                JOIN document_clients dc ON d.id = dc.document_id
                WHERE dc.client_id = :user_id
                ORDER BY d.created_at DESC";
        
        $stmt = db()->prepare($sql);
        $stmt->execute(['user_id' => $user['id']]);
        $documents = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Error fetching client documents: " . $e->getMessage());
}

// Count documents by status
$documentCounts = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'in_movement' => 0
];

// Process document counts from the retrieved documents
foreach ($documents as $doc) {
    $documentCounts['total']++;
    
    $status = strtolower($doc['status']);
    if (isset($documentCounts[$status])) {
        $documentCounts[$status]++;
    } else if ($status === 'in_movement') {
        $documentCounts['in_movement']++;
    }
}

// Status classes for badges
$statusClasses = [
    'pending' => 'bg-yellow-100 text-yellow-800',
    'approved' => 'bg-green-100 text-green-800',
    'rejected' => 'bg-red-100 text-red-800',
    'in_movement' => 'bg-blue-100 text-blue-800',
    'done' => 'bg-indigo-100 text-indigo-800'
];

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

<!-- Dashboard Content -->
<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Welcome Header with Quick Stats -->
        <div class="bg-white shadow-sm rounded-lg p-6 mb-8 flex flex-col md:flex-row justify-between items-center">
            <div class="mb-4 md:mb-0">
                <h1 class="text-2xl font-semibold text-gray-900">Welcome, <?= htmlspecialchars($user['name']); ?></h1>
                <p class="text-gray-600">
                    <span class="font-medium uppercase"><?= htmlspecialchars($user['role']); ?></span> 
                    | Track your documents and their movement through departments
                </p>
            </div>
            <div class="bg-primary-50 rounded-lg px-6 py-3 flex items-center border border-primary-100">
                <i class="fas fa-chart-line text-primary-600 text-xl mr-3"></i>
                <div>
                    <p class="text-sm text-gray-600">Total Documents</p>
                    <p class="text-2xl font-bold text-primary-700"><?= $documentCounts['total']; ?></p>
                </div>
            </div>
        </div>
        
        <!-- Document Statistics Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white shadow-sm rounded-lg p-4 flex items-center border-l-4 border-yellow-400">
                <div class="p-3 rounded-full bg-yellow-100 mr-4">
                    <i class="fas fa-hourglass-half text-yellow-500 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Pending</p>
                    <p class="text-2xl font-semibold"><?= $documentCounts['pending']; ?></p>
                </div>
            </div>
            
            <div class="bg-white shadow-sm rounded-lg p-4 flex items-center border-l-4 border-green-400">
                <div class="p-3 rounded-full bg-green-100 mr-4">
                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Approved</p>
                    <p class="text-2xl font-semibold"><?= $documentCounts['approved']; ?></p>
                </div>
            </div>
            
            <div class="bg-white shadow-sm rounded-lg p-4 flex items-center border-l-4 border-red-400">
                <div class="p-3 rounded-full bg-red-100 mr-4">
                    <i class="fas fa-times-circle text-red-500 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Rejected</p>
                    <p class="text-2xl font-semibold"><?= $documentCounts['rejected']; ?></p>
                </div>
            </div>
            
            <div class="bg-white shadow-sm rounded-lg p-4 flex items-center border-l-4 border-blue-400">
                <div class="p-3 rounded-full bg-blue-100 mr-4">
                    <i class="fas fa-exchange-alt text-blue-500 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">In Movement</p>
                    <p class="text-2xl font-semibold"><?= $documentCounts['in_movement']; ?></p>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white shadow-sm rounded-lg mb-8 overflow-hidden">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="text-lg font-medium text-gray-900">Quick Actions</h2>
            </div>
            <div class="p-6 grid grid-cols-2 md:grid-cols-3 gap-4">
                <a href="track.php" class="bg-white border border-gray-200 hover:bg-gray-50 rounded-lg p-4 flex flex-col items-center text-center transition">
                    <div class="p-3 rounded-full bg-blue-100 mb-3">
                        <i class="fas fa-search text-blue-600 text-xl"></i>
                    </div>
                    <span class="text-gray-900 font-medium">Track Documents</span>
                </a>
                
                <a href="client_documents.php" class="bg-white border border-gray-200 hover:bg-gray-50 rounded-lg p-4 flex flex-col items-center text-center transition">
                    <div class="p-3 rounded-full bg-purple-100 mb-3">
                        <i class="fas fa-file-alt text-purple-600 text-xl"></i>
                    </div>
                    <span class="text-gray-900 font-medium">All Documents</span>
                </a>
                
                <a href="notifications.php" class="bg-white border border-gray-200 hover:bg-gray-50 rounded-lg p-4 flex flex-col items-center text-center transition">
                    <div class="p-3 rounded-full bg-yellow-100 mb-3">
                        <i class="fas fa-bell text-yellow-600 text-xl"></i>
                    </div>
                    <span class="text-gray-900 font-medium">Notifications</span>
                </a>
            </div>
        </div>
        
        <!-- My Documents -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">My Documents</h3>
                <a href="client_documents.php" class="text-sm font-medium text-primary-600 hover:text-primary-700">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            
            <?php if (empty($documents)): ?>
            <div class="p-6 text-center text-gray-500">
                <i class="fas fa-folder-open text-4xl mb-3"></i>
                <p>No documents found for your account.</p>
                <p class="text-sm mt-2">If you think this is an error, please contact support or try submitting a document.</p>
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
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php 
                        // Only show the latest 5 documents
                        $latestDocuments = array_slice($documents, 0, 5);
                        foreach ($latestDocuments as $document): 
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-full bg-primary-50 text-primary-600">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($document['title']); ?></div>
                                        <div class="text-sm text-gray-500">ID: <?= htmlspecialchars($document['doc_unique_id']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-800">
                                    <?= htmlspecialchars($document['type']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                <?= htmlspecialchars($document['department']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
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
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Document History/Activity -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">Document Activity</h3>
                <p class="text-sm text-gray-600 mt-1">Recent updates on your documents</p>
            </div>
            
            <?php if (empty($documents)): ?>
            <div class="p-6 text-center text-gray-500">
                <i class="fas fa-history text-4xl mb-3"></i>
                <p>No document activity to display.</p>
            </div>
            <?php else: ?>
            <div class="divide-y divide-gray-200">
                <?php 
                // Only show the latest 3 documents for activity
                $activityDocuments = array_slice($documents, 0, 3);
                foreach ($activityDocuments as $document): 
                ?>
                <div class="p-4 hover:bg-gray-50">
                    <div class="flex items-start">
                        <div class="h-10 w-10 rounded-full bg-primary-50 flex items-center justify-center flex-shrink-0">
                            <?php
                            $icon = 'fa-file-alt';
                            switch($document['status']) {
                                case 'approved': $icon = 'fa-check-circle'; break;
                                case 'rejected': $icon = 'fa-times-circle'; break;
                                case 'in_movement': $icon = 'fa-exchange-alt'; break;
                                case 'done': $icon = 'fa-check-double'; break;
                            }
                            ?>
                            <i class="fas <?= $icon ?> text-primary-600"></i>
                        </div>
                        <div class="ml-4 flex-1">
                            <div class="flex justify-between">
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900"><?= htmlspecialchars($document['title']); ?></h4>
                                    <p class="text-xs text-gray-500 mt-1">
                                        Currently in <span class="font-medium"><?= htmlspecialchars($document['department']); ?></span> department
                                    </p>
                                </div>
                                <span class="px-2 h-fit inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?= $statusClasses[$document['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                    <?= ucfirst(str_replace('_', ' ', $document['status'])); ?>
                                </span>
                            </div>
                            <div class="mt-2 text-xs text-gray-500 flex items-center">
                                <i class="far fa-calendar-alt mr-1"></i> 
                                <?= date('M d, Y', strtotime($document['created_at'])); ?>
                                <span class="mx-2">•</span>
                                <span>ID: <?= htmlspecialchars($document['doc_unique_id']); ?></span>
                            </div>
                            <div class="mt-2 flex justify-end">
                                <a href="client_document_view.php?id=<?= $document['id']; ?>" class="text-primary-600 hover:text-primary-900 text-xs font-medium">
                                    <i class="fas fa-eye mr-1"></i> View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>
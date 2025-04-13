<?php
/**
 * Dashboard Page
 * 
 * This page displays statistics and document listings for the logged-in user
 */

// Include header file
require_once '../includes/auth.php';

// Redirect if not logged in
requireLogin();

// Get user data
$user = getCurrentUser();

// Redirect clients to client dashboard
if ($user['role'] === 'client') {
    header('Location: client_dashboard.php');
    exit;
}

// Include database connection
require_once '../config/db.php';

// Initialize statistics
$stats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'in_movement' => 0,
    'done' => 0
];

// For admin and supervisor, show all documents
// For clerk and viewer, show only their department's documents
$isAdminOrSupervisor = hasRole(['admin', 'supervisor']);

try {
    // Get document counts for statistics
    if ($isAdminOrSupervisor) {
        // Admin/Supervisor: Get all document counts
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN status = 'in_movement' THEN 1 ELSE 0 END) as in_movement,
                    SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done
                FROM documents";
        $stmt = db()->prepare($sql);
        $stmt->execute();
    } else {
        // Clerk/Viewer: Get department's document counts
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN status = 'in_movement' THEN 1 ELSE 0 END) as in_movement,
                    SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done
                FROM documents
                WHERE department = :department";
        $stmt = db()->prepare($sql);
        $stmt->execute(['department' => $user['department']]);
    }
    
    // Get result
    $result = $stmt->fetch();
    
    // Update statistics if data found
    if ($result) {
        $stats = [
            'total' => (int)$result['total'],
            'pending' => (int)$result['pending'],
            'approved' => (int)$result['approved'],
            'rejected' => (int)$result['rejected'],
            'in_movement' => (int)$result['in_movement'],
            'done' => (int)$result['done']
        ];
    }
    
    // Get recent documents
    if ($isAdminOrSupervisor) {
        // Admin/Supervisor: Get all recent documents
        $sql = "SELECT 
                    d.id, d.doc_unique_id, d.title, d.type, d.department, d.status, 
                    d.created_at, u.name as uploader_name
                FROM documents d
                JOIN users u ON d.uploaded_by = u.id
                ORDER BY d.created_at DESC
                LIMIT 3";
        $stmt = db()->prepare($sql);
        $stmt->execute();
    } else {
        // Clerk/Viewer: Get department's recent documents
        $sql = "SELECT 
                    d.id, d.doc_unique_id, d.title, d.type, d.department, d.status, 
                    d.created_at, u.name as uploader_name
                FROM documents d
                JOIN users u ON d.uploaded_by = u.id
                WHERE d.department = :department
                ORDER BY d.created_at DESC
                LIMIT 3";
        $stmt = db()->prepare($sql);
        $stmt->execute(['department' => $user['department']]);
    }
    
    // Get recent documents
    $recentDocuments = $stmt->fetchAll();
    
    // Get documents by status
    $myUploads = [];
    
    // Get user's uploaded documents
    $sql = "SELECT 
                id, doc_unique_id, title, type, department, status, created_at
            FROM documents
            WHERE uploaded_by = :user_id
            ORDER BY created_at DESC
            LIMIT 3";
    $stmt = db()->prepare($sql);
    $stmt->execute(['user_id' => $user['id']]);
    $myUploads = $stmt->fetchAll();
    
    // Get recent document movements
    if ($isAdminOrSupervisor) {
        // Admin/Supervisor: Get all recent movements
        $sql = "SELECT 
                    m.id, d.doc_unique_id, d.title, m.from_department, m.to_department, 
                    u.name as moved_by_name, m.moved_at
                FROM document_movements m
                JOIN documents d ON m.document_id = d.id
                JOIN users u ON m.moved_by = u.id
                ORDER BY m.moved_at DESC
                LIMIT 3";
        $stmt = db()->prepare($sql);
        $stmt->execute();
    } else {
        // Clerk/Viewer: Get movements relevant to their department
        $sql = "SELECT 
                    m.id, d.doc_unique_id, d.title, m.from_department, m.to_department, 
                    u.name as moved_by_name, m.moved_at
                FROM document_movements m
                JOIN documents d ON m.document_id = d.id
                JOIN users u ON m.moved_by = u.id
                WHERE m.from_department = :department OR m.to_department = :department
                ORDER BY m.moved_at DESC
                LIMIT 3";
        $stmt = db()->prepare($sql);
        $stmt->execute(['department' => $user['department']]);
    }
    
    // Get recent movements
    $recentMovements = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while fetching dashboard data.";
}

// Set page title
$page_title = "Dashboard";

// Include header
include_once '../includes/header.php';
?>

<!-- Dashboard Content -->
<div class="py-6">
    <div class="mx-auto px-4 sm:px-6 md:px-8">
        <h1 class="text-2xl font-semibold text-gray-900">Dashboard</h1>
    </div>
    
    <div class="mx-auto px-4 sm:px-6 md:px-8">
        <!-- Welcome Card -->
        <div class="bg-white shadow rounded-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-2">Welcome, <?= htmlspecialchars($user['name']); ?></h2>
            <p class="text-gray-600">
                You're logged in as <span class="font-semibold uppercase"><?= htmlspecialchars($user['role']); ?></span> 
                in the <span class="font-semibold"><?= htmlspecialchars($user['department']); ?></span> department.
            </p>
        </div>
        
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 mb-8">
            <div class="bg-white shadow rounded-lg p-4 flex items-center">
                <div class="p-3 rounded-full bg-purple-100 mr-4">
                    <i class="fas fa-file-alt text-purple-500 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Total Documents</p>
                    <p class="text-2xl font-semibold"><?= $stats['total']; ?></p>
                </div>
            </div>
            
            <div class="bg-white shadow rounded-lg p-4 flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 mr-4">
                    <i class="fas fa-hourglass-half text-yellow-500 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Pending</p>
                    <p class="text-2xl font-semibold"><?= $stats['pending']; ?></p>
                </div>
            </div>
            
            <div class="bg-white shadow rounded-lg p-4 flex items-center">
                <div class="p-3 rounded-full bg-green-100 mr-4">
                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Approved</p>
                    <p class="text-2xl font-semibold"><?= $stats['approved']; ?></p>
                </div>
            </div>
            
            <div class="bg-white shadow rounded-lg p-4 flex items-center">
                <div class="p-3 rounded-full bg-red-100 mr-4">
                    <i class="fas fa-times-circle text-red-500 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Rejected</p>
                    <p class="text-2xl font-semibold"><?= $stats['rejected']; ?></p>
                </div>
            </div>
            
            <div class="bg-white shadow rounded-lg p-4 flex items-center">
                <div class="p-3 rounded-full bg-blue-100 mr-4">
                    <i class="fas fa-exchange-alt text-blue-500 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">In Movement</p>
                    <p class="text-2xl font-semibold"><?= $stats['in_movement']; ?></p>
                </div>
            </div>
            
            <div class="bg-white shadow rounded-lg p-4 flex items-center">
                <div class="p-3 rounded-full bg-indigo-100 mr-4">
                    <i class="fas fa-check-double text-indigo-500 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Complete</p>
                    <p class="text-2xl font-semibold"><?= $stats['done'] ?? 0; ?></p>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex flex-wrap gap-4 mb-8">
            <a href="upload.php" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md flex items-center">
                <i class="fas fa-upload mr-2"></i> Upload New Document
            </a>
            <a href="track.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md flex items-center">
                <i class="fas fa-search mr-2"></i> Search Documents
            </a>
            <?php if (hasRole(['admin', 'supervisor'])): ?>
            <a href="approve.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md flex items-center">
                <i class="fas fa-check-circle mr-2"></i> Approve Documents
            </a>
            <?php endif; ?>
            <a href="move.php" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md flex items-center">
                <i class="fas fa-exchange-alt mr-2"></i> Move Documents
            </a>
        </div>
        
        <!-- Recent Documents Table -->
        <div class="bg-white shadow rounded-lg overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">Recent Documents</h3>
                <span class="text-xs text-gray-500">Showing 3 most recent</span>
            </div>
            
            <?php if (empty($recentDocuments)): ?>
            <div class="p-6 text-center text-gray-500">
                <i class="fas fa-folder-open text-4xl mb-3"></i>
                <p>No documents found. Upload a new document to get started.</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Document ID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uploaded By</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recentDocuments as $doc): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($doc['doc_unique_id']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($doc['title']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($doc['type']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($doc['department']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                    $statusClasses = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'approved' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                        'in_movement' => 'bg-blue-100 text-blue-800'
                                    ];
                                    $statusClass = $statusClasses[$doc['status']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass; ?>">
                                    <?= ucfirst(str_replace('_', ' ', $doc['status'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($doc['uploader_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('M d, Y', strtotime($doc['created_at'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="view_document.php?id=<?= $doc['id']; ?>" class="text-primary-600 hover:text-primary-900 mr-3">View</a>
                                <?php if (canApproveDocuments() && $doc['status'] === 'pending'): ?>
                                <a href="approve.php?id=<?= $doc['id']; ?>" class="text-green-600 hover:text-green-900 mr-3">Approve</a>
                                <?php endif; ?>
                                <a href="move.php?id=<?= $doc['id']; ?>" class="text-blue-600 hover:text-blue-900">Move</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <div class="px-6 py-3 bg-gray-50 text-right">
                <a href="track.php" class="text-primary-600 hover:text-primary-900 text-sm font-medium">
                    View All Documents <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
        
        <!-- Two Column Layout for My Uploads and Recent Movements -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- My Uploads -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">My Uploads</h3>
                    <span class="text-xs text-gray-500">Showing 3 most recent</span>
                </div>
                
                <?php if (empty($myUploads)): ?>
                <div class="p-6 text-center text-gray-500">
                    <i class="fas fa-cloud-upload-alt text-4xl mb-3"></i>
                    <p>You haven't uploaded any documents yet.</p>
                </div>
                <?php else: ?>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($myUploads as $doc): ?>
                    <div class="p-4 hover:bg-gray-50">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="text-sm font-medium text-gray-900"><?= htmlspecialchars($doc['title']); ?></h4>
                                <p class="text-xs text-gray-500 mt-1">
                                    <span class="mr-3"><?= htmlspecialchars($doc['doc_unique_id']); ?></span>
                                    <span><?= htmlspecialchars($doc['type']); ?></span>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="far fa-calendar-alt mr-1"></i> 
                                    <?= date('M d, Y', strtotime($doc['created_at'])); ?>
                                </p>
                            </div>
                            <div>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?= $statusClasses[$doc['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                    <?= ucfirst(str_replace('_', ' ', $doc['status'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="mt-3 flex justify-end">
                            <a href="view_document.php?id=<?= $doc['id']; ?>" class="text-primary-600 hover:text-primary-900 text-xs font-medium mr-3">
                                View
                            </a>
                            <a href="track_history.php?id=<?= $doc['id']; ?>" class="text-blue-600 hover:text-blue-900 text-xs font-medium">
                                Track History
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="px-6 py-3 bg-gray-50 text-right border-t border-gray-200">
                    <a href="my_uploads.php" class="text-primary-600 hover:text-primary-900 text-sm font-medium">
                        View All My Uploads <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            
            <!-- Recent Movements -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">Recent Movements</h3>
                    <span class="text-xs text-gray-500">Showing 3 most recent</span>
                </div>
                
                <?php if (empty($recentMovements)): ?>
                <div class="p-6 text-center text-gray-500">
                    <i class="fas fa-exchange-alt text-4xl mb-3"></i>
                    <p>No document movements recorded yet.</p>
                </div>
                <?php else: ?>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($recentMovements as $movement): ?>
                    <div class="p-4 hover:bg-gray-50">
                        <div class="flex flex-col">
                            <h4 class="text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($movement['title']); ?> 
                                <span class="text-gray-500">(<?= htmlspecialchars($movement['doc_unique_id']); ?>)</span>
                            </h4>
                            <div class="flex items-center mt-2">
                                <span class="text-xs text-gray-700"><?= htmlspecialchars($movement['from_department']); ?></span>
                                <i class="fas fa-arrow-right text-xs text-gray-500 mx-2"></i>
                                <span class="text-xs text-gray-700"><?= htmlspecialchars($movement['to_department']); ?></span>
                            </div>
                            <div class="text-xs text-gray-500 mt-2">
                                <span>Moved by <?= htmlspecialchars($movement['moved_by_name']); ?></span>
                                <span class="ml-2">
                                    <i class="far fa-clock mr-1"></i> 
                                    <?= date('M d, Y - h:i A', strtotime($movement['moved_at'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="px-6 py-3 bg-gray-50 text-right border-t border-gray-200">
                    <a href="all_movements.php" class="text-primary-600 hover:text-primary-900 text-sm font-medium">
                        View All Movements <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?> 
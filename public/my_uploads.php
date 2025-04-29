<?php
/**
 * My Uploads Page
 * 
 * This page displays all documents uploaded by the current user
 */

// Include header file
require_once '../includes/auth.php';

// Redirect if not logged in
requireLogin();

// Get user data
$user = getCurrentUser();

// Include database connection
require_once '../config/db.php';

// Initialize variables for pagination
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$recordsPerPage = 20;
$offset = ($currentPage - 1) * $recordsPerPage;
$totalRecords = 0;

try {
    // Count total uploads by user
    $sql = "SELECT COUNT(*) as total FROM documents WHERE uploaded_by = :user_id";
    $stmt = db()->prepare($sql);
    $stmt->execute(['user_id' => $user['id']]);
    $result = $stmt->fetch();
    $totalRecords = $result ? (int)$result['total'] : 0;
    
    // Calculate total pages
    $totalPages = ceil($totalRecords / $recordsPerPage);
    
    // Get user's uploaded documents with pagination
    $sql = "SELECT 
                d.id, d.doc_unique_id, d.title, d.type, d.department, d.status, d.created_at
            FROM documents d
            WHERE d.uploaded_by = :user_id
            ORDER BY d.created_at DESC
            LIMIT :offset, :limit";
    
    $stmt = db()->prepare($sql);
    $stmt->bindValue(':user_id', $user['id'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
    $stmt->execute();
    
    $myUploads = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("My Uploads error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while fetching your uploads.";
    $myUploads = [];
}

// Set page title
$page_title = "My Uploads";

// Include header
include_once '../includes/header.php';
?>

<!-- My Uploads Content -->
<div class="py-6">
    <div class="mx-auto px-4 sm:px-6 md:px-8">
        <h1 class="text-2xl font-semibold text-gray-900">My Uploads</h1>
    </div>
    
    <div class="mx-auto px-4 sm:px-6 md:px-8 mt-6">
        <!-- My Uploads Table -->
        <div class="bg-white shadow rounded-lg overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">All Documents You've Uploaded</h3>
                <a href="upload.php" class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1 rounded-md text-sm flex items-center">
                    <i class="fas fa-plus mr-1"></i> Upload New
                </a>
            </div>
            
            <?php if (empty($myUploads)): ?>
            <div class="p-6 text-center text-gray-500">
                <i class="fas fa-cloud-upload-alt text-4xl mb-3"></i>
                <p>You haven't uploaded any documents yet.</p>
                <div class="mt-4">
                    <a href="upload.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700">
                        <i class="fas fa-upload mr-2"></i> Upload Your First Document
                    </a>
                </div>
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
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($myUploads as $doc): ?>
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
                                        'in_movement' => 'bg-blue-100 text-blue-800',
                                        'done' => 'bg-indigo-100 text-indigo-800'
                                    ];
                                    $statusClass = $statusClasses[$doc['status']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass; ?>">
                                    <?= ucfirst(str_replace('_', ' ', $doc['status'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('M d, Y', strtotime($doc['created_at'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="view_document.php?id=<?= $doc['id']; ?>" class="text-primary-600 hover:text-primary-900 mr-3">View</a>
                                <a href="track_history.php?id=<?= $doc['id']; ?>" class="text-blue-600 hover:text-blue-900">Track History</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <div class="flex justify-between items-center">
                    <div class="text-sm text-gray-500">
                        Showing <?= min(($currentPage - 1) * $recordsPerPage + 1, $totalRecords) ?> 
                        to <?= min($currentPage * $recordsPerPage, $totalRecords) ?> 
                        of <?= $totalRecords ?> uploads
                    </div>
                    <div class="flex space-x-1">
                        <?php if ($currentPage > 1): ?>
                        <a href="?page=<?= $currentPage - 1 ?>" class="px-3 py-1 rounded-md text-sm text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                            Previous
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                        <a href="?page=<?= $currentPage + 1 ?>" class="px-3 py-1 rounded-md text-sm text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                            Next
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?> 
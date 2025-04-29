<?php
/**
 * All Movements Page
 * 
 * This page displays all document movements with pagination
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

// For admin and supervisor, show all movements
// For clerk and viewer, show only their department's movements
$isAdminOrSupervisor = hasRole(['admin', 'supervisor']);

try {
    // Count total movements based on user role
    if ($isAdminOrSupervisor) {
        // Admin/Supervisor: Count all movements
        $sql = "SELECT COUNT(*) as total FROM document_movements";
        $stmt = db()->prepare($sql);
        $stmt->execute();
    } else {
        // Clerk/Viewer: Count movements relevant to their department
        $sql = "SELECT COUNT(*) as total 
                FROM document_movements 
                WHERE from_department = :department OR to_department = :department";
        $stmt = db()->prepare($sql);
        $stmt->execute(['department' => $user['department']]);
    }
    
    $result = $stmt->fetch();
    $totalRecords = $result ? (int)$result['total'] : 0;
    
    // Calculate total pages
    $totalPages = ceil($totalRecords / $recordsPerPage);
    
    // Get movements with pagination
    if ($isAdminOrSupervisor) {
        // Admin/Supervisor: Get all movements
        $sql = "SELECT 
                    m.id, d.id as document_id, d.doc_unique_id, d.title, m.from_department, m.to_department, 
                    u.name as moved_by_name, m.note, m.moved_at
                FROM document_movements m
                JOIN documents d ON m.document_id = d.id
                JOIN users u ON m.moved_by = u.id
                ORDER BY m.moved_at DESC
                LIMIT :offset, :limit";
        
        $stmt = db()->prepare($sql);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        // Clerk/Viewer: Get movements relevant to their department
        $sql = "SELECT 
                    m.id, d.id as document_id, d.doc_unique_id, d.title, m.from_department, m.to_department, 
                    u.name as moved_by_name, m.note, m.moved_at
                FROM document_movements m
                JOIN documents d ON m.document_id = d.id
                JOIN users u ON m.moved_by = u.id
                WHERE m.from_department = :department OR m.to_department = :department
                ORDER BY m.moved_at DESC
                LIMIT :offset, :limit";
        
        $stmt = db()->prepare($sql);
        $stmt->bindValue(':department', $user['department'], PDO::PARAM_STR);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    $movements = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("All Movements error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while fetching movement data.";
    $movements = [];
}

// Set page title
$page_title = "All Document Movements";

// Include header
include_once '../includes/header.php';
?>

<!-- All Movements Content -->
<div class="py-6">
    <div class="mx-auto px-4 sm:px-6 md:px-8">
        <h1 class="text-2xl font-semibold text-gray-900">Document Movements</h1>
    </div>
    
    <div class="mx-auto px-4 sm:px-6 md:px-8 mt-6">
        <!-- Movements Table -->
        <div class="bg-white shadow rounded-lg overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">All Document Movements</h3>
                <a href="move.php" class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1 rounded-md text-sm flex items-center">
                    <i class="fas fa-exchange-alt mr-1"></i> Move Document
                </a>
            </div>
            
            <?php if (empty($movements)): ?>
            <div class="p-6 text-center text-gray-500">
                <i class="fas fa-exchange-alt text-4xl mb-3"></i>
                <p>No document movements recorded yet.</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Document ID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">To</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Moved By</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($movements as $movement): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($movement['doc_unique_id']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($movement['title']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($movement['from_department']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($movement['to_department']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($movement['moved_by_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('M d, Y - h:i A', strtotime($movement['moved_at'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="view_document.php?id=<?= $movement['document_id']; ?>" class="text-primary-600 hover:text-primary-900 mr-3">View Document</a>
                                <a href="track_history.php?id=<?= $movement['document_id']; ?>" class="text-blue-600 hover:text-blue-900">Track History</a>
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
                        of <?= $totalRecords ?> movements
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
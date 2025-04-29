<?php
/**
 * Document Search Page
 * 
 * This page allows users to search for documents using various criteria.
 */

// Include authentication utilities
require_once '../includes/auth.php';

// Require login to access this page
requireLogin();

// Get current user data
$user = getCurrentUser();

// Include database connection
require_once '../config/db.php';

// Initialize variables
$documents = [];
$totalDocuments = 0;
$departments = [];
$documentTypes = [];
$hasSearched = false;
$resultsPerPage = 10;
$currentPage = 1;
$totalPages = 1;

// Get search filters from query string or form submission
$filters = [
    'title' => $_GET['title'] ?? '',
    'doc_id' => $_GET['doc_id'] ?? '',
    'type' => $_GET['type'] ?? '',
    'department' => $_GET['department'] ?? '',
    'status' => $_GET['status'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'uploaded_by' => $_GET['uploaded_by'] ?? ''
];

// Get pagination parameters
if (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0) {
    $currentPage = (int)$_GET['page'];
}

// Get departments for dropdown
try {
    $sql = "SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department";
    $stmt = db()->prepare($sql);
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching departments: " . $e->getMessage());
    $departments = [];
}

// Get document types
try {
    $sql = "SELECT DISTINCT type FROM documents WHERE type IS NOT NULL AND type != '' ORDER BY type";
    $stmt = db()->prepare($sql);
    $stmt->execute();
    $documentTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching document types: " . $e->getMessage());
    $documentTypes = [];
}

// Process search if any filter is applied
if (isset($_GET['search']) || array_filter($filters)) {
    $hasSearched = true;
    
    try {
        // Build query conditions based on filters
        $conditions = [];
        $params = [];
        
        if (!empty($filters['title'])) {
            $conditions[] = "d.title LIKE :title";
            $params['title'] = '%' . $filters['title'] . '%';
        }
        
        if (!empty($filters['doc_id'])) {
            $conditions[] = "d.doc_unique_id LIKE :doc_id";
            $params['doc_id'] = '%' . $filters['doc_id'] . '%';
        }
        
        if (!empty($filters['type'])) {
            $conditions[] = "d.type = :type";
            $params['type'] = $filters['type'];
        }
        
        if (!empty($filters['department'])) {
            $conditions[] = "d.department = :department";
            $params['department'] = $filters['department'];
        }
        
        if (!empty($filters['status'])) {
            $conditions[] = "d.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $conditions[] = "DATE(d.created_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $conditions[] = "DATE(d.created_at) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['uploaded_by'])) {
            $conditions[] = "u.name LIKE :uploaded_by";
            $params['uploaded_by'] = '%' . $filters['uploaded_by'] . '%';
        }
        
        // Access control - non-admin users can only see their department's documents
        if (!hasRole('admin')) {
            $conditions[] = "(d.department = :user_department OR d.uploaded_by = :user_id)";
            $params['user_department'] = $user['department'];
            $params['user_id'] = $user['id'];
        }
        
        // Construct where clause
        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        // Count total matching documents (for pagination)
        $countSql = "SELECT COUNT(*) FROM documents d
                    LEFT JOIN users u ON d.uploaded_by = u.id
                    $whereClause";
        $countStmt = db()->prepare($countSql);
        $countStmt->execute($params);
        $totalDocuments = $countStmt->fetchColumn();
        
        // Calculate pagination
        $totalPages = ceil($totalDocuments / $resultsPerPage);
        $offset = ($currentPage - 1) * $resultsPerPage;
        
        // Get documents with pagination
        $sql = "SELECT d.*, u.name as uploader_name
                FROM documents d
                LEFT JOIN users u ON d.uploaded_by = u.id
                $whereClause
                ORDER BY d.created_at DESC
                LIMIT :offset, :limit";
        
        $stmt = db()->prepare($sql);
        
        // Bind pagination parameters
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $resultsPerPage, PDO::PARAM_INT);
        
        // Bind search parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        
        $stmt->execute();
        $documents = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Search error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while searching for documents.";
    }
}

// Set page title
$page_title = "Document Search";

// Include header
include_once '../includes/header.php';
?>

<div class="py-6">
    <div class="mx-auto px-4 sm:px-6 md:px-8">
        <h1 class="text-2xl font-semibold text-gray-900">Document Search</h1>
        <p class="mt-1 text-sm text-gray-600">Search for documents using various criteria</p>
    </div>
    
    <div class="mx-auto px-4 sm:px-6 md:px-8">
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
        
        <!-- Search Form -->
        <div class="bg-white shadow rounded-lg overflow-hidden mt-6">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">Search Filters</h3>
            </div>
            
            <form action="search.php" method="GET" class="p-6">
                <input type="hidden" name="search" value="1">
                
                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                    <!-- Document Title -->
                    <div class="sm:col-span-3">
                        <label for="title" class="block text-sm font-medium text-gray-700">Document Title</label>
                        <div class="mt-1">
                            <input type="text" name="title" id="title" value="<?= htmlspecialchars($filters['title']); ?>"
                                class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>
                    
                    <!-- Document ID -->
                    <div class="sm:col-span-3">
                        <label for="doc_id" class="block text-sm font-medium text-gray-700">Document ID</label>
                        <div class="mt-1">
                            <input type="text" name="doc_id" id="doc_id" value="<?= htmlspecialchars($filters['doc_id']); ?>"
                                class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>
                    
                    <!-- Document Type -->
                    <div class="sm:col-span-2">
                        <label for="type" class="block text-sm font-medium text-gray-700">Document Type</label>
                        <div class="mt-1">
                            <select id="type" name="type" 
                                class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                <option value="">All Types</option>
                                <?php foreach ($documentTypes as $type): ?>
                                <option value="<?= htmlspecialchars($type); ?>" <?= ($filters['type'] === $type) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($type); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Department -->
                    <div class="sm:col-span-2">
                        <label for="department" class="block text-sm font-medium text-gray-700">Department</label>
                        <div class="mt-1">
                            <select id="department" name="department" 
                                class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept); ?>" <?= ($filters['department'] === $dept) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($dept); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Status -->
                    <div class="sm:col-span-2">
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <div class="mt-1">
                            <select id="status" name="status" 
                                class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                <option value="">All Statuses</option>
                                <option value="pending" <?= ($filters['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_movement" <?= ($filters['status'] === 'in_movement') ? 'selected' : ''; ?>>In Movement</option>
                                <option value="approved" <?= ($filters['status'] === 'approved') ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?= ($filters['status'] === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Date Range -->
                    <div class="sm:col-span-2">
                        <label for="date_from" class="block text-sm font-medium text-gray-700">Date From</label>
                        <div class="mt-1">
                            <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($filters['date_from']); ?>"
                                class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>
                    
                    <div class="sm:col-span-2">
                        <label for="date_to" class="block text-sm font-medium text-gray-700">Date To</label>
                        <div class="mt-1">
                            <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($filters['date_to']); ?>"
                                class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>
                    
                    <!-- Uploaded By -->
                    <div class="sm:col-span-2">
                        <label for="uploaded_by" class="block text-sm font-medium text-gray-700">Uploaded By</label>
                        <div class="mt-1">
                            <input type="text" name="uploaded_by" id="uploaded_by" value="<?= htmlspecialchars($filters['uploaded_by']); ?>"
                                class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex items-center justify-end space-x-3">
                    <a href="search.php" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        <i class="fas fa-eraser mr-2"></i> Clear
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        <i class="fas fa-search mr-2"></i> Search
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Search Results -->
        <?php if ($hasSearched): ?>
        <div class="mt-6">
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">Search Results</h3>
                    <span class="text-sm text-gray-500"><?= number_format($totalDocuments); ?> document(s) found</span>
                </div>
                
                <?php if (empty($documents)): ?>
                <div class="p-8 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                        <i class="fas fa-search text-2xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">No documents found</h3>
                    <p class="mt-2 text-sm text-gray-500">Try adjusting your search criteria to find what you're looking for.</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Document
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Department
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Uploaded By
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($documents as $document): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-md bg-primary-100 text-primary-700">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <a href="view_document.php?id=<?= $document['id']; ?>" class="hover:text-primary-600">
                                                    <?= htmlspecialchars($document['title']); ?>
                                                </a>
                                            </div>
                                            <div class="text-sm text-gray-500 flex">
                                                <span class="mr-2">ID: <?= htmlspecialchars($document['doc_unique_id']); ?></span>
                                                <span class="mr-2">|</span>
                                                <span><?= htmlspecialchars($document['type']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($document['department']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $statusClass = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'in_movement' => 'bg-blue-100 text-blue-800',
                                        'approved' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800'
                                    ];
                                    $statusText = [
                                        'pending' => 'Pending',
                                        'in_movement' => 'In Movement',
                                        'approved' => 'Approved',
                                        'rejected' => 'Rejected'
                                    ];
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusClass[$document['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                        <?= $statusText[$document['status']] ?? ucfirst($document['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($document['uploader_name'] ?? 'Unknown'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('M d, Y', strtotime($document['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="view_document.php?id=<?= $document['id']; ?>" class="text-primary-600 hover:text-primary-900 mr-3">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    
                                    <?php if (($document['status'] !== 'approved' && $document['status'] !== 'rejected') && 
                                              (hasRole(['admin', 'department_head', 'supervisor']) || 
                                              $user['department'] === $document['department'] || 
                                              $document['uploaded_by'] === $user['id'])): ?>
                                    <a href="move.php?id=<?= $document['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-exchange-alt"></i> Move
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (($document['status'] !== 'approved' && $document['status'] !== 'rejected') && 
                                               hasRole(['admin', 'supervisor', 'manager'])): ?>
                                    <a href="approve.php?id=<?= $document['id']; ?>&action=approve" class="text-green-600 hover:text-green-900 mr-3">
                                        <i class="fas fa-check"></i> Approve
                                    </a>
                                    <a href="approve.php?id=<?= $document['id']; ?>&action=reject" class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-times"></i> Reject
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                    <nav class="flex items-center justify-between">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <?php if ($currentPage > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage - 1])); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Previous
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($currentPage < $totalPages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage + 1])); ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Next
                            </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing
                                    <span class="font-medium"><?= min(($currentPage - 1) * $resultsPerPage + 1, $totalDocuments); ?></span>
                                    to
                                    <span class="font-medium"><?= min($currentPage * $resultsPerPage, $totalDocuments); ?></span>
                                    of
                                    <span class="font-medium"><?= $totalDocuments; ?></span>
                                    results
                                </p>
                            </div>
                            
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <?php if ($currentPage > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage - 1])); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Previous</span>
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $startPage = max(1, $currentPage - 2);
                                    $endPage = min($totalPages, $startPage + 4);
                                    if ($endPage - $startPage < 4) {
                                        $startPage = max(1, $endPage - 4);
                                    }
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++):
                                    ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 <?= $i === $currentPage ? 'bg-primary-50 text-primary-600 border-primary-500 z-10' : 'bg-white text-gray-500 hover:bg-gray-50'; ?> text-sm font-medium">
                                        <?= $i; ?>
                                    </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($currentPage < $totalPages): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage + 1])); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Next</span>
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    </nav>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?> 
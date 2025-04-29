<?php
/**
 * Document Tracking Page
 * 
 * This page allows users to search and track documents in the system
 */

// Include authentication utilities
require_once '../includes/auth.php';

// Require login to access this page
requireLogin();

// Get current user data
$user = getCurrentUser();

// Include database connection
require_once '../config/db.php';

// Get departments for dropdown filter
try {
    $sql = "SELECT DISTINCT department FROM users ORDER BY department";
    $stmt = db()->prepare($sql);
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching departments: " . $e->getMessage());
    $departments = [];
}

// Document types for filter
$documentTypes = [
    'Invoice',
    'Delivery Note',
    'Project Authorization',
    'Memo',
    'Contract',
    'Report',
    'Proposal',
    'Policy',
    'Receipt',
    'Other'
];

// Document statuses for filter
$statuses = [
    'pending' => 'Pending',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
    'in_movement' => 'In Movement',
    'done' => 'Complete'
];

// Process search request
$searchResults = [];
$totalResults = 0;
$searchPerformed = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $searchPerformed = true;
    
    // Get search parameters
    $searchTitle = isset($_GET['title']) ? trim($_GET['title']) : '';
    $searchType = isset($_GET['type']) ? $_GET['type'] : '';
    $searchDepartment = isset($_GET['department']) ? $_GET['department'] : '';
    $searchStatus = isset($_GET['status']) ? $_GET['status'] : '';
    $searchDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $searchDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    
    try {
        // Build search query
        $sql = "SELECT d.id, d.doc_unique_id, d.title, d.type, d.department, d.status, d.created_at, 
                       u.name as uploader_name
                FROM documents d
                JOIN users u ON d.uploaded_by = u.id
                WHERE 1=1";
        
        $params = [];
        
        // Add filters if provided
        if (!empty($searchTitle)) {
            $sql .= " AND d.title LIKE :title";
            $params['title'] = "%$searchTitle%";
        }
        
        if (!empty($searchType)) {
            $sql .= " AND d.type = :type";
            $params['type'] = $searchType;
        }
        
        if (!empty($searchDepartment)) {
            $sql .= " AND d.department = :department";
            $params['department'] = $searchDepartment;
        }
        
        if (!empty($searchStatus)) {
            $sql .= " AND d.status = :status";
            $params['status'] = $searchStatus;
        }
        
        if (!empty($searchDateFrom)) {
            $sql .= " AND DATE(d.created_at) >= :date_from";
            $params['date_from'] = $searchDateFrom;
        }
        
        if (!empty($searchDateTo)) {
            $sql .= " AND DATE(d.created_at) <= :date_to";
            $params['date_to'] = $searchDateTo;
        }
        
        // For non-admin users, limit to their department unless they are supervisors
        if (!hasRole(['admin', 'supervisor'])) {
            $sql .= " AND d.department = :user_department";
            $params['user_department'] = $user['department'];
        }
        
        // Count total results for pagination
        $countSql = str_replace("SELECT d.id, d.doc_unique_id, d.title, d.type, d.department, d.status, d.created_at, 
                       u.name as uploader_name", "SELECT COUNT(*) as total", $sql);
        $countStmt = db()->prepare($countSql);
        $countStmt->execute($params);
        $totalResults = $countStmt->fetch()['total'];
        
        // Add sorting and pagination
        $sql .= " ORDER BY d.created_at DESC";
        
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $searchResults = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Search error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while searching documents.";
    }
}

// Set page title
$page_title = "Track Documents";

// Include header
include_once '../includes/header.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
        <h1 class="text-2xl font-bold text-gray-900">Track Documents</h1>
        <p class="mt-1 text-sm text-gray-600">Search and find documents in the system</p>
    </div>
    
    <div class="max-w-7xl mx-auto mt-6 px-4 sm:px-6 md:px-8">
        <!-- Search Form -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-100 mb-8">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center">
                <i class="fas fa-search text-primary-500 text-xl mr-3"></i>
                <h3 class="text-lg font-semibold text-gray-800">Search Filters</h3>
            </div>
            
            <form action="track.php" method="GET" class="p-6">
                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                    <!-- Document Title -->
                    <div class="sm:col-span-3">
                        <label for="title" class="block text-sm font-medium text-gray-700">Document Title</label>
                        <div class="mt-1">
                            <input type="text" name="title" id="title" 
                                class="focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                placeholder="Search by title..."
                                value="<?= isset($_GET['title']) ? htmlspecialchars($_GET['title']) : ''; ?>">
                        </div>
                    </div>
                    
                    <!-- Document Type -->
                    <div class="sm:col-span-3">
                        <label for="type" class="block text-sm font-medium text-gray-700">Document Type</label>
                        <div class="mt-1">
                            <select id="type" name="type" 
                                class="focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                <option value="">All Types</option>
                                <?php foreach ($documentTypes as $type): ?>
                                <option value="<?= htmlspecialchars($type); ?>" <?= (isset($_GET['type']) && $_GET['type'] === $type) ? 'selected' : ''; ?>>
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
                                class="focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                <option value="">All Departments</option>
                                <!-- Common departments -->
                                <option value="IT" <?= (isset($_GET['department']) && $_GET['department'] === 'IT') ? 'selected' : ''; ?>>IT</option>
                                <option value="Finance" <?= (isset($_GET['department']) && $_GET['department'] === 'Finance') ? 'selected' : ''; ?>>Finance</option>
                                <option value="HR" <?= (isset($_GET['department']) && $_GET['department'] === 'HR') ? 'selected' : ''; ?>>HR</option>
                                <option value="Legal" <?= (isset($_GET['department']) && $_GET['department'] === 'Legal') ? 'selected' : ''; ?>>Legal</option>
                                <option value="Operations" <?= (isset($_GET['department']) && $_GET['department'] === 'Operations') ? 'selected' : ''; ?>>Operations</option>
                                <option value="Procurement" <?= (isset($_GET['department']) && $_GET['department'] === 'Procurement') ? 'selected' : ''; ?>>Procurement</option>
                                <option value="Administration" <?= (isset($_GET['department']) && $_GET['department'] === 'Administration') ? 'selected' : ''; ?>>Administration</option>
                                <!-- Database departments that aren't in the standard list -->
                                <?php 
                                $standardDepts = ['IT', 'Finance', 'HR', 'Legal', 'Operations', 'Procurement', 'Administration'];
                                foreach ($departments as $dept): 
                                    if (!in_array($dept, $standardDepts)):
                                ?>
                                <option value="<?= htmlspecialchars($dept); ?>" <?= (isset($_GET['department']) && $_GET['department'] === $dept) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($dept); ?>
                                </option>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Status -->
                    <div class="sm:col-span-2">
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <div class="mt-1">
                            <select id="status" name="status" 
                                class="focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                <option value="">All Statuses</option>
                                <?php foreach ($statuses as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value); ?>" <?= (isset($_GET['status']) && $_GET['status'] === $value) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($label); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Date Range -->
                    <div class="sm:col-span-2">
                        <label for="date_from" class="block text-sm font-medium text-gray-700">Date Range</label>
                        <div class="mt-1 flex space-x-2">
                            <input type="date" name="date_from" id="date_from" 
                                class="focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                placeholder="From"
                                value="<?= isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>">
                            <input type="date" name="date_to" id="date_to" 
                                class="focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                placeholder="To"
                                value="<?= isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <a href="track.php" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 mr-3">
                        <i class="fas fa-times mr-2"></i> Clear
                    </a>
                    <button type="submit" name="search" value="1" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        <i class="fas fa-search mr-2"></i> Search Documents
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Search Results -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-file-alt text-primary-500 text-xl mr-3"></i>
                    <h3 class="text-lg font-semibold text-gray-800">Documents</h3>
                </div>
                <?php if ($searchPerformed): ?>
                <span class="text-sm text-gray-600">
                    <?= $totalResults ?> document<?= $totalResults !== 1 ? 's' : '' ?> found
                </span>
                <?php endif; ?>
            </div>
            
            <?php if ($searchPerformed && empty($searchResults)): ?>
            <div class="p-6 text-center">
                <div class="py-6">
                    <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-800 mb-1">No documents found</h3>
                    <p class="text-gray-600">Try adjusting your search filters</p>
                </div>
            </div>
            <?php elseif (!$searchPerformed): ?>
            <div class="p-6 text-center">
                <div class="py-6">
                    <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-800 mb-1">Search for documents</h3>
                    <p class="text-gray-600">Use the filters above to find documents</p>
                </div>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Document ID
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Title
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Type
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Department
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Uploaded By
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($searchResults as $document): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($document['doc_unique_id']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($document['title']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($document['type']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($document['department']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $statusClass = 'bg-gray-100 text-gray-800';
                                switch ($document['status']) {
                                    case 'pending':
                                        $statusClass = 'bg-yellow-100 text-yellow-800';
                                        break;
                                    case 'approved':
                                        $statusClass = 'bg-green-100 text-green-800';
                                        break;
                                    case 'rejected':
                                        $statusClass = 'bg-red-100 text-red-800';
                                        break;
                                    case 'in_movement':
                                        $statusClass = 'bg-blue-100 text-blue-800';
                                        break;
                                }
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass; ?>">
                                    <?= ucfirst($document['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= date('M d, Y', strtotime($document['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($document['uploader_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="view_document.php?id=<?= $document['id']; ?>" class="text-primary-600 hover:text-primary-900 mr-3">
                                    View
                                </a>
                                <?php if (hasRole(['admin', 'supervisor'])): ?>
                                <a href="approve.php?id=<?= $document['id']; ?>" class="text-primary-600 hover:text-primary-900 mr-3">
                                    Approve
                                </a>
                                <?php endif; ?>
                                <a href="move.php?id=<?= $document['id']; ?>" class="text-primary-600 hover:text-primary-900">
                                    Move
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?> 
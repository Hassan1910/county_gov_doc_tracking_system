<?php
/**
 * Client Documents Page
 * 
 * This page shows all documents for a client with filtering and sorting options.
 */

// Include authentication utilities
require_once '../includes/auth.php';

// Require login to access this page
requireLogin();

// Get current user data
$user = getCurrentUser();

// Redirect non-client users to the regular dashboard
if (!in_array($user['role'], ['client', 'contractor'])) {
    header('Location: dashboard.php');
    exit;
}

// Include database connection
require_once '../config/db.php';

// Initialize variables for filter and sorting
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filterType = isset($_GET['type']) ? $_GET['type'] : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Validate sort parameters to prevent SQL injection
$validSortFields = ['created_at', 'title', 'type', 'department', 'status'];
$sortBy = in_array($sortBy, $validSortFields) ? $sortBy : 'created_at';
$sortOrder = ($sortOrder === 'ASC') ? 'ASC' : 'DESC';

// Fetch client's documents with filtering
try {
    // First, let's check which columns exist in the documents table
    $checkDocUniqueId = db()->query("SHOW COLUMNS FROM documents LIKE 'doc_unique_id'");
    $hasDocUniqueId = $checkDocUniqueId->rowCount() > 0;
    
    $checkSubmitterId = db()->query("SHOW COLUMNS FROM documents LIKE 'submitter_id'");
    $hasSubmitterId = $checkSubmitterId->rowCount() > 0;
    
    // Check if document_clients table exists
    $checkTable = db()->query("SHOW TABLES LIKE 'document_clients'");
    $hasDocumentClientsTable = $checkTable->rowCount() > 0;
    
    // Build the base query
    $sql = "SELECT d.id, d.title, d.type, d.department, d.status, d.created_at,
            u.name as clerk_name";
    
    // Add doc_unique_id if it exists
    if ($hasDocUniqueId) {
        $sql .= ", d.doc_unique_id";
    } else {
        $sql .= ", d.id as doc_unique_id";
    }
    
    $sql .= " FROM documents d
            JOIN users u ON d.uploaded_by = u.id
            WHERE ";
    
    $conditions = [];
    
    // Add submitter_id condition if exists
    if ($hasSubmitterId) {
        $conditions[] = "d.submitter_id = :user_id";
    }
    
    // Add document_clients join condition if the table exists
    if ($hasDocumentClientsTable) {
        $conditions[] = "d.id IN (SELECT document_id FROM document_clients WHERE client_id = :client_id)";
    }
    
    // If no conditions are added, default to checking uploaded_by
    if (empty($conditions)) {
        $conditions[] = "d.uploaded_by = :user_id";
    }
    
    $sql .= "(" . implode(" OR ", $conditions) . ")";
    
    // Add filters
    if (!empty($filterStatus)) {
        $sql .= " AND d.status = :status";
    }
    
    if (!empty($filterType)) {
        $sql .= " AND d.type = :type";
    }
    
    // Add sorting
    $sql .= " ORDER BY d.$sortBy $sortOrder";
    
    $stmt = db()->prepare($sql);
    
    // Bind parameters
    $params = [
        'user_id' => $user['id']
    ];
    
    // Only add client_id parameter if document_clients table exists
    if ($hasDocumentClientsTable) {
        $params['client_id'] = $user['id'];
    }
    
    if (!empty($filterStatus)) {
        $params['status'] = $filterStatus;
    }
    
    if (!empty($filterType)) {
        $params['type'] = $filterType;
    }
    
    $stmt->execute($params);
    $documents = $stmt->fetchAll();
    
    // Get document types for filter dropdown - with similar checks
    $sql = "SELECT DISTINCT type FROM documents WHERE ";
    
    $typeConditions = [];
    if ($hasSubmitterId) {
        $typeConditions[] = "submitter_id = :user_id";
    }
    
    if ($hasDocumentClientsTable) {
        $typeConditions[] = "id IN (SELECT document_id FROM document_clients WHERE client_id = :client_id)";
    }
    
    if (empty($typeConditions)) {
        $typeConditions[] = "uploaded_by = :user_id";
    }
    
    $sql .= "(" . implode(" OR ", $typeConditions) . ")";
    
    $stmt = db()->prepare($sql);
    $typeParams = ['user_id' => $user['id']];
    
    if ($hasDocumentClientsTable) {
        $typeParams['client_id'] = $user['id'];
    }
    
    $stmt->execute($typeParams);
    $documentTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    error_log("Error fetching client documents: " . $e->getMessage());
    $documents = [];
    $documentTypes = [];
}

// Set page title
$page_title = "My Documents";

// Include header
include_once '../includes/header.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
        <div class="flex items-center justify-between flex-wrap">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">My Documents</h1>
                <p class="mt-1 text-sm text-gray-600">View and track all your documents</p>
            </div>
            <div>
                <a href="client_dashboard.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <div class="max-w-7xl mx-auto mt-6 px-4 sm:px-6 md:px-8">
        <!-- Filter and Sort Options -->
        <div class="bg-white shadow-md rounded-lg p-4 mb-6">
            <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Status Filter -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                    <select id="status" name="status" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md">
                        <option value="">All Statuses</option>
                        <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="in_movement" <?= $filterStatus === 'in_movement' ? 'selected' : '' ?>>In Movement</option>
                        <option value="done" <?= $filterStatus === 'done' ? 'selected' : '' ?>>Complete</option>
                    </select>
                </div>
                
                <!-- Document Type Filter -->
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700">Document Type</label>
                    <select id="type" name="type" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md">
                        <option value="">All Types</option>
                        <?php foreach ($documentTypes as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>" <?= $filterType === $type ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Sort By -->
                <div>
                    <label for="sort" class="block text-sm font-medium text-gray-700">Sort By</label>
                    <select id="sort" name="sort" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md">
                        <option value="created_at" <?= $sortBy === 'created_at' ? 'selected' : '' ?>>Date</option>
                        <option value="title" <?= $sortBy === 'title' ? 'selected' : '' ?>>Title</option>
                        <option value="type" <?= $sortBy === 'type' ? 'selected' : '' ?>>Type</option>
                        <option value="department" <?= $sortBy === 'department' ? 'selected' : '' ?>>Department</option>
                        <option value="status" <?= $sortBy === 'status' ? 'selected' : '' ?>>Status</option>
                    </select>
                </div>
                
                <!-- Sort Order -->
                <div>
                    <label for="order" class="block text-sm font-medium text-gray-700">Order</label>
                    <select id="order" name="order" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md">
                        <option value="DESC" <?= $sortOrder === 'DESC' ? 'selected' : '' ?>>Newest First</option>
                        <option value="ASC" <?= $sortOrder === 'ASC' ? 'selected' : '' ?>>Oldest First</option>
                    </select>
                </div>
                
                <!-- Apply Button -->
                <div class="md:col-span-4 flex justify-end">
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Documents List -->
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    All Documents (<?= count($documents) ?>)
                </h3>
            </div>
            
            <?php if (empty($documents)): ?>
                <div class="text-center py-10">
                    <i class="fas fa-folder-open text-gray-400 text-4xl mb-3"></i>
                    <p class="text-gray-500 text-lg">No documents found</p>
                    <?php if (!empty($filterStatus) || !empty($filterType)): ?>
                        <p class="text-gray-400 text-sm mt-2">Try changing your filters</p>
                    <?php else: ?>
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
                            <?php foreach ($documents as $document): ?>
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
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?> 
<?php
// Define a log function outside of try-catch 
function debug_log($message) {
    $error_log_path = __DIR__ . '/../logs/dashboard_debug.log';
    if (!is_dir(__DIR__ . '/../logs')) {
        mkdir(__DIR__ . '/../logs', 0777, true);
    }
    file_put_contents($error_log_path, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
}

try {
    session_start();
    /**
     * Dashboard Page
     * 
     * This page displays statistics and document listings for the logged-in user
     */
    
    // Enable error display for debugging
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    
    // Debug log path
    $error_log_path = __DIR__ . '/../logs/dashboard_debug.log';
    
    // Create log directory if it doesn't exist
    if (!is_dir(__DIR__ . '/../logs')) {
        mkdir(__DIR__ . '/../logs', 0777, true);
    }
    
    // Check if log file is writable
    debug_log("Dashboard page loaded. Log file is writable.");
    debug_log("PHP version: " . phpversion());
    
    // Include header file
    require_once '../includes/auth.php';
    
    // Redirect if not logged in
    requireLogin();
    
    // Get user data
    $user = getCurrentUser();
    debug_log("User loaded: " . $user['role'] . " - " . $user['name']);
    
    // Redirect clients and contractors to client dashboard
    if ($user['role'] === 'client' || $user['role'] === 'contractor') {
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
    
    // Variables to store query results
    $pendingApprovals = [];
    $recentDocuments = [];
    $myUploads = [];
    $recentMovements = [];
    
    // Define which users have full access to all documents
    $hasFullAccess = hasRole(['admin', 'supervisor', 'manager']);
    debug_log("User has full access: " . ($hasFullAccess ? 'Yes' : 'No'));
    
    // Fetch data based on user role
    try {
        debug_log("Starting database queries");
        
        // First, check if the documents table exists
        $checkTable = "SHOW TABLES LIKE 'documents'";
        debug_log("Running query: " . $checkTable);
        $tableCheck = db()->query($checkTable);
        $documentsTableExists = $tableCheck->rowCount() > 0;
        debug_log("Documents table exists: " . ($documentsTableExists ? 'Yes' : 'No'));
        
        if (!$documentsTableExists) {
            throw new PDOException("Documents table does not exist");
        }
        
        // Common SQL parts to prevent duplication
        $statsSqlSelect = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                        SUM(CASE WHEN status = 'in_movement' THEN 1 ELSE 0 END) as in_movement,
                        SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done
                    FROM documents";

        $recentDocSqlSelect = "SELECT 
                        d.id, d.doc_unique_id, d.title, d.type, d.department, d.status, 
                        d.created_at, IFNULL(u.name, 'Unknown User') as uploader_name
                    FROM documents d
                    LEFT JOIN users u ON d.uploaded_by = u.id";

        // Check if document_movements table exists and has required columns
        $hasMovementsTable = false;
        $checkMovementsTable = "SHOW TABLES LIKE 'document_movements'";
        debug_log("Checking movements table: " . $checkMovementsTable);
        $tableCheck = db()->query($checkMovementsTable);
        
        if ($tableCheck->rowCount() > 0) {
            // Table exists, now check for required columns
            $checkFromDeptColumn = "SHOW COLUMNS FROM document_movements LIKE 'from_department'";
            $checkToDeptColumn = "SHOW COLUMNS FROM document_movements LIKE 'to_department'";
            $checkMovedByColumn = "SHOW COLUMNS FROM document_movements LIKE 'moved_by'";
            $checkMovedAtColumn = "SHOW COLUMNS FROM document_movements LIKE 'moved_at'";
            
            $fromDeptExists = db()->query($checkFromDeptColumn)->rowCount() > 0;
            $toDeptExists = db()->query($checkToDeptColumn)->rowCount() > 0;
            $movedByExists = db()->query($checkMovedByColumn)->rowCount() > 0;
            $movedAtExists = db()->query($checkMovedAtColumn)->rowCount() > 0;
            
            debug_log("Movements table columns: from_department=" . ($fromDeptExists ? 'Yes' : 'No') . 
                     ", to_department=" . ($toDeptExists ? 'Yes' : 'No') . 
                     ", moved_by=" . ($movedByExists ? 'Yes' : 'No') . 
                     ", moved_at=" . ($movedAtExists ? 'Yes' : 'No'));
            
            if ($fromDeptExists && $toDeptExists && $movedByExists && $movedAtExists) {
                $hasMovementsTable = true;
                
                $recentMovementSqlSelect = "SELECT 
                            m.id, d.doc_unique_id, d.title, m.from_department, m.to_department, 
                            IFNULL(u.name, 'Unknown User') as moved_by_name, m.moved_at
                        FROM document_movements m
                        LEFT JOIN documents d ON m.document_id = d.id
                        LEFT JOIN users u ON m.moved_by = u.id";
            } else {
                debug_log("Movements table exists but is missing required columns, skipping movement queries");
            }
        } else {
            debug_log("Movements table does not exist, skipping movement queries");
        }

        // Get document counts for statistics
        if ($hasFullAccess) {
            // Admin/Supervisor/Manager: Get all document counts
            $sql = $statsSqlSelect;
            debug_log("Admin stats query: " . $sql);
            $stmt = db()->prepare($sql);
            $stmt->execute();
        } else {
            // Clerk/Viewer/Managers: Get department's document counts
            $sql = $statsSqlSelect . " WHERE department = :department";
            debug_log("Department stats query: " . $sql . " with department=" . $user['department']);
            $stmt = db()->prepare($sql);
            $stmt->execute(['department' => $user['department']]);
        }
        
        // Get result
        $result = $stmt->fetch();
        debug_log("Stats result: " . ($result ? "Data found" : "No data found"));
        
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
            debug_log("Stats updated: total=" . $stats['total']);
        }

        // Check if documents table has expected columns
        $checkDocIdColumn = "SHOW COLUMNS FROM documents LIKE 'id'";
        debug_log("Running query: " . $checkDocIdColumn);
        $docIdCheck = db()->query($checkDocIdColumn);
        $hasExpectedColumns = $docIdCheck->rowCount() > 0;
        debug_log("Documents table has expected columns: " . ($hasExpectedColumns ? 'Yes' : 'No'));
        
        // Only proceed with document queries if the table has expected structure
        if ($hasExpectedColumns) {
            debug_log("Processing document queries with expected structure");
            // Get pending approval documents for managers
            if (hasRole(['manager', 'assistant_manager', 'senior_manager'])) {
                debug_log("User is a manager type role, fetching pending approvals");
                
                if ($user['role'] === 'senior_manager') {
                    // Check if needs_senior_approval column exists
                    $checkSeniorApproval = "SHOW COLUMNS FROM documents LIKE 'needs_senior_approval'";
                    debug_log("Checking for senior approval column: " . $checkSeniorApproval);
                    $seniorApprovalCheck = db()->query($checkSeniorApproval);
                    $hasNeedsSeniorApproval = $seniorApprovalCheck->rowCount() > 0;
                    debug_log("Senior approval column exists: " . ($hasNeedsSeniorApproval ? 'Yes' : 'No'));
                    
                    $sql = "SELECT d.id, d.doc_unique_id, d.title, d.type, d.status, d.created_at,
                                IFNULL(u.name, 'Unknown User') as uploader_name
                            FROM documents d
                            LEFT JOIN users u ON d.uploaded_by = u.id
                            WHERE d.department = :department";
                    
                    // Add the needs_senior_approval check only if column exists
                    if ($hasNeedsSeniorApproval) {
                        $sql .= " AND d.needs_senior_approval = 1";
                    }
                    
                    $sql .= " ORDER BY d.created_at DESC LIMIT 5";
                    debug_log("Senior manager query: " . $sql);
                } else {
                    // Check if 'pending_approval' status exists in database
                    $checkPendingApprovalStatus = "SHOW COLUMNS FROM documents LIKE 'status'";
                    debug_log("Checking status column: " . $checkPendingApprovalStatus);
                    $statusCheck = db()->query($checkPendingApprovalStatus);
                    $statusInfo = $statusCheck->fetch(PDO::FETCH_ASSOC);
                    debug_log("Status column info: " . json_encode($statusInfo));
                    
                    // Default status to check
                    $statusToCheck = ($user['role'] === 'assistant_manager') ? 'pending' : 'pending_approval';
                    debug_log("Initial status to check: " . $statusToCheck);
                    
                    // If status column is enum type and pending_approval not in the enum values, fallback to pending
                    if ($statusInfo && strpos($statusInfo['Type'], 'enum') !== false && 
                        strpos($statusInfo['Type'], 'pending_approval') === false) {
                        $statusToCheck = 'pending';
                        debug_log("Status changed to 'pending' as 'pending_approval' not found in enum");
                    }
                    
                    $sql = "SELECT d.id, d.doc_unique_id, d.title, d.type, d.status, d.created_at, 
                                IFNULL(u.name, 'Unknown User') as uploader_name
                            FROM documents d
                            LEFT JOIN users u ON d.uploaded_by = u.id
                            WHERE d.department = :department AND d.status = :status
                            ORDER BY d.created_at DESC
                            LIMIT 5";
                    debug_log("Manager/Assistant manager query: " . $sql . " with status=" . $statusToCheck);
                }
                
                $stmt = db()->prepare($sql);
                $params = ['department' => $user['department']];
                
                if ($user['role'] !== 'senior_manager') {
                    $params['status'] = $statusToCheck;
                }
                
                debug_log("Executing manager query with params: " . json_encode($params));
                $stmt->execute($params);
                $pendingApprovals = $stmt->fetchAll();
                debug_log("Pending approvals found: " . count($pendingApprovals));
            }
            
            // Get recent documents
            debug_log("Getting recent documents");
            if ($hasFullAccess) {
                // Admin/Supervisor/Manager: Get all recent documents
                $sql = $recentDocSqlSelect . " ORDER BY d.created_at DESC LIMIT 5";
                debug_log("Full access recent documents query: " . $sql);
                $stmt = db()->prepare($sql);
                $stmt->execute();
            } else {
                // Clerk/Viewer/Managers: Get department's recent documents
                $sql = $recentDocSqlSelect . " WHERE d.department = :department ORDER BY d.created_at DESC LIMIT 5";
                debug_log("Department recent documents query: " . $sql . " with department=" . $user['department']);
                $stmt = db()->prepare($sql);
                $stmt->execute(['department' => $user['department']]);
            }
            
            // Get recent documents
            $recentDocuments = $stmt->fetchAll();
            debug_log("Recent documents found: " . count($recentDocuments));
            
            // Get user's uploaded documents
            $sql = "SELECT 
                        id, doc_unique_id, title, type, department, status, created_at
                    FROM documents
                    WHERE uploaded_by = :user_id
                    ORDER BY created_at DESC
                    LIMIT 5";
            debug_log("My uploads query: " . $sql . " with user_id=" . $user['id']);
            $stmt = db()->prepare($sql);
            $stmt->execute(['user_id' => $user['id']]);
            $myUploads = $stmt->fetchAll();
            debug_log("My uploads found: " . count($myUploads));
            
            // Get recent document movements if table exists
            if ($hasMovementsTable) {
                debug_log("Processing document movements");
                if ($hasFullAccess) {
                    // Admin/Supervisor/Manager: Get all recent movements
                    $sql = $recentMovementSqlSelect . " ORDER BY m.moved_at DESC LIMIT 5";
                    debug_log("Full access movements query: " . $sql);
                    $stmt = db()->prepare($sql);
                    $stmt->execute();
                } else {
                    // Clerk/Viewer/Managers: Get movements relevant to their department
                    $sql = $recentMovementSqlSelect . " WHERE m.from_department = :department OR m.to_department = :department 
                            ORDER BY m.moved_at DESC LIMIT 5";
                    debug_log("Department movements query: " . $sql . " with department=" . $user['department']);
                    $stmt = db()->prepare($sql);
                    $stmt->execute(['department' => $user['department']]);
                }
                
                // Get recent movements
                $recentMovements = $stmt->fetchAll();
                debug_log("Recent movements found: " . count($recentMovements));
            }
        } else {
            debug_log("WARNING: Documents table doesn't have expected columns - skipping document queries");
        }
    } catch (PDOException $e) {
        debug_log("PDO ERROR: " . $e->getMessage() . " in line " . $e->getLine() . " of " . $e->getFile());
        debug_log("Error code: " . $e->getCode());
        if (isset($e->errorInfo)) {
            debug_log("SQL Error Info: " . json_encode($e->errorInfo));
        }
        if (isset($sql)) {
            debug_log("Last SQL Query: " . $sql);
        }
        error_log("Dashboard error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while fetching dashboard data.";
    }

    debug_log("Preparing to render dashboard with data");
    // Status classes for badges
    $statusClasses = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'approved' => 'bg-green-100 text-green-800',
        'rejected' => 'bg-red-100 text-red-800',
        'in_movement' => 'bg-blue-100 text-blue-800',
        'done' => 'bg-indigo-100 text-indigo-800'
    ];

    // Set page title
    $page_title = "Dashboard";

    // Include header
    include_once '../includes/header.php';
} catch (Exception $e) {
    // Log critical errors
    debug_log("CRITICAL ERROR: " . $e->getMessage());
    debug_log("Error in line " . $e->getLine() . " of " . $e->getFile());
    debug_log("Stack trace: " . $e->getTraceAsString());
    
    // Set error message
    $_SESSION['error'] = "An error occurred while loading the dashboard. Please try again later.";
    
    // Include header if not already included
    if (!function_exists('hasRole')) {
        include_once '../includes/header.php';
    }
}
?>

<!-- Dashboard Content -->
<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Welcome Header with Quick Stats -->
        <div class="bg-white shadow-sm rounded-lg p-6 mb-8 flex flex-col md:flex-row justify-between items-center">
            <div class="mb-4 md:mb-0">
                <h1 class="text-2xl font-semibold text-gray-900">Welcome, <?= htmlspecialchars($user['name']); ?></h1>
                <p class="text-gray-600">
                    <span class="font-medium uppercase"><?= htmlspecialchars($user['role']); ?></span> 
                    in the <span class="font-medium"><?= htmlspecialchars($user['department']); ?></span> department
                </p>
            </div>
            <div class="bg-primary-50 rounded-lg px-6 py-3 flex items-center border border-primary-100">
                <i class="fas fa-chart-line text-primary-600 text-xl mr-3"></i>
                <div>
                    <p class="text-sm text-gray-600">Total Documents</p>
                    <p class="text-2xl font-bold text-primary-700"><?= $stats['total']; ?></p>
                </div>
            </div>
        </div>
        
        <!-- Document Statistics Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
            <div class="bg-white shadow-sm rounded-lg p-4 flex items-center border-l-4 border-yellow-400">
                <div class="p-3 rounded-full bg-yellow-100 mr-4">
                    <i class="fas fa-hourglass-half text-yellow-500 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Pending</p>
                    <p class="text-2xl font-semibold"><?= $stats['pending']; ?></p>
                </div>
            </div>
            
            <div class="bg-white shadow-sm rounded-lg p-4 flex items-center border-l-4 border-green-400">
                <div class="p-3 rounded-full bg-green-100 mr-4">
                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Approved</p>
                    <p class="text-2xl font-semibold"><?= $stats['approved']; ?></p>
                </div>
            </div>
            
            <div class="bg-white shadow-sm rounded-lg p-4 flex items-center border-l-4 border-red-400">
                <div class="p-3 rounded-full bg-red-100 mr-4">
                    <i class="fas fa-times-circle text-red-500 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Rejected</p>
                    <p class="text-2xl font-semibold"><?= $stats['rejected']; ?></p>
                </div>
            </div>
            
            <div class="bg-white shadow-sm rounded-lg p-4 flex items-center border-l-4 border-blue-400">
                <div class="p-3 rounded-full bg-blue-100 mr-4">
                    <i class="fas fa-exchange-alt text-blue-500 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">In Movement</p>
                    <p class="text-2xl font-semibold"><?= $stats['in_movement']; ?></p>
                </div>
            </div>
            
            <div class="bg-white shadow-sm rounded-lg p-4 flex items-center border-l-4 border-indigo-400">
                <div class="p-3 rounded-full bg-indigo-100 mr-4">
                    <i class="fas fa-check-double text-indigo-500 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Complete</p>
                    <p class="text-2xl font-semibold"><?= $stats['done'] ?? 0; ?></p>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white shadow-sm rounded-lg mb-8 overflow-hidden">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="text-lg font-medium text-gray-900">Quick Actions</h2>
            </div>
            <div class="p-6 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php if ($user['role'] !== 'manager' && $user['role'] !== 'assistant_manager'): ?>
                <a href="upload.php" class="bg-white border border-gray-200 hover:bg-gray-50 rounded-lg p-4 flex flex-col items-center text-center transition">
                    <div class="p-3 rounded-full bg-primary-100 mb-3">
                        <i class="fas fa-upload text-primary-600 text-xl"></i>
                    </div>
                    <span class="text-gray-900 font-medium">Upload Document</span>
                </a>
                <?php endif; ?>
                
                <a href="track.php" class="bg-white border border-gray-200 hover:bg-gray-50 rounded-lg p-4 flex flex-col items-center text-center transition">
                    <div class="p-3 rounded-full bg-blue-100 mb-3">
                        <i class="fas fa-search text-blue-600 text-xl"></i>
                    </div>
                    <span class="text-gray-900 font-medium">Search Documents</span>
                </a>
                
                <?php if (hasRole(['admin', 'supervisor', 'manager', 'assistant_manager'])): ?>
                <a href="approve.php" class="bg-white border border-gray-200 hover:bg-gray-50 rounded-lg p-4 flex flex-col items-center text-center transition">
                    <div class="p-3 rounded-full bg-green-100 mb-3">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <span class="text-gray-900 font-medium">Approve Documents</span>
                </a>
                <?php endif; ?>
                
                <a href="move.php" class="bg-white border border-gray-200 hover:bg-gray-50 rounded-lg p-4 flex flex-col items-center text-center transition">
                    <div class="p-3 rounded-full bg-purple-100 mb-3">
                        <i class="fas fa-exchange-alt text-purple-600 text-xl"></i>
                    </div>
                    <span class="text-gray-900 font-medium">Move Documents</span>
                </a>
                
                <?php if (hasRole(['admin'])): ?>
                <a href="register.php?role=manager" class="bg-white border border-gray-200 hover:bg-gray-50 rounded-lg p-4 flex flex-col items-center text-center transition">
                    <div class="p-3 rounded-full bg-orange-100 mb-3">
                        <i class="fas fa-user-tie text-orange-600 text-xl"></i>
                    </div>
                    <span class="text-gray-900 font-medium">Register Manager</span>
                </a>
                
                <a href="users.php" class="bg-white border border-gray-200 hover:bg-gray-50 rounded-lg p-4 flex flex-col items-center text-center transition">
                    <div class="p-3 rounded-full bg-cyan-100 mb-3">
                        <i class="fas fa-users-cog text-cyan-600 text-xl"></i>
                    </div>
                    <span class="text-gray-900 font-medium">Manage Users</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Manager-Specific: Pending Approvals -->
        <?php if (hasRole(['manager', 'assistant_manager', 'senior_manager']) && !empty($pendingApprovals)): ?>
        <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-8">
            <div class="bg-yellow-50 px-6 py-4 border-b border-yellow-100 flex items-center">
                <div class="p-2 rounded-lg bg-yellow-100 mr-3">
                    <i class="fas fa-bell text-yellow-600"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">
                        <?= $user['role'] === 'assistant_manager' ? 'Documents Awaiting Your Approval' : 'Pending Approvals' ?>
                    </h3>
                    <p class="text-sm text-gray-600">These documents need your attention</p>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Document ID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($pendingApprovals as $doc): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($doc['doc_unique_id']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($doc['title']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($doc['type']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('M d, Y', strtotime($doc['created_at'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="view_document.php?id=<?= $doc['id']; ?>" class="text-primary-600 hover:text-primary-900 mr-3">View</a>
                                <?php if ($user['role'] === 'assistant_manager'): ?>
                                <a href="move.php?id=<?= urlencode($doc['id']); ?>" class="text-green-600 hover:text-green-900">Approve</a>
                                <?php elseif ($user['role'] === 'senior_manager'): ?>
                                <a href="manager_actions.php?id=<?= $doc['id']; ?>" class="text-green-600 hover:text-green-900">Process</a>
                                <?php else: ?>
                                <a href="approve.php?doc_id=<?= urlencode($doc['id']); ?>" class="text-green-600 hover:text-green-900">Review</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Main Content Area: Recent Documents -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">Recent Documents</h3>
                <a href="track.php" class="text-sm font-medium text-primary-600 hover:text-primary-700">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
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
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recentDocuments as $doc): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($doc['doc_unique_id']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($doc['title']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($doc['type']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($doc['department']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                    $statusClass = $statusClasses[$doc['status']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass; ?>">
                                    <?= ucfirst(str_replace('_', ' ', $doc['status'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('M d, Y', strtotime($doc['created_at'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="view_document.php?id=<?= $doc['id']; ?>" class="text-primary-600 hover:text-primary-900 mr-3">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="move.php?id=<?= $doc['id']; ?>" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-exchange-alt"></i> Move
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Two Column Layout for My Uploads and Recent Movements -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <?php if ($user['role'] !== 'manager' && $user['role'] !== 'assistant_manager'): ?>
            <!-- My Uploads -->
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">My Uploads</h3>
                    <a href="my_uploads.php" class="text-sm font-medium text-primary-600 hover:text-primary-700">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
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
                                    <span class="px-2 py-0.5 rounded bg-gray-100"><?= htmlspecialchars($doc['type']); ?></span>
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
                                <i class="fas fa-eye mr-1"></i> View
                            </a>
                            <a href="track_history.php?id=<?= $doc['id']; ?>" class="text-blue-600 hover:text-blue-900 text-xs font-medium">
                                <i class="fas fa-history mr-1"></i> Track History
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Recent Movements -->
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">Recent Movements</h3>
                    <a href="all_movements.php" class="text-sm font-medium text-primary-600 hover:text-primary-700">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
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
                                <span class="text-gray-500 text-xs">(<?= htmlspecialchars($movement['doc_unique_id']); ?>)</span>
                            </h4>
                            <div class="flex items-center mt-2 text-sm">
                                <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700"><?= htmlspecialchars($movement['from_department']); ?></span>
                                <i class="fas fa-arrow-right text-xs text-gray-500 mx-2"></i>
                                <span class="px-2 py-0.5 rounded bg-primary-50 text-primary-700"><?= htmlspecialchars($movement['to_department']); ?></span>
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
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?> 
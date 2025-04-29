<?php
/**
 * Track History Page
 * 
 * This page displays the complete history of a document including
 * all movements and approvals
 */

// Include header file
require_once '../includes/auth.php';

// Redirect if not logged in
requireLogin();

// Get user data
$user = getCurrentUser();

// Include database connection
require_once '../config/db.php';

// Check if document ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "No document ID provided.";
    header('Location: dashboard.php');
    exit;
}

$document_id = intval($_GET['id']);

try {
    // Get document details
    $sql = "SELECT 
                d.id, d.doc_unique_id, d.title, d.type, d.department, d.status, d.created_at,
                u.name as uploader_name
            FROM documents d
            JOIN users u ON d.uploaded_by = u.id
            WHERE d.id = :id";
    $stmt = db()->prepare($sql);
    $stmt->execute(['id' => $document_id]);
    $document = $stmt->fetch();
    
    if (!$document) {
        $_SESSION['error'] = "Document not found.";
        header('Location: dashboard.php');
        exit;
    }
    
    // Get document movements
    $sql = "SELECT 
                m.id, m.from_department, m.to_department, m.note,
                u.name as moved_by_name, m.moved_at
            FROM document_movements m
            JOIN users u ON m.moved_by = u.id
            WHERE m.document_id = :document_id
            ORDER BY m.moved_at ASC";
    $stmt = db()->prepare($sql);
    $stmt->execute(['document_id' => $document_id]);
    $movements = $stmt->fetchAll();
    
    // Check if document_approvals table exists and get approvals if it does
    $approvals = [];
    
    // First check if the document_approvals table exists
    try {
        $dbConnection = db();
        $tableCheckSql = "SHOW TABLES LIKE 'document_approvals'";
        $tableCheckStmt = $dbConnection->prepare($tableCheckSql);
        $tableCheckStmt->execute();
        $tableExists = $tableCheckStmt->rowCount() > 0;
        
        if (!$tableExists) {
            // Table doesn't exist, create it
            $createTableSql = "CREATE TABLE IF NOT EXISTS `document_approvals` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `document_id` int(11) NOT NULL,
                `approved_by` int(11) NOT NULL,
                `status` enum('approved','rejected') NOT NULL,
                `comment` text,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `document_id` (`document_id`),
                KEY `approved_by` (`approved_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $dbConnection->exec($createTableSql);
            error_log("Created document_approvals table");
            // Table was just created, so it will be empty
            $approvals = [];
        } else {
            // Table exists, proceed with query
            $sql = "SELECT 
                        a.id, a.status as approval_status, a.comment,
                        u.name as approved_by_name, a.created_at as approved_at
                    FROM document_approvals a
                    JOIN users u ON a.approved_by = u.id
                    WHERE a.document_id = :document_id
                    ORDER BY a.created_at ASC";
            $stmt = $dbConnection->prepare($sql);
            $stmt->execute(['document_id' => $document_id]);
            $approvals = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        // Just log the error but continue - don't stop the page from loading
        error_log("Error checking for document_approvals table: " . $e->getMessage());
        // Continue with empty approvals
        $approvals = [];
    }
    
} catch (PDOException $e) {
    error_log("Track history error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while retrieving document history.";
    header('Location: dashboard.php');
    exit;
}

// Set page title
$page_title = "Document History";

// Include header
include_once '../includes/header.php';
?>

<!-- Track History Content -->
<div class="py-6">
    <div class="mx-auto px-4 sm:px-6 md:px-8">
        <h1 class="text-2xl font-semibold text-gray-900">Document History</h1>
    </div>
    
    <div class="mx-auto px-4 sm:px-6 md:px-8 mt-6">
        <!-- Document Details Card -->
        <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">Document Details</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Document ID</p>
                        <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['doc_unique_id']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Title</p>
                        <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['title']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Type</p>
                        <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['type']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Current Department</p>
                        <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['department']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Status</p>
                        <p class="mt-1">
                            <?php
                                $statusClasses = [
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'approved' => 'bg-green-100 text-green-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                    'in_movement' => 'bg-blue-100 text-blue-800',
                                    'done' => 'bg-indigo-100 text-indigo-800'
                                ];
                                $statusClass = $statusClasses[$document['status']] ?? 'bg-gray-100 text-gray-800';
                            ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass; ?>">
                                <?= ucfirst(str_replace('_', ' ', $document['status'])); ?>
                            </span>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Uploaded By</p>
                        <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['uploader_name']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Upload Date</p>
                        <p class="mt-1 text-sm text-gray-900"><?= date('M d, Y - h:i A', strtotime($document['created_at'])); ?></p>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-between">
                    <a href="view_document.php?id=<?= $document['id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700">
                        <i class="fas fa-file-alt mr-2"></i> View Document
                    </a>
                    <a href="javascript:history.back()" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Timeline -->
        <div class="bg-white shadow rounded-lg overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">Movement & Approval History</h3>
            </div>
            
            <div class="p-6">
                <div class="flow-root">
                    <ul class="-mb-8">
                        <!-- Document Created -->
                        <li>
                            <div class="relative pb-8">
                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                <div class="relative flex space-x-3">
                                    <div>
                                        <span class="h-8 w-8 rounded-full bg-purple-500 flex items-center justify-center ring-8 ring-white">
                                            <i class="fas fa-file-alt text-white"></i>
                                        </span>
                                    </div>
                                    <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                        <div>
                                            <p class="text-sm text-gray-500">Document uploaded by <span class="font-medium text-gray-900"><?= htmlspecialchars($document['uploader_name']); ?></span></p>
                                        </div>
                                        <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                            <time datetime="<?= $document['created_at']; ?>"><?= date('M d, Y - h:i A', strtotime($document['created_at'])); ?></time>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                        
                        <?php
                        // Combine movements and approvals into a single timeline
                        $timeline = [];
                        
                        foreach ($movements as $movement) {
                            $timeline[] = [
                                'type' => 'movement',
                                'data' => $movement,
                                'date' => $movement['moved_at']
                            ];
                        }
                        
                        foreach ($approvals as $approval) {
                            $timeline[] = [
                                'type' => 'approval',
                                'data' => $approval,
                                'date' => $approval['approved_at']
                            ];
                        }
                        
                        // Sort timeline by date (ascending)
                        usort($timeline, function($a, $b) {
                            return strtotime($a['date']) - strtotime($b['date']);
                        });
                        
                        // Display timeline
                        foreach ($timeline as $index => $item):
                            $isLast = $index === count($timeline) - 1;
                        ?>
                        <li>
                            <div class="relative pb-8">
                                <?php if (!$isLast): ?>
                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                <?php endif; ?>
                                <div class="relative flex space-x-3">
                                    <?php if ($item['type'] === 'movement'): ?>
                                    <!-- Movement Item -->
                                    <div>
                                        <span class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center ring-8 ring-white">
                                            <i class="fas fa-exchange-alt text-white"></i>
                                        </span>
                                    </div>
                                    <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                        <div>
                                            <p class="text-sm text-gray-500">
                                                Moved from <span class="font-medium text-gray-900"><?= htmlspecialchars($item['data']['from_department']); ?></span> 
                                                to <span class="font-medium text-gray-900"><?= htmlspecialchars($item['data']['to_department']); ?></span> 
                                                by <span class="font-medium text-gray-900"><?= htmlspecialchars($item['data']['moved_by_name']); ?></span>
                                            </p>
                                            <?php if (!empty($item['data']['note'])): ?>
                                            <p class="mt-1 text-sm text-gray-500 italic">"<?= htmlspecialchars($item['data']['note']); ?>"</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                            <time datetime="<?= $item['date']; ?>"><?= date('M d, Y - h:i A', strtotime($item['date'])); ?></time>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <!-- Approval Item -->
                                    <div>
                                        <?php if ($item['data']['approval_status'] === 'approved'): ?>
                                        <span class="h-8 w-8 rounded-full bg-green-500 flex items-center justify-center ring-8 ring-white">
                                            <i class="fas fa-check text-white"></i>
                                        </span>
                                        <?php else: ?>
                                        <span class="h-8 w-8 rounded-full bg-red-500 flex items-center justify-center ring-8 ring-white">
                                            <i class="fas fa-times text-white"></i>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                        <div>
                                            <p class="text-sm text-gray-500">
                                                Document <?= $item['data']['approval_status'] === 'approved' ? 'approved' : 'rejected' ?> 
                                                by <span class="font-medium text-gray-900"><?= htmlspecialchars($item['data']['approved_by_name']); ?></span>
                                            </p>
                                            <?php if (!empty($item['data']['comment'])): ?>
                                            <p class="mt-1 text-sm text-gray-500 italic">"<?= htmlspecialchars($item['data']['comment']); ?>"</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                            <time datetime="<?= $item['date']; ?>"><?= date('M d, Y - h:i A', strtotime($item['date'])); ?></time>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                        
                        <?php if (empty($timeline)): ?>
                        <!-- No History -->
                        <li>
                            <div class="relative pb-8">
                                <div class="relative flex space-x-3">
                                    <div>
                                        <span class="h-8 w-8 rounded-full bg-gray-400 flex items-center justify-center ring-8 ring-white">
                                            <i class="fas fa-info text-white"></i>
                                        </span>
                                    </div>
                                    <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                        <div>
                                            <p class="text-sm text-gray-500">No movement or approval history yet</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <?php endif; ?>
                        
                        <!-- Current Status -->
                        <li>
                            <div class="relative pb-4">
                                <div class="relative flex space-x-3">
                                    <div>
                                        <?php
                                        $statusIcons = [
                                            'pending' => '<i class="fas fa-hourglass-half text-white"></i>',
                                            'approved' => '<i class="fas fa-check-circle text-white"></i>',
                                            'rejected' => '<i class="fas fa-times-circle text-white"></i>',
                                            'in_movement' => '<i class="fas fa-exchange-alt text-white"></i>',
                                            'done' => '<i class="fas fa-check-double text-white"></i>'
                                        ];
                                        $statusBgColors = [
                                            'pending' => 'bg-yellow-500',
                                            'approved' => 'bg-green-500',
                                            'rejected' => 'bg-red-500',
                                            'in_movement' => 'bg-blue-500',
                                            'done' => 'bg-indigo-500'
                                        ];
                                        $icon = $statusIcons[$document['status']] ?? '<i class="fas fa-question text-white"></i>';
                                        $bgColor = $statusBgColors[$document['status']] ?? 'bg-gray-500';
                                        ?>
                                        <span class="h-8 w-8 rounded-full <?= $bgColor; ?> flex items-center justify-center ring-8 ring-white">
                                            <?= $icon; ?>
                                        </span>
                                    </div>
                                    <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                        <div>
                                            <p class="text-sm text-gray-500">
                                                Current status: <span class="font-medium text-gray-900"><?= ucfirst(str_replace('_', ' ', $document['status'])); ?></span>
                                            </p>
                                        </div>
                                        <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                            <time datetime="<?= date('c'); ?>"><?= date('M d, Y - h:i A'); ?></time>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Ensure timeline visibility */
.flow-root {
    overflow: visible;
    margin-bottom: 2rem;
}

.flow-root ul {
    padding-bottom: 3rem;
}

/* Ensure timeline container has sufficient space */
.bg-white.shadow.rounded-lg.overflow-hidden.mb-8 {
    overflow: visible !important;
    margin-bottom: 4rem !important;
}
</style>

<?php include_once '../includes/footer.php'; ?> 
<?php
/**
 * Document Movement Page
 * 
 * This page allows users to move documents between departments
 */

// Include authentication utilities
require_once '../includes/auth.php';

// Require login to access this page
requireLogin();

// Get current user data
$user = getCurrentUser();

// Include database connection
require_once '../config/db.php';

// Handle single document view if ID is provided
$singleDocument = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        $sql = "SELECT d.id, d.doc_unique_id, d.title, d.file_path, d.type, d.department, 
                       d.status, d.created_at, u.name as uploader_name, d.final_destination 
                FROM documents d
                JOIN users u ON d.uploaded_by = u.id
                WHERE d.id = :id";
        $stmt = db()->prepare($sql);
        $stmt->execute(['id' => $_GET['id']]);
        
        if ($stmt->rowCount() > 0) {
            $singleDocument = $stmt->fetch();
            
            // Also get movement history
            $moveSql = "SELECT m.from_department, m.to_department, m.note, m.moved_at, 
                             u.name as moved_by_name
                        FROM document_movements m
                        JOIN users u ON m.moved_by = u.id
                        WHERE m.document_id = :document_id
                        ORDER BY m.moved_at DESC";
            $moveStmt = db()->prepare($moveSql);
            $moveStmt->execute(['document_id' => $_GET['id']]);
            $movementHistory = $moveStmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Error fetching document: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while retrieving the document.";
        header('Location: dashboard.php');
        exit;
    }
}

// Get all departments for dropdown
try {
    // Common standard departments
    $departments = [
        'IT', 
        'Finance', 
        'HR', 
        'Legal', 
        'Operations', 
        'Procurement', 
        'Administration'
    ];
    
    // Add any custom departments from the database
    $sql = "SELECT DISTINCT department FROM users WHERE department NOT IN ('IT', 'Finance', 'HR', 'Legal', 'Operations', 'Procurement', 'Administration') ORDER BY department";
    $stmt = db()->prepare($sql);
    $stmt->execute();
    $dbDepartments = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Merge departments
    $departments = array_merge($departments, $dbDepartments);
    sort($departments);
} catch (PDOException $e) {
    error_log("Error fetching departments: " . $e->getMessage());
    // Fallback to standard departments in case of error
}

// Process document movement form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['document_id'], $_POST['to_department'])) {
    // Validate the destination department
    if (empty($_POST['to_department'])) {
        $_SESSION['error'] = "Destination department is required.";
        header('Location: move.php' . (isset($_POST['document_id']) ? '?id=' . $_POST['document_id'] : ''));
        exit;
    }
    
    try {
        // Get current document info
        $sql = "SELECT id, department FROM documents WHERE id = :id";
        $stmt = db()->prepare($sql);
        $stmt->execute(['id' => $_POST['document_id']]);
        $document = $stmt->fetch();
        
        if (!$document) {
            $_SESSION['error'] = "Document not found.";
            header('Location: move.php');
            exit;
        }
        
        // Check if document is being moved to the same department
        if ($document['department'] === $_POST['to_department']) {
            $_SESSION['error'] = "Document is already in the selected department.";
            header('Location: move.php?id=' . $_POST['document_id']);
            exit;
        }
        
        // Begin transaction
        db()->beginTransaction();
        
        // Record the movement
        $sql = "INSERT INTO document_movements (document_id, from_department, to_department, moved_by, note, moved_at) 
                VALUES (:document_id, :from_department, :to_department, :moved_by, :note, NOW())";
        $stmt = db()->prepare($sql);
        $stmt->execute([
            'document_id' => $_POST['document_id'],
            'from_department' => $document['department'],
            'to_department' => $_POST['to_department'],
            'moved_by' => $user['id'],
            'note' => $_POST['note'] ?? null
        ]);
        
        // Update document's department and status
        $sql = "UPDATE documents SET 
                department = :department, 
                status = 'in_movement' 
                WHERE id = :id";
        $stmt = db()->prepare($sql);
        $stmt->execute([
            'department' => $_POST['to_department'],
            'id' => $_POST['document_id']
        ]);
        
        // If the document has reached its final destination, set status to 'pending_approval'
        $sql = "SELECT department, final_destination, status FROM documents WHERE id = :id";
        $stmt = db()->prepare($sql);
        $stmt->execute(['id' => $_POST['document_id']]);
        $doc = $stmt->fetch();
        if ($doc && $doc['department'] === $doc['final_destination'] && $doc['status'] === 'in_movement') {
            $sql = "UPDATE documents SET status = 'pending_approval' WHERE id = :id";
            $stmt = db()->prepare($sql);
            $stmt->execute(['id' => $_POST['document_id']]);
        }
        
        // Log the activity
        $details = "Document ID: " . $_POST['document_unique_id'] . " moved from " . 
                   $document['department'] . " to " . $_POST['to_department'];
        logActivity('document_moved', $details);
        
        // Commit transaction
        db()->commit();
        
        $_SESSION['success'] = "Document has been moved to " . $_POST['to_department'] . " department.";
        
        // Redirect to move documents list
        header('Location: move.php');
        exit;
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        try {
            // Only roll back if there's an active transaction
            if (db()->inTransaction()) {
                db()->rollBack();
            }
        } catch (Exception $ex) {
            // Log any issues with the rollback itself
            error_log("Error rolling back transaction: " . $ex->getMessage());
        }
        
        error_log("Error processing document movement: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while processing the document movement. Error: " . $e->getMessage();
        header('Location: move.php' . (isset($_POST['document_id']) ? '?id=' . $_POST['document_id'] : ''));
        exit;
    }
}

// Get movable documents (not rejected)
$movableDocuments = [];
try {
    $sql = "SELECT d.id, d.doc_unique_id, d.title, d.type, d.department, d.status, d.created_at, 
                   u.name as uploader_name 
            FROM documents d
            JOIN users u ON d.uploaded_by = u.id
            WHERE d.status != 'rejected'";
    
    // Non-admin users can only see documents from their department
    if (!hasRole('admin')) {
        $sql .= " AND d.department = :department";
        $params = ['department' => $user['department']];
    } else {
        $params = [];
    }
    
    $sql .= " ORDER BY d.created_at DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $movableDocuments = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching movable documents: " . $e->getMessage());
}

// Set page title
$page_title = "Move Documents";

// Include header
include_once '../includes/header.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Move Documents</h1>
                <p class="mt-1 text-sm text-gray-600">Transfer documents between departments</p>
            </div>
            <a href="dashboard.php" class="flex items-center text-primary-600 hover:text-primary-800">
                <i class="fas fa-arrow-left mr-2"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>
    </div>
    
    <div class="max-w-7xl mx-auto mt-6 px-4 sm:px-6 md:px-8">
        <?php if ($singleDocument): ?>
        <!-- Single Document Movement View -->
        <div class="bg-white shadow-lg rounded-lg overflow-hidden border border-gray-100 mb-6">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-file-alt text-primary-500 text-xl mr-3"></i>
                    <h3 class="text-lg font-semibold text-gray-800">Document Transfer</h3>
                </div>
                <span class="text-sm px-2 py-1 rounded-full <?php 
                    switch ($singleDocument['status']) {
                        case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                        case 'approved': echo 'bg-green-100 text-green-800'; break;
                        case 'rejected': echo 'bg-red-100 text-red-800'; break;
                        case 'in_movement': echo 'bg-blue-100 text-blue-800'; break;
                        default: echo 'bg-gray-100 text-gray-800';
                    }
                ?>">
                    <?= ucfirst($singleDocument['status']); ?>
                </span>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-2">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4"><?= htmlspecialchars($singleDocument['title']); ?></h2>
                        
                        <div class="mb-6 grid grid-cols-2 gap-4">
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Document ID</h4>
                                <p class="text-gray-800"><?= htmlspecialchars($singleDocument['doc_unique_id']); ?></p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Type</h4>
                                <p class="text-gray-800"><?= htmlspecialchars($singleDocument['type']); ?></p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Current Department</h4>
                                <p class="text-gray-800"><?= htmlspecialchars($singleDocument['department']); ?></p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Final Destination</h4>
                                <p class="text-gray-800">
                                    <?= htmlspecialchars($singleDocument['final_destination'] ?? 'Not specified'); ?>
                                    <?php if (isset($singleDocument['final_destination']) && $singleDocument['department'] == $singleDocument['final_destination']): ?>
                                    <span class="inline-flex items-center ml-2 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i> Final Destination Reached
                                    </span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Date Uploaded</h4>
                                <p class="text-gray-800"><?= date('M d, Y', strtotime($singleDocument['created_at'])); ?></p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Uploaded By</h4>
                                <p class="text-gray-800"><?= htmlspecialchars($singleDocument['uploader_name']); ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <a href="download.php?id=<?= $singleDocument['id']; ?>&view=1" target="_blank" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <i class="fas fa-eye mr-2"></i> View Document
                            </a>
                            <a href="download.php?id=<?= $singleDocument['id']; ?>" download class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 ml-2">
                                <i class="fas fa-download mr-2"></i> Download
                            </a>
                        </div>
                        
                        <?php if (!empty($movementHistory)): ?>
                        <div class="mt-6">
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Movement History</h4>
                            <div class="bg-gray-50 rounded-md p-4">
                                <?php foreach ($movementHistory as $movement): ?>
                                <div class="mb-3 pb-3 border-b border-gray-200 last:border-0 last:mb-0 last:pb-0">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="font-medium"><?= htmlspecialchars($movement['moved_by_name']); ?></span>
                                            <span class="ml-2 text-sm text-gray-600">
                                                moved from <span class="font-medium"><?= htmlspecialchars($movement['from_department']); ?></span>
                                                to <span class="font-medium"><?= htmlspecialchars($movement['to_department']); ?></span>
                                            </span>
                                        </div>
                                        <span class="text-xs text-gray-500"><?= date('M d, Y H:i', strtotime($movement['moved_at'])); ?></span>
                                    </div>
                                    <?php if (!empty($movement['note'])): ?>
                                    <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($movement['note']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="md:col-span-1">
                        <div class="bg-gray-50 p-4 rounded-md">
                            <h4 class="font-medium text-gray-700 mb-4">Transfer Document</h4>
                            
                            <?php if ($singleDocument['status'] === 'rejected'): ?>
                            <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-circle text-red-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-red-700">
                                            This document has been rejected and cannot be moved.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php elseif (isset($singleDocument['final_destination']) && $singleDocument['department'] == $singleDocument['final_destination']): ?>
                            <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-check-circle text-green-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-green-700">
                                            This document has reached its final destination and is now awaiting approval.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <form action="approve.php" method="GET">
                                <input type="hidden" name="id" value="<?= $singleDocument['id']; ?>">
                                <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 mb-4">
                                    <i class="fas fa-clipboard-check mr-2"></i> Proceed to Approval
                                </button>
                            </form>
                            <?php else: ?>
                            <form action="move.php" method="POST">
                                <input type="hidden" name="document_id" value="<?= $singleDocument['id']; ?>">
                                <input type="hidden" name="document_unique_id" value="<?= $singleDocument['doc_unique_id']; ?>">
                                
                                <div class="mb-4">
                                    <label for="to_department" class="block text-sm font-medium text-gray-700 mb-1">Destination Department <span class="text-red-500">*</span></label>
                                    <select id="to_department" name="to_department" class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md" required>
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                        <?php if ($dept !== $singleDocument['department']): ?>
                                        <option value="<?= htmlspecialchars($dept); ?>">
                                            <?= htmlspecialchars($dept); ?>
                                        </option>
                                        <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="note" class="block text-sm font-medium text-gray-700 mb-1">Note (Optional)</label>
                                    <textarea id="note" name="note" rows="3" class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Reason for transfer or special instructions..."></textarea>
                                </div>
                                
                                <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                    <i class="fas fa-exchange-alt mr-2"></i> Transfer Document
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Document List -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-exchange-alt text-primary-500 text-xl mr-3"></i>
                    <h3 class="text-lg font-semibold text-gray-800">Documents</h3>
                </div>
                <span class="text-sm text-gray-600"><?= count($movableDocuments); ?> document(s)</span>
            </div>
            
            <?php if (empty($movableDocuments)): ?>
            <div class="p-6 text-center">
                <div class="py-6">
                    <i class="fas fa-folder-open text-gray-300 text-5xl mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-800 mb-1">No documents available</h3>
                    <p class="text-gray-600">No documents were found to move</p>
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
                                Current Department
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date Uploaded
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($movableDocuments as $document): ?>
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
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php 
                                    switch ($document['status']) {
                                        case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'approved': echo 'bg-green-100 text-green-800'; break;
                                        case 'rejected': echo 'bg-red-100 text-red-800'; break;
                                        case 'in_movement': echo 'bg-blue-100 text-blue-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <?= ucfirst($document['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= date('M d, Y', strtotime($document['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <?php if ($document['status'] !== 'rejected'): ?>
                                <a href="move.php?id=<?= $document['id']; ?>" class="text-primary-600 hover:text-primary-900">
                                    Move
                                </a>
                                <?php else: ?>
                                <span class="text-gray-400 cursor-not-allowed" title="Rejected documents cannot be moved">
                                    Cannot Move
                                </span>
                                <?php endif; ?>
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
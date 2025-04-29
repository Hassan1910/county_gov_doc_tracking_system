<?php
/**
 * Document Approval Page
 * 
 * This page allows admin and supervisor users to approve or reject documents
 */

// Include authentication utilities
require_once '../includes/auth.php';

// Require login and admin/supervisor role
requireLogin();
requireRole(['admin', 'supervisor', 'manager']);

// Get current user data
$user = getCurrentUser();

// Include database connection
require_once '../config/db.php';

// Handle single document view if ID is provided
$singleDocument = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        $sql = "SELECT d.id, d.doc_unique_id, d.title, d.file_path, d.type, d.department, 
                       d.status, d.created_at, u.name as uploader_name 
                FROM documents d
                JOIN users u ON d.uploaded_by = u.id
                WHERE d.id = :id";
        $stmt = db()->prepare($sql);
        $stmt->execute(['id' => $_GET['id']]);
        
        if ($stmt->rowCount() > 0) {
            $singleDocument = $stmt->fetch();
            
            // Also get approval history if any
            $appSql = "SELECT a.action, a.comments, a.created_at, u.name as approver_name
                       FROM document_approvals a
                       JOIN users u ON a.approved_by = u.id
                       WHERE a.document_id = :document_id
                       ORDER BY a.created_at DESC";
            $appStmt = db()->prepare($appSql);
            $appStmt->execute(['document_id' => $_GET['id']]);
            $approvalHistory = $appStmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Error fetching document: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while retrieving the document.";
        header('Location: dashboard.php');
        exit;
    }
}

// DEBUG FLAG
$debug = true;

// Handle approval/rejection form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['document_id'])) {
    if ($debug) {
        echo '<pre style="background: #fffbe6; color: #333; border: 1px solid #e0c97f; padding: 10px;">DEBUG POST DATA:\n';
        print_r($_POST);
        echo "</pre>";
    }
    
    if (!in_array($_POST['action'], ['approve', 'reject'])) {
        $_SESSION['error'] = "Invalid action.";
        header('Location: approve.php');
        exit;
    }
    
    try {
        // Begin transaction
        db()->beginTransaction();
        $transactionStarted = true;
        
        // Record approval/rejection
        $sql = "INSERT INTO document_approvals (document_id, approved_by, action, comments, created_at) 
                VALUES (:document_id, :approved_by, :action, :comments, NOW())";
        $stmt = db()->prepare($sql);
        $stmt->execute([
            'document_id' => $_POST['document_id'],
            'approved_by' => $user['id'],
            'action' => $_POST['action'],
            'comments' => $_POST['comments'] ?? null
        ]);
        
        // Update document status
        $status = ($_POST['action'] === 'approve') ? 'approved' : 'rejected';
        $sql = "UPDATE documents SET status = :status WHERE id = :id";
        $stmt = db()->prepare($sql);
        $stmt->execute([
            'status' => $status,
            'id' => $_POST['document_id']
        ]);
        
        // Log the activity
        $action = ($_POST['action'] === 'approve') ? 'document_approved' : 'document_rejected';
        $details = "Document ID: " . (!empty($_POST['document_unique_id']) ? $_POST['document_unique_id'] : 'N/A');
        logActivity($action, $details);
        
        // Commit transaction
        db()->commit();
        $transactionStarted = false;
        
        $_SESSION['success'] = "Document has been " . ($_POST['action'] === 'approve' ? 'approved' : 'rejected') . " successfully.";
        
        // Redirect to pending documents list
        header('Location: approve.php');
        exit;
        
    } catch (PDOException $e) {
        // Rollback transaction on error if it was successfully started
        if (isset($transactionStarted) && $transactionStarted) {
            try {
                db()->rollBack();
            } catch (Exception $rollbackException) {
                error_log("Error rolling back transaction: " . $rollbackException->getMessage());
            }
        }
        if ($debug) {
            echo '<pre style="background: #ffe6e6; color: #a00; border: 1px solid #e0a0a0; padding: 10px;">DEBUG ERROR:\n';
            echo $e->getMessage();
            echo "</pre>";
        }
        error_log("Error processing approval: " . $e->getMessage() . " POST: " . print_r($_POST, true));
        $_SESSION['error'] = "An error occurred while processing the approval.";
        // header('Location: approve.php'); 
        // exit; 
    }
}

// Get pending documents
$pendingDocuments = [];
try {
    $sql = "SELECT d.id, d.doc_unique_id, d.title, d.type, d.department, d.created_at, 
                   u.name as uploader_name 
            FROM documents d
            JOIN users u ON d.uploaded_by = u.id
            WHERE d.status = 'pending'";
    
    // For supervisors who are not admins, only show documents from their department
    if ($user['role'] === 'supervisor' && !hasRole('admin')) {
        $sql .= " AND d.department = :department";
        $params = ['department' => $user['department']];
    } else {
        $params = [];
    }
    
    $sql .= " ORDER BY d.created_at DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $pendingDocuments = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching pending documents: " . $e->getMessage());
}

// Set page title
$page_title = "Approve Documents";

// Include header
include_once '../includes/header.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Approve Documents</h1>
                <p class="mt-1 text-sm text-gray-600">Review and approve/reject pending documents</p>
            </div>
            <a href="dashboard.php" class="flex items-center text-primary-600 hover:text-primary-800">
                <i class="fas fa-arrow-left mr-2"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>
    </div>
    
    <div class="max-w-7xl mx-auto mt-6 px-4 sm:px-6 md:px-8">
        <?php if ($singleDocument): ?>
        <!-- Single Document Approval View -->
        <div class="bg-white shadow-lg rounded-lg overflow-hidden border border-gray-100 mb-6">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-file-alt text-primary-500 text-xl mr-3"></i>
                    <h3 class="text-lg font-semibold text-gray-800">Document Review</h3>
                </div>
                <span class="text-sm bg-yellow-100 text-yellow-800 font-medium py-1 px-2 rounded-full">Pending</span>
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
                                <h4 class="text-sm font-medium text-gray-500">Department</h4>
                                <p class="text-gray-800"><?= htmlspecialchars($singleDocument['department']); ?></p>
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
                            <a href="download.php?id=<?= $singleDocument['id']; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 ml-2">
                                <i class="fas fa-download mr-2"></i> Download PDF
                            </a>
                        </div>
                        
                        <?php if (!empty($approvalHistory)): ?>
                        <div class="mt-6">
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Approval History</h4>
                            <div class="bg-gray-50 rounded-md p-4">
                                <?php foreach ($approvalHistory as $approval): ?>
                                <div class="mb-3 pb-3 border-b border-gray-200 last:border-0 last:mb-0 last:pb-0">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="font-medium"><?= htmlspecialchars($approval['approver_name']); ?></span>
                                            <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?= $approval['action'] === 'approve' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <?= ucfirst($approval['action']); ?>
                                            </span>
                                        </div>
                                        <span class="text-xs text-gray-500"><?= date('M d, Y H:i', strtotime($approval['created_at'])); ?></span>
                                    </div>
                                    <?php if (!empty($approval['comments'])): ?>
                                    <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($approval['comments']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="md:col-span-1">
                        <div class="bg-gray-50 p-4 rounded-md">
                            <h4 class="font-medium text-gray-700 mb-4">Approval Decision</h4>
                            
                            <form action="approve.php" method="POST">
                                <input type="hidden" name="document_id" value="<?= $singleDocument['id']; ?>">
                                <input type="hidden" name="document_unique_id" value="<?= $singleDocument['doc_unique_id']; ?>">
                                
                                <div class="mb-4">
                                    <label for="comments" class="block text-sm font-medium text-gray-700 mb-1">Comments (Optional)</label>
                                    <textarea id="comments" name="comments" rows="4" class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md"></textarea>
                                </div>
                                
                                <div class="flex space-x-2">
                                    <button type="submit" name="action" value="approve" class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                        <i class="fas fa-check mr-2"></i> Approve
                                    </button>
                                    <button type="submit" name="action" value="reject" class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                        <i class="fas fa-times mr-2"></i> Reject
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Pending Documents List -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-clock text-primary-500 text-xl mr-3"></i>
                    <h3 class="text-lg font-semibold text-gray-800">Pending Documents</h3>
                </div>
                <span class="text-sm text-gray-600"><?= count($pendingDocuments); ?> pending document(s)</span>
            </div>
            
            <?php if (empty($pendingDocuments)): ?>
            <div class="p-6 text-center">
                <div class="py-6">
                    <i class="fas fa-check-circle text-gray-300 text-5xl mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-800 mb-1">No pending documents</h3>
                    <p class="text-gray-600">All documents have been processed</p>
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
                                Date Uploaded
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
                        <?php foreach ($pendingDocuments as $document): ?>
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
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= date('M d, Y', strtotime($document['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($document['uploader_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="approve.php?id=<?= $document['id']; ?>" class="text-primary-600 hover:text-primary-900">
                                    Review
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
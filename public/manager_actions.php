<?php
/**
 * Manager Actions Page
 * 
 * This page allows senior managers to approve, reject, or process payment for documents
 * after they have been approved by assistant managers.
 */

// Include authentication utilities
require_once '../includes/auth.php';

// Require login to access this page
requireLogin();

// Only senior managers can access this page
if (!hasRole(['senior_manager'])) {
    $_SESSION['error'] = "You don't have permission to access this page.";
    header('Location: dashboard.php');
    exit;
}

// Get current user data
$user = getCurrentUser();

// Include database connection
require_once '../config/db.php';

// Initialize variables
$errors = [];
$success = '';
$documentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$document = null;

// Check if we have a valid document ID
if (!$documentId) {
    $_SESSION['error'] = "Document ID is required.";
    header('Location: dashboard.php');
    exit;
}

// Get document details
try {
    $sql = "SELECT d.*, u.name as uploaded_by_name, 
                  (SELECT name FROM users WHERE id = d.submitter_id) as contractor_name
            FROM documents d
            JOIN users u ON d.uploaded_by = u.id
            WHERE d.id = :id AND d.department = :department";
    $stmt = db()->prepare($sql);
    $stmt->execute([
        'id' => $documentId,
        'department' => $user['department']
    ]);
    $document = $stmt->fetch();
    
    if (!$document) {
        $_SESSION['error'] = "Document not found or not in your department.";
        header('Location: dashboard.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Error retrieving document: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while retrieving document details.";
    header('Location: dashboard.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        // Begin transaction
        db()->beginTransaction();
        
        $action = $_POST['action'];
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
        
        // Validate action
        if (!in_array($action, ['approve', 'reject', 'pay', 'complete'])) {
            throw new Exception("Invalid action specified.");
        }
        
        if ($action === 'reject' && empty($notes)) {
            throw new Exception("Notes are required when rejecting a document.");
        }
        
        // Record the action
        $sql = "INSERT INTO approval_actions (document_id, user_id, action, notes, created_at) 
                VALUES (:document_id, :user_id, :action, :notes, NOW())";
        $stmt = db()->prepare($sql);
        $stmt->execute([
            'document_id' => $documentId,
            'user_id' => $user['id'],
            'action' => $action,
            'notes' => $notes
        ]);
        
        // Update document status based on action
        switch ($action) {
            case 'approve':
                $sql = "UPDATE documents SET status = 'approved' WHERE id = :id";
                $statusMsg = "approved";
                break;
                
            case 'reject':
                $sql = "UPDATE documents SET status = 'rejected' WHERE id = :id";
                $statusMsg = "rejected";
                break;
                
            case 'pay':
                $sql = "UPDATE documents SET payment_status = 'paid' WHERE id = :id";
                $statusMsg = "marked as paid";
                break;
                
            case 'complete':
                // Get a clerk to send the document to
                $clerkSql = "SELECT id, department FROM users WHERE role = 'clerk' LIMIT 1";
                $clerkStmt = db()->prepare($clerkSql);
                $clerkStmt->execute();
                $clerk = $clerkStmt->fetch();
                
                if (!$clerk) {
                    throw new Exception("No clerk found to complete the process.");
                }
                
                // Move the document to the clerk
                $moveSql = "INSERT INTO document_movements 
                           (document_id, from_department, to_department, moved_by, note, moved_at) 
                           VALUES (:document_id, :from_department, :to_department, :moved_by, :note, NOW())";
                $moveStmt = db()->prepare($moveSql);
                $moveStmt->execute([
                    'document_id' => $documentId,
                    'from_department' => $user['department'],
                    'to_department' => $clerk['department'],
                    'moved_by' => $user['id'],
                    'note' => 'Completed by senior manager. Sent to clerk for final processing.'
                ]);
                
                // Update document's department
                $sql = "UPDATE documents SET department = :department, status = 'approved' WHERE id = :id";
                db()->prepare($sql)->execute([
                    'department' => $clerk['department'],
                    'id' => $documentId
                ]);
                
                $statusMsg = "completed and sent to clerk";
                break;
        }
        
        // Execute the status update query if not 'complete' (already executed above)
        if ($action !== 'complete') {
            $stmt = db()->prepare($sql);
            $stmt->execute(['id' => $documentId]);
        }
        
        // Send notification to contractor if exists
        if ($document['submitter_id']) {
            $message = "Your document \"{$document['title']}\" has been $statusMsg by the senior manager.";
            
            if ($action === 'reject' && !empty($notes)) {
                $message .= " Reason: $notes";
            }
            
            $sql = "INSERT INTO client_notifications (client_id, document_id, message, is_read, created_at) 
                    VALUES (:client_id, :document_id, :message, 0, NOW())";
            $stmt = db()->prepare($sql);
            $stmt->execute([
                'client_id' => $document['submitter_id'],
                'document_id' => $documentId,
                'message' => $message
            ]);
        }
        
        // Commit the transaction
        db()->commit();
        
        // Set success message and redirect
        $_SESSION['success'] = "Document successfully $statusMsg.";
        header('Location: view_document.php?id=' . $documentId);
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        
        error_log("Error processing manager action: " . $e->getMessage());
        $errors[] = $e->getMessage();
    }
}

// Set page title
$page_title = "Manager Actions: " . $document['title'];

// Include header
include_once '../includes/header.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-semibold text-gray-900">Manager Actions</h1>
            <a href="view_document.php?id=<?= $documentId ?>" class="text-primary-600 hover:text-primary-900">
                <i class="fas fa-arrow-left mr-1"></i> Back to Document
            </a>
        </div>
        <p class="mt-1 text-sm text-gray-600">Process the document approval, payment, or completion</p>
    </div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8 mt-5">
        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-5">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-400"></i>
                    </div>
                    <div class="ml-3">
                        <?php foreach ($errors as $error): ?>
                            <p class="text-sm text-red-700"><?= htmlspecialchars($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-5">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700"><?= htmlspecialchars($success) ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Document Information Card -->
        <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
            <div class="px-4 py-5 sm:px-6 bg-gray-50">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Document Information</h3>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">Details about the document being processed.</p>
            </div>
            <div class="border-t border-gray-200">
                <dl>
                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Document ID</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?= htmlspecialchars($document['doc_unique_id']) ?></dd>
                    </div>
                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Title</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?= htmlspecialchars($document['title']) ?></dd>
                    </div>
                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Type</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?= htmlspecialchars($document['type']) ?></dd>
                    </div>
                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Uploaded By</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?= htmlspecialchars($document['uploaded_by_name']) ?></dd>
                    </div>
                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Contractor</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?= htmlspecialchars($document['contractor_name'] ?? 'N/A') ?></dd>
                    </div>
                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1 sm:mt-0 sm:col-span-2">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                            switch($document['status']) {
                                                case 'approved': echo 'bg-green-100 text-green-800'; break;
                                                case 'rejected': echo 'bg-red-100 text-red-800'; break;
                                                case 'pending_approval': echo 'bg-yellow-100 text-yellow-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                        ?>">
                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $document['status']))) ?>
                            </span>
                        </dd>
                    </div>
                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Payment Status</dt>
                        <dd class="mt-1 sm:mt-0 sm:col-span-2">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                            switch($document['payment_status'] ?? 'pending') {
                                                case 'paid': echo 'bg-green-100 text-green-800'; break;
                                                case 'approved': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'rejected': echo 'bg-red-100 text-red-800'; break;
                                                default: echo 'bg-yellow-100 text-yellow-800';
                                            }
                                        ?>">
                                <?= htmlspecialchars(ucfirst($document['payment_status'] ?? 'pending')) ?>
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
        
        <!-- Manager Actions Form -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="px-4 py-5 sm:px-6 bg-gray-50">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Available Actions</h3>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">Choose an action to process this document.</p>
            </div>
            
            <div class="p-6">
                <form action="manager_actions.php?id=<?= $documentId ?>" method="POST">
                    
                    <div class="mb-6">
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">
                            Notes
                        </label>
                        <textarea id="notes" name="notes" rows="3"
                            class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md"
                            placeholder="Add any relevant notes or comments about this action"
                        ><?= isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : '' ?></textarea>
                        <p class="mt-1 text-xs text-gray-500">Required for rejections, optional for other actions.</p>
                    </div>
                    
                    <div class="mb-6">
                        <fieldset>
                            <legend class="block text-sm font-medium text-gray-700 mb-2">Select Action</legend>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                
                                <!-- Approve Button -->
                                <div>
                                    <button type="submit" name="action" value="approve"
                                        class="w-full inline-flex justify-center py-2 px-4 border border-transparent 
                                            shadow-sm text-sm font-medium rounded-md text-white bg-green-600 
                                            hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 
                                            focus:ring-green-500">
                                        <i class="fas fa-check mr-2"></i> Approve
                                    </button>
                                </div>
                                
                                <!-- Reject Button -->
                                <div>
                                    <button type="submit" name="action" value="reject"
                                        class="w-full inline-flex justify-center py-2 px-4 border border-transparent 
                                            shadow-sm text-sm font-medium rounded-md text-white bg-red-600 
                                            hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 
                                            focus:ring-red-500">
                                        <i class="fas fa-times mr-2"></i> Reject
                                    </button>
                                </div>
                                
                                <!-- Pay Button -->
                                <div>
                                    <button type="submit" name="action" value="pay"
                                        class="w-full inline-flex justify-center py-2 px-4 border border-transparent 
                                            shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 
                                            hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 
                                            focus:ring-blue-500">
                                        <i class="fas fa-money-bill-wave mr-2"></i> Pay
                                    </button>
                                </div>
                                
                                <!-- Complete Button -->
                                <div>
                                    <button type="submit" name="action" value="complete"
                                        class="w-full inline-flex justify-center py-2 px-4 border border-transparent 
                                            shadow-sm text-sm font-medium rounded-md text-white bg-purple-600 
                                            hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 
                                            focus:ring-purple-500">
                                        <i class="fas fa-check-double mr-2"></i> Complete
                                    </button>
                                </div>
                            </div>
                        </fieldset>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?> 
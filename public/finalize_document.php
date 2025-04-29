<?php
/**
 * Document Finalization
 * 
 * This page allows admins to mark documents as finalized when they've completed
 * their journey through the departments and reached their final destination.
 */

// Include authentication utilities
require_once '../includes/auth.php';

// Require login and admin role
requireLogin();
requireRole('admin');

// Get current user data
$user = getCurrentUser();

// Include database connection
require_once '../config/db.php';

// Process finalization form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['document_id'])) {
    $documentId = filter_var($_POST['document_id'], FILTER_VALIDATE_INT);
    $completionNote = filter_var($_POST['completion_note'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    
    if ($documentId === false) {
        $_SESSION['error'] = "Invalid document ID.";
        header('Location: dashboard.php');
        exit;
    }
    
    try {
        // Start transaction
        db()->beginTransaction();
        
        // Get document information
        $sql = "SELECT id, doc_unique_id, title, department, final_destination, status, submitter_id 
                FROM documents 
                WHERE id = :id";
        $stmt = db()->prepare($sql);
        $stmt->execute(['id' => $documentId]);
        $document = $stmt->fetch();
        
        if (!$document) {
            throw new Exception("Document not found.");
        }
        
        // Verify document is at its final destination
        if ($document['department'] != $document['final_destination']) {
            throw new Exception("Document has not reached its final destination yet.");
        }
        
        // Update document status to "finalized"
        $sql = "UPDATE documents 
                SET status = 'finalized', 
                    finalized_at = NOW(), 
                    finalized_by = :finalized_by, 
                    finalization_note = :note
                WHERE id = :id";
        $stmt = db()->prepare($sql);
        $result = $stmt->execute([
            'finalized_by' => $user['id'],
            'note' => $completionNote,
            'id' => $documentId
        ]);
        
        if (!$result) {
            throw new Exception("Failed to finalize document.");
        }
        
        // Log the activity
        $details = "Finalized document ID: " . $document['doc_unique_id'];
        logActivity('document_finalized', $details);
        
        // Send notification to client
        if (!empty($document['submitter_id'])) {
            $sql = "INSERT INTO client_notifications (client_id, document_id, message, is_read, created_at)
                    VALUES (:client_id, :document_id, :message, 0, NOW())";
            $stmt = db()->prepare($sql);
            $stmt->execute([
                'client_id' => $document['submitter_id'],
                'document_id' => $documentId,
                'message' => "Your document \"{$document['title']}\" has been processed and finalized. You can download it from your dashboard or visit our office to collect the physical document."
            ]);
        }
        
        // Commit transaction
        db()->commit();
        
        $_SESSION['success'] = "Document has been successfully finalized.";
        header('Location: view_document.php?id=' . $documentId);
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        
        error_log("Error finalizing document: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred: " . $e->getMessage();
        header('Location: view_document.php?id=' . $documentId);
        exit;
    }
}

// Handle document ID from URL parameter
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Document ID is required.";
    header('Location: dashboard.php');
    exit;
}

$documentId = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if ($documentId === false) {
    $_SESSION['error'] = "Invalid document ID.";
    header('Location: dashboard.php');
    exit;
}

// Get document information
try {
    $sql = "SELECT d.id, d.doc_unique_id, d.title, d.file_path, d.type, 
                   d.department, d.final_destination, d.status, d.created_at,
                   u.name as uploader_name
            FROM documents d
            JOIN users u ON d.uploaded_by = u.id
            WHERE d.id = :id";
    $stmt = db()->prepare($sql);
    $stmt->execute(['id' => $documentId]);
    
    if ($stmt->rowCount() === 0) {
        $_SESSION['error'] = "Document not found.";
        header('Location: dashboard.php');
        exit;
    }
    
    $document = $stmt->fetch();
    
    // Verify document is at its final destination
    if ($document['department'] != $document['final_destination']) {
        $_SESSION['error'] = "Document has not reached its final destination yet.";
        header('Location: view_document.php?id=' . $documentId);
        exit;
    }
    
    // Verify document isn't already finalized
    if ($document['status'] === 'finalized') {
        $_SESSION['error'] = "Document has already been finalized.";
        header('Location: view_document.php?id=' . $documentId);
        exit;
    }
    
} catch (PDOException $e) {
    error_log("Error fetching document: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while retrieving the document.";
    header('Location: dashboard.php');
    exit;
}

// Set page title
$page_title = "Finalize Document";

// Include header
include_once '../includes/header.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-gray-900">Finalize Document</h1>
            <a href="view_document.php?id=<?= $documentId; ?>" class="text-primary-600 hover:text-primary-900">
                <i class="fas fa-arrow-left mr-1"></i> Back to Document
            </a>
        </div>
    </div>
    
    <div class="max-w-7xl mx-auto mt-6 px-4 sm:px-6 md:px-8">
        <!-- Document Details Card -->
        <div class="bg-white shadow-lg rounded-lg overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-file-alt text-primary-500 text-xl mr-3"></i>
                    <h3 class="text-lg font-semibold text-gray-800">Document Information</h3>
                </div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    <i class="fas fa-check-circle mr-1"></i> Final Destination Reached
                </span>
            </div>
            
            <div class="p-6">
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-6">
                    <div class="md:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Document Title</dt>
                        <dd class="mt-1 text-lg font-medium text-gray-900"><?= htmlspecialchars($document['title']); ?></dd>
                    </div>
                    
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Document ID</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['doc_unique_id']); ?></dd>
                    </div>
                    
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Current Department</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                <?= htmlspecialchars($document['department']); ?>
                                <i class="fas fa-check ml-1"></i>
                            </span>
                        </dd>
                    </div>
                    
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Document Type</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['type']); ?></dd>
                    </div>
                    
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                <?= ucfirst($document['status']); ?>
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
        
        <!-- Finalization Form -->
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">Finalize Document</h3>
            </div>
            
            <div class="p-6">
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                Finalizing a document marks it as fully processed. The client will be notified that their document 
                                is complete and can be collected or downloaded.
                            </p>
                        </div>
                    </div>
                </div>
                
                <form action="finalize_document.php" method="POST">
                    <input type="hidden" name="document_id" value="<?= $document['id']; ?>">
                    
                    <div class="mb-6">
                        <label for="completion_note" class="block text-sm font-medium text-gray-700 mb-1">Completion Note</label>
                        <textarea id="completion_note" name="completion_note" rows="4" 
                            class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md"
                            placeholder="Add any notes about the document completion process..."></textarea>
                        <p class="mt-1 text-xs text-gray-500">
                            This note will be included in the internal record (optional)
                        </p>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-3">
                        <a href="view_document.php?id=<?= $documentId; ?>" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            <i class="fas fa-check-circle mr-2"></i> Mark Document as Finalized
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?> 
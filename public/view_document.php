<?php
/**
 * Document View Page
 * 
 * This page displays the details of a document and its history.
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
$documentId = null;
$document = null;
$history = [];
$approvals = [];
$canMove = false;
$canApprove = false;
$canReject = false;

// Check if document ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Document ID is required.";
    header('Location: dashboard.php');
    exit;
}

// Get and sanitize document ID
$documentId = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if ($documentId === false) {
    $_SESSION['error'] = "Invalid document ID.";
    header('Location: dashboard.php');
    exit;
}

try {
    // Get document details with uploader information
    $sql = "SELECT d.id, d.title, d.file_path, d.type, d.department, 
                   d.status, d.created_at, d.uploaded_by,
                   u.name as uploader_name, u.email as uploader_email, u.department as uploader_department";
    
    // Add doc_unique_id if it exists
    $checkColumn = "SHOW COLUMNS FROM documents LIKE 'doc_unique_id'";
    $columnCheck = db()->query($checkColumn);
    if ($columnCheck->rowCount() > 0) {
        $sql .= ", d.doc_unique_id";
    } else {
        $sql .= ", d.id as doc_unique_id";
    }
    
    // Add updated_at if it exists
    $checkColumn = "SHOW COLUMNS FROM documents LIKE 'updated_at'";
    $columnCheck = db()->query($checkColumn);
    if ($columnCheck->rowCount() > 0) {
        $sql .= ", d.updated_at";
    } else {
        $sql .= ", d.created_at as updated_at";
    }
    
    // Add reference_number if it exists
    $checkColumn = "SHOW COLUMNS FROM documents LIKE 'reference_number'";
    $columnCheck = db()->query($checkColumn);
    if ($columnCheck->rowCount() > 0) {
        $sql .= ", d.reference_number";
    } else {
        $sql .= ", NULL as reference_number";
    }
    
    // Add description if it exists
    $checkColumn = "SHOW COLUMNS FROM documents LIKE 'description'";
    $columnCheck = db()->query($checkColumn);
    if ($columnCheck->rowCount() > 0) {
        $sql .= ", d.description";
    } else {
        $sql .= ", NULL as description";
    }
    
    // Add final_destination if it exists
    $checkColumn = "SHOW COLUMNS FROM documents LIKE 'final_destination'";
    $columnCheck = db()->query($checkColumn);
    if ($columnCheck->rowCount() > 0) {
        $sql .= ", IFNULL(d.final_destination, d.department) as final_destination";
    } else {
        $sql .= ", d.department as final_destination";
    }
    
    // Add finalized fields if they exist
    $checkColumn = "SHOW COLUMNS FROM documents LIKE 'finalized_at'";
    $columnCheck = db()->query($checkColumn);
    if ($columnCheck->rowCount() > 0) {
        $sql .= ", IFNULL(d.finalized_at, NULL) as finalized_at";
    } else {
        $sql .= ", NULL as finalized_at";
    }
    
    $checkColumn = "SHOW COLUMNS FROM documents LIKE 'finalized_by'";
    $columnCheck = db()->query($checkColumn);
    if ($columnCheck->rowCount() > 0) {
        $sql .= ", IFNULL(d.finalized_by, NULL) as finalized_by";
    } else {
        $sql .= ", NULL as finalized_by";
    }
    
    $checkColumn = "SHOW COLUMNS FROM documents LIKE 'finalization_note'";
    $columnCheck = db()->query($checkColumn);
    if ($columnCheck->rowCount() > 0) {
        $sql .= ", IFNULL(d.finalization_note, NULL) as finalization_note";
    } else {
        $sql .= ", NULL as finalization_note";
    }
    
    // Add submitter_id if it exists
    $checkColumn = "SHOW COLUMNS FROM documents LIKE 'submitter_id'";
    $columnCheck = db()->query($checkColumn);
    if ($columnCheck->rowCount() > 0) {
        $sql .= ", d.submitter_id";
    } else {
        $sql .= ", NULL as submitter_id";
    }
    
    // Complete the query
    $sql .= " FROM documents d
            JOIN users u ON d.uploaded_by = u.id
            WHERE d.id = :id";
    
    $stmt = db()->prepare($sql);
    $stmt->execute(['id' => $documentId]);
    
    // Check if document exists
    if ($stmt->rowCount() === 0) {
        $_SESSION['error'] = "Document not found.";
        header('Location: dashboard.php');
        exit;
    }
    
    // Get document data
    $document = $stmt->fetch();
    
    // Check user permissions
    // Only users from the document's current department or admins can view it
    $hasViewPermission = hasRole('admin') || $user['department'] === $document['department'];
    if (!$hasViewPermission) {
        $_SESSION['error'] = "You don't have permission to view this document.";
        header('Location: dashboard.php');
        exit;
    }
    
    // Check if user can move the document
    // Only admins, department heads of the current department, or supervisors can move documents
    $canMove = (hasRole(['admin', 'department_head', 'supervisor']) && 
                ($user['department'] === $document['department'] || hasRole('admin'))) &&
                ($document['status'] !== 'approved' && $document['status'] !== 'rejected');
    
    // Check if user can approve/reject the document
    // Only admins or supervisors can approve/reject documents
    $canApprove = hasRole(['admin', 'supervisor']) && 
                  $document['status'] !== 'approved' && 
                  $document['status'] !== 'rejected';
    
    // Get document movement history
    $sql = "SELECT m.id, m.document_id, m.created_at";
    
    // Check if from_department exists
    $checkColumn = "SHOW COLUMNS FROM document_movements LIKE 'from_department'";
    $columnCheck = db()->query($checkColumn);
    if ($columnCheck->rowCount() > 0) {
        $sql .= ", m.from_department";
    } else {
        $sql .= ", NULL as from_department";
    }
    
    // Check if to_department exists
    $checkColumn = "SHOW COLUMNS FROM document_movements LIKE 'to_department'";
    $columnCheck = db()->query($checkColumn);
    if ($columnCheck->rowCount() > 0) {
        $sql .= ", m.to_department";
    } else {
        $sql .= ", NULL as to_department";
    }
    
    // Check if notes exists (or if it's called note)
    $checkColumn = "SHOW COLUMNS FROM document_movements LIKE 'notes'";
    $columnCheck = db()->query($checkColumn);
    if ($columnCheck->rowCount() > 0) {
        $sql .= ", m.notes";
    } else {
        $checkColumn = "SHOW COLUMNS FROM document_movements LIKE 'note'";
        $columnCheck = db()->query($checkColumn);
        if ($columnCheck->rowCount() > 0) {
            $sql .= ", m.note as notes";
        } else {
            $sql .= ", NULL as notes";
        }
    }
    
    // Check if moved_by exists
    $checkColumn = "SHOW COLUMNS FROM document_movements LIKE 'moved_by'";
    $columnCheck = db()->query($checkColumn);
    if ($columnCheck->rowCount() > 0) {
        $userJoin = "JOIN users u ON m.moved_by = u.id";
        $sql .= ", u.name as moved_by_name, u.email as moved_by_email";
    } else {
        $userJoin = "";
        $sql .= ", NULL as moved_by_name, NULL as moved_by_email";
    }
    
    // Check if the moved_at column exists or if created_at should be used
    $checkColumn = "SHOW COLUMNS FROM document_movements LIKE 'moved_at'";
    $columnCheck = db()->query($checkColumn);
    if ($columnCheck->rowCount() > 0) {
        $orderBy = "ORDER BY m.moved_at DESC";
    } else {
        $orderBy = "ORDER BY m.created_at DESC";
    }
    
    // Complete the query
    $sql .= " FROM document_movements m ";
    if (!empty($userJoin)) {
        $sql .= $userJoin;
    }
    $sql .= " WHERE m.document_id = :document_id ";
    $sql .= $orderBy;
    
    $stmt = db()->prepare($sql);
    $stmt->execute(['document_id' => $documentId]);
    $history = $stmt->fetchAll();
    
    // Get document approvals with similar checks
    $sql = "SELECT a.id, a.document_id, a.created_at";
    
    // Check if action field exists
    $checkColumn = "SHOW COLUMNS FROM document_approvals LIKE 'action'";
    $columnCheck = db()->query($checkColumn);
    if ($columnCheck->rowCount() > 0) {
        $sql .= ", a.action";
    } else {
        $sql .= ", 'approved' as action"; // Default to 'approved' if no action column
    }
    
    // Check if comments field exists
    $checkColumn = "SHOW COLUMNS FROM document_approvals LIKE 'comments'";
    $columnCheck = db()->query($checkColumn);
    if ($columnCheck->rowCount() > 0) {
        $sql .= ", a.comments";
    } else {
        $checkColumn = "SHOW COLUMNS FROM document_approvals LIKE 'comment'";
        $columnCheck = db()->query($checkColumn);
        if ($columnCheck->rowCount() > 0) {
            $sql .= ", a.comment as comments";
        } else {
            $sql .= ", NULL as comments";
        }
    }
    
    // Check if user_id field exists
    $checkColumn = "SHOW COLUMNS FROM document_approvals LIKE 'user_id'";
    $columnCheck = db()->query($checkColumn);
    if ($columnCheck->rowCount() > 0) {
        $userIdField = "a.user_id";
        $userJoin = "JOIN users u ON a.user_id = u.id";
    } else {
        $checkColumn = "SHOW COLUMNS FROM document_approvals LIKE 'approved_by'";
        $columnCheck = db()->query($checkColumn);
        if ($columnCheck->rowCount() > 0) {
            $userIdField = "a.approved_by";
            $userJoin = "JOIN users u ON a.approved_by = u.id";
        } else {
            $userIdField = "NULL";
            $userJoin = "";
        }
    }
    
    // Add user info if join is possible
    if (!empty($userJoin)) {
        $sql .= ", u.name as user_name, u.email as user_email, u.department as user_department";
    } else {
        $sql .= ", NULL as user_name, NULL as user_email, NULL as user_department";
    }
    
    // Complete the query
    $sql .= " FROM document_approvals a ";
    if (!empty($userJoin)) {
        $sql .= $userJoin;
    }
    $sql .= " WHERE a.document_id = :document_id ";
    $sql .= "ORDER BY a.created_at DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute(['document_id' => $documentId]);
    $approvals = $stmt->fetchAll();
    
} catch (PDOException $e) {
    // Log error and show generic error message
    error_log("Document view error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while retrieving document details.";
    header('Location: dashboard.php');
    exit;
}

// Set page title
$page_title = "View Document: " . $document['title'];

// Include header
include_once '../includes/header.php';
?>

<div class="py-6">
    <div class="mx-auto px-4 sm:px-6 md:px-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-gray-900">Document Details</h1>
            <a href="dashboard.php" class="text-primary-600 hover:text-primary-900">
                <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
            </a>
        </div>
    </div>
    
    <div class="mx-auto px-4 sm:px-6 md:px-8">
        <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-50 border-l-4 border-green-400 p-4 mt-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700"><?= $_SESSION['success']; ?></p>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
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
        
        <!-- Document Actions -->
        <?php if ($canMove || $canApprove || (hasRole('admin') && isset($document['final_destination']) && $document['department'] == $document['final_destination'] && $document['status'] != 'finalized') || (hasRole(['admin', 'supervisor']) && ($document['status'] === 'approved' || $document['status'] === 'finalized') && $document['status'] !== 'done')): ?>
        <div class="mt-6 flex flex-wrap gap-4">
            <?php if ($canMove): ?>
            <a href="move.php?id=<?= $documentId; ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-exchange-alt mr-2"></i> Move Document
            </a>
            <?php endif; ?>
            
            <?php if ($canApprove): ?>
            <a href="approve.php?id=<?= $documentId; ?>&action=approve" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                <i class="fas fa-check mr-2"></i> Approve Document
            </a>
            
            <a href="approve.php?id=<?= $documentId; ?>&action=reject" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                <i class="fas fa-times mr-2"></i> Reject Document
            </a>
            <?php endif; ?>
            
            <?php if (hasRole('admin') && isset($document['final_destination']) && $document['department'] == $document['final_destination'] && $document['status'] != 'finalized'): ?>
            <a href="finalize_document.php?id=<?= $documentId; ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                <i class="fas fa-flag-checkered mr-2"></i> Finalize Document
            </a>
            <?php endif; ?>
            
            <?php if (hasRole(['admin', 'supervisor']) && ($document['status'] === 'approved' || $document['status'] === 'finalized') && $document['status'] !== 'done'): ?>
            <button type="button" id="markAsDoneBtn" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <i class="fas fa-check-double mr-2"></i> Mark as Complete
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Mark as Done Form (Hidden by default) -->
        <?php if (hasRole(['admin', 'supervisor']) && ($document['status'] === 'approved' || $document['status'] === 'finalized') && $document['status'] !== 'done'): ?>
        <div id="markAsDoneForm" class="hidden bg-white shadow rounded-lg p-4 mt-4">
            <h4 class="text-lg font-medium text-gray-900 mb-4">Mark Document as Complete</h4>
            <p class="text-sm text-gray-600 mb-4">
                This will mark the document as COMPLETE, indicating it has reached its final stage and is ready for collection or download.
            </p>
            <form action="finalize_document.php" method="POST">
                <input type="hidden" name="document_id" value="<?= $documentId; ?>">
                
                <div class="mb-4">
                    <label for="comment" class="block text-sm font-medium text-gray-700 mb-1">Note (Optional)</label>
                    <textarea id="comment" name="completion_note" rows="3" class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Add any notes about the completed document..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancelMarkAsDone" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i class="fas fa-check-double mr-2"></i> Confirm Completion
                    </button>
                </div>
            </form>
        </div>
        
        <script>
            document.getElementById('markAsDoneBtn').addEventListener('click', function() {
                document.getElementById('markAsDoneForm').classList.remove('hidden');
                this.classList.add('hidden');
            });
            
            document.getElementById('cancelMarkAsDone').addEventListener('click', function() {
                document.getElementById('markAsDoneForm').classList.add('hidden');
                document.getElementById('markAsDoneBtn').classList.remove('hidden');
            });
        </script>
        <?php endif; ?>
        
        <!-- Document Details Card -->
        <div class="bg-white shadow rounded-lg overflow-hidden mt-6">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">Document Information</h3>
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
                        <dt class="text-sm font-medium text-gray-500">Reference Number</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['reference_number'] ?: 'None'); ?></dd>
                    </div>
                    
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Current Department</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                <?= htmlspecialchars($document['department']); ?>
                            </span>
                        </dd>
                    </div>
                    
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Final Destination</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= (isset($document['final_destination']) && $document['department'] == $document['final_destination']) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                <?= htmlspecialchars($document['final_destination'] ?? 'Not specified'); ?>
                                <?php if (isset($document['final_destination']) && $document['department'] == $document['final_destination']): ?>
                                <i class="fas fa-check-circle ml-1"></i>
                                <?php endif; ?>
                            </span>
                            
                            <?php if (hasRole('admin')): ?>
                            <button type="button" id="changeFinalDestBtn" class="ml-2 text-xs text-primary-600 hover:text-primary-800" title="Change Final Destination">
                                <i class="fas fa-edit"></i> Change
                            </button>
                            
                            <div id="changeFinalDestForm" class="hidden mt-2 p-3 bg-gray-50 rounded border border-gray-200">
                                <form action="process_final_destination.php" method="POST" class="flex items-end space-x-2">
                                    <input type="hidden" name="document_id" value="<?= $documentId; ?>">
                                    <div class="flex-grow">
                                        <label for="new_final_destination" class="block text-xs font-medium text-gray-700 mb-1">New Final Destination</label>
                                        <select id="new_final_destination" name="new_final_destination" class="block w-full shadow-sm text-sm border-gray-300 rounded-md">
                                            <?php
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
                                            foreach ($departments as $dept): ?>
                                                <option value="<?= htmlspecialchars($dept); ?>" <?= ($document['final_destination'] == $dept) ? 'selected' : ''; ?>>
                                                    <?= htmlspecialchars($dept); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="px-3 py-2 text-xs font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700">
                                        Update
                                    </button>
                                    <button type="button" id="cancelChangeFinalDest" class="px-3 py-2 text-xs font-medium rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">
                                        Cancel
                                    </button>
                                </form>
                            </div>
                            
                            <script>
                                document.getElementById('changeFinalDestBtn').addEventListener('click', function() {
                                    document.getElementById('changeFinalDestForm').classList.remove('hidden');
                                    this.classList.add('hidden');
                                });
                                
                                document.getElementById('cancelChangeFinalDest').addEventListener('click', function() {
                                    document.getElementById('changeFinalDestForm').classList.add('hidden');
                                    document.getElementById('changeFinalDestBtn').classList.remove('hidden');
                                });
                            </script>
                            <?php endif; ?>
                        </dd>
                    </div>
                    
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Document Type</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['type']); ?></dd>
                    </div>
                    
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <?php
                            $statusClass = [
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'in_movement' => 'bg-blue-100 text-blue-800',
                                'approved' => 'bg-green-100 text-green-800',
                                'rejected' => 'bg-red-100 text-red-800',
                                'finalized' => 'bg-purple-100 text-purple-800',
                                'pending_approval' => 'bg-yellow-100 text-yellow-800',
                                'done' => 'bg-indigo-100 text-indigo-800'
                            ];
                            $statusText = [
                                'pending' => 'Pending',
                                'in_movement' => 'In Movement',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'finalized' => 'Finalized',
                                'pending_approval' => 'Pending Approval',
                                'done' => 'Complete'
                            ];
                            ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusClass[$document['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                <?= $statusText[$document['status']] ?? ucfirst($document['status']); ?>
                            </span>
                            
                            <?php if (isset($document['status']) && $document['status'] === 'finalized' && !empty($document['finalized_at'])): ?>
                            <span class="ml-2 text-xs text-gray-500">
                                <?= date('M d, Y - h:i A', strtotime($document['finalized_at'])); ?>
                            </span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Upload Date</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?= date('M d, Y - h:i A', strtotime($document['created_at'])); ?></dd>
                    </div>
                    
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?= date('M d, Y - h:i A', strtotime($document['updated_at'])); ?></dd>
                    </div>
                    
                    <div class="md:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Uploaded By</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <div class="flex items-center">
                                <div class="h-8 w-8 rounded-full bg-primary-500 flex items-center justify-center text-white">
                                    <?= strtoupper(substr($document['uploader_name'], 0, 1)); ?>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($document['uploader_name']); ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($document['uploader_email']); ?> | <?= htmlspecialchars($document['uploader_department']); ?></p>
                                </div>
                            </div>
                        </dd>
                    </div>
                    
                    <div class="md:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Description</dt>
                        <dd class="mt-1 text-sm text-gray-900 whitespace-pre-line"><?= htmlspecialchars($document['description'] ?: 'No description provided.'); ?></dd>
                    </div>
                    
                    <div class="md:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">File Attachment</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <?php if (!empty($document['file_path'])): ?>
                            <a href="download.php?id=<?= $documentId; ?>" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                <i class="fas fa-download mr-1"></i> Download File
                            </a>
                            <?php if (!empty($document['file_path'])): ?>
                            <span class="ml-2 text-xs text-gray-500"><?= htmlspecialchars(basename($document['file_path'])); ?></span>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="italic text-gray-500">No file attached</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
        
        <!-- Approval History -->
        <?php if (!empty($approvals)): ?>
        <div class="bg-white shadow rounded-lg overflow-hidden mt-6">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">Approval History</h3>
            </div>
            
            <div class="overflow-hidden">
                <ul class="divide-y divide-gray-200">
                    <?php foreach ($approvals as $approval): ?>
                    <li class="p-6">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <div class="h-10 w-10 rounded-full flex items-center justify-center 
                                    <?= $approval['action'] === 'approve' ? 'bg-green-500' : 'bg-red-500'; ?> text-white">
                                    <i class="fas <?= $approval['action'] === 'approve' ? 'fa-check' : 'fa-times'; ?>"></i>
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-medium text-gray-900">
                                    Document was <?= $approval['action'] === 'approve' ? 'approved' : 'rejected'; ?> by 
                                    <span class="font-semibold"><?= htmlspecialchars($approval['user_name']); ?></span>
                                    (<?= htmlspecialchars($approval['user_department']); ?>)
                                </div>
                                <div class="mt-1 text-sm text-gray-500">
                                    <time datetime="<?= $approval['created_at']; ?>">
                                        <?= date('M d, Y - h:i A', strtotime($approval['created_at'])); ?>
                                    </time>
                                </div>
                                <?php if (!empty($approval['comments'])): ?>
                                <div class="mt-2 text-sm text-gray-700 p-3 bg-gray-50 rounded">
                                    <?= htmlspecialchars($approval['comments']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Movement History -->
        <?php if (!empty($history)): ?>
        <div class="bg-white shadow rounded-lg overflow-hidden mt-6">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">Movement History</h3>
            </div>
            
            <div class="overflow-hidden">
                <ul class="divide-y divide-gray-200">
                    <?php foreach ($history as $movement): ?>
                    <li class="p-6">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center text-white">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-medium text-gray-900">
                                    Moved from 
                                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($movement['from_department']); ?>
                                    </span>
                                    to
                                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($movement['to_department']); ?>
                                    </span>
                                    by <?= htmlspecialchars($movement['moved_by_name']); ?>
                                </div>
                                <div class="mt-1 text-sm text-gray-500">
                                    <time datetime="<?= $movement['created_at']; ?>">
                                        <?= date('M d, Y - h:i A', strtotime($movement['created_at'])); ?>
                                    </time>
                                </div>
                                <?php if (!empty($movement['notes'])): ?>
                                <div class="mt-2 text-sm text-gray-700 p-3 bg-gray-50 rounded">
                                    <?= htmlspecialchars($movement['notes']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?> 
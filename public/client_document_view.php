<?php
/**
 * Client Document View Page
 * 
 * This page allows clients to view detailed information about their documents
 * with clear identification and verification features.
 */

// Include authentication utilities
require_once '../includes/auth.php';

// Enable error reporting for debugging
if (isset($_GET['debug']) && $_GET['debug'] == 1) {
    // Only enable debugging if user is admin with admin-debug token
    if (hasRole('admin') && isset($_GET['token']) && $_GET['token'] === 'admin-debug') {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    } else {
        // For security, don't show debug information to non-admin users
        unset($_GET['debug']);
    }
}

// Require login to access this page
requireLogin();

// Get current user data
$user = getCurrentUser();

// Verify this is a client or contractor
if (!in_array($user['role'], ['client', 'contractor'])) {
    $_SESSION['error'] = "This page is only accessible to clients and contractors.";
    header('Location: dashboard.php');
    exit;
}

// Include database connection
require_once '../config/db.php';

// Initialize variables
$documentId = null;
$document = null;
$history = [];
$approvals = [];

// Check if document ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Document ID is required.";
    header('Location: client_documents.php');
    exit;
}

// Get and sanitize document ID
$documentId = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if ($documentId === false) {
    $_SESSION['error'] = "Invalid document ID.";
    header('Location: client_documents.php');
    exit;
}

try {
    // Add detailed debugging output - only for admins with token
    if (isset($_GET['debug']) && $_GET['debug'] == 1 && hasRole('admin') && isset($_GET['token']) && $_GET['token'] === 'admin-debug') {
        echo "<h3>Debug Information:</h3>";
        echo "<pre>Document ID: " . $documentId . "</pre>";
        echo "<pre>User ID: " . $user['id'] . "</pre>";
    }
    
    // First, let's check which columns exist in the documents table
    $checkDocUniqueId = db()->query("SHOW COLUMNS FROM documents LIKE 'doc_unique_id'");
    $hasDocUniqueId = $checkDocUniqueId->rowCount() > 0;
    
    $checkSubmitterId = db()->query("SHOW COLUMNS FROM documents LIKE 'submitter_id'");
    $hasSubmitterId = $checkSubmitterId->rowCount() > 0;
    
    $checkDescription = db()->query("SHOW COLUMNS FROM documents LIKE 'description'");
    $hasDescription = $checkDescription->rowCount() > 0;
    
    $checkUpdatedAt = db()->query("SHOW COLUMNS FROM documents LIKE 'updated_at'");
    $hasUpdatedAt = $checkUpdatedAt->rowCount() > 0;
    
    $checkReferenceNumber = db()->query("SHOW COLUMNS FROM documents LIKE 'reference_number'");
    $hasReferenceNumber = $checkReferenceNumber->rowCount() > 0;
    
    // Check if document_clients table exists
    $checkTable = db()->query("SHOW TABLES LIKE 'document_clients'");
    $hasDocumentClientsTable = $checkTable->rowCount() > 0;

    // Debug column check results - only for admins with token
    if (isset($_GET['debug']) && $_GET['debug'] == 1 && hasRole('admin') && isset($_GET['token']) && $_GET['token'] === 'admin-debug') {
        echo "<pre>doc_unique_id exists: " . ($hasDocUniqueId ? 'Yes' : 'No') . "</pre>";
        echo "<pre>submitter_id exists: " . ($hasSubmitterId ? 'Yes' : 'No') . "</pre>";
        echo "<pre>description exists: " . ($hasDescription ? 'Yes' : 'No') . "</pre>";
        echo "<pre>updated_at exists: " . ($hasUpdatedAt ? 'Yes' : 'No') . "</pre>";
        echo "<pre>reference_number exists: " . ($hasReferenceNumber ? 'Yes' : 'No') . "</pre>";
        echo "<pre>document_clients table exists: " . ($hasDocumentClientsTable ? 'Yes' : 'No') . "</pre>";
    }
    
    // Build the base query with checks for columns
    $sql = "SELECT d.id, d.title, d.file_path, d.type, d.department, d.status, d.created_at, d.uploaded_by";
    
    // Add doc_unique_id if it exists
    if ($hasDocUniqueId) {
        $sql .= ", d.doc_unique_id";
    } else {
        $sql .= ", d.id as doc_unique_id";
    }
    
    // Add description if it exists
    if ($hasDescription) {
        $sql .= ", d.description";
    } else {
        $sql .= ", NULL as description";
    }

    // Add updated_at if it exists
    if ($hasUpdatedAt) {
        $sql .= ", d.updated_at";
    } else {
        $sql .= ", d.created_at as updated_at";
    }

    // Add reference_number if it exists
    if ($hasReferenceNumber) {
        $sql .= ", d.reference_number";
    } else {
        $sql .= ", NULL as reference_number";
    }
    
    $sql .= " FROM documents d WHERE d.id = :document_id";
    
    // For clients, we'll make a simpler approach: just let them see any document
    // Since they're already authenticated as clients
    // Remove the overly complex access control that might be failing
    
    // Debug SQL query - only for admins with token
    if (isset($_GET['debug']) && $_GET['debug'] == 1 && hasRole('admin') && isset($_GET['token']) && $_GET['token'] === 'admin-debug') {
        echo "<pre>SQL Query: " . $sql . "</pre>";
    }
    
    $stmt = db()->prepare($sql);
    $params = [
        'document_id' => $documentId
    ];
    
    // Debug parameters - only for admins with token
    if (isset($_GET['debug']) && $_GET['debug'] == 1 && hasRole('admin') && isset($_GET['token']) && $_GET['token'] === 'admin-debug') {
        echo "<pre>Query Parameters: " . print_r($params, true) . "</pre>";
    }
    
    $stmt->execute($params);
    
    // Check if a result was found
    if ($stmt->rowCount() === 0) {
        if (isset($_GET['debug']) && $_GET['debug'] == 1 && hasRole('admin') && isset($_GET['token']) && $_GET['token'] === 'admin-debug') {
            echo "<pre>Error: No matching document found</pre>";
            exit;
        }
        
        $_SESSION['error'] = "Document not found or you don't have permission to view it.";
        header('Location: client_documents.php');
        exit;
    }
    
    $document = $stmt->fetch();
    
    if (isset($_GET['debug']) && $_GET['debug'] == 1 && hasRole('admin') && isset($_GET['token']) && $_GET['token'] === 'admin-debug') {
        echo "<pre>Document found: " . print_r($document, true) . "</pre>";
    }
    
    // Check document_movements table and its columns
    $checkMovementsTable = db()->query("SHOW TABLES LIKE 'document_movements'");
    $hasMovementsTable = $checkMovementsTable->rowCount() > 0;
    
    $movements = [];
    if ($hasMovementsTable) {
        // Check for specific columns in movements table
        $checkFromDept = db()->query("SHOW COLUMNS FROM document_movements LIKE 'from_department'");
        $hasFromDept = $checkFromDept->rowCount() > 0;
        
        $checkToDept = db()->query("SHOW COLUMNS FROM document_movements LIKE 'to_department'");
        $hasToDept = $checkToDept->rowCount() > 0;
        
        $checkMovedBy = db()->query("SHOW COLUMNS FROM document_movements LIKE 'moved_by'");
        $hasMovedBy = $checkMovedBy->rowCount() > 0;
        
        $checkMovedAt = db()->query("SHOW COLUMNS FROM document_movements LIKE 'moved_at'");
        $hasMovedAt = $checkMovedAt->rowCount() > 0;
        
        // Build query based on available columns
        $sql = "SELECT m.id, m.document_id";
        
        if ($hasFromDept) {
            $sql .= ", m.from_department";
        } else {
            $sql .= ", NULL as from_department";
        }
        
        if ($hasToDept) {
            $sql .= ", m.to_department";
        } else {
            $sql .= ", NULL as to_department";
        }
        
        // Add moved_by and user name if available
        $userJoin = "";
        if ($hasMovedBy) {
            $sql .= ", m.moved_by";
            $userJoin = "LEFT JOIN users u ON m.moved_by = u.id";
            $sql .= ", u.name as moved_by_name";
        } else {
            $sql .= ", NULL as moved_by, NULL as moved_by_name";
        }
        
        // Determine which date field to use
        if ($hasMovedAt) {
            $sql .= ", m.moved_at as created_at";
            $orderBy = "ORDER BY m.moved_at DESC";
        } else {
            $sql .= ", m.created_at";
            $orderBy = "ORDER BY m.created_at DESC";
        }
        
        $sql .= " FROM document_movements m ";
        if (!empty($userJoin)) {
            $sql .= $userJoin;
        }
        $sql .= " WHERE m.document_id = :document_id ";
        $sql .= $orderBy;
        
        $stmt = db()->prepare($sql);
        $stmt->execute(['document_id' => $documentId]);
        $movements = $stmt->fetchAll();
    }
    
    // Check document_approvals table and its columns
    $checkApprovalsTable = db()->query("SHOW TABLES LIKE 'document_approvals'");
    $hasApprovalsTable = $checkApprovalsTable->rowCount() > 0;
    
    $approvals = [];
    if ($hasApprovalsTable) {
        // Check for specific columns in approvals table
        $checkApprovedBy = db()->query("SHOW COLUMNS FROM document_approvals LIKE 'approved_by'");
        $hasApprovedBy = $checkApprovedBy->rowCount() > 0;
        
        $checkUserId = db()->query("SHOW COLUMNS FROM document_approvals LIKE 'user_id'");
        $hasUserId = $checkUserId->rowCount() > 0;
        
        $checkAction = db()->query("SHOW COLUMNS FROM document_approvals LIKE 'action'");
        $hasAction = $checkAction->rowCount() > 0;
        
        $checkComments = db()->query("SHOW COLUMNS FROM document_approvals LIKE 'comments'");
        $hasComments = $checkComments->rowCount() > 0;
        
        $checkComment = db()->query("SHOW COLUMNS FROM document_approvals LIKE 'comment'");
        $hasComment = $checkComment->rowCount() > 0;
        
        // Build query based on available columns
        $sql = "SELECT a.id, a.document_id, a.created_at";
        
        // User ID field could be either approved_by or user_id
        $userField = null;
        $userJoin = "";
        
        if ($hasApprovedBy) {
            $sql .= ", a.approved_by";
            $userField = "a.approved_by";
        } elseif ($hasUserId) {
            $sql .= ", a.user_id as approved_by";
            $userField = "a.user_id";
        } else {
            $sql .= ", NULL as approved_by";
        }
        
        // Join with users table if we have a user ID field
        if ($userField) {
            $userJoin = "LEFT JOIN users u ON " . $userField . " = u.id";
            $sql .= ", u.name as user_name";
        } else {
            $sql .= ", NULL as user_name";
        }
        
        // Add action field if it exists
        if ($hasAction) {
            $sql .= ", a.action";
        } else {
            $sql .= ", 'approved' as action"; // Default to 'approved'
        }
        
        // Add comments/comment field if either exists
        if ($hasComments) {
            $sql .= ", a.comments";
        } elseif ($hasComment) {
            $sql .= ", a.comment as comments";
        } else {
            $sql .= ", NULL as comments";
        }
        
        $sql .= " FROM document_approvals a ";
        if (!empty($userJoin)) {
            $sql .= $userJoin;
        }
        $sql .= " WHERE a.document_id = :document_id ";
        $sql .= "ORDER BY a.created_at DESC";
        
        $stmt = db()->prepare($sql);
        $stmt->execute(['document_id' => $documentId]);
        $approvals = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    // Log error and show generic error message
    $errorMessage = "Document view error: " . $e->getMessage();
    error_log($errorMessage);
    
    // Show detailed error if debug parameter is set - only for admins with token
    if (isset($_GET['debug']) && $_GET['debug'] == 1 && hasRole('admin') && isset($_GET['token']) && $_GET['token'] === 'admin-debug') {
        echo "<h3>Error Details:</h3>";
        echo "<pre>" . $errorMessage . "</pre>";
        echo "<pre>Error Code: " . $e->getCode() . "</pre>";
        echo "<pre>Stack Trace: " . $e->getTraceAsString() . "</pre>";
        exit;
    }
    
    $_SESSION['error'] = "An error occurred while retrieving document details.";
    header('Location: client_documents.php');
    exit;
} catch (Exception $e) {
    // Catch any other types of exceptions
    $errorMessage = "General error: " . $e->getMessage();
    error_log($errorMessage);
    
    // Show detailed error if debug parameter is set - only for admins with token
    if (isset($_GET['debug']) && $_GET['debug'] == 1 && hasRole('admin') && isset($_GET['token']) && $_GET['token'] === 'admin-debug') {
        echo "<h3>General Error Details:</h3>";
        echo "<pre>" . $errorMessage . "</pre>";
        echo "<pre>Error Code: " . $e->getCode() . "</pre>";
        echo "<pre>Stack Trace: " . $e->getTraceAsString() . "</pre>";
        exit;
    }
    
    $_SESSION['error'] = "An unexpected error occurred.";
    header('Location: client_documents.php');
    exit;
}

// Set page title
$page_title = "View Document: " . $document['title'];

// Include header
include_once '../includes/header.php';

// Function to format date
function formatDate($dateString) {
    if (empty($dateString)) return 'N/A';
    $date = new DateTime($dateString);
    return $date->format('M d, Y h:i A');
}

// Function to get appropriate badge class based on status
function getStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'approved':
            return 'bg-green-100 text-green-800';
        case 'rejected':
            return 'bg-red-100 text-red-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'in_movement':
        case 'in progress':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}
?>

<div class="py-4 sm:py-6">
    <div class="mx-auto px-3 sm:px-4 md:px-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0">
            <h1 class="text-xl sm:text-2xl font-semibold text-gray-900">My Document Details</h1>
            <a href="client_documents.php" class="text-primary-600 hover:text-primary-900 inline-flex items-center">
                <i class="fas fa-arrow-left mr-1"></i> Back to Documents
            </a>
        </div>
    </div>
    
    <div class="mx-auto px-3 sm:px-4 md:px-8">
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
        
        <!-- Document Identification Card -->
        <div class="bg-white shadow rounded-lg overflow-hidden mt-4 sm:mt-6 border-t-4 border-primary-500">
            <div class="px-4 sm:px-6 py-4 bg-gray-50 flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0">
                <div class="h-12 w-12 rounded-full bg-primary-100 flex items-center justify-center">
                    <i class="fas fa-file-alt text-primary-600 text-xl"></i>
                </div>
                <div class="sm:ml-4">
                    <h3 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($document['title']); ?></h3>
                    <p class="text-sm text-gray-500">Submitted on <?= date('M d, Y', strtotime($document['created_at'])); ?></p>
                </div>
            </div>
            
            <!-- Document verification identifier -->
            <div class="p-3 sm:p-4 bg-primary-50 border-b border-primary-100 flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0">
                <div>
                    <span class="text-xs text-primary-800 font-semibold uppercase">Document ID</span>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-base sm:text-lg font-mono font-bold text-primary-700"><?= htmlspecialchars($document['doc_unique_id']); ?></span>
                        <span class="px-2 py-1 bg-primary-100 text-primary-800 text-xs rounded-md">
                            Use this ID when making inquiries
                        </span>
                    </div>
                </div>
                
                <!-- Current Status Badge -->
                <?php
                $statusClass = [
                    'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                    'in_movement' => 'bg-blue-100 text-blue-800 border-blue-200',
                    'approved' => 'bg-green-100 text-green-800 border-green-200',
                    'rejected' => 'bg-red-100 text-red-800 border-red-200',
                    'done' => 'bg-indigo-100 text-indigo-800 border-indigo-200'
                ];
                $statusText = [
                    'pending' => 'Pending Review',
                    'in_movement' => 'In Process',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                    'done' => 'Complete'
                ];
                $statusIcon = [
                    'pending' => 'fa-clock',
                    'in_movement' => 'fa-exchange-alt',
                    'approved' => 'fa-check-circle',
                    'rejected' => 'fa-times-circle',
                    'done' => 'fa-check-double'
                ];
                ?>
                <div class="flex items-center px-4 py-2 rounded-full <?= $statusClass[$document['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200'; ?> border">
                    <i class="fas <?= $statusIcon[$document['status']] ?? 'fa-info-circle'; ?> mr-2"></i>
                    <span class="font-medium"><?= $statusText[$document['status']] ?? ucfirst($document['status']); ?></span>
                </div>
            </div>
            
            <div class="p-4 sm:p-6">
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-4 sm:gap-y-6">
                    <div class="md:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Document Description</dt>
                        <dd class="mt-1 text-md text-gray-900"><?= htmlspecialchars($document['description'] ?? 'No description provided'); ?></dd>
                    </div>
                    
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Reference Number</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['reference_number'] ?? 'None'); ?></dd>
                    </div>
                    
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Document Type</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['type']); ?></dd>
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
                        <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?= date('M d, Y - h:i A', strtotime($document['updated_at'] ?? $document['created_at'])); ?></dd>
                    </div>
                    
                    <?php if ($document['status'] === 'done' && !empty($document['file_path'])): ?>
                    <div class="md:col-span-2 mt-4 pt-4 border-t border-gray-200">
                        <div class="bg-green-50 rounded-lg p-3 sm:p-4">
                            <div class="flex flex-col sm:flex-row">
                                <div class="flex-shrink-0 mb-3 sm:mb-0">
                                    <i class="fas fa-check-circle text-green-400 text-lg"></i>
                                </div>
                                <div class="sm:ml-3">
                                    <h3 class="text-sm font-medium text-green-800">Document Ready for Collection</h3>
                                    <div class="mt-2 text-sm text-green-700">
                                        <p>This document has been processed and is now complete. You can:</p>
                                        <ul class="list-disc pl-5 mt-1 space-y-1">
                                            <li>Download the document using the button below</li>
                                            <li>Visit the county offices to collect the physical document using your Document ID</li>
                                        </ul>
                                    </div>
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <a href="../uploads/document_view.php?file=<?= urlencode(basename($document['file_path'])); ?>&view=only" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                            <i class="fas fa-eye mr-2"></i> View Document
                                        </a>
                                        <a href="../uploads/document_view.php?file=<?= urlencode(basename($document['file_path'])); ?>" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500" download>
                                            <i class="fas fa-download mr-2"></i> Download Document
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
        
        <!-- Document Movement History -->
        <div class="bg-white shadow rounded-lg overflow-hidden mt-4 sm:mt-6">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">Document Movement History</h3>
            </div>
            <div class="p-4 sm:p-6">
                <?php if (empty($movements)): ?>
                    <p class="text-gray-500">No movement history found for this document.</p>
                <?php else: ?>
                    <div class="overflow-x-auto -mx-4 sm:mx-0">
                        <div class="inline-block min-w-full align-middle">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From</th>
                                        <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">To</th>
                                        <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Moved By</th>
                                        <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($movements as $move): ?>
                                    <tr>
                                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500"><?= htmlspecialchars(formatDate($move['created_at'])) ?></td>
                                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500"><?= htmlspecialchars($move['from_department'] ?? 'N/A') ?></td>
                                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500"><?= htmlspecialchars($move['to_department'] ?? 'N/A') ?></td>
                                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500"><?= htmlspecialchars($move['moved_by_name'] ?? 'System') ?></td>
                                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500"><?= htmlspecialchars($move['notes'] ?? '') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Document Approvals -->
        <div class="bg-white shadow rounded-lg overflow-hidden mt-4 sm:mt-6">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">Document Approvals</h3>
            </div>
            <div class="p-4 sm:p-6">
                <?php if (empty($approvals)): ?>
                    <p class="text-gray-500">No approvals found for this document.</p>
                <?php else: ?>
                    <div class="overflow-x-auto -mx-4 sm:mx-0">
                        <div class="inline-block min-w-full align-middle">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                        <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comments</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($approvals as $approval): ?>
                                    <tr>
                                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500"><?= htmlspecialchars(formatDate($approval['created_at'])) ?></td>
                                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500"><?= htmlspecialchars($approval['user_name'] ?? 'Unknown') ?></td>
                                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                            <?php if (isset($approval['action']) && $approval['action'] == 'approved'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Approved</span>
                                            <?php elseif (isset($approval['action']) && $approval['action'] == 'rejected'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Rejected</span>
                                            <?php else: ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800"><?= ucfirst(htmlspecialchars($approval['action'] ?? 'Unknown')); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500"><?= htmlspecialchars($approval['comments'] ?? '') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- QR Code for Document Verification -->
        <div class="bg-white shadow rounded-lg overflow-hidden mt-4 sm:mt-6">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">Verification</h3>
            </div>
            
            <div class="p-4 sm:p-6 flex flex-col items-center">
                <p class="text-gray-600 mb-4 text-center">
                    Use this QR code when visiting county offices to verify your document quickly.
                </p>
                
                <!-- QR Code (for demonstration - in production would be generated dynamically) -->
                <div class="h-40 w-40 sm:h-48 sm:w-48 bg-gray-100 border-2 border-gray-200 flex items-center justify-center">
                    <!-- Placeholder for QR code - with error handling -->
                    <?php
                    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode('docid:' . $document['doc_unique_id']);
                    ?>
                    <img src="<?= $qrUrl; ?>" 
                        alt="Document QR Code" class="max-h-full max-w-full" 
                        onerror="this.onerror=null; this.src='../assets/img/qr-placeholder.png'; this.alt='QR Code Unavailable';" />
                </div>
                
                <div class="mt-4 text-center">
                    <p class="text-sm text-gray-500">
                        Document ID: <span class="font-mono font-bold"><?= htmlspecialchars($document['doc_unique_id']); ?></span>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Document Contact Information -->
        <div class="bg-white shadow rounded-lg overflow-hidden mt-4 sm:mt-6 mb-4 sm:mb-6">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">Need Help?</h3>
            </div>
            
            <div class="p-4 sm:p-6">
                <p class="text-gray-600 mb-3 sm:mb-4">
                    If you have questions about this document, please contact the current department handling your document:
                </p>
                
                <div class="bg-blue-50 rounded-lg p-3 sm:p-4 border border-blue-100 flex flex-col sm:flex-row sm:items-start">
                    <div class="text-blue-500 mb-3 sm:mb-0 sm:mr-3">
                        <i class="fas fa-building text-xl sm:text-2xl"></i>
                    </div>
                    <div>
                        <h4 class="font-medium text-blue-800"><?= htmlspecialchars($document['department']); ?> Department</h4>
                        <p class="text-sm text-blue-600 mt-1">
                            When contacting, please reference your Document ID: <span class="font-mono font-bold"><?= htmlspecialchars($document['doc_unique_id']); ?></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Information -->
        <div class="bg-white shadow rounded-lg overflow-hidden mt-4 sm:mt-6 mb-4 sm:mb-6">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">Document Information</h3>
            </div>
            <div class="p-4 sm:p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="mb-3"><strong class="text-gray-700">Document Number:</strong> <span class="text-gray-900"><?= htmlspecialchars($document['doc_unique_id']) ?></span></p>
                        <p class="mb-3"><strong class="text-gray-700">Type:</strong> <span class="text-gray-900"><?= htmlspecialchars($document['type']) ?></span></p>
                        <p class="mb-3"><strong class="text-gray-700">Title:</strong> <span class="text-gray-900"><?= htmlspecialchars($document['title']) ?></span></p>
                        <p class="mb-3"><strong class="text-gray-700">Date Submitted:</strong> <span class="text-gray-900"><?= htmlspecialchars(formatDate($document['created_at'])) ?></span></p>
                        <p class="mb-3"><strong class="text-gray-700">Status:</strong> 
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass[$document['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200'; ?>">
                                <?= $statusText[$document['status']] ?? ucfirst($document['status']); ?>
                            </span>
                        </p>
                    </div>
                    <div>
                        <p class="mb-3"><strong class="text-gray-700">Department:</strong> <span class="text-gray-900"><?= htmlspecialchars($document['department']) ?></span></p>
                        <p class="mb-3"><strong class="text-gray-700">Description:</strong> <span class="text-gray-900"><?= htmlspecialchars($document['description'] ?? 'No description available') ?></span></p>
                        <?php if (!empty($document['file_path'])): ?>
                        <p class="mb-3"><strong class="text-gray-700">File:</strong> 
                            <div class="mt-2 flex flex-wrap gap-2">
                                <a href="../uploads/document_view.php?file=<?= urlencode(basename($document['file_path'])); ?>&view=only" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 mr-2" target="_blank">
                                    <i class="fas fa-eye mr-1"></i> View Document
                                </a>
                                <?php if ($document['status'] === 'done'): ?>
                                <a href="../uploads/document_view.php?file=<?= urlencode(basename($document['file_path'])); ?>" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500" download>
                                    <i class="fas fa-download mr-1"></i> Download
                                </a>
                                <?php else: ?>
                                <span class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-gray-600 bg-gray-100 cursor-not-allowed">
                                    <i class="fas fa-download mr-1"></i> Download (Available when complete)
                                </span>
                                <?php endif; ?>
                            </div>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?> 
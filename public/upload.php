<?php
/**
 * Document Upload Page
 * 
 * This page allows users to upload new PDF documents to the system.
 */

// Include authentication utilities
require_once '../includes/auth.php';

// Require login to access this page
requireLogin();

// Only clerks, admins, and department management can upload
if (!hasRole(['admin', 'clerk', 'assistant_manager', 'senior_manager'])) {
    $_SESSION['error'] = "You don't have permission to upload documents.";
    header('Location: dashboard.php');
    exit;
}

// Get current user data
$user = getCurrentUser();

// Include database connection
require_once '../config/db.php';

// Get departments for dropdown
try {
    $sql = "SELECT DISTINCT department FROM users ORDER BY department";
    $stmt = db()->prepare($sql);
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching departments: " . $e->getMessage());
    $departments = [];
}

// Get contractors for dropdown (only for admin and clerk)
$contractors = [];
if (hasRole(['admin', 'clerk'])) {
    try {
        $sql = "SELECT id, name, email, role FROM users WHERE role IN ('contractor', 'client') ORDER BY role, name";
        $stmt = db()->prepare($sql);
        $stmt->execute();
        $contractors = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching contractors: " . $e->getMessage());
    }
}

// Get document trails for dropdown
$documentTrails = [];
try {
    $sql = "SELECT dt.id, dt.name, dt.description, COUNT(dts.id) AS step_count
            FROM document_trails dt
            LEFT JOIN document_trail_steps dts ON dt.id = dts.trail_id
            GROUP BY dt.id, dt.name
            ORDER BY dt.name";
    $stmt = db()->prepare($sql);
    $stmt->execute();
    $documentTrails = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching document trails: " . $e->getMessage());
}

// Document types
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

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Set page title
$page_title = "Upload Document";

// Include header
include_once '../includes/header.php';
?>

<div class="py-6">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 md:px-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Upload Document</h1>
                <p class="mt-1 text-sm text-gray-600">Upload a new PDF document for tracking and approval</p>
            </div>
            <div>
                <?php if (hasRole(['admin', 'clerk'])): ?>
                <a href="document_trails.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-route mr-2"></i> Manage Document Trails
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="max-w-5xl mx-auto mt-6 px-4 sm:px-6 md:px-8">
        <?php if (isset($_SESSION['success'])): ?>
        <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700">
                        <?= $_SESSION['success']; ?>
                    </p>
                    <?php
                    // Display QR code if available for last uploaded document
                    if (isset($_SESSION['last_uploaded_doc_id'])) {
                        require_once '../config/db.php';
                        $stmt = db()->prepare("SELECT qr_code_path FROM documents WHERE id = :id");
                        $stmt->execute(['id' => $_SESSION['last_uploaded_doc_id']]);
                        $qr = $stmt->fetchColumn();
                        if ($qr) {
                            echo '<div class="mt-2"><span class="font-semibold">Document QR Code:</span><br><img src="../' . htmlspecialchars($qr) . '" alt="Document QR Code" style="width:180px;height:180px;border:1px solid #ccc;padding:4px;background:#fff;"></div>';
                        }
                        unset($_SESSION['last_uploaded_doc_id']);
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4">
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
        
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    Document Information
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    Fill in the details below to upload a new document
                </p>
            </div>
            
            <div class="px-4 py-5 sm:p-6">
                <form action="../process/upload_process.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    
                    <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                        <div class="sm:col-span-3">
                            <label for="title" class="block text-sm font-medium text-gray-700">
                                Document Title *
                            </label>
                            <div class="mt-1">
                                <input type="text" name="title" id="title" 
                                       class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                       required>
                            </div>
                        </div>
                        
                        <div class="sm:col-span-3">
                            <label for="type" class="block text-sm font-medium text-gray-700">
                                Document Type *
                            </label>
                            <div class="mt-1">
                                <select id="type" name="type" 
                                        class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                        required>
                                    <option value="">Select Type</option>
                                    <?php foreach ($documentTypes as $type): ?>
                                    <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Contractor Selection (for clerks and admins) -->
                        <?php if (hasRole(['admin', 'clerk'])): ?>
                        <div class="sm:col-span-3">
                            <label for="submitter_id" class="block text-sm font-medium text-gray-700">
                                Contractor / Client
                            </label>
                            <div class="mt-1">
                                <select id="submitter_id" name="submitter_id" 
                                        class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                    <option value="">Select Contractor or Client</option>
                                    <?php foreach ($contractors as $contractor): ?>
                                    <option value="<?= $contractor['id'] ?>">
                                        <?= htmlspecialchars($contractor['name']) ?> (<?= ucfirst($contractor['role']) ?> - <?= htmlspecialchars($contractor['email']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="mt-1 text-xs text-gray-500">
                                    Select the contractor or client this document belongs to. A QR code will be generated for tracking.
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Document Trail Selection -->
                        <div class="sm:col-span-3">
                            <label for="trail_id" class="block text-sm font-medium text-gray-700">
                                Document Trail
                            </label>
                            <div class="mt-1">
                                <select id="trail_id" name="trail_id" 
                                        class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                    <option value="">No Predefined Trail</option>
                                    <?php foreach ($documentTrails as $trail): ?>
                                    <option value="<?= $trail['id'] ?>">
                                        <?= htmlspecialchars($trail['name']) ?> (<?= $trail['step_count'] ?> steps)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="mt-1 text-xs text-gray-500">
                                    Optional: Select a predefined path for this document to follow through departments.
                                </p>
                            </div>
                        </div>
                        
                        <div class="sm:col-span-3">
                            <label for="department" class="block text-sm font-medium text-gray-700">
                                Initial Department *
                            </label>
                            <div class="mt-1">
                                <select id="department" name="department" 
                                        class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                        required>
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept) ?>" 
                                            <?= $dept === $user['department'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="sm:col-span-3">
                            <label for="final_destination" class="block text-sm font-medium text-gray-700">
                                Final Destination Department *
                            </label>
                            <div class="mt-1">
                                <select id="final_destination" name="final_destination" 
                                        class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                        required>
                                    <option value="">Select Final Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept) ?>">
                                        <?= htmlspecialchars($dept) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="sm:col-span-6">
                            <label for="notes" class="block text-sm font-medium text-gray-700">
                                Notes (Optional)
                            </label>
                            <div class="mt-1">
                                <textarea id="notes" name="notes" rows="3"
                                          class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md"></textarea>
                            </div>
                        </div>
                        
                        <div class="sm:col-span-6">
                            <label class="block text-sm font-medium text-gray-700">
                                Upload Document * (PDF only, max 10MB)
                            </label>
                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                <div class="space-y-1 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <div class="flex text-sm text-gray-600">
                                        <label for="document_file" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                            <span>Upload a file</span>
                                            <input id="document_file" name="document_file" type="file" class="sr-only" required accept="application/pdf">
                                        </label>
                                        <p class="pl-1">or drag and drop</p>
                                    </div>
                                    <p class="text-xs text-gray-500">
                                        PDF up to 10MB
                                    </p>
                                </div>
                            </div>
                            <div class="mt-2">
                                <div id="file-name" class="text-sm text-gray-500"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-8 flex justify-end">
                        <a href="dashboard.php" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancel
                        </a>
                        <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Upload Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Display selected file name
    document.getElementById('document_file').addEventListener('change', function(e) {
        const fileName = e.target.files[0]?.name || 'No file selected';
        document.getElementById('file-name').textContent = fileName;
    });
    
    // Update final destination based on document trail selection
    document.getElementById('trail_id').addEventListener('change', function() {
        const trailId = this.value;
        if (!trailId) return; // No trail selected
        
        // Get the final step of the selected trail using AJAX
        const xhr = new XMLHttpRequest();
        xhr.open('GET', `../includes/ajax/get_trail_final_step.php?trail_id=${trailId}`, true);
        xhr.onload = function() {
            if (this.status === 200) {
                try {
                    const response = JSON.parse(this.responseText);
                    if (response.success && response.department) {
                        const finalDestinationSelect = document.getElementById('final_destination');
                        
                        // Find and select the matching option
                        for (let i = 0; i < finalDestinationSelect.options.length; i++) {
                            if (finalDestinationSelect.options[i].value === response.department) {
                                finalDestinationSelect.selectedIndex = i;
                                break;
                            }
                        }
                    }
                } catch (e) {
                    console.error('Error parsing trail data:', e);
                }
            }
        };
        xhr.send();
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>
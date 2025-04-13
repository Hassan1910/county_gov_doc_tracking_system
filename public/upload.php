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

// Get clients for dropdown (only for admin and clerk)
$clients = [];
if (hasRole(['admin', 'clerk'])) {
    try {
        $sql = "SELECT id, name, email FROM users WHERE role = 'client' ORDER BY name";
        $stmt = db()->prepare($sql);
        $stmt->execute();
        $clients = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching clients: " . $e->getMessage());
    }
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
            <a href="dashboard.php" class="flex items-center text-primary-600 hover:text-primary-800">
                <i class="fas fa-arrow-left mr-2"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>
    </div>
    
    <div class="max-w-5xl mx-auto mt-6 px-4 sm:px-6 md:px-8">
        <div class="bg-white shadow-lg rounded-lg overflow-hidden border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center">
                <i class="fas fa-file-upload text-primary-500 text-xl mr-3"></i>
                <h3 class="text-lg font-semibold text-gray-800">Document Details</h3>
            </div>
            
            <form action="../process/upload_process.php" method="POST" enctype="multipart/form-data" class="p-6" id="uploadForm">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                
                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                    <!-- Document Title -->
                    <div class="sm:col-span-4">
                        <label for="title" class="block text-sm font-medium text-gray-700">Document Title <span class="text-red-500">*</span></label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-heading text-gray-400"></i>
                            </div>
                            <input type="text" name="title" id="title" 
                                class="pl-10 focus:ring-primary-500 focus:border-primary-500 block w-full text-base border-gray-300 rounded-md h-11"
                                required placeholder="Enter document title">
                        </div>
                    </div>
                    
                    <!-- Document Type -->
                    <div class="sm:col-span-3">
                        <label for="type" class="block text-sm font-medium text-gray-700">Document Type <span class="text-red-500">*</span></label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-file-alt text-gray-400"></i>
                            </div>
                            <select id="type" name="type" 
                                class="pl-10 focus:ring-primary-500 focus:border-primary-500 block w-full text-base border-gray-300 rounded-md h-11"
                                required>
                                <option value="">Select Document Type</option>
                                <?php foreach ($documentTypes as $type): ?>
                                <option value="<?= htmlspecialchars($type); ?>"><?= htmlspecialchars($type); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <i class="fas fa-chevron-down text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Department -->
                    <div class="sm:col-span-3">
                        <label for="department" class="block text-sm font-medium text-gray-700">Department <span class="text-red-500">*</span></label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-building text-gray-400"></i>
                            </div>
                            <select id="department" name="department" 
                                class="pl-10 focus:ring-primary-500 focus:border-primary-500 block w-full text-base border-gray-300 rounded-md h-11"
                                required>
                                <option value="">Select Department</option>
                                <!-- Common departments -->
                                <option value="IT" <?= ($user['department'] === 'IT') ? 'selected' : ''; ?>>IT</option>
                                <option value="Finance" <?= ($user['department'] === 'Finance') ? 'selected' : ''; ?>>Finance</option>
                                <option value="HR" <?= ($user['department'] === 'HR') ? 'selected' : ''; ?>>HR</option>
                                <option value="Legal" <?= ($user['department'] === 'Legal') ? 'selected' : ''; ?>>Legal</option>
                                <option value="Operations" <?= ($user['department'] === 'Operations') ? 'selected' : ''; ?>>Operations</option>
                                <option value="Procurement" <?= ($user['department'] === 'Procurement') ? 'selected' : ''; ?>>Procurement</option>
                                <option value="Administration" <?= ($user['department'] === 'Administration') ? 'selected' : ''; ?>>Administration</option>
                                <!-- Add any custom departments from database that aren't in the list above -->
                                <?php 
                                $standardDepts = ['IT', 'Finance', 'HR', 'Legal', 'Operations', 'Procurement', 'Administration'];
                                foreach ($departments as $dept): 
                                    if (!in_array($dept, $standardDepts)):
                                ?>
                                <option value="<?= htmlspecialchars($dept); ?>" <?= ($dept === $user['department']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($dept); ?>
                                </option>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </select>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <i class="fas fa-chevron-down text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Final Destination -->
                    <div class="sm:col-span-3">
                        <label for="final_destination" class="block text-sm font-medium text-gray-700">Final Destination <span class="text-red-500">*</span></label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-flag-checkered text-gray-400"></i>
                            </div>
                            <select id="final_destination" name="final_destination" 
                                class="pl-10 focus:ring-primary-500 focus:border-primary-500 block w-full text-base border-gray-300 rounded-md h-11"
                                required>
                                <option value="">Select Final Destination</option>
                                <!-- Common departments -->
                                <option value="IT">IT</option>
                                <option value="Finance">Finance</option>
                                <option value="HR">HR</option>
                                <option value="Legal">Legal</option>
                                <option value="Operations">Operations</option>
                                <option value="Procurement">Procurement</option>
                                <option value="Administration">Administration</option>
                                <!-- Add any custom departments from database that aren't in the list above -->
                                <?php 
                                $standardDepts = ['IT', 'Finance', 'HR', 'Legal', 'Operations', 'Procurement', 'Administration'];
                                foreach ($departments as $dept): 
                                    if (!in_array($dept, $standardDepts)):
                                ?>
                                <option value="<?= htmlspecialchars($dept); ?>">
                                    <?= htmlspecialchars($dept); ?>
                                </option>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </select>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <i class="fas fa-chevron-down text-gray-400"></i>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            The final department where this document will be processed/approved
                        </p>
                    </div>
                    
                    <!-- Client Selector (only for admin/clerk) -->
                    <?php if (hasRole(['admin', 'clerk']) && !empty($clients)): ?>
                    <div class="sm:col-span-3">
                        <label for="submitter_id" class="block text-sm font-medium text-gray-700">Client Submitter</label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <select id="submitter_id" name="submitter_id" 
                                class="pl-10 focus:ring-primary-500 focus:border-primary-500 block w-full text-base border-gray-300 rounded-md h-11">
                                <option value="">Select Client (if applicable)</option>
                                <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id']; ?>">
                                    <?= htmlspecialchars($client['name']); ?> (<?= htmlspecialchars($client['email']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <i class="fas fa-chevron-down text-gray-400"></i>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            Select a client if you're uploading on their behalf
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Notes / Description -->
                    <div class="sm:col-span-6">
                        <label for="notes" class="block text-sm font-medium text-gray-700">Notes / Description</label>
                        <div class="mt-1">
                            <textarea id="notes" name="notes" rows="4" 
                                class="focus:ring-primary-500 focus:border-primary-500 block w-full text-base border-gray-300 rounded-md"
                                placeholder="Add any additional notes or context about this document..."></textarea>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            Brief description about the document (optional)
                        </p>
                    </div>
                    
                    <!-- File Upload -->
                    <div class="sm:col-span-6">
                        <label for="document_file" class="block text-sm font-medium text-gray-700">Upload PDF Document <span class="text-red-500">*</span></label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:bg-gray-50 transition-colors duration-200" id="dropZone">
                            <div class="space-y-1 text-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                <div class="flex text-sm text-gray-600">
                                    <label for="document_file" class="relative cursor-pointer bg-white rounded-md font-medium text-primary-600 hover:text-primary-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-primary-500">
                                        <span>Upload a file</span>
                                        <input id="document_file" name="document_file" type="file" class="sr-only" accept=".pdf" required>
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500">
                                    PDF file only (MAX. 10MB)
                                </p>
                            </div>
                        </div>
                        <div id="filePreview" class="mt-3 hidden p-3 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="flex items-center">
                                <i class="fas fa-file-pdf text-primary-500 text-xl mr-3"></i>
                                <div class="flex-1">
                                    <div id="fileName" class="text-sm font-medium text-gray-900"></div>
                                    <div id="fileSize" class="text-xs text-gray-500"></div>
                                </div>
                                <button type="button" id="removeFile" class="text-gray-400 hover:text-red-500">
                                    <i class="fas fa-times-circle"></i>
                                </button>
                            </div>
                        </div>
                        <div id="fileError" class="mt-2 text-sm text-red-600 hidden"></div>
                    </div>
                </div>
                
                <div class="mt-8 pt-5 border-t border-gray-200">
                    <div class="flex justify-end">
                        <span id="uploadStatus" class="mr-4 text-sm text-gray-500 hidden">
                            <i class="fas fa-spinner fa-spin"></i> Uploading document...
                        </span>
                        <a href="dashboard.php" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 mr-3">
                            <i class="fas fa-times mr-2"></i> Cancel
                        </a>
                        <button type="submit" id="submitButton" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            <i class="fas fa-upload mr-2"></i> Upload Document
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Tips & Info Box -->
        <div class="mt-6 bg-blue-50 rounded-lg p-4 border border-blue-200">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-blue-400 text-lg"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Tips for document uploads</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <ul class="list-disc pl-5 space-y-1">
                            <li>Use descriptive titles to make documents easier to find</li>
                            <li>Select the correct department to ensure proper routing</li>
                            <li>PDF files must be under 10MB in size</li>
                            <li>Add notes to provide context about the document's purpose</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Enhanced File Upload -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('document_file');
        const filePreview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const removeFile = document.getElementById('removeFile');
        const fileError = document.getElementById('fileError');
        const uploadForm = document.getElementById('uploadForm');
        const submitButton = document.getElementById('submitButton');
        const uploadStatus = document.getElementById('uploadStatus');
        
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        // Highlight drop area when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });
        
        // Handle dropped files
        dropZone.addEventListener('drop', handleDrop, false);
        
        // Handle selected files from input
        fileInput.addEventListener('change', handleFiles, false);
        
        // Handle remove file button
        removeFile.addEventListener('click', function() {
            fileInput.value = '';
            filePreview.classList.add('hidden');
            dropZone.classList.remove('hidden');
            fileError.classList.add('hidden');
        });
        
        // Handle form submission
        uploadForm.addEventListener('submit', function(e) {
            // Show upload status and disable button
            uploadStatus.classList.remove('hidden');
            submitButton.disabled = true;
            submitButton.classList.add('opacity-75');
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Uploading...';
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        function highlight() {
            dropZone.classList.add('bg-gray-100');
            dropZone.classList.add('border-primary-300');
        }
        
        function unhighlight() {
            dropZone.classList.remove('bg-gray-100');
            dropZone.classList.remove('border-primary-300');
        }
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles({ target: { files: files } });
        }
        
        function handleFiles(e) {
            let files = e.target.files;
            if (files && files[0]) {
                const file = files[0];
                
                // Validate file type
                if (!file.type.match('application/pdf')) {
                    fileError.textContent = '⚠️ Error: Only PDF files are allowed.';
                    fileError.classList.remove('hidden');
                    fileInput.value = '';
                    return;
                }
                
                // Validate file size
                if (file.size > 10 * 1024 * 1024) {
                    fileError.textContent = '⚠️ Error: File size exceeds the 10MB limit.';
                    fileError.classList.remove('hidden');
                    fileInput.value = '';
                    return;
                }
                
                // Display file information
                fileName.textContent = file.name;
                fileSize.textContent = `Size: ${(file.size / (1024 * 1024)).toFixed(2)} MB`;
                
                // Show preview, hide drop zone
                filePreview.classList.remove('hidden');
                dropZone.classList.add('hidden');
                fileError.classList.add('hidden');
            }
        }
    });
</script>

<?php include_once '../includes/footer.php'; ?> 
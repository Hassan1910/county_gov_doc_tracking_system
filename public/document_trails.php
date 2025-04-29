<?php
/**
 * Document Trails Management
 * 
 * This page allows clerks to define document trails (predefined paths) that documents will follow.
 */

// Include authentication utilities
require_once '../includes/auth.php';

// Require login to access this page
requireLogin();

// Only clerks and admins can access this page
if (!hasRole(['clerk', 'admin'])) {
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
$trails = [];
$trailToEdit = null;
$departments = [];
$managerTypes = ['assistant_manager', 'senior_manager'];

// Get all departments for dropdown
try {
    // Common standard departments
    $deptList = [
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
    $departments = array_merge($deptList, $dbDepartments);
    sort($departments);
} catch (PDOException $e) {
    error_log("Error fetching departments: " . $e->getMessage());
}

// Check for edit request
$trailId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
if ($trailId > 0) {
    try {
        // Fetch trail details
        $sql = "SELECT * FROM document_trails WHERE id = :id";
        $stmt = db()->prepare($sql);
        $stmt->execute(['id' => $trailId]);
        $trailToEdit = $stmt->fetch();
        
        if ($trailToEdit) {
            // Fetch trail steps
            $sql = "SELECT * FROM document_trail_steps WHERE trail_id = :trail_id ORDER BY step_order";
            $stmt = db()->prepare($sql);
            $stmt->execute(['trail_id' => $trailId]);
            $trailToEdit['steps'] = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Error fetching trail details: " . $e->getMessage());
        $errors[] = "Could not load trail details. Please try again.";
    }
}

// Process form submission for creating/editing trails
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Form validation
    if ($_POST['action'] === 'create_trail' || $_POST['action'] === 'update_trail') {
        $name = trim($_POST['trail_name']);
        $description = trim($_POST['trail_description']);
        
        if (empty($name)) {
            $errors[] = "Trail name is required.";
        }
        
        $steps = isset($_POST['steps']) ? $_POST['steps'] : [];
        if (count($steps) < 1) {
            $errors[] = "At least one step is required for the trail.";
        }
        
        // Process if no errors
        if (empty($errors)) {
            try {
                db()->beginTransaction();
                
                if ($_POST['action'] === 'create_trail') {
                    // Insert new trail
                    $sql = "INSERT INTO document_trails (name, description, created_by) 
                            VALUES (:name, :description, :created_by)";
                    $stmt = db()->prepare($sql);
                    $stmt->execute([
                        'name' => $name,
                        'description' => $description,
                        'created_by' => $user['id']
                    ]);
                    
                    $trailId = db()->lastInsertId();
                    
                } else {
                    // Update existing trail
                    $trailId = (int)$_POST['trail_id'];
                    
                    $sql = "UPDATE document_trails SET name = :name, description = :description 
                            WHERE id = :id";
                    $stmt = db()->prepare($sql);
                    $stmt->execute([
                        'name' => $name,
                        'description' => $description,
                        'id' => $trailId
                    ]);
                    
                    // Delete existing steps
                    $sql = "DELETE FROM document_trail_steps WHERE trail_id = :trail_id";
                    $stmt = db()->prepare($sql);
                    $stmt->execute(['trail_id' => $trailId]);
                }
                
                // Insert steps
                $stepOrder = 1;
                foreach ($steps as $step) {
                    $sql = "INSERT INTO document_trail_steps (trail_id, step_order, department, requires_approval) 
                            VALUES (:trail_id, :step_order, :department, :requires_approval)";
                    $stmt = db()->prepare($sql);
                    $stmt->execute([
                        'trail_id' => $trailId,
                        'step_order' => $stepOrder,
                        'department' => $step['department'],
                        'requires_approval' => isset($step['requires_approval']) ? 1 : 0
                    ]);
                    $stepOrder++;
                }
                
                db()->commit();
                
                $success = "Document trail " . ($_POST['action'] === 'create_trail' ? 'created' : 'updated') . " successfully!";
                
                // Redirect to avoid resubmission
                header('Location: document_trails.php?success=' . urlencode($success));
                exit;
                
            } catch (PDOException $e) {
                db()->rollBack();
                error_log("Error saving document trail: " . $e->getMessage());
                $errors[] = "An error occurred while saving the document trail. Please try again.";
            }
        }
    }
    
    // Process delete request
    if ($_POST['action'] === 'delete_trail' && isset($_POST['trail_id'])) {
        $trailId = (int)$_POST['trail_id'];
        
        try {
            // Check if trail is being used by any documents
            $sql = "SELECT COUNT(*) as count FROM documents WHERE trail_id = :trail_id";
            $stmt = db()->prepare($sql);
            $stmt->execute(['trail_id' => $trailId]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                $errors[] = "This trail cannot be deleted because it is being used by {$result['count']} document(s).";
            } else {
                db()->beginTransaction();
                
                // Delete steps first
                $sql = "DELETE FROM document_trail_steps WHERE trail_id = :trail_id";
                $stmt = db()->prepare($sql);
                $stmt->execute(['trail_id' => $trailId]);
                
                // Delete trail
                $sql = "DELETE FROM document_trails WHERE id = :id";
                $stmt = db()->prepare($sql);
                $stmt->execute(['id' => $trailId]);
                
                db()->commit();
                
                $success = "Document trail deleted successfully!";
                header('Location: document_trails.php?success=' . urlencode($success));
                exit;
            }
        } catch (PDOException $e) {
            db()->rollBack();
            error_log("Error deleting document trail: " . $e->getMessage());
            $errors[] = "An error occurred while deleting the document trail. Please try again.";
        }
    }
}

// Get all document trails
try {
    $sql = "SELECT dt.*, u.name as created_by_name, 
                  (SELECT COUNT(*) FROM document_trail_steps WHERE trail_id = dt.id) as step_count
           FROM document_trails dt
           JOIN users u ON dt.created_by = u.id
           ORDER BY dt.created_at DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute();
    $trails = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching document trails: " . $e->getMessage());
    $errors[] = "Could not load document trails. Please try again.";
}

// Set page title
$page_title = "Document Trails Management";

// Include header
include_once '../includes/header.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
        <h1 class="text-2xl font-semibold text-gray-900">Document Trails Management</h1>
        <p class="mt-1 text-sm text-gray-600">Define predefined document movement paths</p>
    </div>

    <div class="max-w-7xl mx-auto mt-6 px-4 sm:px-6 md:px-8">
        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-400"></i>
                    </div>
                    <div class="ml-3">
                        <?php foreach ($errors as $error): ?>
                            <p class="text-sm text-red-700"><?= $error ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['success'])): ?>
            <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700"><?= htmlspecialchars($_GET['success']) ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Create/Edit Document Trail Form -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    <?= $trailToEdit ? 'Edit Document Trail' : 'Create New Document Trail' ?>
                </h3>
                <p class="mt-1 text-sm text-gray-600">
                    Define the path that documents will follow through departments
                </p>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <form method="POST" id="trailForm">
                    <input type="hidden" name="action" value="<?= $trailToEdit ? 'update_trail' : 'create_trail' ?>">
                    <?php if ($trailToEdit): ?>
                        <input type="hidden" name="trail_id" value="<?= $trailToEdit['id'] ?>">
                    <?php endif; ?>

                    <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                        <div class="sm:col-span-3">
                            <label for="trail_name" class="block text-sm font-medium text-gray-700">Trail Name</label>
                            <div class="mt-1">
                                <input type="text" name="trail_name" id="trail_name" 
                                       class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                       value="<?= $trailToEdit ? htmlspecialchars($trailToEdit['name']) : '' ?>" required>
                            </div>
                        </div>

                        <div class="sm:col-span-6">
                            <label for="trail_description" class="block text-sm font-medium text-gray-700">Description (Optional)</label>
                            <div class="mt-1">
                                <textarea name="trail_description" id="trail_description" rows="2" 
                                          class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                ><?= $trailToEdit ? htmlspecialchars($trailToEdit['description']) : '' ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h4 class="text-md font-medium text-gray-900">Trail Steps</h4>
                        <p class="text-sm text-gray-500">Define the sequence of departments that documents will move through</p>

                        <div id="steps-container" class="mt-4 space-y-4">
                            <?php 
                            $initialSteps = $trailToEdit && isset($trailToEdit['steps']) ? $trailToEdit['steps'] : [['department' => '', 'requires_approval' => true]];
                            foreach ($initialSteps as $index => $step):
                            ?>
                                <div class="step-row flex items-center gap-4 p-3 border border-gray-200 rounded-md bg-gray-50">
                                    <div class="step-number bg-indigo-100 text-indigo-700 w-7 h-7 rounded-full flex items-center justify-center font-medium">
                                        <?= ($index + 1) ?>
                                    </div>
                                    <div class="flex-1 grid grid-cols-12 gap-4">
                                        <div class="col-span-8">
                                            <label class="sr-only">Department</label>
                                            <select name="steps[<?= $index ?>][department]" required
                                                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                                <option value="">Select Department</option>
                                                <?php foreach ($departments as $dept): ?>
                                                    <option value="<?= htmlspecialchars($dept) ?>" 
                                                        <?= (isset($step['department']) && $step['department'] == $dept) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($dept) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-span-4 flex items-center">
                                            <input type="checkbox" name="steps[<?= $index ?>][requires_approval]"
                                                   id="approval-<?= $index ?>"
                                                   class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                                   <?= (!isset($step['requires_approval']) || $step['requires_approval'] ? 'checked' : '') ?>>
                                            <label for="approval-<?= $index ?>" class="ml-2 block text-sm text-gray-700">
                                                Requires Approval
                                            </label>
                                        </div>
                                    </div>
                                    <?php if ($index > 0 || count($initialSteps) > 1): ?>
                                        <button type="button" class="remove-step text-red-500 hover:text-red-700">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" id="add-step" class="mt-4 inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-plus-circle mr-2"></i> Add Step
                        </button>
                    </div>

                    <div class="mt-8 flex justify-end">
                        <a href="document_trails.php" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancel
                        </a>
                        <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <?= $trailToEdit ? 'Update' : 'Create' ?> Trail
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Document Trails List -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    Defined Document Trails
                </h3>
                <p class="mt-1 text-sm text-gray-600">
                    List of all document trails defined in the system
                </p>
            </div>
            <?php if (empty($trails)): ?>
                <div class="p-6 text-center">
                    <p class="text-gray-500">No document trails defined yet.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Steps</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created On</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($trails as $trail): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($trail['name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= !empty($trail['description']) ? htmlspecialchars(substr($trail['description'], 0, 50)) . (strlen($trail['description']) > 50 ? '...' : '') : '-' ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= $trail['step_count'] ?> department(s)
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($trail['created_by_name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M j, Y', strtotime($trail['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="document_trails.php?edit=<?= $trail['id'] ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <button type="button" 
                                                class="text-red-600 hover:text-red-900 delete-trail"
                                                data-id="<?= $trail['id'] ?>"
                                                data-name="<?= htmlspecialchars($trail['name']) ?>">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </button>
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

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="fixed z-10 inset-0 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div id="modal-backdrop" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

        <!-- This element is to trick the browser into centering the modal contents. -->
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            Delete Document Trail
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500" id="delete-message">
                                Are you sure you want to delete this trail? This action cannot be undone.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="delete_trail">
                <input type="hidden" name="trail_id" id="delete-trail-id">
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Delete
                    </button>
                    <button type="button" id="cancel-delete" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add step button functionality
    document.getElementById('add-step').addEventListener('click', function() {
        const stepsContainer = document.getElementById('steps-container');
        const stepCount = stepsContainer.children.length;
        const newStepIndex = stepCount;
        
        const stepTemplate = `
            <div class="step-row flex items-center gap-4 p-3 border border-gray-200 rounded-md bg-gray-50">
                <div class="step-number bg-indigo-100 text-indigo-700 w-7 h-7 rounded-full flex items-center justify-center font-medium">
                    ${stepCount + 1}
                </div>
                <div class="flex-1 grid grid-cols-12 gap-4">
                    <div class="col-span-8">
                        <label class="sr-only">Department</label>
                        <select name="steps[${newStepIndex}][department]" required
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                            <option value="">Select Department</option>
                            ${Array.from(document.querySelector('select[name^="steps"]').options)
                                .filter(option => option.value)
                                .map(option => `<option value="${option.value}">${option.text}</option>`)
                                .join('')}
                        </select>
                    </div>
                    <div class="col-span-4 flex items-center">
                        <input type="checkbox" name="steps[${newStepIndex}][requires_approval]"
                               id="approval-${newStepIndex}"
                               class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                               checked>
                        <label for="approval-${newStepIndex}" class="ml-2 block text-sm text-gray-700">
                            Requires Approval
                        </label>
                    </div>
                </div>
                <button type="button" class="remove-step text-red-500 hover:text-red-700">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        `;
        
        stepsContainer.insertAdjacentHTML('beforeend', stepTemplate);
        attachRemoveHandlers();
        updateStepNumbers();
    });
    
    // Remove step button functionality
    function attachRemoveHandlers() {
        document.querySelectorAll('.remove-step').forEach(button => {
            button.removeEventListener('click', removeStep);
            button.addEventListener('click', removeStep);
        });
    }
    
    function removeStep(e) {
        e.target.closest('.step-row').remove();
        updateStepNumbers();
    }
    
    // Update step numbers after adding/removing steps
    function updateStepNumbers() {
        document.querySelectorAll('.step-row').forEach((row, index) => {
            const stepNumber = row.querySelector('.step-number');
            stepNumber.textContent = index + 1;
            
            // Update name attributes for form submission
            const departmentSelect = row.querySelector('select[name^="steps"]');
            const requiresApprovalCheckbox = row.querySelector('input[name^="steps"]');
            
            departmentSelect.name = `steps[${index}][department]`;
            requiresApprovalCheckbox.name = `steps[${index}][requires_approval]`;
            requiresApprovalCheckbox.id = `approval-${index}`;
            const label = row.querySelector(`label[for^="approval-"]`);
            label.setAttribute('for', `approval-${index}`);
        });
    }
    
    // Initialize event handlers
    attachRemoveHandlers();
    
    // Delete trail modal functionality
    const deleteModal = document.getElementById('delete-modal');
    const modalBackdrop = document.getElementById('modal-backdrop');
    const cancelDelete = document.getElementById('cancel-delete');
    const deleteTrailId = document.getElementById('delete-trail-id');
    const deleteMessage = document.getElementById('delete-message');
    
    document.querySelectorAll('.delete-trail').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            
            deleteTrailId.value = id;
            deleteMessage.textContent = `Are you sure you want to delete the trail "${name}"? This action cannot be undone.`;
            
            deleteModal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        });
    });
    
    function closeModal() {
        deleteModal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
    
    modalBackdrop.addEventListener('click', closeModal);
    cancelDelete.addEventListener('click', closeModal);
});
</script>

<?php include_once '../includes/footer.php'; ?>
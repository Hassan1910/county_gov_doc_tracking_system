<?php
/**
 * Document Movement Reports
 * 
 * This page allows clerks to generate and view reports on document movements.
 */

// Include authentication utilities
require_once '../includes/auth.php';

// Require login to access this page
requireLogin();

// Only allow clerks and admins to access this page
if (!hasRole(['admin', 'clerk'])) {
    $_SESSION['error'] = "You don't have permission to access this page.";
    header('Location: dashboard.php');
    exit;
}

// Get current user data
$user = getCurrentUser();

// Include database connection
require_once '../config/db.php';

// Initialize variables
$contractors = [];
$departments = [];
$movements = [];
$totalMovements = 0;

// Get search parameters
$filterContractor = isset($_GET['contractor_id']) ? (int)$_GET['contractor_id'] : null;
$filterDepartment = isset($_GET['department']) ? $_GET['department'] : '';
$filterDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$filterDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'moved_at';
$sortDirection = isset($_GET['sort_dir']) ? $_GET['sort_dir'] : 'DESC';
$exportFormat = isset($_GET['export']) ? $_GET['export'] : null;

// Get all contractors
try {
    $sql = "SELECT id, name, contractor_id FROM users WHERE role = 'contractor' ORDER BY name";
    $stmt = db()->prepare($sql);
    $stmt->execute();
    $contractors = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching contractors: " . $e->getMessage());
}

// Get all departments
try {
    $sql = "SELECT DISTINCT department FROM users WHERE department != '' ORDER BY department";
    $stmt = db()->prepare($sql);
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching departments: " . $e->getMessage());
}

// Build the query for movements
try {
    // Base SQL
    $sql = "SELECT 
                m.id, m.from_department, m.to_department, m.note, m.moved_at,
                d.id as document_id, d.doc_unique_id, d.title, d.type, d.status,
                u1.name as moved_by_name,
                u2.name as contractor_name, u2.contractor_id as contractor_reg_id
            FROM 
                document_movements m
                JOIN documents d ON m.document_id = d.id
                JOIN users u1 ON m.moved_by = u1.id
                LEFT JOIN users u2 ON d.submitter_id = u2.id
            WHERE 
                m.moved_at BETWEEN :date_from AND :date_to";

    // Parameter array
    $params = [
        'date_from' => $filterDateFrom . ' 00:00:00',
        'date_to' => $filterDateTo . ' 23:59:59'
    ];

    // Add contractor filter
    if ($filterContractor) {
        $sql .= " AND d.submitter_id = :contractor_id";
        $params['contractor_id'] = $filterContractor;
    }

    // Add department filter (either from or to)
    if (!empty($filterDepartment)) {
        $sql .= " AND (m.from_department = :department OR m.to_department = :department)";
        $params['department'] = $filterDepartment;
    }

    // Add status filter
    if (!empty($filterStatus)) {
        $sql .= " AND d.status = :status";
        $params['status'] = $filterStatus;
    }

    // Add sorting
    $allowedSortColumns = ['moved_at', 'doc_unique_id', 'from_department', 'to_department', 'status'];
    $sortBy = in_array($sortBy, $allowedSortColumns) ? $sortBy : 'moved_at';
    $sortDirection = $sortDirection === 'ASC' ? 'ASC' : 'DESC';

    if ($sortBy === 'doc_unique_id') {
        $sql .= " ORDER BY d.doc_unique_id " . $sortDirection;
    } else {
        $sql .= " ORDER BY m." . $sortBy . " " . $sortDirection;
    }

    // Execute query
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $movements = $stmt->fetchAll();
    $totalMovements = count($movements);

    // Handle export if requested
    if ($exportFormat && in_array($exportFormat, ['csv', 'pdf'])) {
        if ($exportFormat === 'csv') {
            exportAsCSV($movements);
        } else {
            exportAsPDF($movements);
        }
    }

} catch (PDOException $e) {
    error_log("Error fetching document movements: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while retrieving the movement data.";
}

// Functions to handle exports
function exportAsCSV($data) {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=document_movements_report_' . date('Y-m-d') . '.csv');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add CSV header row
    fputcsv($output, [
        'Document ID', 
        'Title', 
        'Contractor', 
        'Contractor ID',
        'From Department', 
        'To Department', 
        'Moved By', 
        'Date', 
        'Status',
        'Notes'
    ]);
    
    // Add data rows
    foreach ($data as $row) {
        fputcsv($output, [
            $row['doc_unique_id'],
            $row['title'],
            $row['contractor_name'] ?? 'N/A',
            $row['contractor_reg_id'] ?? 'N/A',
            $row['from_department'],
            $row['to_department'],
            $row['moved_by_name'],
            date('Y-m-d H:i', strtotime($row['moved_at'])),
            $row['status'],
            $row['note']
        ]);
    }
    
    // Close output stream
    fclose($output);
    exit;
}

function exportAsPDF($data) {
    // This would require a PDF library like TCPDF or MPDF
    // For now, we'll just show a message that this feature is coming soon
    $_SESSION['error'] = "PDF export functionality is coming soon.";
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Set page title
$page_title = "Document Movement Reports";

// Include header
include_once '../includes/header.php';
?>

<div class="py-6">
    <div class="mx-auto px-4 sm:px-6 md:px-8">
        <h1 class="text-2xl font-semibold text-gray-900">Document Movement Reports</h1>
        <p class="mt-1 text-sm text-gray-600">
            Generate reports on document movements and track document flow through departments
        </p>
    </div>
    
    <div class="mx-auto px-4 sm:px-6 md:px-8">
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

        <!-- Filters Form -->
        <div class="bg-white shadow rounded-lg mt-6 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">Filter Movement Reports</h3>
            </div>
            
            <div class="p-6">
                <form action="movement_reports.php" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                    <!-- Contractor Filter -->
                    <div>
                        <label for="contractor_id" class="block text-sm font-medium text-gray-700 mb-1">Contractor</label>
                        <select id="contractor_id" name="contractor_id" class="block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                            <option value="">All Contractors</option>
                            <?php foreach ($contractors as $contractor): ?>
                            <option value="<?= $contractor['id']; ?>" <?= $filterContractor == $contractor['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($contractor['name']); ?> 
                                <?= !empty($contractor['contractor_id']) ? '(' . htmlspecialchars($contractor['contractor_id']) . ')' : ''; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Department Filter -->
                    <div>
                        <label for="department" class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <select id="department" name="department" class="block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?= htmlspecialchars($dept); ?>" <?= $filterDepartment == $dept ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($dept); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Status Filter -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Document Status</label>
                        <select id="status" name="status" class="block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                            <option value="">All Statuses</option>
                            <option value="pending" <?= $filterStatus == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_movement" <?= $filterStatus == 'in_movement' ? 'selected' : ''; ?>>In Movement</option>
                            <option value="assistant_approved" <?= $filterStatus == 'assistant_approved' ? 'selected' : ''; ?>>Assistant Approved</option>
                            <option value="approved" <?= $filterStatus == 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?= $filterStatus == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="done" <?= $filterStatus == 'done' ? 'selected' : ''; ?>>Done</option>
                        </select>
                    </div>
                    
                    <!-- Date From -->
                    <div>
                        <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                        <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($filterDateFrom); ?>" 
                            class="block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                    </div>
                    
                    <!-- Date To -->
                    <div>
                        <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                        <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($filterDateTo); ?>" 
                            class="block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                    </div>
                    
                    <!-- Sort By -->
                    <div>
                        <label for="sort_by" class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                        <div class="flex gap-2">
                            <select id="sort_by" name="sort_by" class="block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                                <option value="moved_at" <?= $sortBy == 'moved_at' ? 'selected' : ''; ?>>Date</option>
                                <option value="doc_unique_id" <?= $sortBy == 'doc_unique_id' ? 'selected' : ''; ?>>Document ID</option>
                                <option value="from_department" <?= $sortBy == 'from_department' ? 'selected' : ''; ?>>From Department</option>
                                <option value="to_department" <?= $sortBy == 'to_department' ? 'selected' : ''; ?>>To Department</option>
                            </select>
                            <select id="sort_dir" name="sort_dir" class="block w-32 py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                                <option value="DESC" <?= $sortDirection == 'DESC' ? 'selected' : ''; ?>>Desc</option>
                                <option value="ASC" <?= $sortDirection == 'ASC' ? 'selected' : ''; ?>>Asc</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Filter Button -->
                    <div class="md:col-span-2 lg:col-span-3 flex justify-between items-end">
                        <div>
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                <i class="fas fa-filter mr-2"></i> Apply Filters
                            </button>
                            <a href="movement_reports.php" class="ml-2 inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                <i class="fas fa-redo-alt mr-2"></i> Reset
                            </a>
                        </div>
                        
                        <div>
                            <button type="submit" name="export" value="csv" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <i class="fas fa-file-csv mr-2"></i> Export CSV
                            </button>
                            <button type="submit" name="export" value="pdf" class="ml-2 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                <i class="fas fa-file-pdf mr-2"></i> Export PDF
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Results Table -->
        <div class="bg-white shadow rounded-lg mt-6 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">Document Movements</h3>
                <span class="text-sm text-gray-600"><?= $totalMovements; ?> results found</span>
            </div>
            
            <?php if (empty($movements)): ?>
            <div class="p-10 text-center text-gray-500">
                <i class="fas fa-inbox text-gray-400 text-5xl mb-4"></i>
                <p>No document movements found matching your criteria.</p>
                <p class="mt-2 text-sm">Try adjusting your filters or selecting a different date range.</p>
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
                                Contractor
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                From
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                To
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Moved By
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($movements as $movement): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <a href="view_document.php?id=<?= $movement['document_id']; ?>" class="text-primary-600 hover:text-primary-900">
                                    <?= htmlspecialchars($movement['doc_unique_id']); ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($movement['title']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= $movement['contractor_name'] ? htmlspecialchars($movement['contractor_name']) : 'N/A'; ?>
                                <?php if (!empty($movement['contractor_reg_id'])): ?>
                                <span class="block text-xs text-gray-400"><?= htmlspecialchars($movement['contractor_reg_id']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($movement['from_department']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($movement['to_department']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($movement['moved_by_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= date('M d, Y H:i', strtotime($movement['moved_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    <?php
                                    switch ($movement['status']) {
                                        case 'pending':
                                            echo 'bg-yellow-100 text-yellow-800';
                                            break;
                                        case 'approved':
                                            echo 'bg-green-100 text-green-800';
                                            break;
                                        case 'rejected':
                                            echo 'bg-red-100 text-red-800';
                                            break;
                                        case 'assistant_approved':
                                            echo 'bg-blue-100 text-blue-800';
                                            break;
                                        case 'done':
                                            echo 'bg-purple-100 text-purple-800';
                                            break;
                                        default:
                                            echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <?php
                                    switch ($movement['status']) {
                                        case 'in_movement':
                                            echo 'In Transit';
                                            break;
                                        case 'assistant_approved':
                                            echo 'Assistant Approved';
                                            break;
                                        default:
                                            echo ucfirst($movement['status']);
                                    }
                                    ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <a href="view_document.php?id=<?= $movement['document_id']; ?>" class="text-primary-600 hover:text-primary-900 mr-3">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="track_history.php?id=<?= $movement['document_id']; ?>" class="text-primary-600 hover:text-primary-900">
                                    <i class="fas fa-history"></i> History
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($movements)): ?>
        <!-- Movement Flow Visualization -->
        <div class="bg-white shadow rounded-lg mt-6 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">Document Movement Flow</h3>
            </div>
            <div class="p-6">
                <div id="movement-flow-chart" style="height: 400px;"></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($movements)): ?>
<!-- Include ApexCharts for visualization -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Process data for department flow visualization
        const movements = <?= json_encode($movements); ?>;
        const departmentFlow = {};
        
        // Count movements between departments
        movements.forEach(movement => {
            const key = `${movement.from_department} → ${movement.to_department}`;
            if (!departmentFlow[key]) {
                departmentFlow[key] = {
                    from: movement.from_department,
                    to: movement.to_department,
                    count: 0
                };
            }
            departmentFlow[key].count++;
        });
        
        // Convert to array and sort by count
        const flowData = Object.values(departmentFlow).sort((a, b) => b.count - a.count);
        
        // Prepare chart data
        const categories = flowData.map(item => `${item.from} → ${item.to}`);
        const series = [{
            name: 'Movements',
            data: flowData.map(item => item.count)
        }];
        
        // Create visualization
        const options = {
            series: series,
            chart: {
                type: 'bar',
                height: 400,
                toolbar: {
                    show: false
                }
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    borderRadius: 4
                }
            },
            dataLabels: {
                enabled: false
            },
            xaxis: {
                categories: categories
            },
            colors: ['#6366F1'],
            tooltip: {
                y: {
                    formatter: function(val) {
                        return val + " movements";
                    }
                }
            },
            title: {
                text: 'Document Movement Flow Between Departments',
                align: 'left',
                style: {
                    fontSize: '16px',
                    fontWeight: 500
                }
            }
        };
        
        const chart = new ApexCharts(document.querySelector("#movement-flow-chart"), options);
        chart.render();
    });
</script>
<?php endif; ?>

<?php include_once '../includes/footer.php'; ?>
<?php
/**
 * Document Reports Page
 * 
 * This page provides statistical reports and analytics for documents.
 */

// Include authentication utilities
require_once '../includes/auth.php';

// Require login to access this page
requireLogin();

// Check if user has permission to view reports
// Only admins and supervisors can access reports
if (!hasRole(['admin', 'supervisor', 'department_head'])) {
    $_SESSION['error'] = "You don't have permission to access reports.";
    header('Location: dashboard.php');
    exit;
}

// Get current user data
$user = getCurrentUser();

// Include database connection
require_once '../config/db.php';

// Initialize report data
$totalDocuments = 0;
$statusCounts = [];
$departmentCounts = [];
$typeCounts = [];
$monthlyStats = [];
$recentApprovals = [];
$pendingApprovals = [];

// Date filter (default to last 30 days)
$dateFilter = $_GET['date_filter'] ?? 'month';
$customStartDate = $_GET['start_date'] ?? '';
$customEndDate = $_GET['end_date'] ?? '';

// Determine date range based on filter
$startDate = null;
$endDate = date('Y-m-d');

switch ($dateFilter) {
    case 'week':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        break;
    case 'month':
        $startDate = date('Y-m-d', strtotime('-30 days'));
        break;
    case 'quarter':
        $startDate = date('Y-m-d', strtotime('-90 days'));
        break;
    case 'year':
        $startDate = date('Y-m-d', strtotime('-1 year'));
        break;
    case 'custom':
        if (!empty($customStartDate)) {
            $startDate = $customStartDate;
        } else {
            $startDate = date('Y-m-d', strtotime('-30 days'));
        }
        
        if (!empty($customEndDate)) {
            $endDate = $customEndDate;
        }
        break;
    default:
        $startDate = date('Y-m-d', strtotime('-30 days'));
}

try {
    // Get total document count
    $sql = "SELECT COUNT(*) FROM documents WHERE created_at BETWEEN :start_date AND :end_date";
    $params = [
        'start_date' => $startDate . ' 00:00:00',
        'end_date' => $endDate . ' 23:59:59'
    ];
    
    // Apply department filter for non-admin users
    if (!hasRole('admin')) {
        $sql .= " AND department = :department";
        $params['department'] = $user['department'];
    }
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $totalDocuments = $stmt->fetchColumn();
    
    // Get document counts by status
    $sql = "SELECT status, COUNT(*) as count FROM documents 
            WHERE created_at BETWEEN :start_date AND :end_date";
    
    // Apply department filter for non-admin users
    if (!hasRole('admin')) {
        $sql .= " AND department = :department";
    }
    
    $sql .= " GROUP BY status ORDER BY count DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Get document counts by department
    $sql = "SELECT department, COUNT(*) as count FROM documents 
            WHERE created_at BETWEEN :start_date AND :end_date";
    
    // Apply department filter for non-admin users
    if (!hasRole('admin')) {
        $sql .= " AND department = :department";
    }
    
    $sql .= " GROUP BY department ORDER BY count DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $departmentCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Get document counts by type
    $sql = "SELECT type, COUNT(*) as count FROM documents 
            WHERE created_at BETWEEN :start_date AND :end_date";
    
    // Apply department filter for non-admin users
    if (!hasRole('admin')) {
        $sql .= " AND department = :department";
    }
    
    $sql .= " GROUP BY type ORDER BY count DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $typeCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Get monthly document stats for the past year
    $sql = "SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM documents 
            WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 1 YEAR)";
    
    // Apply department filter for non-admin users
    if (!hasRole('admin')) {
        $sql .= " AND department = :department";
        $params = ['department' => $user['department']];
    } else {
        $params = [];
    }
    
    $sql .= " GROUP BY DATE_FORMAT(created_at, '%Y-%m')
              ORDER BY month ASC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $monthlyStats = $stmt->fetchAll();
    
    // Get recent approvals
    $sql = "SELECT 
                a.id, a.action, a.comments, a.created_at,
                d.id as document_id, d.title, d.type, d.department, d.doc_unique_id,
                u.name as approved_by_name
            FROM document_approvals a
            JOIN documents d ON a.document_id = d.id
            JOIN users u ON a.approved_by = u.id
            WHERE a.created_at BETWEEN :start_date AND :end_date";
    
    // Apply department filter for non-admin users
    if (!hasRole('admin')) {
        $sql .= " AND d.department = :department";
    }
    
    $sql .= " ORDER BY a.created_at DESC LIMIT 10";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $recentApprovals = $stmt->fetchAll();
    
    // Get pending documents that need approval
    $sql = "SELECT 
                d.id, d.title, d.type, d.department, d.doc_unique_id, d.created_at,
                u.name as uploaded_by
            FROM documents d
            JOIN users u ON d.uploaded_by = u.id
            WHERE d.status = 'pending'";
    
    // Apply department filter for non-admin users
    if (!hasRole('admin')) {
        $sql .= " AND d.department = :department";
    }
    
    $sql .= " ORDER BY d.created_at ASC LIMIT 10";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $pendingApprovals = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Reports page error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while generating the reports.";
}

// Set page title
$page_title = "Document Reports";

// Include header
include_once '../includes/header.php';
?>

<div class="py-6">
    <div class="mx-auto px-4 sm:px-6 md:px-8">
        <h1 class="text-2xl font-semibold text-gray-900">Document Reports</h1>
        <p class="mt-1 text-sm text-gray-600">
            Statistical reports and analytics for documents
            <?php if (!hasRole('admin')): ?>
            in your department (<?= htmlspecialchars($user['department']); ?>)
            <?php endif; ?>
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
        
        <!-- Date Filter -->
        <div class="bg-white shadow rounded-lg mt-6 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">Date Range Filter</h3>
            </div>
            
            <div class="p-6">
                <form action="reports.php" method="GET" class="flex flex-wrap gap-4 items-end">
                    <div>
                        <label for="date_filter" class="block text-sm font-medium text-gray-700">Time Period</label>
                        <select id="date_filter" name="date_filter" onchange="toggleCustomDateInputs(this.value)"
                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                            <option value="week" <?= $dateFilter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="month" <?= $dateFilter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="quarter" <?= $dateFilter === 'quarter' ? 'selected' : ''; ?>>Last 90 Days</option>
                            <option value="year" <?= $dateFilter === 'year' ? 'selected' : ''; ?>>Last Year</option>
                            <option value="custom" <?= $dateFilter === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                    
                    <div id="custom_date_container" class="<?= $dateFilter === 'custom' ? 'flex' : 'hidden'; ?> flex-wrap gap-4">
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                            <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($customStartDate); ?>"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700">End Date</label>
                            <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($customEndDate); ?>"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                        </div>
                    </div>
                    
                    <div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            <i class="fas fa-filter mr-2"></i> Apply Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            <!-- Total Documents -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-md bg-primary-100 p-3">
                                <i class="fas fa-file-alt text-primary-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Total Documents
                                </dt>
                                <dd>
                                    <div class="text-lg font-semibold text-gray-900">
                                        <?= number_format($totalDocuments); ?>
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Documents Pending -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-md bg-yellow-100 p-3">
                                <i class="fas fa-clock text-yellow-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Pending Documents
                                </dt>
                                <dd>
                                    <div class="text-lg font-semibold text-gray-900">
                                        <?= number_format($statusCounts['pending'] ?? 0); ?>
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Documents Approved -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-md bg-green-100 p-3">
                                <i class="fas fa-check-circle text-green-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Approved Documents
                                </dt>
                                <dd>
                                    <div class="text-lg font-semibold text-gray-900">
                                        <?= number_format($statusCounts['approved'] ?? 0); ?>
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Documents Rejected -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-md bg-red-100 p-3">
                                <i class="fas fa-times-circle text-red-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Rejected Documents
                                </dt>
                                <dd>
                                    <div class="text-lg font-semibold text-gray-900">
                                        <?= number_format($statusCounts['rejected'] ?? 0); ?>
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-2">
            <!-- Status Chart -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800">Documents by Status</h3>
                </div>
                <div class="p-6">
                    <div id="status-chart" style="height: 300px;"></div>
                </div>
            </div>
            
            <!-- Department Chart -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800">Documents by Department</h3>
                </div>
                <div class="p-6">
                    <div id="department-chart" style="height: 300px;"></div>
                </div>
            </div>
        </div>
        
        <div class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-2">
            <!-- Type Chart -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800">Documents by Type</h3>
                </div>
                <div class="p-6">
                    <div id="type-chart" style="height: 300px;"></div>
                </div>
            </div>
            
            <!-- Monthly Trend Chart -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800">Monthly Document Trends</h3>
                </div>
                <div class="p-6">
                    <div id="monthly-chart" style="height: 300px;"></div>
                </div>
            </div>
        </div>
        
        <!-- Tables Section -->
        <div class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-2">
            <!-- Recent Approvals/Rejections -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800">Recent Approvals/Rejections</h3>
                </div>
                <?php if (empty($recentApprovals)): ?>
                <div class="p-6 text-center text-gray-500">
                    No recent approvals or rejections found.
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Document
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Action
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    By
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recentApprovals as $approval): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <a href="view_document.php?id=<?= $approval['document_id']; ?>" class="hover:text-primary-600">
                                            <?= htmlspecialchars($approval['title']); ?>
                                        </a>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        ID: <?= htmlspecialchars($approval['doc_unique_id']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $approval['action'] === 'approve' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?= ucfirst($approval['action'] === 'approve' ? 'Approved' : 'Rejected'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($approval['approved_by_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('M d, Y', strtotime($approval['created_at'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Pending Approvals -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800">Documents Pending Approval</h3>
                </div>
                
                <?php if (empty($pendingApprovals)): ?>
                <div class="p-6 text-center text-gray-500">
                    No documents are pending approval.
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Document
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Department
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Uploaded By
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Waiting Since
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($pendingApprovals as $document): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <a href="view_document.php?id=<?= $document['id']; ?>" class="hover:text-primary-600">
                                            <?= htmlspecialchars($document['title']); ?>
                                        </a>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        ID: <?= htmlspecialchars($document['doc_unique_id']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($document['department']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($document['uploaded_by']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('M d, Y', strtotime($document['created_at'])); ?>
                                    <span class="text-xs text-gray-400">
                                        (<?= timeAgo($document['created_at']); ?>)
                                    </span>
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
</div>

<!-- Include Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Toggle custom date inputs based on selected filter
    function toggleCustomDateInputs(value) {
        const container = document.getElementById('custom_date_container');
        if (value === 'custom') {
            container.classList.remove('hidden');
            container.classList.add('flex');
        } else {
            container.classList.add('hidden');
            container.classList.remove('flex');
        }
    }
    
    // Status Pie Chart
    const statusCtx = document.getElementById('status-chart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: [
                <?php
                $statusLabels = [
                    'pending' => 'Pending',
                    'in_movement' => 'In Movement',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected'
                ];
                
                foreach ($statusCounts as $status => $count) {
                    echo "'" . ($statusLabels[$status] ?? ucfirst($status)) . "',";
                }
                ?>
            ],
            datasets: [{
                data: [
                    <?php
                    foreach ($statusCounts as $count) {
                        echo $count . ',';
                    }
                    ?>
                ],
                backgroundColor: [
                    '#FBBF24', // Yellow for pending
                    '#3B82F6', // Blue for in_movement
                    '#10B981', // Green for approved
                    '#EF4444'  // Red for rejected
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    // Department Bar Chart
    const deptCtx = document.getElementById('department-chart').getContext('2d');
    const deptChart = new Chart(deptCtx, {
        type: 'bar',
        data: {
            labels: [
                <?php
                foreach ($departmentCounts as $dept => $count) {
                    echo "'" . $dept . "',";
                }
                ?>
            ],
            datasets: [{
                label: 'Documents',
                data: [
                    <?php
                    foreach ($departmentCounts as $count) {
                        echo $count . ',';
                    }
                    ?>
                ],
                backgroundColor: '#6366F1',
                borderColor: '#4F46E5',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    
    // Document Type Chart
    const typeCtx = document.getElementById('type-chart').getContext('2d');
    const typeChart = new Chart(typeCtx, {
        type: 'doughnut',
        data: {
            labels: [
                <?php
                foreach ($typeCounts as $type => $count) {
                    echo "'" . $type . "',";
                }
                ?>
            ],
            datasets: [{
                data: [
                    <?php
                    foreach ($typeCounts as $count) {
                        echo $count . ',';
                    }
                    ?>
                ],
                backgroundColor: [
                    '#F43F5E', '#D946EF', '#8B5CF6', 
                    '#6366F1', '#3B82F6', '#0EA5E9',
                    '#10B981', '#84CC16', '#EAB308',
                    '#F59E0B', '#F97316', '#EF4444'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    // Monthly trend line chart
    const monthlyCtx = document.getElementById('monthly-chart').getContext('2d');
    const monthlyChart = new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: [
                <?php
                foreach ($monthlyStats as $stat) {
                    $date = DateTime::createFromFormat('Y-m', $stat['month']);
                    echo "'" . $date->format('M Y') . "',";
                }
                ?>
            ],
            datasets: [
                {
                    label: 'Total',
                    data: [
                        <?php
                        foreach ($monthlyStats as $stat) {
                            echo $stat['total'] . ',';
                        }
                        ?>
                    ],
                    borderColor: '#6366F1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.1
                },
                {
                    label: 'Approved',
                    data: [
                        <?php
                        foreach ($monthlyStats as $stat) {
                            echo $stat['approved'] . ',';
                        }
                        ?>
                    ],
                    borderColor: '#10B981',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    tension: 0.1
                },
                {
                    label: 'Rejected',
                    data: [
                        <?php
                        foreach ($monthlyStats as $stat) {
                            echo $stat['rejected'] . ',';
                        }
                        ?>
                    ],
                    borderColor: '#EF4444',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    tension: 0.1
                },
                {
                    label: 'Pending',
                    data: [
                        <?php
                        foreach ($monthlyStats as $stat) {
                            echo $stat['pending'] . ',';
                        }
                        ?>
                    ],
                    borderColor: '#FBBF24',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    tension: 0.1
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
</script>

<?php
// Helper function to calculate time ago
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    
    if ($difference < 60) {
        return $difference . " seconds ago";
    } elseif ($difference < 3600) {
        return round($difference / 60) . " minutes ago";
    } elseif ($difference < 86400) {
        return round($difference / 3600) . " hours ago";
    } elseif ($difference < 604800) {
        return round($difference / 86400) . " days ago";
    } elseif ($difference < 2592000) {
        return round($difference / 604800) . " weeks ago";
    } elseif ($difference < 31536000) {
        return round($difference / 2592000) . " months ago";
    } else {
        return round($difference / 31536000) . " years ago";
    }
}

include_once '../includes/footer.php';
?> 
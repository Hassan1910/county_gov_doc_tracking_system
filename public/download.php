<?php
/**
 * Document Download
 * 
 * This script securely serves document files from the uploads directory
 */

// Include authentication utilities
require_once '../includes/auth.php';

// Require login to access this page
requireLogin();

// Get current user data
$user = getCurrentUser();

// Include database connection
require_once '../config/db.php';

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
    // Get document details including file_path
    $sql = "SELECT d.*, u.department as uploader_department
            FROM documents d
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
    // Only users from the document's current department or admins can download it
    $hasViewPermission = hasRole('admin') || $user['department'] === $document['department'];
    if (!$hasViewPermission) {
        $_SESSION['error'] = "You don't have permission to download this document.";
        header('Location: dashboard.php');
        exit;
    }
    
    // Check if the file exists
    $filePath = '../' . $document['file_path']; // Ensure we're looking in the proper uploads folder
    
    if (!file_exists($filePath)) {
        $_SESSION['error'] = "File not found on the server.";
        header('Location: view_document.php?id=' . $documentId);
        exit;
    }
    
    // Get file information
    $fileInfo = pathinfo($filePath);
    $fileName = $document['file_name'] ?? $fileInfo['basename'];
    
    // Ensure the file has a .pdf extension
    if (!preg_match('/\.pdf$/i', $fileName)) {
        $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '.pdf';
    }
    
    // Check if this is a view request or a download request
    $isViewRequest = isset($_GET['view']) && $_GET['view'] == 1;
    
    // Set appropriate headers
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    
    if ($isViewRequest) {
        // For viewing in browser
        header('Content-Disposition: inline; filename="' . $fileName . '"');
    } else {
        // For downloading
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
    }
    
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    
    // Clear output buffer to prevent any other content from being sent
    ob_clean();
    flush();
    
    // Read the file and output it to the browser
    readfile($filePath);
    exit;
    
} catch (PDOException $e) {
    // Log error and show generic error message
    error_log("Document download error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while retrieving the document.";
    header('Location: dashboard.php');
    exit;
} 
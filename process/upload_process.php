<?php
/**
 * Document Upload Process
 * 
 * Handles file uploads, validation, and database insertion
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once '../config/db.php';
require_once '../includes/auth.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: ../public/login.php');
    exit;
}

// Check if form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid form submission. Please try again.";
        header('Location: ../public/upload.php');
        exit;
    }
    
    // Get and sanitize user input
    $title = filter_var($_POST['title'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $type = filter_var($_POST['type'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $department = filter_var($_POST['department'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $final_destination = filter_var($_POST['final_destination'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $notes = filter_var($_POST['notes'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $user_id = $_SESSION['user_id'];
    
    // Initialize errors array
    $errors = [];
    
    // Basic validation
    if (empty($title)) {
        $errors[] = "Document title is required.";
    }
    
    if (empty($type)) {
        $errors[] = "Document type is required.";
    }
    
    if (empty($department)) {
        $errors[] = "Department is required.";
    }
    
    if (empty($final_destination)) {
        $errors[] = "Final destination is required.";
    }
    
    // File upload validation
    if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        // Handle file upload errors
        $fileError = $_FILES['document_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        switch ($fileError) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = "The uploaded file exceeds the maximum allowed size.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = "The file was only partially uploaded. Please try again.";
                break;
            case UPLOAD_ERR_NO_FILE:
                $errors[] = "No file was uploaded. Please select a file.";
                break;
            default:
                $errors[] = "An error occurred during file upload. Please try again.";
        }
    } else {
        $file = $_FILES['document_file'];
        
        // Check file type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $fileType = $finfo->file($file['tmp_name']);
        
        if ($fileType !== 'application/pdf') {
            $errors[] = "Only PDF files are allowed. Detected file type: " . $fileType;
        }
        
        // Check file size (10MB limit)
        if ($file['size'] > 10 * 1024 * 1024) {
            $errors[] = "File size exceeds 10MB limit.";
        }
    }
    
    // If there are validation errors, redirect back with error messages
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        header('Location: ../public/upload.php');
        exit;
    }
    
    // All validation passed, proceed with file upload and database insertion
    try {
        // Generate unique document ID
        // Format: DOC-YEAR-RANDOMNUMBER (e.g., DOC-2025-12345)
        $year = date('Y');
        $randomNumber = mt_rand(10000, 99999);
        $doc_unique_id = "DOC-{$year}-{$randomNumber}";
        
        // Create uploads directory if it doesn't exist
        $uploadsDir = __DIR__ . '/../uploads';
        if (!file_exists($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }
        
        // Generate unique filename to prevent overwriting
        $fileExtension = 'pdf';  // We already validated it's a PDF
        $uniqueFilename = uniqid() . '_' . $doc_unique_id . '.' . $fileExtension;
        $uploadPath = $uploadsDir . '/' . $uniqueFilename;
        
        // Move uploaded file to destination
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception("Failed to move uploaded file.");
        }
        
        // Insert document information into database
        $sql = "INSERT INTO documents (title, file_path, type, department, final_destination, status, uploaded_by, created_at, doc_unique_id) 
                VALUES (:title, :file_path, :type, :department, :final_destination, 'pending', :uploaded_by, NOW(), :doc_unique_id)";
        
        $stmt = db()->prepare($sql);
        $stmt->execute([
            'title' => $title,
            'file_path' => 'uploads/' . $uniqueFilename,
            'type' => $type,
            'department' => $department,
            'final_destination' => $final_destination,
            'uploaded_by' => $user_id,
            'doc_unique_id' => $doc_unique_id
        ]);
        
        // Get inserted document ID
        $documentId = db()->lastInsertId();
        
        // Get current user data to check if it's a clerk processing for a client
        $current_user = getCurrentUser();
        
        // If the current user is a clerk or admin, check if this is a client submission
        $submitter_id = null;
        if (hasRole(['admin', 'clerk']) && isset($_POST['submitter_id']) && !empty($_POST['submitter_id'])) {
            $submitter_id = intval($_POST['submitter_id']);
            
            // Verify the submitter exists and is a client
            $sql = "SELECT id FROM users WHERE id = :id AND role = 'client'";
            $stmt = db()->prepare($sql);
            $stmt->execute(['id' => $submitter_id]);
            
            if ($stmt->fetch()) {
                // Update the document to include the submitter
                try {
                    $sql = "UPDATE documents SET submitter_id = :submitter_id WHERE id = :id";
                    $stmt = db()->prepare($sql);
                    $stmt->execute([
                        'submitter_id' => $submitter_id,
                        'id' => $documentId
                    ]);
                } catch (Exception $e) {
                    error_log("Could not update submitter_id: " . $e->getMessage());
                }
                
                // Associate the document with the client in the document_clients table
                try {
                    $sql = "INSERT INTO document_clients (document_id, client_id, created_at) 
                            VALUES (:document_id, :client_id, NOW())";
                    $stmt = db()->prepare($sql);
                    $stmt->execute([
                        'document_id' => $documentId,
                        'client_id' => $submitter_id
                    ]);
                } catch (Exception $e) {
                    error_log("Could not add to document_clients: " . $e->getMessage());
                }
                
                // Create notification for the client
                try {
                    $sql = "INSERT INTO client_notifications (client_id, document_id, message, is_read, created_at) 
                            VALUES (:client_id, :document_id, :message, 0, NOW())";
                    $stmt = db()->prepare($sql);
                    $stmt->execute([
                        'client_id' => $submitter_id,
                        'document_id' => $documentId,
                        'message' => "Your document \"{$title}\" has been uploaded and is now pending review."
                    ]);
                } catch (Exception $e) {
                    error_log("Could not create client notification: " . $e->getMessage());
                }
            }
        }
        
        // Log the activity
        logActivity('document_upload', "Uploaded document: {$title} ({$doc_unique_id})");
        
        // Set success message
        $_SESSION['success'] = "Document uploaded successfully. Document ID: {$doc_unique_id}";
        
        // Redirect to the document view page
        header("Location: ../public/view_document.php?id={$documentId}");
        exit;
        
    } catch (Exception $e) {
        // Log error and show generic error message
        error_log("Document upload error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while uploading your document. Please try again.";
        header('Location: ../public/upload.php');
        exit;
    }
    
} else {
    // If not a POST request, redirect to upload page
    header('Location: ../public/upload.php');
    exit;
} 
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
require_once '../includes/qrcode_utils.php';

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
    $trail_id = !empty($_POST['trail_id']) ? filter_var($_POST['trail_id'], FILTER_VALIDATE_INT) : null;
    
    // Always set submitter_id to the current user if role is client or contractor
    $currentUser = getCurrentUser();
    if (in_array($currentUser['role'], ['client', 'contractor'])) {
        $submitter_id = $currentUser['id'];
    } else {
        // For admin or clerk uploads, allow specifying submitter_id via form (e.g., uploading on behalf of a client)
        $submitter_id = isset($_POST['submitter_id']) && is_numeric($_POST['submitter_id']) ? (int)$_POST['submitter_id'] : null;
    }
    
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
        // Create a detailed log file for debugging
        $debug_log = fopen(__DIR__ . '/../uploads/upload_debug.log', 'a');
        fwrite($debug_log, date('Y-m-d H:i:s') . " - Starting upload process\n");
        
        // Generate a unique document ID
        $doc_unique_id = 'DOC-' . date('Y') . '-' . mt_rand(10000, 99999);
        fwrite($debug_log, "Generated doc_unique_id: $doc_unique_id\n");
        
        // Create uploads directory if it doesn't exist
        $uploadsDir = __DIR__ . '/../uploads';
        if (!file_exists($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
            fwrite($debug_log, "Created uploads directory\n");
        }
        
        // Generate unique filename to prevent overwriting
        $fileExtension = 'pdf';  // We already validated it's a PDF
        $uniqueFilename = uniqid() . '_' . $doc_unique_id . '.' . $fileExtension;
        $uploadPath = $uploadsDir . '/' . $uniqueFilename;
        fwrite($debug_log, "Upload path: $uploadPath\n");
        
        // Move uploaded file to destination
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            fwrite($debug_log, "ERROR: Failed to move uploaded file from {$file['tmp_name']} to $uploadPath\n");
            throw new Exception("Failed to move uploaded file.");
        }
        fwrite($debug_log, "File successfully moved to $uploadPath\n");
        
        // Log all parameters that will be inserted
        fwrite($debug_log, "Database parameters: \n");
        fwrite($debug_log, "title: $title\n");
        fwrite($debug_log, "file_path: uploads/$uniqueFilename\n");
        fwrite($debug_log, "type: $type\n");
        fwrite($debug_log, "department: $department\n");
        fwrite($debug_log, "final_destination: $final_destination\n");
        fwrite($debug_log, "uploaded_by: $user_id\n");
        fwrite($debug_log, "doc_unique_id: $doc_unique_id\n");
        fwrite($debug_log, "submitter_id: " . ($submitter_id ?? 'null') . "\n");
        fwrite($debug_log, "trail_id: " . ($trail_id ?? 'null') . "\n");
        
        // Insert document into database
        $sql = "INSERT INTO documents (title, file_path, type, department, final_destination, uploaded_by, doc_unique_id, submitter_id, trail_id, created_at, file_name) 
                VALUES (:title, :file_path, :type, :department, :final_destination, :uploaded_by, :doc_unique_id, :submitter_id, :trail_id, NOW(), :file_name)";
        
        fwrite($debug_log, "Preparing SQL: $sql\n");
        
        try {
            $stmt = db()->prepare($sql);
            $params = [
                'title' => $title, 
                'file_path' => 'uploads/' . $uniqueFilename,
                'file_name' => pathinfo($file['name'], PATHINFO_FILENAME),
                'type' => $type,
                'department' => $department,
                'final_destination' => $final_destination,
                'uploaded_by' => $user_id,
                'doc_unique_id' => $doc_unique_id,
                'submitter_id' => $submitter_id,
                'trail_id' => $trail_id
            ];
            $stmt->execute($params);
            fwrite($debug_log, "SQL executed successfully\n");
        } catch (PDOException $dbEx) {
            fwrite($debug_log, "ERROR during SQL execution: " . $dbEx->getMessage() . "\n");
            throw $dbEx; // re-throw to be caught by outer catch
        }
        
        // Get inserted document ID
        $documentId = db()->lastInsertId();
        fwrite($debug_log, "Document ID from lastInsertId(): $documentId\n");
        
        if (!$documentId || !is_numeric($documentId)) {
            fwrite($debug_log, "ERROR: Invalid document ID returned\n");
            throw new Exception("Document upload failed: could not retrieve document ID.");
        }
        
        // If a trail was selected, get the first step and update the document
        if ($trail_id) {
            fwrite($debug_log, "Processing trail_id: $trail_id\n");
            
            $sql = "SELECT id, department FROM document_trail_steps 
                    WHERE trail_id = :trail_id AND step_order = 1";
            $stmt = db()->prepare($sql);
            $stmt->execute(['trail_id' => $trail_id]);
            $firstStep = $stmt->fetch();
            
            if ($firstStep) {
                fwrite($debug_log, "Found first step with department: {$firstStep['department']}\n");
                
                // Update document with first step's department 
                // (if different from the initial department)
                if ($department != $firstStep['department']) {
                    fwrite($debug_log, "Updating document department from $department to {$firstStep['department']}\n");
                    
                    $sql = "UPDATE documents SET department = :department, current_trail_step = 1
                            WHERE id = :id";
                    $stmt = db()->prepare($sql);
                    $stmt->execute([
                        'department' => $firstStep['department'],
                        'id' => $documentId
                    ]);
                    
                    // Record the movement
                    $sql = "INSERT INTO document_movements 
                            (document_id, from_department, to_department, moved_by, note, moved_at) 
                            VALUES (:document_id, :from_department, :to_department, :moved_by, :note, NOW())";
                    $stmt = db()->prepare($sql);
                    $stmt->execute([
                        'document_id' => $documentId,
                        'from_department' => $department,
                        'to_department' => $firstStep['department'],
                        'moved_by' => $user_id,
                        'note' => 'Initial assignment based on document trail'
                    ]);
                    fwrite($debug_log, "Movement record created\n");
                } else {
                    // If department is the same, still set current_trail_step
                    fwrite($debug_log, "Department unchanged, only setting current_trail_step\n");
                    $sql = "UPDATE documents SET current_trail_step = 1 WHERE id = :id";
                    $stmt = db()->prepare($sql);
                    $stmt->execute(['id' => $documentId]);
                }
            } else {
                fwrite($debug_log, "No first step found for trail_id: $trail_id\n");
            }
        }
        
        // Generate QR code if submitter (contractor) is specified
        if ($submitter_id) {
            fwrite($debug_log, "Generating QR code for submitter_id: $submitter_id\n");
            try {
                $qrCodePath = generateDocumentQRCode($doc_unique_id, $submitter_id);
                fwrite($debug_log, "QR code path: $qrCodePath\n");
                
                // Save QR code path to document
                $sql = "UPDATE documents SET qr_code_path = :qr_code_path WHERE id = :id";
                $stmt = db()->prepare($sql);
                $stmt->execute([
                    'qr_code_path' => $qrCodePath,
                    'id' => $documentId
                ]);
                // Store document ID in session for QR display
                $_SESSION['last_uploaded_doc_id'] = $documentId;
                fwrite($debug_log, "QR code path saved to document\n");
            } catch (Exception $qrEx) {
                fwrite($debug_log, "ERROR generating QR code: " . $qrEx->getMessage() . "\n");
                // Continue even if QR generation fails
            }
        }
        
        // Send notification to contractor if one is associated
        if (!empty($submitter_id)) {
            fwrite($debug_log, "Sending notification to submitter_id: $submitter_id\n");
            try {
                $message = "Your document \"" . $title . "\" (ID: " . $doc_unique_id . ") has been submitted and is being processed.";
                
                $sql = "INSERT INTO client_notifications 
                        (client_id, document_id, message, is_read, created_at) 
                        VALUES (:client_id, :document_id, :message, 0, NOW())";
                $stmt = db()->prepare($sql);
                $stmt->execute([
                    'client_id' => $submitter_id,
                    'document_id' => $documentId,
                    'message' => $message
                ]);
                fwrite($debug_log, "Notification sent successfully\n");
            } catch (PDOException $e) {
                // Just log the error but continue - notification is not critical
                fwrite($debug_log, "ERROR sending notification: " . $e->getMessage() . "\n");
                error_log("Error sending notification: " . $e->getMessage());
            }
        }
        
        // Log activity
        try {
            logActivity('document_upload', "Uploaded document: $title ($doc_unique_id)");
            fwrite($debug_log, "Activity logged\n");
        } catch (Exception $logEx) {
            fwrite($debug_log, "ERROR logging activity: " . $logEx->getMessage() . "\n");
        }
        
        // Set success message
        $_SESSION['success'] = "Document uploaded successfully!";
        fwrite($debug_log, "Upload completed successfully, redirecting to view_document.php?id=$documentId\n");
        fclose($debug_log);
        
        // Redirect to view the document
        header("Location: ../public/view_document.php?id=$documentId");
        exit;
        
    } catch (PDOException $e) {
        if (isset($debug_log)) {
            fwrite($debug_log, "FATAL ERROR (PDOException): " . $e->getMessage() . "\n");
            fclose($debug_log);
        }
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while saving your document to the database. Please try again.";
        header('Location: ../public/upload.php');
        exit;
    } catch (Exception $e) {
        if (isset($debug_log)) {
            fwrite($debug_log, "FATAL ERROR (Exception): " . $e->getMessage() . "\n");
            fclose($debug_log);
        }
        error_log("General error during document upload: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while processing your document: " . $e->getMessage();
        header('Location: ../public/upload.php');
        exit;
    }
    
} else {
    // If not a POST request, redirect to upload page
    header('Location: ../public/upload.php');
    exit;
}
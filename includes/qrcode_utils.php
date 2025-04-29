<?php
/**
 * QR Code Utils
 * 
 * This file contains utilities for generating QR codes for document tracking.
 */

// Include the PHP QR Code library
// Note: This requires the PHP QR Code library to be installed
// via Composer or downloaded from http://phpqrcode.sourceforge.net/
// and placed in a library folder

/**
 * Generate a QR code for a document
 * 
 * @param string $documentId The document ID or unique identifier
 * @param string $contractorId The contractor/client ID
 * @param string $savePath Path where to save the QR code image
 * @return string The path to the generated QR code image
 */
function generateDocumentQRCode($documentId, $contractorId, $savePath = null) {
    // Create QR code content - includes document ID and contractor ID
    $qrContent = json_encode([
        'doc_id' => $documentId,
        'contractor_id' => $contractorId,
        'timestamp' => time()
    ]);
    
    // Define file name based on document ID
    $fileName = 'qrcode_' . preg_replace('/[^A-Za-z0-9]/', '_', $documentId) . '.png';
    
    // Determine save path
    if ($savePath === null) {
        $savePath = __DIR__ . '/../uploads/qrcodes/';
    }
    
    // Ensure directory exists
    if (!file_exists($savePath)) {
        mkdir($savePath, 0755, true);
    }
    
    $fullPath = $savePath . $fileName;
    
    // Check if we have the QR code library or use a fallback
    if (class_exists('QRcode')) {
        // Use QR code library if available
        \QRcode::png($qrContent, $fullPath, 'L', 4, 2);
    } else {
        // Fallback to Google Charts API
        $size = '300x300';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://chart.googleapis.com/chart?cht=qr&chs=' . $size . '&chl=' . urlencode($qrContent));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $qrImage = curl_exec($ch);
        curl_close($ch);
        
        // Save the image
        file_put_contents($fullPath, $qrImage);
    }
    
    return 'uploads/qrcodes/' . $fileName;
}

/**
 * Generate a tracking URL for a document
 * 
 * @param string $documentId The document unique ID
 * @return string The tracking URL
 */
function getDocumentTrackingURL($documentId) {
    // Get the current domain
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domain = $_SERVER['HTTP_HOST'];
    
    // Create the tracking URL
    return $protocol . $domain . '/county_gov_tracking_system/public/track.php?doc_id=' . urlencode($documentId);
}

/**
 * Extract information from a QR code
 * 
 * @param string $qrContent The content from the QR code
 * @return array The extracted information
 */
function parseQRCode($qrContent) {
    $data = json_decode($qrContent, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $data;
    }
    return false;
}
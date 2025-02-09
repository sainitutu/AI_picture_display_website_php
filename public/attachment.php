<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Database.php';

// Enable error reporting and increase memory limit
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '512M');

// Disable output buffering
while (ob_get_level()) {
    ob_end_clean();
}

error_log("Attachment: Starting request");
error_log("Attachment: GET params: " . print_r($_GET, true));

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    error_log("Attachment: No ID provided");
    header('HTTP/1.0 404 Not Found');
    exit;
}

error_log("Attachment: Looking for ID: " . $id);

$db = new Database();
try {
    // Get the attachment by ID
    $attachment = $db->getAttachmentById($id);
    if (!$attachment || empty($attachment['file_path'])) {
        error_log("Attachment: No valid attachment found for ID: " . $id);
        error_log("Attachment details: " . print_r($attachment, true));
        header('HTTP/1.0 404 Not Found');
        echo "找不到副件檔案";
        exit;
    }
    error_log("Attachment: Attachment found: " . print_r($attachment, true));

    // 確保檔案路徑正確且在允許的目錄內
    $requestedPath = ATTACHMENT_PATH . $attachment['file_path'];
    error_log("Attachment: Requested path: " . $requestedPath);
    
    $filePath = realpath($requestedPath);
    if (!$filePath) {
        error_log("Attachment: Cannot resolve file path: " . $requestedPath);
        header('HTTP/1.0 404 Not Found');
        echo "找不到副件檔案";
        exit;
    }
    
    // 確保檔案在允許的目錄內
    $assetsDir = realpath(ATTACHMENT_PATH);
    if (strpos($filePath, $assetsDir) !== 0) {
        error_log("Attachment: File path is outside allowed directory");
        error_log("Attachment: Assets dir: " . $assetsDir);
        error_log("Attachment: File path: " . $filePath);
        header('HTTP/1.0 403 Forbidden');
        echo "無法存取此檔案";
        exit;
    }
    
    error_log("Attachment: Resolved file path: " . $filePath);
} catch (Exception $e) {
    error_log("Attachment: Error: " . $e->getMessage());
    header('HTTP/1.0 500 Internal Server Error');
    exit;
}
error_log("Attachment: Trying to access file: " . $filePath);

if (!file_exists($filePath) || !is_readable($filePath)) {
    error_log("Attachment: File not found or not readable: " . $filePath);
    error_log("Attachment: File exists: " . (file_exists($filePath) ? 'Yes' : 'No'));
    error_log("Attachment: File readable: " . (is_readable($filePath) ? 'Yes' : 'No'));
    error_log("Attachment: File permissions: " . substr(sprintf('%o', fileperms($filePath)), -4));
    header('HTTP/1.0 404 Not Found');
    echo "找不到副件檔案或無法讀取";
    exit;
}

// Log file information
$filesize = filesize($filePath);
error_log("Attachment: File exists, size: " . $filesize . " bytes");
error_log("Attachment: File mime type: " . mime_content_type($filePath));
error_log("Attachment: File permissions: " . substr(sprintf('%o', fileperms($filePath)), -4));

// Add cache control headers
$etag = md5_file($filePath);
$lastModified = gmdate('D, d M Y H:i:s', filemtime($filePath)) . ' GMT';

header('ETag: "' . $etag . '"');
header('Last-Modified: ' . $lastModified);
header('Cache-Control: public, max-age=31536000'); // Cache for 1 year

$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mimeTypes = [
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'json' => 'application/json',
    'txt' => 'text/plain'
];

// 驗證檔案類型
$actualMime = mime_content_type($filePath);
$expectedMime = $mimeTypes[$extension] ?? 'application/octet-stream';
error_log("Attachment: Expected mime type: " . $expectedMime);
error_log("Attachment: Actual mime type: " . $actualMime);

// 檢查是否為支援的圖片類型
$isImage = in_array($extension, ['png', 'jpg', 'jpeg', 'webp']);
if ($isImage) {
    $isValidImage = strpos($actualMime, 'image/') === 0;
    if (!$isValidImage) {
        error_log("Attachment: Invalid image file");
        header('HTTP/1.0 400 Bad Request');
        echo "無效的圖片檔案";
        exit;
    }
}

$contentType = $mimeTypes[$extension] ?? 'application/octet-stream';
header('Content-Type: ' . $contentType);
header('Content-Length: ' . $filesize);
header('Accept-Ranges: bytes');

if (!in_array($extension, ['png', 'jpg', 'jpeg', 'webp'])) {
    // For non-image files, force download
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
}

// 直接使用 readfile 輸出檔案
error_log("Attachment: Starting file output");
if (@readfile($filePath) === false) {
    error_log("Attachment: Error reading file");
    header('HTTP/1.0 500 Internal Server Error');
    exit;
}
error_log("Attachment: Finished file output");

<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Database.php';

// Increase memory limit for large files
ini_set('memory_limit', '512M');

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) {
        throw new Exception('Invalid image ID');
    }

    $db = new Database();
    $image = $db->getImage($id);

    if (!$image) {
        throw new Exception('Image not found');
    }

    $filepath = UPLOAD_PATH . $image['image_path'];
    if (!file_exists($filepath)) {
        throw new Exception('Image file not found');
    }

    // Get file information
    $filesize = filesize($filepath);
    if ($filesize === false) {
        throw new Exception('Could not get file size');
    }

    $mime = mime_content_type($filepath);
    if ($mime === false) {
        throw new Exception('Could not determine mime type');
    }

    // Set headers for proper file streaming
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . $filesize);
    header('Content-Disposition: inline; filename="' . basename($filepath) . '"');
    header('Accept-Ranges: bytes');
    
    // Add cache control headers
    $etag = md5_file($filepath);
    $lastModified = gmdate('D, d M Y H:i:s', filemtime($filepath)) . ' GMT';
    
    header('ETag: "' . $etag . '"');
    header('Last-Modified: ' . $lastModified);
    header('Cache-Control: public, max-age=31536000'); // Cache for 1 year

    // Check if the file has been modified
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') === $etag) {
        header('HTTP/1.1 304 Not Modified');
        exit;
    }

    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= filemtime($filepath)) {
        header('HTTP/1.1 304 Not Modified');
        exit;
    }

    // Stream file in chunks to prevent memory issues with large files
    $handle = fopen($filepath, 'rb');
    if ($handle === false) {
        throw new Exception('Could not open file for reading');
    }

    // Stream the file in 8KB chunks
    $chunkSize = 8192;
    while (!feof($handle)) {
        $chunk = fread($handle, $chunkSize);
        if ($chunk === false) {
            throw new Exception('Error reading file chunk');
        }
        echo $chunk;
        flush();
    }

    fclose($handle);

} catch (Exception $e) {
    error_log("Error serving image ID $id: " . $e->getMessage());
    
    switch ($e->getMessage()) {
        case 'Invalid image ID':
        case 'Image not found':
        case 'Image file not found':
            header('HTTP/1.0 404 Not Found');
            break;
        default:
            header('HTTP/1.0 500 Internal Server Error');
    }
    exit;
}

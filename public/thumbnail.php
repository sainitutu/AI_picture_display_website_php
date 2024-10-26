<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

$db = new Database();
$image = $db->getImage($id);

if (!$image) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

$originalPath = UPLOAD_PATH . $image['image_path'];
$thumbnailPath = THUMBNAIL_PATH . $image['image_path'];

if (!file_exists($originalPath)) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

// Generate thumbnail if it doesn't exist
if (!file_exists($thumbnailPath)) {
    $maxWidth = 300;
    $maxHeight = 300;

    list($width, $height) = getimagesize($originalPath);
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    $newWidth = $width * $ratio;
    $newHeight = $height * $ratio;

    $extension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION));
    
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            $source = imagecreatefromjpeg($originalPath);
            break;
        case 'png':
            $source = imagecreatefrompng($originalPath);
            break;
        case 'gif':
            $source = imagecreatefromgif($originalPath);
            break;
        case 'webp':
            $source = imagecreatefromwebp($originalPath);
            break;
        default:
            header('HTTP/1.0 400 Bad Request');
            exit;
    }

    $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG images
    if ($extension === 'png') {
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
    }

    imagecopyresampled(
        $thumbnail, $source,
        0, 0, 0, 0,
        $newWidth, $newHeight,
        $width, $height
    );

    // Create directory if it doesn't exist
    $thumbnailDir = dirname($thumbnailPath);
    if (!file_exists($thumbnailDir)) {
        mkdir($thumbnailDir, 0777, true);
    }

    // Save thumbnail
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($thumbnail, $thumbnailPath, 85);
            break;
        case 'png':
            imagepng($thumbnail, $thumbnailPath, 8);
            break;
        case 'gif':
            imagegif($thumbnail, $thumbnailPath);
            break;
        case 'webp':
            imagewebp($thumbnail, $thumbnailPath, 85);
            break;
    }

    imagedestroy($source);
    imagedestroy($thumbnail);
}

// Output thumbnail
$mime = mime_content_type($thumbnailPath);
header('Content-Type: ' . $mime);
readfile($thumbnailPath);

<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Database.php';

// Increase memory limit for large image processing
ini_set('memory_limit', '512M');

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
    try {
        $maxWidth = 300;
        $maxHeight = 300;

        // Get image info without loading the whole image
        $imageInfo = getimagesize($originalPath);
        if ($imageInfo === false) {
            throw new Exception('Could not get image size');
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $mime = $imageInfo['mime'];

        // Calculate new dimensions
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);

        $extension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION));
        
        // Create source image with error handling
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $source = @imagecreatefromjpeg($originalPath);
                break;
            case 'png':
                $source = @imagecreatefrompng($originalPath);
                break;
            case 'gif':
                $source = @imagecreatefromgif($originalPath);
                break;
            case 'webp':
                $source = @imagecreatefromwebp($originalPath);
                break;
            default:
                throw new Exception('Unsupported image format');
        }

        if (!$source) {
            throw new Exception('Failed to create source image');
        }

        // Create thumbnail with error handling
        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
        if (!$thumbnail) {
            throw new Exception('Failed to create thumbnail canvas');
        }
        
        // Preserve transparency for PNG images
        if ($extension === 'png') {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            
            // Fill with transparent background
            $transparent = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
            imagefilledrectangle($thumbnail, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Use progressive resizing for very large images
        if ($width > 2000 || $height > 2000) {
            $steps = ceil(log(max($width / $newWidth, $height / $newHeight), 2));
            $tmpWidth = $width;
            $tmpHeight = $height;
            $tmpImage = $source;

            for ($i = 1; $i < $steps; $i++) {
                $stepWidth = (int)($width / pow(2, $i));
                $stepHeight = (int)($height / pow(2, $i));
                
                $temp = imagecreatetruecolor($stepWidth, $stepHeight);
                if ($extension === 'png') {
                    imagealphablending($temp, false);
                    imagesavealpha($temp, true);
                }
                
                imagecopyresampled(
                    $temp, $tmpImage,
                    0, 0, 0, 0,
                    $stepWidth, $stepHeight,
                    $tmpWidth, $tmpHeight
                );
                
                if ($i > 1) {
                    imagedestroy($tmpImage);
                }
                
                $tmpImage = $temp;
                $tmpWidth = $stepWidth;
                $tmpHeight = $stepHeight;
            }

            // Final resize to target dimensions
            imagecopyresampled(
                $thumbnail, $tmpImage,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $tmpWidth, $tmpHeight
            );

            if ($steps > 1) {
                imagedestroy($tmpImage);
            }
        } else {
            // Direct resize for smaller images
            imagecopyresampled(
                $thumbnail, $source,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $width, $height
            );
        }

        // Create directory if it doesn't exist
        $thumbnailDir = dirname($thumbnailPath);
        if (!file_exists($thumbnailDir)) {
            if (!mkdir($thumbnailDir, 0777, true)) {
                throw new Exception('Failed to create thumbnail directory');
            }
        }

        // Save thumbnail with error handling
        $success = false;
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $success = imagejpeg($thumbnail, $thumbnailPath, 85);
                break;
            case 'png':
                $success = imagepng($thumbnail, $thumbnailPath, 8);
                break;
            case 'gif':
                $success = imagegif($thumbnail, $thumbnailPath);
                break;
            case 'webp':
                $success = imagewebp($thumbnail, $thumbnailPath, 85);
                break;
        }

        if (!$success) {
            throw new Exception('Failed to save thumbnail');
        }

        // Clean up
        imagedestroy($source);
        imagedestroy($thumbnail);

    } catch (Exception $e) {
        // Log error
        error_log("Thumbnail generation failed for image ID $id: " . $e->getMessage());
        
        // Create a fallback thumbnail
        $fallbackWidth = 300;
        $fallbackHeight = 300;
        $fallback = imagecreatetruecolor($fallbackWidth, $fallbackHeight);
        $bgColor = imagecolorallocate($fallback, 240, 240, 240);
        $textColor = imagecolorallocate($fallback, 120, 120, 120);
        
        imagefilledrectangle($fallback, 0, 0, $fallbackWidth, $fallbackHeight, $bgColor);
        
        // Add error text
        $text = "Error loading image";
        $font = 5; // Built-in font
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);
        $x = ($fallbackWidth - $textWidth) / 2;
        $y = ($fallbackHeight - $textHeight) / 2;
        
        imagestring($fallback, $font, $x, $y, $text, $textColor);
        
        // Save fallback thumbnail
        imagejpeg($fallback, $thumbnailPath, 85);
        imagedestroy($fallback);
    }
}

// Output thumbnail
try {
    if (!file_exists($thumbnailPath)) {
        throw new Exception('Thumbnail file not found');
    }
    
    $mime = mime_content_type($thumbnailPath);
    if ($mime === false) {
        throw new Exception('Could not determine mime type');
    }
    
    header('Content-Type: ' . $mime);
    if (!readfile($thumbnailPath)) {
        throw new Exception('Failed to output thumbnail');
    }
} catch (Exception $e) {
    error_log("Error serving thumbnail for image ID $id: " . $e->getMessage());
    header('HTTP/1.0 500 Internal Server Error');
    exit;
}

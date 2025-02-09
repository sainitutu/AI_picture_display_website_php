<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';

try {
    $db = getDB();
    $stmt = $db->query('SELECT * FROM attachments');
    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Attachments in database:\n";
    foreach ($attachments as $attachment) {
        echo "\nAttachment ID: " . $attachment['id'] . "\n";
        echo "Image ID: " . $attachment['image_id'] . "\n";
        echo "File Path: " . $attachment['file_path'] . "\n";
        echo "Created At: " . $attachment['created_at'] . "\n";
        
        $fullPath = __DIR__ . '/../assets/' . $attachment['file_path'];
        echo "Full Path: " . $fullPath . "\n";
        echo "File Exists: " . (file_exists($fullPath) ? "Yes" : "No") . "\n";
        if (file_exists($fullPath)) {
            echo "File Size: " . filesize($fullPath) . " bytes\n";
            echo "File Permissions: " . substr(sprintf('%o', fileperms($fullPath)), -4) . "\n";
        }
        echo "----------------------------------------\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

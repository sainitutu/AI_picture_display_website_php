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

$filepath = UPLOAD_PATH . $image['image_path'];
if (!file_exists($filepath)) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

$mime = mime_content_type($filepath);
header('Content-Type: ' . $mime);
readfile($filepath);

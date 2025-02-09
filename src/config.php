<?php
define('DB_PATH', __DIR__ . '/../database/aishow.db');
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');
define('THUMBNAIL_PATH', __DIR__ . '/../assets/thumbnails/');
define('ATTACHMENT_PATH', __DIR__ . '/../assets/');

// Set up error logging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_error.log');

// Create logs directory if it doesn't exist
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0777, true);
}

// Initialize database connection
function getDB() {
    static $db = null;
    if ($db === null) {
        try {
            $db = new PDO('sqlite:' . DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->exec('PRAGMA foreign_keys = ON;');
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $db;
}

// Create required directories if they don't exist
$directories = [
    dirname(DB_PATH),
    UPLOAD_PATH,
    THUMBNAIL_PATH,
    ATTACHMENT_PATH . 'attachments',
    __DIR__ . '/../logs'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Initialize database if it doesn't exist
if (!file_exists(DB_PATH)) {
    $db = getDB();
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    $db->exec($schema);
}

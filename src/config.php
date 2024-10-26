<?php
define('DB_PATH', __DIR__ . '/../database/aishow.db');
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');
define('THUMBNAIL_PATH', __DIR__ . '/../assets/thumbnails/');

// Ensure error reporting is enabled during development
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    THUMBNAIL_PATH
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

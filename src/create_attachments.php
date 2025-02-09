<?php
require_once __DIR__ . '/config.php';

try {
    $db = getDB();
    
    // Drop existing table if exists
    $db->exec('DROP TABLE IF EXISTS attachments');
    
    // Create attachments table
    $db->exec('
        CREATE TABLE attachments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            image_id INTEGER NOT NULL,
            file_path TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE CASCADE
        );
        
        CREATE INDEX idx_attachments_image_id ON attachments(image_id);
    ');
    
    echo "Attachments table created successfully.\n";
} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}

<?php
require_once __DIR__ . '/config.php';

try {
    $db = getDB();
    
    // Create attachments table
    $db->exec('
        CREATE TABLE IF NOT EXISTS attachments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            image_id INTEGER NOT NULL,
            file_path TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE CASCADE
        );
        
        CREATE INDEX IF NOT EXISTS idx_attachments_image_id ON attachments(image_id);
    ');
    
    echo "Schema updated successfully.\n";
} catch (Exception $e) {
    echo "Error updating schema: " . $e->getMessage() . "\n";
}

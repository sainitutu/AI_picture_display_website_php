<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/cleanup.php';

header('Content-Type: application/json');

try {
    // Check if directories are writable
    if (!is_writable(UPLOAD_PATH)) {
        throw new Exception('上傳目錄沒有寫入權限: ' . UPLOAD_PATH);
    }
    if (!is_writable(THUMBNAIL_PATH)) {
        throw new Exception('縮圖目錄沒有寫入權限: ' . THUMBNAIL_PATH);
    }

    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    // Increase memory limit and execution time for large directories
    ini_set('memory_limit', '512M');
    set_time_limit(300); // 5 minutes

    $cleanup = new ImageCleanup(false, true);
    $results = $cleanup->cleanup();
    
    if ($results === false) {
        throw new Exception('清理過程發生錯誤');
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'message' => sprintf(
            '已清理 %d 個未使用的上傳檔案、%d 個未使用的縮圖檔案和 %d 個未使用的副件檔案',
            $results['deleted_uploads'],
            $results['deleted_thumbnails'],
            $results['deleted_attachments']
        )
    ]);
} catch (Exception $e) {
    // Log the error with full details
    error_log("Cleanup error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '整理資料時發生錯誤: ' . $e->getMessage()
    ]);
}

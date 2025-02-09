<?php
require_once __DIR__ . '/config.php';

// Get the last 100 lines of the error log
function tail($filename, $lines = 100) {
    $file = file($filename);
    if (!$file) return "無法讀取日誌檔案";
    
    return implode("", array_slice($file, -$lines));
}

$logFile = __DIR__ . '/../logs/php_error.log';
if (file_exists($logFile)) {
    echo "最近的錯誤日誌：\n\n";
    echo tail($logFile);
} else {
    echo "日誌檔案尚未建立";
}

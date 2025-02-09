<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';

try {
    $db = getDB();
    
    // 檢查資料表是否存在
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    echo "資料表列表：\n";
    print_r($tables);
    echo "\n";

    // 檢查 attachments 表結構
    if (in_array('attachments', $tables)) {
        $columns = $db->query("PRAGMA table_info(attachments)")->fetchAll(PDO::FETCH_ASSOC);
        echo "attachments 表結構：\n";
        print_r($columns);
        echo "\n";

        // 檢查 attachments 表內容
        $attachments = $db->query("SELECT * FROM attachments")->fetchAll(PDO::FETCH_ASSOC);
        echo "attachments 表內容：\n";
        print_r($attachments);
        echo "\n";
    } else {
        echo "attachments 表不存在\n";
    }
} catch (Exception $e) {
    echo "錯誤：" . $e->getMessage() . "\n";
}

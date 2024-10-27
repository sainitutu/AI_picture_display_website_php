<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => '不允許的請求方法']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $prompt = isset($data['prompt']) ? trim($data['prompt']) : '';

    if (empty($prompt)) {
        echo json_encode(['error' => '提詞不能為空']);
        exit;
    }

    // Get database connection
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all keywords from database
    $stmt = $db->prepare('SELECT keyword FROM keywords');
    $stmt->execute();
    $dbKeywords = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Split prompt into parts
    $promptParts = array_map('trim', explode(',', $prompt));
    
    // Find matches
    $matches = [];
    foreach ($promptParts as $part) {
        foreach ($dbKeywords as $keyword) {
            if (stripos($part, $keyword) !== false) {
                $matches[] = $keyword;
            }
        }
    }
    
    // Remove duplicates
    $matches = array_unique($matches);
    
    echo json_encode([
        'success' => true,
        'keywords' => array_values($matches)
    ]);

} catch (Exception $e) {
    error_log('Analyze keywords error: ' . $e->getMessage());
    echo json_encode([
        'error' => '分析關鍵詞失敗: ' . $e->getMessage()
    ]);
}

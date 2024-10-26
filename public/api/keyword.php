<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '不允許的請求方法']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$keyword = isset($data['keyword']) ? trim($data['keyword']) : '';

if (empty($keyword)) {
    http_response_code(400);
    echo json_encode(['error' => '關鍵詞不能為空']);
    exit;
}

try {
    $db = new Database();
    if ($db->addKeyword($keyword)) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => '關鍵詞已存在']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => '新增關鍵詞失敗']);
}

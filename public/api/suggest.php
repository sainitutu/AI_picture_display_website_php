<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/Database.php';

header('Content-Type: application/json');

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($query)) {
    echo json_encode([]);
    exit;
}

try {
    $db = new Database();
    $suggestions = $db->getKeywordSuggestions($query);
    echo json_encode($suggestions);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch suggestions']);
}

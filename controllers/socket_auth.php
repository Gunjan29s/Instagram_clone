<?php
require_once __DIR__ . '/../views/_page_helpers.php';
app_start_session();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

echo json_encode([
    'success' => true,
    'user_id' => (int) $_SESSION['user_id'],
    'username' => (string) ($_SESSION['username'] ?? ''),
]);

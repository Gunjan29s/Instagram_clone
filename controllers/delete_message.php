<?php
require_once __DIR__ . '/../views/_page_helpers.php';
app_start_session();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_csrf();
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();
$userId = (int) $_SESSION['user_id'];
$messageId = (int) ($_POST['message_id'] ?? 0);

if ($messageId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid message']);
    exit;
}

$stmt = $db->prepare('DELETE FROM messages WHERE id = ? AND sender_id = ?');
$stmt->execute([$messageId, $userId]);

echo json_encode(['success' => $stmt->rowCount() > 0]);

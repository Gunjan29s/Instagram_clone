<?php
// controllers/block_user.php
// User ko block / unblock karna (AJAX JSON endpoint)

require_once __DIR__ . '/../views/_page_helpers.php';
app_start_session();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}
require_csrf();

require_once __DIR__ . '/../config/database.php';

$db         = Database::getInstance()->getConnection();
$blockerId  = (int) $_SESSION['user_id'];
$blockedId  = (int) ($_POST['user_id'] ?? 0);
$action     = trim($_POST['action'] ?? ''); // 'block' or 'unblock'

if ($blockedId <= 0 || $blockedId === $blockerId || !in_array($action, ['block', 'unblock'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid params']);
    exit;
}

// blocked_users table ensure karo
$db->exec("
    CREATE TABLE IF NOT EXISTS blocked_users (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        blocker_id  INT NOT NULL,
        blocked_id  INT NOT NULL,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_block (blocker_id, blocked_id),
        FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
    )
");

if ($action === 'block') {
    // Block karo
    $db->prepare(
        "INSERT IGNORE INTO blocked_users (blocker_id, blocked_id, created_at) VALUES (?, ?, NOW())"
    )->execute([$blockerId, $blockedId]);

    // Dono taraf se unfollow karo
    $db->prepare("DELETE FROM follows WHERE (follower_id = ? AND following_id = ?) OR (follower_id = ? AND following_id = ?)")
       ->execute([$blockerId, $blockedId, $blockedId, $blockerId]);

    // Follow requests bhi delete karo
    $db->prepare("DELETE FROM follow_requests WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)")
       ->execute([$blockerId, $blockedId, $blockedId, $blockerId]);

    // Follow notifications hatao taaki Follow Back dobara na dikhe
    $db->prepare("DELETE FROM notifications WHERE (user_id = ? AND from_user_id = ?) OR (user_id = ? AND from_user_id = ?)")
       ->execute([$blockerId, $blockedId, $blockedId, $blockerId]);

    // Message requests bhi delete karo
    $db->prepare("DELETE FROM message_requests WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)")
       ->execute([$blockerId, $blockedId, $blockedId, $blockerId]);

    echo json_encode(['success' => true, 'state' => 'blocked']);

} else {
    // Unblock karo
    $db->prepare(
        "DELETE FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?"
    )->execute([$blockerId, $blockedId]);

    echo json_encode(['success' => true, 'state' => 'unblocked']);
}

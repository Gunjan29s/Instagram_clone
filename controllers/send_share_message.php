<?php
// controllers/send_share_message.php
require_once __DIR__ . '/../views/_page_helpers.php';
app_start_session();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}
require_csrf();

require_once __DIR__ . '/../config/database.php';
$db         = Database::getInstance()->getConnection();
$senderId   = (int) $_SESSION['user_id'];
$receiverId = (int) ($_POST['receiver_id'] ?? 0);
$message    = trim($_POST['message'] ?? '');

if (strlen($message) > 2000) {
    $message = substr($message, 0, 2000);
}

if ($receiverId <= 0 || $receiverId === $senderId || $message === '') {
    echo json_encode(['success' => false]);
    exit;
}

// Verify receiver exists
$chk = $db->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
$chk->execute([$receiverId]);
if (!$chk->fetch()) {
    echo json_encode(['success' => false]);
    exit;
}

$blocked = $db->prepare(
    "SELECT id FROM blocked_users
     WHERE (blocker_id = ? AND blocked_id = ?)
        OR (blocker_id = ? AND blocked_id = ?)
     LIMIT 1"
);
$blocked->execute([$senderId, $receiverId, $receiverId, $senderId]);
if ($blocked->fetch()) {
    echo json_encode(['success' => false]);
    exit;
}

$db->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())")
   ->execute([$senderId, $receiverId, $message]);
$messageId = (int) $db->lastInsertId();

$fc = $db->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ? LIMIT 1");
$fc->execute([$senderId, $receiverId]);
$isFollowingReceiver = (bool) $fc->fetch();

$rc = $db->prepare("SELECT status FROM message_requests WHERE sender_id = ? AND receiver_id = ? LIMIT 1");
$rc->execute([$senderId, $receiverId]);
$existingRequest = $rc->fetch();
$requestAccepted = ($existingRequest && $existingRequest['status'] === 'accepted');

if (!$isFollowingReceiver && !$requestAccepted) {
    if (!$existingRequest) {
        $db->prepare("INSERT IGNORE INTO message_requests (sender_id, receiver_id, status, created_at) VALUES (?, ?, 'pending', NOW())")
           ->execute([$senderId, $receiverId]);
    }
} else {
    try {
        $db->prepare("INSERT INTO notifications (user_id, from_user_id, type, created_at) VALUES (?, ?, 'message', NOW())")
           ->execute([$receiverId, $senderId]);
    } catch (Exception $e) {}
}

echo json_encode([
    'success' => true,
    'message' => [
        'id' => $messageId,
        'sender_id' => $senderId,
        'receiver_id' => $receiverId,
        'message' => $message,
        'created_at' => date('Y-m-d H:i:s'),
    ],
]);

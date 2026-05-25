<?php
require_once __DIR__ . '/../views/_page_helpers.php';
app_start_session();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();
$senderId = (int) $_SESSION['user_id'];
$receiverId = (int) ($_POST['receiver_id'] ?? 0);
$message = trim((string) ($_POST['message'] ?? ''));
$sharePostId = (int) ($_POST['share_post_id'] ?? 0);

if (strlen($message) > 2000) {
    $message = substr($message, 0, 2000);
}

if ($sharePostId > 0) {
    $pc = $db->prepare('SELECT id FROM posts WHERE id = ? LIMIT 1');
    $pc->execute([$sharePostId]);
    if ($pc->fetch()) {
        $message = '__POST_SHARE__:' . $sharePostId;
    }
}

if ($receiverId <= 0 || $receiverId === $senderId || $message === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid message']);
    exit;
}

$rx = $db->prepare('SELECT id, username, profile_pic FROM users WHERE id = ? LIMIT 1');
$rx->execute([$receiverId]);
$receiver = $rx->fetch();

if (!$receiver) {
    echo json_encode(['success' => false, 'message' => 'Receiver not found']);
    exit;
}

$blocked = $db->prepare(
    'SELECT id FROM blocked_users
     WHERE (blocker_id = ? AND blocked_id = ?)
        OR (blocker_id = ? AND blocked_id = ?)
     LIMIT 1'
);
$blocked->execute([$senderId, $receiverId, $receiverId, $senderId]);

if ($blocked->fetch()) {
    echo json_encode(['success' => false, 'message' => 'User is blocked']);
    exit;
}

$fc = $db->prepare('SELECT id FROM follows WHERE follower_id = ? AND following_id = ? LIMIT 1');
$fc->execute([$senderId, $receiverId]);
$isFollowingReceiver = (bool) $fc->fetch();

$rc = $db->prepare('SELECT status FROM message_requests WHERE sender_id = ? AND receiver_id = ? LIMIT 1');
$rc->execute([$senderId, $receiverId]);
$existingRequest = $rc->fetch();
$requestAccepted = ($existingRequest && $existingRequest['status'] === 'accepted');

$stmt = $db->prepare('INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())');
$stmt->execute([$senderId, $receiverId, $message]);
$messageId = (int) $db->lastInsertId();

if (!$isFollowingReceiver && !$requestAccepted) {
    if (!$existingRequest) {
        $db->prepare("INSERT IGNORE INTO message_requests (sender_id, receiver_id, status, created_at) VALUES (?, ?, 'pending', NOW())")
            ->execute([$senderId, $receiverId]);
    }
} else {
    try {
        $db->prepare("INSERT INTO notifications (user_id, from_user_id, type, created_at) VALUES (?, ?, 'message', NOW())")
            ->execute([$receiverId, $senderId]);
    } catch (Throwable $e) {
    }
}

$senderStmt = $db->prepare('SELECT id, username, profile_pic FROM users WHERE id = ? LIMIT 1');
$senderStmt->execute([$senderId]);
$sender = $senderStmt->fetch() ?: ['id' => $senderId, 'username' => 'user', 'profile_pic' => ''];

echo json_encode([
    'success' => true,
    'message' => [
        'id' => $messageId,
        'sender_id' => $senderId,
        'receiver_id' => $receiverId,
        'message' => $message,
        'created_at' => date('Y-m-d H:i:s'),
        'sender_username' => $sender['username'] ?? 'user',
        'sender_profile_pic' => $sender['profile_pic'] ?? '',
        'receiver_username' => $receiver['username'] ?? 'user',
        'is_request' => (!$isFollowingReceiver && !$requestAccepted),
    ],
]);

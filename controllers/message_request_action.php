<?php
// controllers/message_request_action.php
require_once __DIR__ . '/../views/_page_helpers.php';
app_start_session();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}
require_csrf();

require_once __DIR__ . '/../config/database.php';

$db       = Database::getInstance()->getConnection();
$uid      = (int) $_SESSION['user_id'];
$senderId = (int) ($_POST['sender_id'] ?? 0);
$action   = $_POST['action'] ?? '';

if ($senderId <= 0 || !in_array($action, ['accept', 'decline'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid params']);
    exit;
}

// Ensure notifications table has 'message_request' type — add it if needed
try {
    $db->exec("ALTER TABLE notifications MODIFY type ENUM('like','comment','follow','mention','message','message_request_declined','follow_request') NOT NULL");
} catch (Exception $e) { /* already up to date */ }

// Verify request exists
$chk = $db->prepare("SELECT id FROM message_requests WHERE sender_id=? AND receiver_id=? AND status='pending' LIMIT 1");
$chk->execute([$senderId, $uid]);
if (!$chk->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Request not found']);
    exit;
}

if ($action === 'accept') {
    $db->prepare("UPDATE message_requests SET status='accepted' WHERE sender_id=? AND receiver_id=?")
       ->execute([$senderId, $uid]);

    // Notification: sender ko batao ki request accept ho gayi
    try {
        $db->prepare("INSERT INTO notifications (user_id, from_user_id, type, created_at) VALUES (?, ?, 'message', NOW())")
           ->execute([$senderId, $uid]);
    } catch (Exception $e) {}

    echo json_encode(['success' => true, 'state' => 'accepted']);

} else {
    // Decline — request delete karo, messages bhi delete karo
    $db->prepare("DELETE FROM message_requests WHERE sender_id=? AND receiver_id=?")
       ->execute([$senderId, $uid]);
    $db->prepare("DELETE FROM messages WHERE sender_id=? AND receiver_id=?")
       ->execute([$senderId, $uid]);

    // Notification: sender ko "not interested" notification bhejo
    try {
        $db->prepare("INSERT INTO notifications (user_id, from_user_id, type, created_at) VALUES (?, ?, 'message_request_declined', NOW())")
           ->execute([$senderId, $uid]);
    } catch (Exception $e) {
        // Fallback agar enum update nahi hua
        try {
            $db->exec("ALTER TABLE notifications MODIFY type ENUM('like','comment','follow','mention','message','message_request_declined','follow_request') NOT NULL");
            $db->prepare("INSERT INTO notifications (user_id, from_user_id, type, created_at) VALUES (?, ?, 'message_request_declined', NOW())")
               ->execute([$senderId, $uid]);
        } catch (Exception $e2) {}
    }

    echo json_encode(['success' => true, 'state' => 'declined']);
}

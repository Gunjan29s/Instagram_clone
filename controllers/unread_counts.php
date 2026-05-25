<?php
// controllers/unread_counts.php
// AJAX endpoint — unread notifications + messages count return karta hai

require_once __DIR__ . '/../views/_page_helpers.php';
app_start_session();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['notifications' => 0, 'messages' => 0, 'requests' => 0]);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$db  = Database::getInstance()->getConnection();
$uid = (int) $_SESSION['user_id'];

// Unread notifications
$nStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$nStmt->execute([$uid]);
$notifCount = (int) $nStmt->fetchColumn();

// Unread messages (from accepted conversations only)
$msgCount = 0;
try {
    $mStmt = $db->prepare("
        SELECT COUNT(*)
        FROM messages
        WHERE receiver_id = ?
          AND is_read = 0
          AND sender_id NOT IN (
              SELECT sender_id FROM message_requests
              WHERE receiver_id = ? AND status = 'pending'
          )
    ");
    $mStmt->execute([$uid, $uid]);
    $msgCount = (int) $mStmt->fetchColumn();
} catch (Exception $e) {
    // Fallback if message_requests table doesn't exist yet
    $mStmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $mStmt->execute([$uid]);
    $msgCount = (int) $mStmt->fetchColumn();
}

// Pending message requests
$reqCount = 0;
try {
    $rStmt = $db->prepare("
        SELECT COUNT(DISTINCT sender_id)
        FROM message_requests
        WHERE receiver_id = ? AND status = 'pending'
    ");
    $rStmt->execute([$uid]);
    $reqCount = (int) $rStmt->fetchColumn();
} catch (Exception $e) { /* table may not exist yet */ }

echo json_encode([
    'notifications' => $notifCount,
    'messages'      => $msgCount + $reqCount,
    'requests'      => $reqCount,
]);

<?php
// controllers/handle_follow_request.php
// Approve or decline a follow request (AJAX JSON endpoint)

require_once __DIR__ . '/../views/_page_helpers.php';
app_start_session();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Follow requests are disabled.']);

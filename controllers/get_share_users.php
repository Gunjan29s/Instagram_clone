<?php
// controllers/get_share_users.php
// Share modal ke liye followed users return karta hai

require_once __DIR__ . '/../views/_page_helpers.php';
app_start_session();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['users' => []]);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$db  = Database::getInstance()->getConnection();
$uid = (int) $_SESSION['user_id'];

// Jinhe current user follow karta hai unhe fetch karo
$stmt = $db->prepare("
    SELECT u.id, u.username, u.full_name, u.profile_pic
    FROM follows f
    JOIN users u ON u.id = f.following_id
    WHERE f.follower_id = ?
    ORDER BY u.username ASC
    LIMIT 50
");
$stmt->execute([$uid]);
$rows = $stmt->fetchAll();

$users = [];
foreach ($rows as $row) {
    $users[] = [
        'id'       => (int) $row['id'],
        'username' => $row['username'],
        'avatar'   => profile_avatar($row['profile_pic'] ?? '', $row['username'] ?? 'User'),
    ];
}

echo json_encode(['users' => $users]);

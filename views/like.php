<?php

require_once __DIR__ . '/_page_helpers.php';
app_start_session();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);

    exit;
}

require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();

$current_user_id = $_SESSION['user_id'];


// Validate Post ID
$post_id = intval($_POST['post_id'] ?? 0);

if ($post_id <= 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid post'
    ]);

    exit;
}


// Check Post Exists
$postStmt = $db->prepare("
    SELECT *
    FROM posts
    WHERE id = ?
");

$postStmt->execute([$post_id]);

$post = $postStmt->fetch();

if (!$post) {

    echo json_encode([
        'success' => false,
        'message' => 'Post not found'
    ]);

    exit;
}


// Check Already Liked
$likeStmt = $db->prepare("
    SELECT *
    FROM likes
    WHERE user_id = ?
    AND post_id = ?
");

$likeStmt->execute([
    $current_user_id,
    $post_id
]);

$alreadyLiked = $likeStmt->fetch();


// Unlike
if ($alreadyLiked) {

    $deleteStmt = $db->prepare("
        DELETE FROM likes
        WHERE user_id = ?
        AND post_id = ?
    ");

    $deleteStmt->execute([
        $current_user_id,
        $post_id
    ]);

    $liked = false;

} else {

    // Like
    $insertStmt = $db->prepare("
        INSERT INTO likes (
            user_id,
            post_id
        )
        VALUES (?, ?)
    ");

    $insertStmt->execute([
        $current_user_id,
        $post_id
    ]);

    $liked = true;


    // Notification
    if ($post['user_id'] != $current_user_id) {

        $notifyStmt = $db->prepare("
            INSERT INTO notifications (
                user_id,
                from_user_id,
                type,
                post_id
            )
            VALUES (?, ?, 'like', ?)
        ");

        $notifyStmt->execute([
            $post['user_id'],
            $current_user_id,
            $post_id
        ]);
    }
}


// Updated Like Count
$countStmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM likes
    WHERE post_id = ?
");

$countStmt->execute([$post_id]);

$totalLikes = $countStmt->fetch()['total'];


// Response
echo json_encode([

    'success' => true,

    'liked' => $liked,

    'likes' => $totalLikes

]);

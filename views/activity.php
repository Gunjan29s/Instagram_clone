<?php
require_once __DIR__ . '/_page_helpers.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();
$pageTitle = 'Instagram - Your Activity';
$activePage = 'activity';
$userId = (int) $_SESSION['user_id'];

$stmt = $db->prepare("
    SELECT 'Like' AS action, posts.caption AS detail, likes.created_at
    FROM likes
    JOIN posts ON posts.id = likes.post_id
    WHERE likes.user_id = ?
    UNION ALL
    SELECT 'Comment' AS action, comments.comment AS detail, comments.created_at
    FROM comments
    WHERE comments.user_id = ?
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->execute([$userId, $userId]);
$items = $stmt->fetchAll();

include __DIR__ . '/../components/head.php';
?>
<div class="post_page d-flex">
    <?php include __DIR__ . '/../components/navbar.php'; ?>
    <main class="flex-grow-1 py-4 px-3" style="max-width:760px;margin:auto">
        <h4 class="mb-4">Your activity</h4>
        <div class="list-group">
            <?php foreach ($items as $item): ?>
                <div class="list-group-item py-3">
                    <div class="fw-semibold"><?= htmlspecialchars($item['action']) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($item['detail'] ?? '') ?></div>
                    <div class="text-muted" style="font-size:12px"><?= htmlspecialchars($item['created_at']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (!$items) render_empty_state('fa-regular fa-clock', 'No activity yet', 'Likes and comments will appear here.'); ?>
    </main>
</div>
<?php include __DIR__ . '/../components/create_modal.php'; ?>
<?php include __DIR__ . '/../components/footer.php'; ?>

<?php
require_once __DIR__ . '/_page_helpers.php';
app_start_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('views/sign_in.php'));
    exit;
}

require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();

$current_user_id = (int) $_SESSION['user_id'];
$post_id         = (int) ($_GET['id'] ?? 0);

if ($post_id <= 0) {
    header('Location: ' . app_url('views/home.php'));
    exit;
}

// Fetch post with user info
$stmt = $db->prepare("
    SELECT posts.*, users.username, users.full_name, users.profile_pic
    FROM posts
    JOIN users ON users.id = posts.user_id
    WHERE posts.id = ?
    LIMIT 1
");
$stmt->execute([$post_id]);
$post = $stmt->fetch();

if (!$post) {
    header('Location: ' . app_url('views/home.php'));
    exit;
}

$pageTitle  = 'Post by @' . htmlspecialchars($post['username']);
$activePage = '';
$shareText = 'Check this ' . (is_video_media($post['media_path'] ?? '', $post['media_type'] ?? '') ? 'reel' : 'post') . ' by @' . ($post['username'] ?? 'user') . ': ' . app_url('views/single_post.php?id=' . $post_id);

// Like check
$likeStmt = $db->prepare("SELECT id FROM likes WHERE user_id = ? AND post_id = ? LIMIT 1");
$likeStmt->execute([$current_user_id, $post_id]);
$isLiked = (bool) $likeStmt->fetch();

// Like count
$lcStmt = $db->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ?");
$lcStmt->execute([$post_id]);
$likeCount = (int) $lcStmt->fetchColumn();

// Comments
$cmtStmt = $db->prepare("
    SELECT comments.*, users.username, users.profile_pic
    FROM comments
    JOIN users ON users.id = comments.user_id
    WHERE comments.post_id = ?
    ORDER BY comments.id ASC
");
$cmtStmt->execute([$post_id]);
$comments = $cmtStmt->fetchAll();

// Saved check
$saveStmt = $db->prepare("SELECT id FROM saved_posts WHERE user_id = ? AND post_id = ? LIMIT 1");
$saveStmt->execute([$current_user_id, $post_id]);
$isSaved = (bool) $saveStmt->fetch();

// Follow check on post owner
$followStmt = $db->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ? LIMIT 1");
$followStmt->execute([$current_user_id, $post['user_id']]);
$isFollowing = (bool) $followStmt->fetch();

include __DIR__ . '/../components/head.php';
?>

<style>
.single_post_wrap {
    max-width: 935px;
    margin: 30px auto;
    background: #fff;
    border: 1px solid #dbdbdb;
    border-radius: 4px;
    display: flex;
    min-height: 500px;
}
.single_post_media_col {
    width: 55%;
    background: #000;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px 0 0 4px;
    overflow: hidden;
}
.single_post_media_col img,
.single_post_media_col video {
    width: 100%;
    height: 100%;
    object-fit: contain;
    max-height: 600px;
}
.single_post_info_col {
    width: 45%;
    display: flex;
    flex-direction: column;
    border-left: 1px solid #dbdbdb;
}
.single_post_header {
    padding: 14px 16px;
    border-bottom: 1px solid #efefef;
    display: flex;
    align-items: center;
    gap: 12px;
}
.single_post_comments_area {
    flex: 1;
    overflow-y: auto;
    padding: 12px 16px;
    max-height: 360px;
}
.single_post_actions {
    padding: 8px 16px;
    border-top: 1px solid #efefef;
    display: flex;
    gap: 14px;
    font-size: 22px;
}
.single_post_likes {
    padding: 4px 16px 2px;
    font-size: 14px;
    font-weight: 600;
}
.single_post_caption {
    padding: 4px 16px 8px;
    font-size: 14px;
}
.single_post_comment_form {
    border-top: 1px solid #efefef;
    padding: 10px 16px;
    display: flex;
    gap: 8px;
    align-items: center;
}
.single_post_comment_form input {
    flex: 1;
    border: none;
    outline: none;
    font-size: 14px;
    background: transparent;
}
.single_post_comment_form button {
    background: none;
    border: none;
    color: #0095f6;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
}
.comment_item_sp {
    margin-bottom: 10px;
    font-size: 14px;
    display: flex;
    gap: 8px;
    align-items: flex-start;
}
.comment_item_sp img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}
.back_btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #262626;
    text-decoration: none;
    font-size: 14px;
    margin-bottom: 16px;
    padding: 4px 0;
}
.back_btn:hover { color: #0095f6; }
@media (max-width: 768px) {
    .single_post_wrap { flex-direction: column; }
    .single_post_media_col { width: 100%; border-radius: 4px 4px 0 0; min-height: 300px; }
    .single_post_info_col { width: 100%; border-left: none; border-top: 1px solid #dbdbdb; }
}
</style>

<div class="post_page d-flex">
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <main class="flex-grow-1 py-4 px-3">
        <div style="max-width:935px; margin: 0 auto;">

            <!-- Back button -->
            <a href="javascript:history.back()" class="back_btn">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>

            <div class="single_post_wrap">

                <!-- Media Column -->
                <div class="single_post_media_col">
                    <?php if (is_video_media($post['media_path'] ?? '', $post['media_type'] ?? '')): ?>
                        <video controls>
                            <source src="<?= htmlspecialchars(post_media_url($post['media_path'])) ?>">
                        </video>
                    <?php else: ?>
                        <img src="<?= htmlspecialchars(post_media_url($post['media_path'])) ?>"
                             alt="post by <?= htmlspecialchars($post['username']) ?>">
                    <?php endif; ?>
                </div>

                <!-- Info Column -->
                <div class="single_post_info_col">

                    <!-- Post Owner Header -->
                    <div class="single_post_header">
                        <a href="<?= htmlspecialchars(app_url('views/profile.php?id=' . (int)$post['user_id'])) ?>">
                            <img src="<?= htmlspecialchars(profile_avatar($post['profile_pic'], $post['username'])) ?>"
                                 class="rounded-circle" width="38" height="38" style="object-fit:cover;" alt="user">
                        </a>
                        <div class="flex-grow-1">
                            <a href="<?= htmlspecialchars(app_url('views/profile.php?id=' . (int)$post['user_id'])) ?>"
                               class="fw-bold text-dark text-decoration-none">
                                <?= htmlspecialchars($post['username']) ?>
                            </a>
                            <?php if (!empty($post['location'])): ?>
                                <div class="small text-muted"><?= htmlspecialchars($post['location']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if ((int)$post['user_id'] !== $current_user_id): ?>
                        <button class="btn btn-sm <?= $isFollowing ? 'btn-outline-secondary' : 'btn-primary' ?>"
                                id="follow_btn_sp"
                                onclick="toggleFollowSP(this, <?= (int)$post['user_id'] ?>)">
                            <?= $isFollowing ? 'Following' : 'Follow' ?>
                        </button>
                        <?php endif; ?>
                    </div>

                    <!-- Caption + Comments -->
                    <div class="single_post_comments_area" id="comments_area_sp">

                        <!-- Caption -->
                        <?php if (!empty($post['caption'])): ?>
                        <div class="comment_item_sp">
                            <img src="<?= htmlspecialchars(profile_avatar($post['profile_pic'], $post['username'])) ?>"
                                 alt="user">
                            <div>
                                <a href="<?= htmlspecialchars(app_url('views/profile.php?id=' . (int)$post['user_id'])) ?>"
                                   class="fw-bold text-dark text-decoration-none me-1">
                                    <?= htmlspecialchars($post['username']) ?>
                                </a>
                                <?= nl2br(htmlspecialchars($post['caption'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Comments -->
                        <?php foreach ($comments as $comment): ?>
                        <div class="comment_item_sp">
                            <img src="<?= htmlspecialchars(profile_avatar($comment['profile_pic'] ?? '', $comment['username'] ?? '')) ?>"
                                 alt="user">
                            <div>
                                <a href="<?= htmlspecialchars(app_url('views/profile.php?id=' . (int)$comment['user_id'])) ?>"
                                   class="fw-bold text-dark text-decoration-none me-1">
                                    <?= htmlspecialchars($comment['username']) ?>
                                </a>
                                <?= nl2br(htmlspecialchars($comment['comment'])) ?>
                                <div class="text-muted" style="font-size:11px; margin-top:2px;">
                                    <?php
                                    $ds = time() - strtotime($comment['created_at']);
                                    if ($ds < 60) echo $ds . 's';
                                    elseif ($ds < 3600) echo floor($ds/60) . 'm';
                                    elseif ($ds < 86400) echo floor($ds/3600) . 'h';
                                    else echo floor($ds/86400) . 'd';
                                    ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Actions -->
                    <div class="single_post_actions">
                        <i class="<?= $isLiked ? 'fa-solid text-danger' : 'fa-regular' ?> fa-heart"
                           style="cursor:pointer;"
                           id="like_btn_sp"
                           onclick="toggleLikeSP(this, <?= $post_id ?>)"></i>

                        <i class="fa-regular fa-comment"
                           style="cursor:pointer;"
                           onclick="document.getElementById('comment_input_sp').focus()"></i>

                        <i class="fa-regular fa-paper-plane"
                           style="cursor:pointer;"
                           data-message-post-id="<?= $post_id ?>"
                           data-message-share="<?= htmlspecialchars($shareText, ENT_QUOTES) ?>"></i>

                        <i class="<?= $isSaved ? 'fa-solid' : 'fa-regular' ?> fa-bookmark ms-auto"
                           style="cursor:pointer;"
                           id="save_btn_sp"
                           onclick="toggleSaveSP(this, <?= $post_id ?>)"></i>
                    </div>

                    <!-- Like count -->
                    <div class="single_post_likes" id="like_count_sp">
                        <?= $likeCount ?> <?= $likeCount === 1 ? 'like' : 'likes' ?>
                    </div>

                    <!-- Timestamp -->
                    <div style="padding:0 16px 8px; font-size:11px; color:#8e8e8e; text-transform:uppercase; letter-spacing:.02em;">
                        <?php
                        $ds = time() - strtotime($post['created_at']);
                        if ($ds < 60) echo $ds . ' seconds ago';
                        elseif ($ds < 3600) echo floor($ds/60) . ' minutes ago';
                        elseif ($ds < 86400) echo floor($ds/3600) . ' hours ago';
                        else echo date('d M Y', strtotime($post['created_at']));
                        ?>
                    </div>

                    <!-- Comment Form -->
                    <div class="single_post_comment_form">
                        <i class="fa-regular fa-face-smile text-muted" style="font-size:20px;"></i>
                        <input type="text" id="comment_input_sp"
                               placeholder="Add a comment…"
                               autocomplete="off"
                               onkeydown="if(event.key==='Enter'){ submitCommentSP(<?= $post_id ?>); }">
                        <button onclick="submitCommentSP(<?= $post_id ?>)">Post</button>
                    </div>

                </div><!-- /info col -->
            </div><!-- /single_post_wrap -->

        </div>
    </main>
</div>

<script>
// Like toggle
function toggleLikeSP(btn, postId) {
    fetch(<?= json_encode(app_url('controllers/toggle_like.php')) ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ post_id: postId })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.status) return;
        const liked = data.status === 'liked';
        btn.className = (liked ? 'fa-solid text-danger' : 'fa-regular') + ' fa-heart';
        document.getElementById('like_count_sp').textContent = data.total + (data.total == 1 ? ' like' : ' likes');
    });
}

// Save toggle
function toggleSaveSP(btn, postId) {
    fetch(<?= json_encode(app_url('controllers/toggle_save.php')) ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ post_id: postId })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        btn.className = (data.status === 'saved' ? 'fa-solid' : 'fa-regular') + ' fa-bookmark ms-auto';
    });
}

// Comment submit
function submitCommentSP(postId) {
    const input = document.getElementById('comment_input_sp');
    const text  = input.value.trim();
    if (!text) return;

    const formData = new FormData();
    formData.append('post_id', postId);
    formData.append('comment', text);

    fetch(<?= json_encode(app_url('controllers/add_comment.php')) ?>, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.status !== 'ok') return;
        const area = document.getElementById('comments_area_sp');
        const div  = document.createElement('div');
        div.className = 'comment_item_sp';
        const safeComment = document.createElement('div');
        safeComment.textContent = data.comment.comment || text;
        div.innerHTML = `
            <img src="<?= htmlspecialchars(profile_avatar($_SESSION['profile_pic'] ?? '', $_SESSION['username'] ?? '')) ?>" alt="user">
            <div>
                <a href="<?= htmlspecialchars(app_url('views/profile.php?id=' . $current_user_id)) ?>"
                   class="fw-bold text-dark text-decoration-none me-1">
                    <?= htmlspecialchars($_SESSION['username'] ?? '') ?>
                </a>
                ${safeComment.innerHTML}
            </div>`;
        area.appendChild(div);
        area.scrollTop = area.scrollHeight;
        input.value = '';
    });
}

// Follow toggle
function toggleFollowSP(btn, userId) {
    fetch(<?= json_encode(app_url('controllers/toggle_follow.php')) ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ following_id: userId })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        if (data.state === 'following') {
            btn.textContent = 'Following';
            btn.className = 'btn btn-sm btn-outline-secondary';
        } else {
            btn.textContent = 'Follow';
            btn.className = 'btn btn-sm btn-primary';
        }
    });
}
</script>

<?php include __DIR__ . '/../components/create_modal.php'; ?>
<?php include __DIR__ . '/../components/footer.php'; ?>

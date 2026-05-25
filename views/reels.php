<?php
// views/reels.php

require_once __DIR__ . '/_page_helpers.php';
app_start_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('views/sign_in.php'));
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/PostModel.php';

$db = Database::getInstance()->getConnection();
$postModel = new PostModel();

$pageTitle  = 'Instagram – Reels';
$activePage = 'reels';

$current_user_id = $_SESSION['user_id'];


// Fetch Video Reels
$reelStmt = $db->prepare("
    SELECT
        posts.*,
        users.username,
        users.profile_pic
    FROM posts
    JOIN users ON posts.user_id = users.id
    WHERE posts.media_type = 'video'
      AND NOT EXISTS (
          SELECT 1 FROM blocked_users
          WHERE (blocker_id = ? AND blocked_id = posts.user_id)
             OR (blocker_id = posts.user_id AND blocked_id = ?)
      )
    ORDER BY posts.id DESC
");
$reelStmt->execute([$current_user_id, $current_user_id]);

$reels = $reelStmt->fetchAll();
$commentsByPost = $postModel->getCommentsByPostIds(array_column($reels, 'id'), 20);

include __DIR__ . '/../components/head.php';
?>

<style>
.reels_wrapper{
    position:relative;
    height:100vh;
    overflow-y:scroll;
    overflow-x:hidden;
    scroll-snap-type:y mandatory;
    background:#000;
    width:min(100%,430px);
    margin:0 auto;
    box-shadow:none;
}

.reels_wrapper::-webkit-scrollbar{
    display:none;
}

.reel_card{
    width:100%;
    height:100vh;
    scroll-snap-align:start;
    position:relative;
    background:#000;
    overflow:hidden;
}

.reel_video{
    display:block;
    width:100%;
    height:100%;
    object-fit:cover;
}

.reel_overlay{
    position:absolute;
    inset:0;
    pointer-events:none;
    background:linear-gradient(
        transparent,
        rgba(0,0,0,.15),
        rgba(0,0,0,.65)
    );
}

.reel_content{
    position:absolute;
    bottom:80px;
    left:20px;
    color:#fff;
    max-width:75%;
}

.reel_actions{
    position:absolute;
    right:15px;
    bottom:90px;
    display:flex;
    flex-direction:column;
    gap:22px;
    color:#fff;
    z-index:10;
}

.reel_actions i{
    font-size:28px;
    cursor:pointer;
}

.reel_actions p{
    margin:0;
    font-size:13px;
    text-align:center;
}

.reel_user{
    display:flex;
    align-items:center;
    gap:10px;
    margin-bottom:10px;
}

.reel_user img{
    width:36px;
    height:36px;
    border-radius:50%;
    object-fit:cover;
}

.reel_follow_btn{
    border:1px solid #fff;
    background:transparent;
    color:#fff;
    padding:3px 14px;
    border-radius:8px;
    font-size:14px;
}

.reel_caption{
    font-size:15px;
    line-height:1.4;
}

.reel_top_bar{
    position:absolute;
    top:0;
    z-index:100;
    width:100%;
    padding:16px;
    color:#fff;
    font-weight:700;
    font-size:22px;
    background:linear-gradient(rgba(0,0,0,.45), transparent);
    display:flex;
    align-items:center;
    justify-content:space-between;
}

.reel_upload_btn{
    width:38px;
    height:38px;
    border:0;
    border-radius:50%;
    color:#fff;
    background:rgba(255,255,255,.18);
    display:inline-flex;
    align-items:center;
    justify-content:center;
}

.reel_delete_btn{
    width:38px;
    height:38px;
    border:0;
    border-radius:50%;
    color:#fff;
    background:rgba(220,53,69,.9);
    display:inline-flex;
    align-items:center;
    justify-content:center;
}

.reel_volume{
    position:absolute;
    top:20px;
    right:20px;
    z-index:20;
    color:#fff;
    font-size:22px;
    cursor:pointer;
    background:rgba(0,0,0,.45);
    width:42px;
    height:42px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:50%;
}

.reel_comment_panel{
    position:absolute;
    left:14px;
    right:68px;
    bottom:18px;
    z-index:30;
    color:#fff;
    background:rgba(0,0,0,.72);
    border:1px solid rgba(255,255,255,.16);
    border-radius:8px;
    padding:10px;
    transform:translateY(12px);
    opacity:0;
    pointer-events:none;
    transition:opacity .18s ease, transform .18s ease;
}

.reel_comment_panel.is-open{
    opacity:1;
    pointer-events:auto;
    transform:translateY(0);
}

.reel_comment_panel .comments_list{
    max-height:150px;
    overflow-y:auto;
    margin-bottom:8px;
}

.reel_comment_panel .comment_item{
    color:#fff;
}

.reel_comment_panel .comment_form{
    margin:0;
    padding:8px 0 0;
    border-top:1px solid rgba(255,255,255,.18);
}

.reel_comment_panel .comment_form input{
    color:#fff;
    background:transparent;
}

.reel_comment_panel .comment_form input::placeholder{
    color:rgba(255,255,255,.72);
}
</style>


<div class="post_page d-flex">

    <?php include __DIR__ . '/../components/navbar.php'; ?>


    <main class="flex-grow-1" style="overflow:hidden;">

        <div class="reels_wrapper">

            <div class="reel_top_bar">
                <span>Reels</span>
                <button type="button"
                        class="reel_upload_btn"
                        data-bs-toggle="modal"
                        data-bs-target="#create_modal"
                        data-upload-type="reel"
                        aria-label="Upload reel">
                    <i class="fa-solid fa-plus"></i>
                </button>
            </div>


            <?php if(count($reels) > 0): ?>

                <?php foreach($reels as $reel): ?>

                <?php
                $post_id = $reel['id'];
                $postComments = $commentsByPost[(int) $post_id] ?? [];

                // Likes
                $likeStmt = $db->prepare("
                    SELECT COUNT(*) as total
                    FROM likes
                    WHERE post_id = ?
                ");

                $likeStmt->execute([$post_id]);

                $likes = $likeStmt->fetch()['total'];


                // Comments
                $commentStmt = $db->prepare("
                    SELECT COUNT(*) as total
                    FROM comments
                    WHERE post_id = ?
                ");

                $commentStmt->execute([$post_id]);

                $comments = $commentStmt->fetch()['total'];


                // Check Like
                $checkLikeStmt = $db->prepare("
                    SELECT id
                    FROM likes
                    WHERE user_id = ?
                    AND post_id = ?
                ");

                $checkLikeStmt->execute([
                    $current_user_id,
                    $post_id
                ]);

                $isLiked = $checkLikeStmt->rowCount() > 0;

                $checkSaveStmt = $db->prepare("
                    SELECT id
                    FROM saved_posts
                    WHERE user_id = ?
                    AND post_id = ?
                ");

                $checkSaveStmt->execute([
                    $current_user_id,
                    $post_id
                ]);

                $isSaved = $checkSaveStmt->rowCount() > 0;


                // Follow Check
                $followStmt = $db->prepare("
                    SELECT id
                    FROM follows
                    WHERE follower_id = ?
                    AND following_id = ?
                ");

                $followStmt->execute([
                    $current_user_id,
                    $reel['user_id']
                ]);

                $isFollowing = $followStmt->rowCount() > 0;
                ?>


                <div class="reel_card" id="reel-<?= $post_id ?>">


                    <!-- Video -->
                    <video class="reel_video"
                           loop
                           playsinline
                           autoplay
                           muted>

                        <source src="<?= htmlspecialchars(post_media_url($reel['media_path'])) ?>">

                    </video>


                    <!-- Overlay -->
                    <div class="reel_overlay"></div>


                    <!-- Volume -->
                    <div class="reel_volume"
                         onclick="toggleMute(this)">

                        <i class="fa-solid fa-volume-xmark"></i>

                    </div>


                    <!-- Reel Content -->
                    <div class="reel_content">

                        <div class="reel_user">

                            <img src="<?= htmlspecialchars(profile_avatar($reel['profile_pic'], $reel['username'])) ?>"
                                 alt="user">

                            <strong>
                                <?= htmlspecialchars($reel['username']) ?>
                            </strong>


                            <?php if($reel['user_id'] != $current_user_id): ?>

                            <button class="reel_follow_btn">

                                <?= $isFollowing ? 'Following' : 'Follow' ?>

                            </button>

                            <?php endif; ?>

                        </div>


                        <div class="reel_caption">

                            <?= htmlspecialchars($reel['caption']) ?>

                        </div>

                    </div>


                    <!-- Actions -->
                    <div class="reel_actions">

                        <!-- Like -->
                        <div class="text-center">

                            <i class="<?= $isLiked ? 'fa-solid fa-heart text-danger' : 'fa-regular fa-heart' ?>"
                               onclick="toggleLike(this, <?= $post_id ?>)">
                            </i>

                            <p id="likes-count-<?= $post_id ?>">
                                <?= number_format($likes) ?>
                            </p>

                        </div>


                        <!-- Comment -->
                        <div class="text-center">

                            <i class="fa-regular fa-comment"
                               data-reel-comment-toggle="<?= $post_id ?>">
                            </i>

                            <p id="reel-comments-count-<?= $post_id ?>">
                                <?= number_format($comments) ?>
                            </p>

                        </div>


                        <!-- Share -->
                        <div class="text-center">
                            <i class="fa-regular fa-paper-plane"
                               style="cursor:pointer"
                               data-message-post-id="<?= $post_id ?>"
                               data-message-share="<?= htmlspecialchars('Check this reel by @' . $reel['username'], ENT_QUOTES) ?>">
                            </i>
                        </div>


                        <!-- Save -->
                        <div class="text-center">

                            <i class="<?= $isSaved ? 'fa-solid' : 'fa-regular' ?> fa-bookmark"
                               data-save-post-id="<?= $post_id ?>"></i>

                        </div>


                        <!-- More -->
                        <div class="text-center">

                            <?php if((int)$reel['user_id'] === (int)$current_user_id): ?>
                                <form action="<?= htmlspecialchars(app_url('controllers/delete_post.php')) ?>"
                                      method="POST"
                                      onsubmit="return confirm('Delete this reel?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="post_id" value="<?= $post_id ?>">
                                    <button type="submit"
                                            class="reel_delete_btn"
                                            title="Delete reel">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>

                        </div>

                    </div>

                    <div class="reel_comment_panel" id="reel-comment-panel-<?= $post_id ?>">
                        <div class="comments_list" id="comments-list-<?= $post_id ?>">
                            <?php foreach($postComments as $comment): ?>
                                <?php
                                $commentUserId = (int) ($comment['user_id'] ?? 0);
                                $canDeleteComment = $commentUserId === (int) $current_user_id || (int) ($reel['user_id'] ?? 0) === (int) $current_user_id;
                                $hideComment = $commentUserId !== (int) $current_user_id;
                                ?>
                                <div class="comment_item <?= $hideComment ? 'is-hidden-comment' : '' ?> mb-1 small d-flex align-items-start gap-2"
                                     data-comment-id="<?= (int) ($comment['id'] ?? 0) ?>"
                                     data-comment-owner="<?= $commentUserId ?>">
                                    <p class="mb-0 flex-grow-1">
                                        <strong><?= htmlspecialchars($comment['username'] ?? '') ?></strong>
                                        <?= htmlspecialchars($comment['comment'] ?? '') ?>
                                    </p>
                                    <?php if($canDeleteComment): ?>
                                        <form action="<?= htmlspecialchars(app_url('controllers/delete_comment.php')) ?>"
                                              method="POST">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="comment_id" value="<?= (int) ($comment['id'] ?? 0) ?>">
                                            <button type="submit" class="btn btn-link btn-sm p-0 text-danger" title="Delete comment">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="button"
                                class="comments_link reel_view_comments_btn"
                                data-view-comments="<?= $post_id ?>">
                            View all <?= (int) $comments ?> comments
                        </button>

                        <form action="<?= htmlspecialchars(app_url('controllers/add_comment.php')) ?>"
                              method="POST"
                              class="comment_form d-flex align-items-center gap-2"
                              data-post-id="<?= $post_id ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="post_id" value="<?= $post_id ?>">
                            <input type="text"
                                   name="comment"
                                   class="form-control border-0"
                                   placeholder="Add a comment..."
                                   required>
                            <button type="submit" class="btn btn-link btn-sm">Post</button>
                        </form>
                    </div>

                </div>

                <?php endforeach; ?>

            <?php else: ?>

            <div class="d-flex flex-column justify-content-center align-items-center text-white"
                 style="height:100vh;">

                <i class="fa-solid fa-video fs-1 mb-3"></i>

                <h4>No Reels Yet</h4>

                <p class="text-white-50">
                    Upload videos to see reels here.
                </p>

            </div>

            <?php endif; ?>

        </div>

    </main>

</div>


<script>
// Auto Play Current Reel
const videos = document.querySelectorAll('.reel_video');

const observer = new IntersectionObserver((entries) => {

    entries.forEach(entry => {

        const video = entry.target;

        if(entry.isIntersecting) {

            video.play();

        } else {

            video.pause();
        }
    });

}, {
    threshold: 0.7
});

videos.forEach(video => {
    observer.observe(video);
});


// Like Toggle
function toggleLike(icon, postId) {

    fetch(<?= json_encode(app_url('controllers/toggle_like.php')) ?>, {

        method: 'POST',

        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },

        body: 'post_id=' + postId

    })
    .then(response => response.json())

    .then(data => {

        if(data.status === 'liked') {

            icon.classList.remove('fa-regular');

            icon.classList.add(
                'fa-solid',
                'text-danger'
            );

        } else {

            icon.classList.remove(
                'fa-solid',
                'text-danger'
            );

            icon.classList.add('fa-regular');
        }

        document.getElementById(
            'likes-count-' + postId
        ).innerText = data.total;
    });
}

document.addEventListener('click', function (event) {
    const toggle = event.target.closest('[data-reel-comment-toggle]');
    if (!toggle) return;

    const postId = toggle.dataset.reelCommentToggle;
    const panel = document.getElementById('reel-comment-panel-' + postId);
    if (!panel) return;

    document.querySelectorAll('.reel_comment_panel.is-open').forEach(openPanel => {
        if (openPanel !== panel) {
            openPanel.classList.remove('is-open');
        }
    });

    panel.classList.toggle('is-open');

    if (panel.classList.contains('is-open')) {
        panel.querySelector('input[name="comment"]')?.focus();
    }
});

// Reel Share — modal dikhao, redirect nahi
function openReelShare(event, postId, username) {
    event.preventDefault();
    event.stopPropagation();
    const shareUrl = <?= json_encode(app_url('views/messages.php')) ?> + '?share_post_id=' + postId;
    document.getElementById('reelShareLink').href = shareUrl;
    document.getElementById('reelShareUser').textContent = '@' + username;
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('reelShareModal'));
    modal.show();
}

// Mute Unmute
function toggleMute(btn) {

    const reelCard = btn.closest('.reel_card');

    const video = reelCard.querySelector('video');

    const icon = btn.querySelector('i');

    video.muted = !video.muted;

    if(video.muted) {

        icon.classList.remove('fa-volume-high');

        icon.classList.add('fa-volume-xmark');

    } else {

        icon.classList.remove('fa-volume-xmark');

        icon.classList.add('fa-volume-high');
    }
}
</script>

<!-- Reel Share Modal -->
<div class="modal fade" id="reelShareModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Share Reel</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="fa-regular fa-paper-plane fs-1 mb-3 text-primary"></i>
                <p class="mb-1">Share <span id="reelShareUser" class="fw-bold"></span>'s reel via message</p>
                <p class="text-muted small mb-4">Recipient will receive a link to this reel in their messages.</p>
                <a id="reelShareLink" href="#" class="btn btn-primary px-5">
                    Send in Messages
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../components/create_modal.php'; ?>
<?php include __DIR__ . '/../components/footer.php'; ?>

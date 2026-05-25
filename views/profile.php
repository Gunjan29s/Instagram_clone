<?php
// views/profile.php

if (!defined('MVC_RENDERING')) {
    require_once __DIR__ . '/../controllers/FrontController.php';
    FrontController::dispatchView('profile');
    return;
}

require_once __DIR__ . '/_page_helpers.php';
require_once __DIR__ . '/../models/FollowModel.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('views/sign_in.php'));
    exit;
}

$pageTitle  = 'Instagram – Profile';
$activePage = 'profile';

$current_user_id = $current_user_id ?? (int) ($_SESSION['user_id'] ?? 0);
$profile_user_id = $profile_user_id ?? (int) ($_GET['id'] ?? $current_user_id);
$profile = $profile ?? [];
$posts = $posts ?? [];
$commentsByPost = $commentsByPost ?? [];
$followModel = new FollowModel();

// ---- Block check ----
$isBlockedByMe = false;
$isBlockedByThem = false;
if ($profile_user_id !== $current_user_id) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $_profDb = Database::getInstance()->getConnection();
        $_profDb->exec("
            CREATE TABLE IF NOT EXISTS blocked_users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                blocker_id INT NOT NULL,
                blocked_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_block (blocker_id, blocked_id),
                FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        $bStmt = $_profDb->prepare(
            "SELECT blocker_id FROM blocked_users
             WHERE (blocker_id = ? AND blocked_id = ?)
                OR (blocker_id = ? AND blocked_id = ?)
             LIMIT 2"
        );
        $bStmt->execute([$current_user_id, $profile_user_id, $profile_user_id, $current_user_id]);
        foreach ($bStmt->fetchAll() as $bRow) {
            if ((int)$bRow['blocker_id'] === $current_user_id) $isBlockedByMe   = true;
            if ((int)$bRow['blocker_id'] === $profile_user_id) $isBlockedByThem = true;
        }
    } catch (Exception $e) {}
}

// Agar unhe humne block kiya hai ya unhone humein block kiya hai — restricted view
$isBlocked = $isBlockedByMe || $isBlockedByThem;
$isFollowing = !empty($profile['is_following']);

$post_count = (int) ($profile['post_count'] ?? 0);
$followers = $followModel->getFollowersCount($profile_user_id);
$following = $followModel->getFollowingCount($profile_user_id);
$followersList = $followModel->getFollowers($profile_user_id, $current_user_id);
$followingList = $followModel->getFollowing($profile_user_id, $current_user_id);
$activeTab = $_GET['tab'] ?? 'posts';
if (!in_array($activeTab, ['posts', 'reels'], true)) {
    $activeTab = 'posts';
}
$profilePicUrl = profile_avatar($profile['profile_pic'] ?? '', $profile['username'] ?? 'User');
$gridPosts = array_values(array_filter($posts, function(array $post) use ($activeTab): bool {
    $isVideo = is_video_media($post['media_path'] ?? '', $post['media_type'] ?? '');
    return $activeTab === 'reels' ? $isVideo : !$isVideo;
}));

include __DIR__ . '/../components/head.php';
?>

<div class="post_page d-flex">

    <?php include __DIR__ . '/../components/navbar.php'; ?>


    <main class="profile_container flex-grow-1 py-4 px-3"
          style="max-width:935px;margin:auto">

        <?php if ($isBlocked && $profile_user_id !== $current_user_id): ?>
        <div class="text-center py-5">
            <i class="fa-solid fa-ban fs-1 mb-3 text-muted"></i>
            <?php if ($isBlockedByMe): ?>
                <h5>You blocked this user</h5>
                <p class="text-muted">Unblock to see their profile again.</p>
                <button type="button"
                        class="btn btn-outline-danger btn-sm profile-block-btn"
                        id="blockBtn"
                        data-user-id="<?= (int) $profile_user_id ?>"
                        data-state="blocked"
                        onclick="toggleBlock(this)">
                    Unblock
                </button>
            <?php else: ?>
                <h5>This profile isn't available</h5>
                <p class="text-muted">You cannot view this user's details.</p>
            <?php endif; ?>
        </div>
        <?php else: ?>

        <div class="d-flex flex-column flex-md-row gap-5 align-items-center align-items-md-start mb-5">

            <div class="text-center">

                <button type="button"
                        class="profile-photo-btn"
                        data-bs-toggle="modal"
                        data-bs-target="#profilePhotoModal"
                        aria-label="Open profile picture">
                    <img src="<?= htmlspecialchars($profilePicUrl) ?>"
                         class="rounded-circle"
                         width="150"
                         height="150"
                         alt="profile"
                         style="border:3px solid #dbdbdb;object-fit:cover;">
                </button>

            </div>


            <div class="flex-grow-1">

                <div class="d-flex flex-wrap align-items-center gap-3 mb-3">

                    <h4 class="mb-0 fw-normal">
                        <?= htmlspecialchars($profile['username']) ?>
                    </h4>


                    <?php if($profile_user_id == $current_user_id): ?>

                    <a href="<?= htmlspecialchars(app_url('views/edit_profile.php')) ?>"
                       class="btn btn-outline-secondary btn-sm">

                        Edit Profile
                    </a>

                    <a href="<?= htmlspecialchars(app_url('views/settings.php')) ?>"
                       class="btn btn-outline-secondary btn-sm">

                        Settings
                    </a>

                    <?php else: ?>

                    <?php if (!$isBlocked): ?>
                    <button type="button"
                            class="btn <?= $isFollowing ? 'btn-outline-secondary is-following' : 'btn-primary' ?> btn-sm px-4 profile-follow-btn"
                            data-user-id="<?= (int) $profile_user_id ?>"
                            data-state="<?= $isFollowing ? 'following' : 'follow' ?>"
                            onclick="toggleProfileFollow(this)">

                        <?= $isFollowing ? 'Following' : 'Follow' ?>

                    </button>

                    <a href="<?= htmlspecialchars(app_url('views/messages.php?user=' . $profile_user_id)) ?>"
                       class="btn btn-outline-secondary btn-sm">
                        Message
                    </a>
                    <?php endif; ?>

                    <button type="button"
                            class="btn btn-outline-danger btn-sm profile-block-btn"
                            id="blockBtn"
                            data-user-id="<?= (int) $profile_user_id ?>"
                            data-state="<?= $isBlockedByMe ? 'blocked' : 'unblocked' ?>"
                            onclick="toggleBlock(this)">
                        <?= $isBlockedByMe ? 'Unblock' : 'Block' ?>
                    </button>

                    <?php endif; ?>

                </div>


                <div class="d-flex gap-4 mb-3">

                    <span>
                        <strong><?= $post_count ?></strong> posts
                    </span>

                    <button type="button"
                            class="profile-stat-btn"
                            data-bs-toggle="modal"
                            data-bs-target="#followersModal">
                        <strong id="followers-count"><?= number_format($followers) ?></strong> followers
                    </button>

                    <button type="button"
                            class="profile-stat-btn"
                            data-bs-toggle="modal"
                            data-bs-target="#followingModal">
                        <strong id="following-count"><?= number_format($following) ?></strong> following
                    </button>

                </div>


                <div>

                    <p class="mb-1 fw-bold">
                        <?= htmlspecialchars($profile['full_name']) ?>
                    </p>

                    <p class="mb-1">
                        <?= nl2br(htmlspecialchars($profile['bio'])) ?>
                    </p>

                    <?php if(!empty($profile['website'])): ?>

                    <a href="<?= htmlspecialchars($profile['website']) ?>"
                       target="_blank"
                       class="text-decoration-none fw-semibold">

                        <?= htmlspecialchars($profile['website']) ?>

                    </a>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <ul class="nav border-top justify-content-center gap-4 mb-4">

            <li class="nav-item">

                <a class="nav-link <?= $activeTab === 'posts' ? 'active text-dark fw-semibold' : 'text-muted' ?> d-flex align-items-center gap-1"
                   href="<?= htmlspecialchars(app_url('views/profile.php?id=' . (int) $profile_user_id . '&tab=posts')) ?>">

                    <i class="fa-solid fa-table-cells-large"></i>

                    POSTS

                </a>

            </li>

            <li class="nav-item">

                <a class="nav-link <?= $activeTab === 'reels' ? 'active text-dark fw-semibold' : 'text-muted' ?> d-flex align-items-center gap-1"
                   href="<?= htmlspecialchars(app_url('views/profile.php?id=' . (int) $profile_user_id . '&tab=reels')) ?>">

                    <i class="fa-solid fa-video"></i>

                    REELS

                </a>

            </li>


            <?php if($profile_user_id == $current_user_id): ?>

            <li class="nav-item">

                <a class="nav-link text-muted d-flex align-items-center gap-1"
                   href="<?= htmlspecialchars(app_url('views/saved.php')) ?>">

                    <i class="fa-regular fa-bookmark"></i>

                    SAVED

                </a>

            </li>

            <?php endif; ?>


            <li class="nav-item">

                <a class="nav-link text-muted d-flex align-items-center gap-1"
                   href="<?= htmlspecialchars(app_url('views/tagged.php?id=' . (int) $profile_user_id)) ?>">

                    <i class="fa-solid fa-tag"></i>

                    TAGGED

                </a>

            </li>

        </ul>


        <div class="row g-1">

            <?php if(count($gridPosts) > 0): ?>

                <?php foreach($gridPosts as $post): ?>
                <?php
                $profilePostId = (int) ($post['id'] ?? 0);
                $profilePostIsVideo = is_video_media($post['media_path'] ?? '', $post['media_type'] ?? '');
                $profileShareText = 'Check this ' . ($profilePostIsVideo ? 'reel' : 'post') . ' by @' . ($profile['username'] ?? 'user') . ': ' . app_url($profilePostIsVideo ? 'views/reels.php#reel-' . $profilePostId : 'views/profile.php?id=' . (int) $profile_user_id);
                $profilePostComments = $commentsByPost[$profilePostId] ?? [];
                ?>

                <div class="col-4">

                    <div onclick="openProfilePost(<?= $profilePostId ?>)"
                         style="aspect-ratio:1;
                                overflow:hidden;
                                cursor:pointer;
                                position:relative;
                                background:#fafafa;">

                        <?php if(!$profilePostIsVideo): ?>

                        <img src="<?= htmlspecialchars(post_media_url($post['media_path'])) ?>"
                             style="width:100%;
                                    height:100%;
                                    object-fit:cover"
                             alt="post">

                        <?php else: ?>

                        <video style="width:100%;
                                      height:100%;
                                      object-fit:cover">

                            <source src="<?= htmlspecialchars(post_media_url($post['media_path'])) ?>">

                        </video>

                        <i class="fa-solid fa-play text-white"
                           style="position:absolute;right:10px;top:8px;text-shadow:0 1px 5px #000"></i>

                        <?php endif; ?>


                        <div class="post_overlay d-flex justify-content-center align-items-center gap-4 text-white fw-bold"
                             style="position:absolute;
                                    inset:0;
                                    background:rgba(0,0,0,.35);
                                    opacity:0;
                                    transition:.2s;">

                            <?php
                            $likes = (int) ($post['total_likes'] ?? 0);
                            $comments = (int) ($post['total_comments'] ?? 0);
                            ?>

                            <span>
                                <i class="fa-solid fa-heart"></i>
                                <?= $likes ?>
                            </span>

                            <span>
                                <i class="fa-solid fa-comment"></i>
                                <?= $comments ?>
                            </span>

                        </div>

                    </div>

                </div>

                <div class="modal profile-post-modal" id="profilePostPreview<?= $profilePostId ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-xl">
                        <div class="modal-content">
                            <div class="profile-post-preview-media">
                                <?php if(!$profilePostIsVideo): ?>
                                    <img src="<?= htmlspecialchars(post_media_url($post['media_path'])) ?>" alt="post preview">
                                <?php else: ?>
                                    <video controls autoplay playsinline>
                                        <source src="<?= htmlspecialchars(post_media_url($post['media_path'])) ?>">
                                    </video>
                                <?php endif; ?>
                            </div>
                            <div class="profile-post-preview-side">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <img src="<?= htmlspecialchars($profilePicUrl) ?>"
                                         class="rounded-circle"
                                         width="38"
                                         height="38"
                                         style="object-fit:cover;"
                                         alt="profile">
                                    <strong><?= htmlspecialchars($profile['username'] ?? 'User') ?></strong>
                                    <button type="button"
                                            class="btn-close ms-auto"
                                            data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                </div>
                                <?php if(!empty($post['caption'])): ?>
                                    <p class="small mb-3"><?= nl2br(htmlspecialchars($post['caption'])) ?></p>
                                <?php endif; ?>
                                <div class="profile-post-comments" id="comments-list-<?= $profilePostId ?>">
                                    <?php foreach($profilePostComments as $comment): ?>
                                        <?php $canDeleteComment = (int) ($comment['user_id'] ?? 0) === $current_user_id || $profile_user_id === $current_user_id; ?>
                                        <div class="comment_item mb-2 small d-flex align-items-start gap-2" data-comment-id="<?= (int) ($comment['id'] ?? 0) ?>">
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

                                <div class="d-flex align-items-center gap-3 fs-4 profile-post-actions">
                                    <i class="fa-regular fa-paper-plane"
                                       data-message-share="<?= htmlspecialchars($profileShareText, ENT_QUOTES) ?>"
                                       data-message-post-id="<?= $profilePostId ?>"
                                       title="Send"
                                       style="cursor:pointer"></i>
                                    <i class="fa-regular fa-comment"
                                       data-comment-post-id="<?= $profilePostId ?>"
                                       title="Comment"
                                       style="cursor:pointer"></i>
                                    <span class="small fw-bold ms-auto"><?= (int) ($post['total_likes'] ?? 0) ?> likes</span>
                                    <?php if($profile_user_id === $current_user_id): ?>
                                        <form action="<?= htmlspecialchars(app_url('controllers/delete_post.php')) ?>"
                                              method="POST"
                                              onsubmit="return confirm('Delete this <?= $profilePostIsVideo ? 'reel' : 'post' ?>?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="post_id" value="<?= $profilePostId ?>">
                                            <button type="submit" class="btn btn-link p-0 text-danger" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <form action="<?= htmlspecialchars(app_url('controllers/add_comment.php')) ?>"
                                      method="POST"
                                      class="comment_form profile-comment-form d-flex align-items-center gap-2"
                                      data-post-id="<?= $profilePostId ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="post_id" value="<?= $profilePostId ?>">
                                    <input type="text"
                                           name="comment"
                                           class="form-control border-0"
                                           placeholder="Add a comment..."
                                           required>
                                    <button type="submit" class="btn btn-link btn-sm">Post</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <?php endforeach; ?>

            <?php else: ?>

            <div class="text-center py-5">

                <i class="fa-regular fa-image fs-1 mb-3 text-muted"></i>

                <h5><?= $activeTab === 'reels' ? 'No Reels Yet' : 'No Posts Yet' ?></h5>

                <p class="text-muted">
                    <?= $activeTab === 'reels' ? 'When you share reels, they will appear on your profile.' : 'When you share posts, they will appear on your profile.' ?>
                </p>

            </div>

            <?php endif; ?>

        </div>
        <?php endif; ?>

    </main>

</div>

<div class="modal fade" id="profilePhotoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content profile-photo-modal">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><?= htmlspecialchars($profile['username'] ?? 'Profile') ?></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img src="<?= htmlspecialchars($profilePicUrl) ?>"
                     alt="profile"
                     class="img-fluid rounded-circle profile-photo-large">
            </div>
        </div>
    </div>
</div>

<?php
$renderFollowUserRow = function(array $user, int $currentUserId, string $listType, bool $isOwnProfile): void {
    $userId = (int) $user['id'];
    ?>
    <div class="follow-user-row d-flex align-items-center gap-3 p-3 border-bottom"
         data-row-user-id="<?= $userId ?>"
         data-search-text="<?= htmlspecialchars(strtolower(($user['username'] ?? '') . ' ' . ($user['full_name'] ?? '')), ENT_QUOTES) ?>">
        <a href="<?= htmlspecialchars(app_url('views/profile.php?id=' . $userId)) ?>">
            <img src="<?= htmlspecialchars(profile_avatar($user['profile_pic'] ?? '', $user['username'] ?? 'User')) ?>"
                 class="rounded-circle"
                 width="48"
                 height="48"
                 style="object-fit:cover;"
                 alt="user">
        </a>

        <div class="flex-grow-1 overflow-hidden">
            <a href="<?= htmlspecialchars(app_url('views/profile.php?id=' . $userId)) ?>"
               class="text-dark text-decoration-none fw-semibold">
                <?= htmlspecialchars($user['username'] ?? 'Unknown') ?>
            </a>
            <div class="small text-muted text-truncate">
                <?= htmlspecialchars($user['full_name'] ?? '') ?>
            </div>
        </div>

        <?php if($userId !== $currentUserId): ?>
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
            <?php if($isOwnProfile && $listType === 'followers'): ?>
                <button type="button"
                        class="btn btn-sm btn-outline-secondary profile-list-remove-btn"
                        data-user-id="<?= $userId ?>"
                        onclick="removeFollowerFromList(this)">
                    Remove
                </button>
            <?php elseif($isOwnProfile && $listType === 'following'): ?>
                <button type="button"
                        class="btn btn-sm btn-outline-secondary profile-list-follow-btn"
                        data-user-id="<?= $userId ?>"
                        data-state="following"
                        onclick="unfollowFromList(this)">
                    Unfollow
                </button>
            <?php else: ?>
                <button type="button"
                        class="btn btn-sm <?= !empty($user['is_following']) ? 'btn-outline-secondary' : 'btn-primary' ?> profile-list-follow-btn"
                        data-user-id="<?= $userId ?>"
                        data-state="<?= !empty($user['is_following']) ? 'following' : 'follow' ?>"
                        onclick="toggleProfileFollow(this)">
                    <?= !empty($user['is_following']) ? 'Following' : 'Follow' ?>
                </button>
            <?php endif; ?>
            <button type="button"
                    class="btn btn-sm btn-outline-danger profile-list-block-btn"
                    data-user-id="<?= $userId ?>"
                    data-state="unblocked"
                    onclick="toggleListBlock(this)">
                Block
            </button>
            </div>
        <?php endif; ?>
    </div>
    <?php
};
?>

<div class="modal fade" id="followersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Followers</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="p-3 border-bottom">
                <input type="search"
                       class="form-control follow-modal-search"
                       data-follow-search="#followersModal"
                       placeholder="Search followers...">
            </div>
            <div class="modal-body p-0">
                <?php if(count($followersList) > 0): ?>
                    <?php foreach($followersList as $followUser): ?>
                        <?php $renderFollowUserRow($followUser, $current_user_id, 'followers', $profile_user_id === $current_user_id); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-5">No followers yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="followingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Following</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="p-3 border-bottom">
                <input type="search"
                       class="form-control follow-modal-search"
                       data-follow-search="#followingModal"
                       placeholder="Search following...">
            </div>
            <div class="modal-body p-0">
                <?php if(count($followingList) > 0): ?>
                    <?php foreach($followingList as $followUser): ?>
                        <?php $renderFollowUserRow($followUser, $current_user_id, 'following', $profile_user_id === $current_user_id); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-5">Not following anyone yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<style>
.post_overlay:hover{
    opacity:1 !important;
}

.profile-stat-btn{
    border:0;
    background:transparent;
    padding:0;
    color:inherit;
}

.profile-stat-btn:hover{
    text-decoration:underline;
}

.profile-photo-btn{
    border:0;
    border-radius:50%;
    background:transparent;
    padding:0;
    cursor:pointer;
}

.profile-photo-large{
    width:min(78vw,360px);
    height:min(78vw,360px);
    object-fit:cover;
}

.profile-post-modal .modal-content{
    display:grid;
    grid-template-columns:minmax(0,1fr) minmax(280px,360px);
    overflow:hidden;
    border:0;
    border-radius:8px;
}

/* Modal blur/transition fix */
.profile-post-modal {
    transition: none !important;
}
.profile-post-modal .modal-dialog {
    transition: none !important;
    transform: none !important;
}
.profile-post-modal.show .modal-dialog {
    transform: none !important;
}

.profile-post-preview-media{
    min-height:72vh;
    display:grid;
    place-items:center;
    background:#000;
}

.profile-post-preview-media img,
.profile-post-preview-media video{
    width:100%;
    height:100%;
    max-height:82vh;
    object-fit:contain;
}

.profile-post-preview-side{
    min-height:72vh;
    display:flex;
    flex-direction:column;
    padding:16px;
    background:#fff;
}

.profile-post-comments{
    flex:1;
    min-height:160px;
    max-height:none;
    overflow-y:auto;
    border-top:1px solid #efefef;
    border-bottom:1px solid #efefef;
    padding:12px 0;
    margin:8px 0 12px;
}

.profile-post-actions{
    padding-bottom:10px;
}

.profile-comment-form{
    margin:0 -16px -16px;
    padding:10px 16px;
    border-top:1px solid #efefef;
}

.follow-user-row.is-hidden-by-search{
    display:none !important;
}

@media(max-width:575.98px){
    .follow-user-row{
        align-items:flex-start !important;
    }

    .follow-user-row .profile-list-follow-btn,
    .follow-user-row .profile-list-remove-btn,
    .follow-user-row .profile-list-block-btn{
        padding:4px 8px;
        font-size:12px;
    }
}

@media(max-width:767.98px){
    .profile-post-modal .modal-content{
        grid-template-columns:1fr;
    }

    .profile-post-preview-media{
        min-height:54vh;
    }
}
</style>

<script>
function openProfilePost(postId) {
    const modalEl = document.getElementById('profilePostPreview' + postId);
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function focusProfileComment(postId) {
    const modalEl = document.getElementById('profilePostPreview' + postId);
    if (!modalEl) return;

    const focusInput = () => {
        const input = modalEl.querySelector('input[name="comment"]');
        if (input) {
            input.focus();
            input.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    };

    // Agar modal already open hai toh seedha focus karo
    if (modalEl.classList.contains('show')) {
        focusInput();
        return;
    }

    // Modal band hai — pehle open karo phir focus karo
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modalEl.addEventListener('shown.bs.modal', function handler() {
        focusInput();
        modalEl.removeEventListener('shown.bs.modal', handler);
    });
    modal.show();
}

// Close modal when clicking outside (backdrop)
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('profile-post-modal')) {
        bootstrap.Modal.getInstance(e.target)?.hide();
    }
});

function setFollowButton(btn, state) {
    btn.dataset.state = state;
    btn.textContent = state === 'following' ? 'Following' : 'Follow';
    btn.classList.toggle('btn-primary', state !== 'following');
    btn.classList.toggle('btn-outline-secondary', state === 'following');
    btn.classList.toggle('is-following', state === 'following');
}

function toggleBlock(btn) {
    const userId = btn.dataset.userId;
    const currentState = btn.dataset.state;
    const action = currentState === 'blocked' ? 'unblock' : 'block';

    if (action === 'block') {
        if (!confirm('Are you sure you want to block this user? They will be unfollowed and cannot message you.')) return;
    }

    fetch(<?= json_encode(app_url('controllers/block_user.php')) ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ user_id: userId, action: action })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        if (data.state === 'blocked') {
            btn.dataset.state = 'blocked';
            btn.textContent = 'Unblock';
            // Hide follow/message buttons
            document.querySelectorAll('.profile-follow-btn').forEach(b => b.style.display = 'none');
            document.querySelectorAll('a[href*="messages.php"]').forEach(a => a.style.display = 'none');
            // Show blocked overlay
            const grid = document.querySelector('.row.g-1');
            if (grid) {
                grid.innerHTML = '<div class="col-12 text-center py-5"><i class="fa-solid fa-ban fs-1 mb-3 text-muted"></i><h5>You blocked this user</h5><p class="text-muted">Unblock to see their posts and profile.</p></div>';
            }
        } else {
            btn.dataset.state = 'unblocked';
            btn.textContent = 'Block';
            // Reload to refresh follow state and posts
            window.location.reload();
        }
    })
    .catch(() => {});
}

function toggleListBlock(btn) {
    const userId = btn.dataset.userId;
    if (!userId) return;
    if (!confirm('Block this user? They will be removed from your follow lists.')) return;

    fetch(<?= json_encode(app_url('controllers/block_user.php')) ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ user_id: userId, action: 'block' })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        document
            .querySelectorAll(`.follow-user-row[data-row-user-id="${userId}"]`)
            .forEach(row => row.remove());
    })
    .catch(() => {});
}

function removeFollowerFromList(btn) {
    const userId = btn.dataset.userId;
    if (!userId) return;
    if (!confirm('Remove this follower?')) return;

    fetch(<?= json_encode(app_url('controllers/remove_follower.php')) ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ user_id: userId })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        document.querySelectorAll(`#followersModal [data-row-user-id="${userId}"]`).forEach(row => row.remove());
        const count = document.getElementById('followers-count');
        if (count) count.textContent = Number(data.followers || 0).toLocaleString();
    })
    .catch(() => {});
}

function unfollowFromList(btn) {
    const userId = btn.dataset.userId;
    if (!userId) return;
    if (!confirm('Unfollow this user?')) return;

    fetch(<?= json_encode(app_url('controllers/toggle_follow.php')) ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ following_id: userId })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        document.querySelectorAll(`#followingModal [data-row-user-id="${userId}"]`).forEach(row => row.remove());
        const count = document.getElementById('following-count');
        if (count) count.textContent = Number(data.current_following || 0).toLocaleString();
    })
    .catch(() => {});
}

document.querySelectorAll('[data-follow-search]').forEach(input => {
    input.addEventListener('input', () => {
        const modal = document.querySelector(input.dataset.followSearch);
        if (!modal) return;
        const query = input.value.trim().toLowerCase();
        modal.querySelectorAll('.follow-user-row').forEach(row => {
            const text = row.dataset.searchText || '';
            row.classList.toggle('is-hidden-by-search', query !== '' && !text.includes(query));
        });
    });
});

function toggleProfileFollow(btn) {
    const userId = btn.dataset.userId;
    if (!userId) return;

    fetch(<?= json_encode(app_url('controllers/toggle_follow.php')) ?>, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({ following_id: userId })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Follow request failed');
        }
        return response.json();
    })
    .then(data => {
        if (!data.success) {
            throw new Error('Follow was not updated');
        }

        // Sirf Follow buttons ko change karo (Block button ko ignore karo)
        document
            .querySelectorAll(`.profile-follow-btn[data-user-id="${userId}"], .profile-list-follow-btn[data-user-id="${userId}"]`)
            .forEach(button => setFollowButton(button, data.state));

        if (String(userId) === <?= json_encode((string) $profile_user_id) ?>) {
            document.getElementById('followers-count').textContent = Number(data.followers || 0).toLocaleString();
            document.getElementById('following-count').textContent = Number(data.following || 0).toLocaleString();
        } else if (<?= json_encode($profile_user_id === $current_user_id) ?>) {
            document.getElementById('following-count').textContent = Number(data.current_following || 0).toLocaleString();

            if (data.state === 'follow') {
                document
                    .querySelectorAll(`#followingModal [data-row-user-id="${userId}"]`)
                    .forEach(row => row.remove());
            }
        }
    })
    .catch(() => {
        window.location.href = <?= json_encode(app_url('views/sign_in.php')) ?>;
    });
}
</script>


<?php include __DIR__ . '/../components/create_modal.php'; ?>
<?php include __DIR__ . '/../components/footer.php'; ?>

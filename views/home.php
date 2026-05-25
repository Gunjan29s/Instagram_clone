<?php
if (!defined('MVC_RENDERING')) {
    require_once __DIR__ . '/../controllers/FrontController.php';
    FrontController::dispatchView('home');
    return;
}

require_once __DIR__ . '/_page_helpers.php';

$pageTitle  = 'Instagram - Home';
$activePage = 'home';

$currentUser = $currentUser ?? [];
$posts = $posts ?? [];
$commentsByPost = $commentsByPost ?? [];
$stories = $stories ?? [];
$storyViewers = $storyViewers ?? [];
$suggestedUsers = $suggestedUsers ?? [];
$user_id = (int) ($user_id ?? ($_SESSION['user_id'] ?? 0));
$storiesByUser = [];

foreach ($stories as $story) {
    $storyUserId = (int) ($story['user_id'] ?? 0);
    if ($storyUserId <= 0) {
        continue;
    }

    if (!isset($storiesByUser[$storyUserId])) {
        $storiesByUser[$storyUserId] = [
            'username' => $story['username'] ?? 'User',
            'avatar' => profile_avatar($story['profile_pic'] ?? '', $story['username'] ?? 'User'),
            'items' => [],
            'has_unseen' => false,
        ];
    }

    $isOwnStory = $storyUserId === $user_id;
    $isViewed = $isOwnStory || !empty($story['viewed_by_current_user']);
    if (!$isViewed) {
        $storiesByUser[$storyUserId]['has_unseen'] = true;
    }

    $storyId = (int) ($story['id'] ?? 0);
    $storySeenBy = [];
    if ($isOwnStory && isset($storyViewers[$storyId])) {
        foreach ($storyViewers[$storyId] as $viewer) {
            $storySeenBy[] = [
                'id' => (int) ($viewer['user_id'] ?? 0),
                'username' => $viewer['username'] ?? 'User',
                'full_name' => $viewer['full_name'] ?? '',
                'avatar' => profile_avatar($viewer['profile_pic'] ?? '', $viewer['username'] ?? 'User'),
                'viewed_at' => $viewer['viewed_at'] ?? '',
            ];
        }
    }

    $storiesByUser[$storyUserId]['items'][] = [
        'id' => $storyId,
        'can_delete' => $isOwnStory,
        'media' => post_media_url($story['media_path'] ?? ''),
        'type' => is_video_media($story['media_path'] ?? '', $story['media_type'] ?? '') ? 'video' : 'image',
        'caption' => $story['caption'] ?? '',
        'created_at' => $story['created_at'] ?? '',
        'created_timestamp' => strtotime($story['created_at']),
        'expires_at' => $story['expires_at'] ?? '',
        'viewed' => $isViewed,
        'seen_by' => $storySeenBy,
        'seen_count' => count($storySeenBy),
    ];
}

foreach ($storiesByUser as &$group) {
    usort($group['items'], function($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });
}
unset($group);

include __DIR__ . '/../components/head.php';
?>

<style>
.story_ring {
    width: 66px;
    height: 66px;
    display: inline-grid;
    place-items: center;
    background: linear-gradient(45deg, #f58529, #dd2a7b, #8134af, #515bd4);
    padding: 3px;
}

.story_ring.is_seen {
    background: #dbdbdb;
}

.story_ring img,
.story_video_thumb,
.story_video_thumb video {
    width: 60px;
    height: 60px;
}

.story_stack_count {
    position: absolute;
    right: -2px;
    top: -4px;
    min-width: 19px;
    height: 19px;
    display: inline-grid;
    place-items: center;
    border: 2px solid #fff;
    border-radius: 999px;
    background: #0095f6;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
}

.story_view_media img,
.story_view_media video {
    object-fit: contain;
    background: #000;
}

.story_nav_btn {
    position: absolute;
    top: 50%;
    z-index: 4;
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 50%;
    color: #fff;
    background: rgba(0, 0, 0, .45);
    transform: translateY(-50%);
}

.story_nav_prev { left: 10px; }
.story_nav_next { right: 10px; }

.story_delete_form {
    position: absolute;
    top: 58px;
    right: 12px;
    z-index: 5;
}

.story_progress_bar {
    display: none;
}

.story_header_info {
    display: flex;
    flex-direction: column;
    gap: 1px;
}
.story_time_ago {
    font-size: 11px;
    color: rgba(255,255,255,0.75);
    font-weight: 400;
}

.story_thumb_time {
    font-size: 10px;
    color: #8e8e8e;
    text-align: center;
    line-height: 1.2;
}

.story_seen_panel {
    position: absolute;
    inset: auto 0 0 0;
    z-index: 4;
    max-height: 42%;
    color: #fff;
    background: linear-gradient(transparent, rgba(0, 0, 0, .92) 18%, rgba(0, 0, 0, .94));
    padding: 44px 14px 14px;
}

.story_seen_title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    font-size: 13px;
    font-weight: 700;
}

.story_seen_list {
    max-height: 180px;
    overflow-y: auto;
}

.story_seen_user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 0;
}

.story_seen_user img {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    object-fit: cover;
}

.story_seen_user span {
    display: block;
    line-height: 1.2;
}

.story_seen_user small {
    display: block;
    color: rgba(255, 255, 255, .68);
}

.story_seen_empty {
    color: rgba(255, 255, 255, .72);
    font-size: 13px;
}
</style>

<div class="post_page d-flex">
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <main class="feed_container flex-grow-1">
        <div class="stories_section d-flex gap-3 overflow-auto">
            <?php foreach ($storiesByUser as $storyGroup): ?>
                <?php
                $firstStory = $storyGroup['items'][0] ?? [];
                $storyCount = count($storyGroup['items']);
                $storyOwnerLabel = !empty($firstStory['can_delete']) ? 'My story' : ($storyGroup['username'] ?? 'User');
                ?>
                <div class="story_item text-center">
                    <button type="button"
                            class="story_ring rounded-circle border-0 position-relative <?= empty($storyGroup['has_unseen']) ? 'is_seen' : '' ?>"
                            data-story-user="<?= htmlspecialchars($storyGroup['username'], ENT_QUOTES) ?>"
                            data-story-avatar="<?= htmlspecialchars($storyGroup['avatar'], ENT_QUOTES) ?>"
                            data-story-items="<?= htmlspecialchars(json_encode($storyGroup['items']), ENT_QUOTES) ?>">
                        <?php if (($firstStory['type'] ?? 'image') === 'video'): ?>
                            <span class="story_video_thumb">
                                <video muted preload="metadata">
                                    <source src="<?= htmlspecialchars($firstStory['media'] ?? '') ?>">
                                </video>
                                <i class="fa-solid fa-play"></i>
                            </span>
                        <?php else: ?>
                            <img src="<?= htmlspecialchars($firstStory['media'] ?? '') ?>"
                                 class="rounded-circle"
                                 alt="story"
                                 style="border:3px solid #fff; object-fit:cover;">
                        <?php endif; ?>
                        <?php if ($storyCount > 1 && !empty($storyGroup['has_unseen'])): ?>
                            <span class="story_stack_count"><?= $storyCount ?></span>
                        <?php endif; ?>
                    </button>
                    <p class="small mt-1 mb-0 text-truncate">
                        <?= htmlspecialchars($storyOwnerLabel) ?>
                    </p>
                    <?php
                    $firstCreated = $firstStory['created_at'] ?? '';
                    if ($firstCreated) {
                        $diffSec = time() - strtotime($firstCreated);
                        $diffMin = (int)($diffSec / 60);
                        $diffHr  = (int)($diffMin / 60);
                        if ($diffMin < 1)       $storyLabel = 'Just now';
                        elseif ($diffMin < 60)  $storyLabel = $diffMin . 'm ago';
                        else                    $storyLabel = $diffHr . 'h ago';
                    } else {
                        $storyLabel = '';
                    }
                    ?>
                    <?php if ($storyLabel): ?>
                        <p class="story_thumb_time mb-0"><?= htmlspecialchars($storyLabel) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php foreach ($posts as $post): ?>
            <?php
            $post_id = (int) $post['id'];
            $totalLikes = (int) ($post['total_likes'] ?? 0);
            $totalComments = (int) ($post['total_comments'] ?? 0);
            $isLiked = !empty($post['is_liked']);
            $isFollowing = !empty($post['is_following']);
            $postComments = $commentsByPost[$post_id] ?? [];
            $shareText = 'Check this ' . (is_video_media($post['media_path'] ?? '', $post['media_type'] ?? '') ? 'reel' : 'post') . ' by @' . ($post['username'] ?? 'user') . ': ' . app_url('views/profile.php?id=' . (int) $post['user_id']);
            ?>

            <article class="post_card card">
                <div class="card-header d-flex align-items-center gap-2 bg-white">
                    <img src="<?= htmlspecialchars(profile_avatar($post['profile_pic'] ?? '', $post['username'] ?? 'User')) ?>"
                         class="rounded-circle"
                         width="40"
                         height="40"
                         alt="user"
                         style="object-fit:cover;">

                    <strong><?= htmlspecialchars($post['username'] ?? 'User') ?></strong>

                    <?php if ((int) $post['user_id'] !== $user_id): ?>
                        <button type="button"
                                class="btn btn-sm follow-pill <?= $isFollowing ? 'is-following' : '' ?> ms-auto"
                                data-follow-user="<?= (int) $post['user_id'] ?>">
                            <?= $isFollowing ? 'Following' : 'Follow' ?>
                        </button>
                    <?php endif; ?>

                    <div class="dropdown ms-auto">
                        <i class="fa-solid fa-ellipsis post-menu-icon"
                           data-bs-toggle="dropdown"
                           aria-expanded="false"
                           style="cursor:pointer"></i>
                        <div class="dropdown-menu dropdown-menu-end">
                            <?php if ((int) $post['user_id'] === $user_id): ?>
                                <form action="<?= htmlspecialchars(app_url('controllers/delete_post.php')) ?>"
                                      method="POST"
                                      onsubmit="return confirm('Delete this post?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="post_id" value="<?= $post_id ?>">
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="fa-solid fa-trash me-2"></i>Delete
                                    </button>
                                </form>
                            <?php else: ?>
                                <button type="button" class="dropdown-item" disabled>No actions</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!is_video_media($post['media_path'] ?? '', $post['media_type'] ?? '')): ?>
                    <img src="<?= htmlspecialchars(post_media_url($post['media_path'] ?? '')) ?>"
                         class="card-img-top"
                         alt="post">
                <?php else: ?>
                    <video controls class="w-100">
                        <source src="<?= htmlspecialchars(post_media_url($post['media_path'] ?? '')) ?>">
                    </video>
                <?php endif; ?>

                <div class="card-body">
                    <div class="post_actions d-flex align-items-center gap-3">
                        <i class="<?= $isLiked ? 'fa-solid fa-heart text-danger' : 'fa-regular fa-heart' ?>"
                           style="cursor:pointer"
                           onclick="toggleLike(this, <?= $post_id ?>)">
                        </i>
                        <i class="fa-regular fa-comment"
                           data-comment-post-id="<?= $post_id ?>"
                           style="cursor:pointer"></i>
                        <i class="fa-regular fa-paper-plane"
                           data-message-share="<?= htmlspecialchars($shareText, ENT_QUOTES) ?>"
                           data-message-post-id="<?= $post_id ?>"
                           title="Send"
                           style="cursor:pointer"></i>
                        <i class="<?= !empty($post['is_saved']) ? 'fa-solid' : 'fa-regular' ?> fa-bookmark ms-auto"
                           data-save-post-id="<?= $post_id ?>"
                           style="cursor:pointer"></i>
                    </div>

                    <p class="likes_line mb-1 fw-bold" id="likes-count-<?= $post_id ?>">
                        <?= $totalLikes ?> likes
                    </p>

                    <?php if (!empty(trim($post['caption'] ?? ''))): ?>
                        <p class="caption_line mb-2" style="word-wrap: break-word;">
                            <strong><?= htmlspecialchars($post['username'] ?? 'User') ?></strong> 
                            <?= nl2br(htmlspecialchars(trim($post['caption']))) ?>
                        </p>
                    <?php endif; ?>

                    <a href="#" class="comments_link text-muted small text-decoration-none" id="comments-count-<?= $post_id ?>">
                        View all <?= $totalComments ?> comments
                    </a>

                    <div class="comments_list" id="comments-list-<?= $post_id ?>">
                        <?php foreach ($postComments as $comment): ?>
                            <?php
                            $commentUserId = (int) ($comment['user_id'] ?? 0);
                            $canDeleteComment = $commentUserId === $user_id || (int) ($post['user_id'] ?? 0) === $user_id;
                            $hideComment = $commentUserId !== $user_id;
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

                    <form action="<?= htmlspecialchars(app_url('controllers/add_comment.php')) ?>"
                          method="POST"
                          class="comment_form mt-3 d-flex align-items-center gap-2"
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
            </article>
        <?php endforeach; ?>

        <?php if (empty($posts)): ?>
            <?php render_empty_state('fa-regular fa-images', 'No posts yet', 'Follow people or create your first post.'); ?>
        <?php endif; ?>
    </main>

    <aside class="right_sidebar d-none d-xl-block">
        <div class="d-flex align-items-center gap-2 mb-4">
            <img src="<?= htmlspecialchars(profile_avatar($currentUser['profile_pic'] ?? '', $currentUser['username'] ?? 'User')) ?>"
                 class="rounded-circle"
                 width="50"
                 height="50"
                 alt="profile"
                 style="object-fit:cover;">

            <div>
                <p class="mb-0 fw-bold"><?= htmlspecialchars($currentUser['username'] ?? 'User') ?></p>
                <p class="mb-0 text-muted small"><?= htmlspecialchars($currentUser['full_name'] ?? '') ?></p>
            </div>

            <a href="<?= htmlspecialchars(app_url('views/profile.php')) ?>"
               class="ms-auto text-primary small fw-bold text-decoration-none">
                Profile
            </a>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted small fw-bold">Suggested for you</span>
            <a href="<?= htmlspecialchars(app_url('views/follow.php')) ?>"
               class="small fw-bold text-decoration-none">
                See all
            </a>
        </div>

        <?php foreach ($suggestedUsers as $suggest): ?>
            <div class="d-flex align-items-center mb-3">
                <img src="<?= htmlspecialchars(profile_avatar($suggest['profile_pic'] ?? '', $suggest['username'] ?? 'User')) ?>"
                     class="rounded-circle"
                     width="42"
                     height="42"
                     alt="user"
                     style="object-fit:cover;">

                <div class="ms-2">
                    <div class="fw-bold small"><?= htmlspecialchars($suggest['username'] ?? 'User') ?></div>
                    <div class="text-muted small">Suggested for you</div>
                </div>

                <a href="<?= htmlspecialchars(app_url('views/follow.php?id=' . (int) $suggest['id'])) ?>"
                   class="btn btn-sm btn-link ms-auto text-decoration-none fw-bold">
                    Follow
                </a>
            </div>
        <?php endforeach; ?>

        <p class="text-muted mt-4" style="font-size:11px">
            &copy; 2026 INSTAGRAM FROM META
        </p>
    </aside>
</div>

<div class="modal fade story_view_modal" id="storyViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content position-relative">
            <div class="story_view_header">
                <img id="storyModalAvatar" src="" alt="story user">
                <div class="story_header_info">
                    <strong id="storyModalUser"></strong>
                    <span class="story_time_ago" id="storyModalTime"></span>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="story_progress_bar" id="storyProgressBar">
                <div class="story_progress_fill" id="storyProgressFill"></div>
            </div>
            <div class="story_view_media" id="storyModalMedia"></div>
            <div class="story_view_caption d-none" id="storyModalCaption"></div>
            <div class="story_seen_panel d-none" id="storySeenPanel">
                <div class="story_seen_title">
                    <i class="fa-regular fa-eye"></i>
                    <span id="storySeenTitle">Seen by 0</span>
                </div>
                <div class="story_seen_list" id="storySeenList"></div>
            </div>
            <form action="<?= htmlspecialchars(app_url('controllers/delete_story.php')) ?>"
                  method="POST"
                  class="story_delete_form d-none"
                  id="storyDeleteForm"
                  onsubmit="return confirm('Delete this story?')">
                <?= csrf_field() ?>
                <input type="hidden" name="story_id" id="storyDeleteId" value="">
                <button type="submit" class="btn btn-sm btn-danger">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </form>
            <button type="button" class="story_nav_btn story_nav_prev d-none" id="storyPrevBtn" aria-label="Previous story">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <button type="button" class="story_nav_btn story_nav_next d-none" id="storyNextBtn" aria-label="Next story">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../components/create_modal.php'; ?>

<script>
function toggleLike(icon, postId) {
    fetch(<?= json_encode(app_url('controllers/toggle_like.php')) ?>, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'post_id=' + encodeURIComponent(postId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'liked') {
            icon.classList.remove('fa-regular');
            icon.classList.add('fa-solid', 'text-danger');
        } else {
            icon.classList.remove('fa-solid', 'text-danger');
            icon.classList.add('fa-regular');
        }

        document.getElementById('likes-count-' + postId).innerText = data.total + ' likes';
    });
}

document.querySelectorAll('[data-follow-user]').forEach(button => {
    button.addEventListener('click', () => {
        fetch(<?= json_encode(app_url('controllers/toggle_follow.php')) ?>, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'following_id=' + encodeURIComponent(button.dataset.followUser)
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) return;
            const following = data.state === 'following';
            button.classList.toggle('is-following', following);
            button.textContent = following ? 'Following' : 'Follow';
        });
    });
});

let activeStories = [];
let activeStoryIndex = 0;
let activeStoryTimer = null;
let activeStoryButton = null;

const SERVER_NOW = <?= time() ?>;
const PAGE_LOAD_TIME = Date.now();

function storyTimeAgo(createdTimestamp) {
    if (!createdTimestamp) return '';
    const elapsedSec = Math.floor((Date.now() - PAGE_LOAD_TIME) / 1000);
    const diffSec = Math.max(0, (SERVER_NOW + elapsedSec) - parseInt(createdTimestamp));
    const diffMins = Math.floor(diffSec / 60);
    const diffHours = Math.floor(diffMins / 60);
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return diffMins + 'm ago';
    if (diffHours < 24) return diffHours + 'h ago';
    return '24h ago';
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function startStoryProgress() {
    // Progress bar hidden hai — kuch nahi karna
}

function startStoryTimer() {
    clearTimeout(activeStoryTimer);
    activeStoryTimer = setTimeout(() => {
        if (activeStoryIndex < activeStories.length - 1) {
            activeStoryIndex++;
            renderActiveStory();
        } else {
            const modalElement = document.getElementById('storyViewModal');
            bootstrap.Modal.getInstance(modalElement)?.hide();
        }
    }, 15000);
}

function renderActiveStory() {
    const mediaBox = document.getElementById('storyModalMedia');
    const captionBox = document.getElementById('storyModalCaption');
    const prevBtn = document.getElementById('storyPrevBtn');
    const nextBtn = document.getElementById('storyNextBtn');
    const deleteForm = document.getElementById('storyDeleteForm');
    const deleteInput = document.getElementById('storyDeleteId');
    const timeEl = document.getElementById('storyModalTime');
    const seenPanel = document.getElementById('storySeenPanel');
    const seenTitle = document.getElementById('storySeenTitle');
    const seenList = document.getElementById('storySeenList');
    const story = activeStories[activeStoryIndex] || {};
    const caption = story.caption || '';
    const seenBy = Array.isArray(story.seen_by) ? story.seen_by : [];

    captionBox.textContent = caption;
    captionBox.classList.toggle('d-none', caption === '' || story.can_delete);
    prevBtn.classList.toggle('d-none', activeStoryIndex <= 0);
    nextBtn.classList.toggle('d-none', activeStoryIndex >= activeStories.length - 1);
    deleteInput.value = story.id || '';
    deleteForm.classList.toggle('d-none', !story.can_delete || !story.id);
    seenPanel.classList.toggle('d-none', !story.can_delete);

    if (story.can_delete) {
        seenTitle.textContent = 'Seen by ' + seenBy.length;
        seenList.innerHTML = seenBy.length
            ? seenBy.map(viewer => `
                <a class="story_seen_user text-white text-decoration-none" href="<?= htmlspecialchars(app_url('views/profile.php')) ?>?id=${encodeURIComponent(parseInt(viewer.id || 0, 10))}">
                    <img src="${escapeHtml(viewer.avatar)}" alt="${escapeHtml(viewer.username)}">
                    <span>
                        <strong>${escapeHtml(viewer.username)}</strong>
                        ${viewer.full_name ? `<small>${escapeHtml(viewer.full_name)}</small>` : ''}
                    </span>
                </a>
            `).join('')
            : '';
    } else {
        seenList.innerHTML = '';
    }

    // Time ago
    if (timeEl) {
        timeEl.textContent = storyTimeAgo(story.created_timestamp || '');
    }

    if (story.type === 'video') {
        mediaBox.innerHTML = `<video controls autoplay playsinline><source src="${story.media}"></video>`;
    } else {
        mediaBox.innerHTML = `<img src="${story.media}" alt="story" decoding="async">`;
    }

    startStoryTimer();
}

function markActiveStoriesSeen() {
    if (!activeStories.length) return;
    const storyIds = activeStories
        .filter(story => story.id && !story.can_delete)
        .map(story => story.id);

    if (!storyIds.length) return;

    activeStories = activeStories.map(story => ({ ...story, viewed: true }));

    if (activeStoryButton) {
        activeStoryButton.classList.add('is_seen');
        activeStoryButton.querySelector('.story_stack_count')?.remove();
        activeStoryButton.dataset.storyItems = JSON.stringify(activeStories);
    }

    fetch(<?= json_encode(app_url('controllers/mark_story_seen.php')) ?>, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ story_ids: storyIds.join(',') }),
        keepalive: true
    }).catch(() => {});
}

document.querySelectorAll('[data-story-items]').forEach(button => {
    button.addEventListener('click', () => {
        const modalElement = document.getElementById('storyViewModal');
        activeStories = JSON.parse(button.dataset.storyItems || '[]');
        activeStoryIndex = 0;
        activeStoryButton = button;

        document.getElementById('storyModalUser').textContent = button.dataset.storyUser || '';
        document.getElementById('storyModalAvatar').src = button.dataset.storyAvatar || '';
        renderActiveStory();
        markActiveStoriesSeen();

        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    });
});

document.getElementById('storyPrevBtn')?.addEventListener('click', () => {
    if (activeStoryIndex > 0) {
        activeStoryIndex--;
        renderActiveStory();
    }
});

document.getElementById('storyNextBtn')?.addEventListener('click', () => {
    if (activeStoryIndex < activeStories.length - 1) {
        activeStoryIndex++;
        renderActiveStory();
    }
});

document.getElementById('storyViewModal')?.addEventListener('hidden.bs.modal', () => {
    clearTimeout(activeStoryTimer);
    document.getElementById('storyModalMedia').innerHTML = '';
    activeStories = [];
    activeStoryIndex = 0;
    activeStoryButton = null;
});
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>

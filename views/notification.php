<?php
require_once __DIR__ . '/_page_helpers.php';
app_start_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('views/sign_in.php'));
    exit;
}

require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();

$pageTitle  = 'Instagram – Notifications';
$activePage = 'notification';

$current_user_id = $_SESSION['user_id'];

$notifyStmt = $db->prepare("
    SELECT
        notifications.*,

        users.username,
        users.profile_pic,

        posts.media_path

    FROM notifications

    JOIN users
    ON notifications.from_user_id = users.id

    LEFT JOIN posts
    ON notifications.post_id = posts.id

    WHERE notifications.user_id = ?

    ORDER BY notifications.id DESC
");

$notifyStmt->execute([$current_user_id]);

$notifications = $notifyStmt->fetchAll();

$readStmt = $db->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE user_id = ?
");

$readStmt->execute([$current_user_id]);

function timeAgo($datetime)
{
    $time = time() - strtotime($datetime);

    if ($time < 60) {
        return $time . 's';
    }

    if ($time < 3600) {
        return floor($time / 60) . 'm';
    }

    if ($time < 86400) {
        return floor($time / 3600) . 'h';
    }

    if ($time < 604800) {
        return floor($time / 86400) . 'd';
    }

    return date('d M', strtotime($datetime));
}

include __DIR__ . '/../components/head.php';
?>

<style>
.notifications_wrapper{
    max-width:650px;
    margin:auto;
}

.notification_card{
    transition:.2s;
    border-radius:12px;
    cursor:pointer;
}

.notification_card:hover{
    background:#fafafa;
}

.notification_unread{
    background:#f2f8ff;
}

.notification_avatar{
    width:48px;
    height:48px;
    border-radius:50%;
    object-fit:cover;
    cursor:pointer;
}

.notification_post{
    width:48px;
    height:48px;
    border-radius:6px;
    object-fit:cover;
    cursor:pointer;
}

.follow_btn{
    border:none;
    background:#0095f6;
    color:#fff;
    padding:6px 14px;
    border-radius:8px;
    font-size:14px;
    font-weight:600;
}

.following_btn{
    border:none;
    background:#efefef;
    color:#000;
    padding:6px 14px;
    border-radius:8px;
    font-size:14px;
    font-weight:600;
}

.empty_notifications{
    height:70vh;
    display:flex;
    align-items:center;
    justify-content:center;
    flex-direction:column;
}
</style>


<div class="post_page d-flex">

    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <main class="flex-grow-1 py-4 px-3">

        <div class="notifications_wrapper">

            <div class="d-flex align-items-center justify-content-between mb-4">
                <h4 class="fw-bold mb-0">Notifications</h4>
            </div>


            <?php if(count($notifications) > 0): ?>

                <?php 
                // Ye array track karega ki kis user ka follow button humne dikha diya hai
                $seen_follow_users = []; 
                ?>

                <?php foreach($notifications as $notify): ?>

                <?php
                $text = '';
                switch($notify['type']) {
                    case 'like':
                        $text = 'liked your post.';
                        break;
                    case 'comment':
                        $text = 'commented on your post.';
                        break;
                    case 'follow':
                    case 'follow_request':
                        $text = 'started following you.';
                        break;
                    case 'mention':
                        $text = 'mentioned you.';
                        break;
                    case 'message':
                        $text = 'sent you a message.';
                        break;
                    case 'message_request_declined':
                        $text = 'is not interested in chatting right now.';
                        break;
                }

                // Follow Check
                $followStmt = $db->prepare("
                    SELECT id FROM follows
                    WHERE follower_id = ? AND following_id = ?
                ");
                $followStmt->execute([$current_user_id, $notify['from_user_id']]);
                $isFollowing = $followStmt->rowCount() > 0;

                // JS ke liye safe values
                $postId       = (int) $notify['post_id'];
                $senderId     = (int) $notify['from_user_id']; // Yaha User ID nikal rahe hain
                
                // Check condition: Post id hai to goToPost, warna goToProfile with senderId
                $cardClickAction = ($postId > 0) ? "goToPost($postId)" : "goToProfile(event, $senderId)";
                ?>

                <div class="notification_card d-flex align-items-center gap-3 p-3 mb-2 <?= $notify['is_read'] ? '' : 'notification_unread' ?>"
                     onclick="<?= $cardClickAction ?>">

                    <img src="<?= htmlspecialchars(profile_avatar($notify['profile_pic'], $notify['username'])) ?>"
                         class="notification_avatar"
                         alt="user"
                         onclick="event.stopPropagation(); goToProfile(event, <?= $senderId ?>)">

                    <div class="flex-grow-1">
                        <div class="small">

                            <strong style="cursor:pointer;"
                                    onclick="event.stopPropagation(); goToProfile(event, <?= $senderId ?>)">
                                <?= htmlspecialchars($notify['username']) ?>
                            </strong>

                            <?= htmlspecialchars($text) ?>

                            <span class="text-muted">
                                <?= timeAgo($notify['created_at']) ?>
                            </span>

                        </div>
                    </div>

                    <div>

                        <?php if($notify['type'] == 'follow'): ?>

                            <?php
                            // Agar is sender ka follow button abhi tak nahi dikhaya gaya hai tabhi show karein
                            $isLatestFollow = !isset($seen_follow_users[$senderId]);
                            if ($isLatestFollow) {
                                $seen_follow_users[$senderId] = true; // Isko seen mark kar do
                            }
                            ?>

                            <?php if($isLatestFollow): ?>
                            <button type="button"
                                    class="<?= $isFollowing ? 'following_btn' : 'follow_btn' ?>"
                                    data-user-id="<?= $senderId ?>"
                                    data-follow-label="Follow Back"
                                    onclick="event.stopPropagation(); toggleFollow(this)">
                                <?= $isFollowing ? 'Following' : 'Follow Back' ?>
                            </button>
                            <?php endif; ?>

                        <?php elseif(!empty($notify['media_path'])): ?>

                            <?php if(str_contains($notify['media_path'], '.mp4')): ?>

                                <video class="notification_post"
                                       muted
                                       onclick="event.stopPropagation(); goToPost(<?= $postId ?>)">
                                    <source src="<?= htmlspecialchars(post_media_url($notify['media_path'])) ?>">
                                </video>

                            <?php else: ?>

                                <img src="<?= htmlspecialchars(post_media_url($notify['media_path'])) ?>"
                                     class="notification_post"
                                     alt="post"
                                     onclick="event.stopPropagation(); goToPost(<?= $postId ?>)">

                            <?php endif; ?>

                        <?php endif; ?>

                    </div>

                </div>

                <?php endforeach; ?>

            <?php else: ?>

            <div class="empty_notifications text-center">
                <i class="fa-regular fa-heart fs-1 mb-4"></i>
                <h4 class="fw-bold">Activity On Your Posts</h4>
                <p class="text-muted">
                    When someone likes or comments on your posts, you'll see it here.
                </p>
            </div>

            <?php endif; ?>

        </div>

    </main>

</div>


<script>
// Us user ki profile kholo jisse notification aaya (User ID use karke)
function goToProfile(event, userId) {
    if (event.target.closest('button')) return;
    // URL mein ?id= lagaya gaya hai kyunki profile.php id se chalti hai
    window.location.href = <?= json_encode(app_url('views/profile.php')) ?> + '?id=' + userId;
}

// Post open karo
function goToPost(postId) {
    if (!postId) return;
    window.location.href = <?= json_encode(app_url('views/single_post.php')) ?> + '?id=' + postId;
}

// Follow toggle
function toggleFollow(btn) {
    const userId = btn.dataset.userId;
    if (!userId) return;

    btn.disabled = true;

    fetch(<?= json_encode(app_url('controllers/toggle_follow.php')) ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ following_id: userId })
    })
    .then(response => {
        if (!response.ok) throw new Error('Network error');
        return response.json();
    })
    .then(data => {
        if (!data.success) throw new Error('Server error');

        if (data.state === 'following') {
            btn.innerText = 'Following';
            btn.classList.remove('follow_btn');
            btn.classList.add('following_btn');
        } else {
            btn.innerText = 'Follow Back';
            btn.classList.remove('following_btn');
            btn.classList.add('follow_btn');
        }
    })
    .catch(err => {
        console.error('toggleFollow error:', err);
    })
    .finally(() => {
        btn.disabled = false;
    });
}
</script>


<?php include __DIR__ . '/../components/create_modal.php'; ?>
<?php include __DIR__ . '/../components/footer.php'; ?>

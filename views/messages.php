<?php
// views/messages.php
require_once __DIR__ . '/_page_helpers.php';
app_start_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('views/sign_in.php'));
    exit;
}

require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();

// Ensure required tables
$db->exec("CREATE TABLE IF NOT EXISTS message_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    status ENUM('pending','accepted','declined') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_request (sender_id, receiver_id),
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
)");

$db->exec("CREATE TABLE IF NOT EXISTS blocked_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blocker_id INT NOT NULL,
    blocked_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_block (blocker_id, blocked_id),
    FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
)");

$pageTitle       = 'Instagram – Messages';
$activePage      = 'messages';
$current_user_id = (int) $_SESSION['user_id'];
$search          = trim($_GET['q'] ?? '');
$shareMessage    = trim($_GET['share'] ?? '');
if (strlen($shareMessage) > 1000) $shareMessage = substr($shareMessage, 0, 1000);
$sharePostId = (int) ($_GET['share_post_id'] ?? $_POST['share_post_id'] ?? 0);
$sharePost   = null;

if ($sharePostId > 0) {
    $s = $db->prepare("SELECT posts.*, users.username, users.profile_pic FROM posts JOIN users ON users.id = posts.user_id WHERE posts.id = ? LIMIT 1");
    $s->execute([$sharePostId]);
    $sharePost = $s->fetch();
    if (!$sharePost) $sharePostId = 0;
}

$chat_user_id = isset($_GET['user']) ? (int) $_GET['user'] : 0;

// ── Send Message ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receiver_id'])) {
    require_csrf();
    $receiver_id       = (int) ($_POST['receiver_id'] ?? 0);
    $message           = trim($_POST['message'] ?? '');
    $postedSharePostId = (int) ($_POST['share_post_id'] ?? 0);
    if (strlen($message) > 2000) {
        $message = substr($message, 0, 2000);
    }

    if ($postedSharePostId > 0) {
        $pc = $db->prepare("SELECT id FROM posts WHERE id = ? LIMIT 1");
        $pc->execute([$postedSharePostId]);
        if ($pc->fetch()) $message = '__POST_SHARE__:' . $postedSharePostId;
    }

    $rx = $db->prepare("SELECT id FROM users WHERE id = ? AND id != ? LIMIT 1");
    $rx->execute([$receiver_id, $current_user_id]);
    $receiverExists = (bool) $rx->fetch();

    if ($receiverExists && $message !== '') {
        $blocked = $db->prepare(
            "SELECT id FROM blocked_users
             WHERE (blocker_id = ? AND blocked_id = ?)
                OR (blocker_id = ? AND blocked_id = ?)
             LIMIT 1"
        );
        $blocked->execute([$current_user_id, $receiver_id, $receiver_id, $current_user_id]);
        if ($blocked->fetch()) {
            if ((strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') || isset($_POST['is_ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'User is blocked']);
                exit;
            }
            header('Location: ' . app_url('views/messages.php'));
            exit;
        }

        // Follow check
        $fc = $db->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ? LIMIT 1");
        $fc->execute([$current_user_id, $receiver_id]);
        $isFollowingReceiver = (bool) $fc->fetch();

        // Existing request check
        $rc = $db->prepare("SELECT status FROM message_requests WHERE sender_id = ? AND receiver_id = ? LIMIT 1");
        $rc->execute([$current_user_id, $receiver_id]);
        $existingRequest = $rc->fetch();
        $requestAccepted = ($existingRequest && $existingRequest['status'] === 'accepted');

        // Insert message
        $db->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())")
           ->execute([$current_user_id, $receiver_id, $message]);
        $messageId = (int) $db->lastInsertId();

        if (!$isFollowingReceiver && !$requestAccepted) {
            if (!$existingRequest) {
                $db->prepare("INSERT IGNORE INTO message_requests (sender_id, receiver_id, status, created_at) VALUES (?, ?, 'pending', NOW())")
                   ->execute([$current_user_id, $receiver_id]);
            }
        } else {
            $db->prepare("INSERT INTO notifications (user_id, from_user_id, type, created_at) VALUES (?, ?, 'message', NOW())")
               ->execute([$receiver_id, $current_user_id]);
        }

        // If it's an AJAX fetch request, exit cleanly with JSON instead of redirecting
        if ((strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') || isset($_POST['is_ajax'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => [
                    'id' => $messageId,
                    'sender_id' => $current_user_id,
                    'receiver_id' => $receiver_id,
                    'message' => $message,
                ],
            ]);
            exit;
        }

        header('Location: ' . app_url('views/messages.php?user=' . $receiver_id));
        exit;
    }
}

// ── Conversations ─────────────────────────────────────────────────────────────
$conversations = [];
try {
    $convoStmt = $db->prepare("
        SELECT DISTINCT u.id, u.username, u.full_name, u.profile_pic,
            (SELECT message FROM messages
             WHERE (sender_id=u.id AND receiver_id=?) OR (sender_id=? AND receiver_id=u.id)
             ORDER BY created_at DESC, id DESC LIMIT 1) AS last_message,
            (SELECT created_at FROM messages
             WHERE (sender_id=u.id AND receiver_id=?) OR (sender_id=? AND receiver_id=u.id)
             ORDER BY created_at DESC, id DESC LIMIT 1) AS last_time,
            (SELECT COUNT(*) FROM messages WHERE sender_id=u.id AND receiver_id=? AND is_read=0) AS unread_count
        FROM users u
        WHERE u.id != ?
          AND EXISTS (
              SELECT 1 FROM messages m
              WHERE (m.sender_id=? AND m.receiver_id=u.id) OR (m.sender_id=u.id AND m.receiver_id=?)
          )
          AND (
              EXISTS (SELECT 1 FROM follows WHERE follower_id=? AND following_id=u.id)
              OR EXISTS (SELECT 1 FROM message_requests WHERE sender_id=u.id AND receiver_id=? AND status='accepted')
              OR EXISTS (SELECT 1 FROM message_requests WHERE sender_id=? AND receiver_id=u.id AND status='accepted')
              OR EXISTS (SELECT 1 FROM messages WHERE sender_id=? AND receiver_id=u.id)
          )
          AND NOT EXISTS (
              SELECT 1 FROM blocked_users
              WHERE (blocker_id=? AND blocked_id=u.id) OR (blocker_id=u.id AND blocked_id=?)
          )
        ORDER BY last_time DESC
    ");
    $convoStmt->execute([
        $current_user_id, $current_user_id,
        $current_user_id, $current_user_id,
        $current_user_id,
        $current_user_id,
        $current_user_id, $current_user_id,
        $current_user_id, $current_user_id,
        $current_user_id, $current_user_id,
        $current_user_id, $current_user_id,
    ]);
    $conversations = $convoStmt->fetchAll();
} catch (Exception $e) {
    $conversations = [];
}

// ── Pending Message Requests ──────────────────────────────────────────────────
$messageRequests = [];
try {
    $mrStmt = $db->prepare("
        SELECT u.id, u.username, u.full_name, u.profile_pic, mr.created_at AS request_time,
               (SELECT message FROM messages WHERE sender_id=u.id AND receiver_id=? ORDER BY id DESC LIMIT 1) AS preview_message
        FROM message_requests mr
        JOIN users u ON u.id = mr.sender_id
        WHERE mr.receiver_id=? AND mr.status='pending'
        ORDER BY mr.created_at DESC
    ");
    $mrStmt->execute([$current_user_id, $current_user_id]);
    $messageRequests = $mrStmt->fetchAll();
} catch (Exception $e) {
    $messageRequests = [];
}

// ── Search / Share Recipients ─────────────────────────────────────────────────
$searchResults = [];
if ($search !== '') {
    $kw = '%' . $search . '%';
    $ss = $db->prepare("
        SELECT id, username, full_name, profile_pic
        FROM users
        WHERE id!=?
          AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)
          AND NOT EXISTS (
              SELECT 1 FROM blocked_users
              WHERE (blocker_id=? AND blocked_id=users.id)
                 OR (blocker_id=users.id AND blocked_id=?)
          )
        ORDER BY username ASC
        LIMIT 20
    ");
    $ss->execute([$current_user_id, $kw, $kw, $kw, $current_user_id, $current_user_id]);
    $searchResults = $ss->fetchAll();
}

$shareRecipients = [];
if (($shareMessage !== '' || $sharePostId > 0) && $search === '') {
    $sr = $db->prepare("
        SELECT DISTINCT u.id, u.username, u.full_name, u.profile_pic FROM users u
        WHERE u.id!=?
          AND (EXISTS(SELECT 1 FROM follows f WHERE f.follower_id=? AND f.following_id=u.id)
               OR EXISTS(SELECT 1 FROM follows f WHERE f.follower_id=u.id AND f.following_id=?))
          AND NOT EXISTS (
              SELECT 1 FROM blocked_users
              WHERE (blocker_id=? AND blocked_id=u.id)
                 OR (blocker_id=u.id AND blocked_id=?)
          )
        ORDER BY u.username ASC LIMIT 20
    ");
    $sr->execute([$current_user_id, $current_user_id, $current_user_id, $current_user_id, $current_user_id]);
    $shareRecipients = $sr->fetchAll();
}

// ── Chat User ─────────────────────────────────────────────────────────────────
$chatUser           = null;
$chatIsRequest      = false;
$chatRequestAccepted = false;

if ($chat_user_id > 0) {
    $cu = $db->prepare("SELECT * FROM users WHERE id=? AND id!=? LIMIT 1");
    $cu->execute([$chat_user_id, $current_user_id]);
    $chatUser = $cu->fetch() ?: null;

    if ($chatUser) {
        $fc2 = $db->prepare("SELECT id FROM follows WHERE follower_id=? AND following_id=? LIMIT 1");
        $fc2->execute([$current_user_id, $chat_user_id]);
        $iFollowThem = (bool) $fc2->fetch();

        $ir = $db->prepare("SELECT status FROM message_requests WHERE sender_id=? AND receiver_id=? LIMIT 1");
        $ir->execute([$chat_user_id, $current_user_id]);
        $incomingReq = $ir->fetch();

        $or2 = $db->prepare("SELECT status FROM message_requests WHERE sender_id=? AND receiver_id=? LIMIT 1");
        $or2->execute([$current_user_id, $chat_user_id]);
        $outgoingReq = $or2->fetch();

        $chatIsRequest       = ($incomingReq && $incomingReq['status'] === 'pending');
        $chatRequestAccepted = ($incomingReq && $incomingReq['status'] === 'accepted')
                             || ($outgoingReq && $outgoingReq['status'] === 'accepted')
                             || $iFollowThem;
    }
}

// ── Messages in chat ──────────────────────────────────────────────────────────
$messages    = [];
$sharedPosts = [];

if ($chat_user_id > 0 && $chatUser) {
    $ms = $db->prepare("SELECT * FROM messages WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY id ASC");
    $ms->execute([$current_user_id, $chat_user_id, $chat_user_id, $current_user_id]);
    $messages = $ms->fetchAll();

    $sharedPostIds = [];
    foreach ($messages as $mr2) {
        if (preg_match('/^__POST_SHARE__:(\d+)$/', $mr2['message'] ?? '', $m2)) $sharedPostIds[] = (int)$m2[1];
    }
    $sharedPostIds = array_values(array_unique(array_filter($sharedPostIds)));
    if ($sharedPostIds) {
        $ph = implode(',', array_fill(0, count($sharedPostIds), '?'));
        $sp = $db->prepare("SELECT posts.*, users.username, users.profile_pic FROM posts JOIN users ON users.id=posts.user_id WHERE posts.id IN ($ph)");
        $sp->execute($sharedPostIds);
        foreach ($sp->fetchAll() as $row) $sharedPosts[(int)$row['id']] = $row;
    }

    $db->prepare("UPDATE messages SET is_read=1 WHERE sender_id=? AND receiver_id=?")
       ->execute([$chat_user_id, $current_user_id]);
}

// ── Shared post renderer ──────────────────────────────────────────────────────
$renderSharedPost = function(?array $post): void {
    if (!$post) { echo '<div class="shared_post_card"><div class="shared_post_meta text-muted">Post no longer available.</div></div>'; return; }
    $isVideo = is_video_media($post['media_path'] ?? '', $post['media_type'] ?? '');
    $postUrl = app_url('views/single_post.php?id=' . (int)$post['id']);
    ?>
    <a href="<?= htmlspecialchars($postUrl) ?>" class="text-decoration-none" target="_self" onclick="event.stopPropagation()">
    <div class="shared_post_card" style="cursor:pointer;">
        <?php if($isVideo): ?>
            <video class="shared_post_media" autoplay loop muted playsinline><source src="<?= htmlspecialchars(post_media_url($post['media_path'])) ?>"></video>
        <?php else: ?>
            <img src="<?= htmlspecialchars(post_media_url($post['media_path'])) ?>" class="shared_post_media" alt="shared post">
        <?php endif; ?>
        <div class="shared_post_meta">
            <div class="fw-bold mb-1" style="color:#262626;">@<?= htmlspecialchars($post['username'] ?? 'user') ?></div>
            <?php if(!empty($post['caption'])): ?><div class="text-truncate" style="color:#262626;"><?= htmlspecialchars($post['caption']) ?></div><?php endif; ?>
            <div style="color:#0095f6; font-size:12px; margin-top:4px;">View Post →</div>
        </div>
    </div>
    </a>
    <?php
};

include __DIR__ . '/../components/head.php';
?>
<style>
.chat_sidebar{width:350px;border-right:1px solid #dbdbdb;overflow-y:auto;}
.chat_item{transition:.2s;}
.chat_item:hover{background:#fafafa;}
.chat_search{padding:12px 16px;border-bottom:1px solid #efefef;}
.chat_search .form-control{background:#efefef;border:0;height:40px;}
.chat_section_label{padding:12px 16px 6px;font-size:12px;font-weight:700;color:#8e8e8e;text-transform:uppercase;letter-spacing:.04em;}
.chat_unread_badge{min-width:20px;height:20px;background:#0095f6;color:#fff;border-radius:999px;font-size:11px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;padding:0 5px;flex-shrink:0;}
.msg_request_banner{background:#fff8e1;border:1px solid #ffe082;border-radius:10px;padding:12px 16px;margin:10px 12px;font-size:13px;}
.msg_request_banner .req_actions{display:flex;gap:8px;margin-top:10px;}
.btn_accept{background:#0095f6;color:#fff;border:none;border-radius:8px;padding:6px 16px;font-size:13px;font-weight:600;cursor:pointer;}
.btn_decline{background:#efefef;color:#262626;border:none;border-radius:8px;padding:6px 16px;font-size:13px;font-weight:600;cursor:pointer;}
.msg_tabs{display:flex;border-bottom:1px solid #efefef;}
.msg_tab{flex:1;text-align:center;padding:10px 0;font-size:14px;font-weight:600;color:#8e8e8e;cursor:pointer;border-bottom:2px solid transparent;transition:.15s;position:relative;}
.msg_tab.active{color:#262626;border-bottom-color:#262626;}
.msg_tab_badge{position:absolute;top:6px;right:calc(50% - 28px);min-width:16px;height:16px;background:#ed4956;color:#fff;border-radius:999px;font-size:10px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;padding:0 4px;}
.chat_blocked_notice{background:#fafafa;border-top:1px solid #dbdbdb;padding:16px;text-align:center;font-size:13px;color:#8e8e8e;}
.chat_messages{background:#fff;}
.msg_sent{background:#3797f0;color:#fff;border-radius:18px 18px 4px 18px;padding:10px 14px;max-width:65%;margin-left:auto;word-break:break-word;}
.msg_received{background:#efefef;border-radius:18px 18px 18px 4px;padding:10px 14px;max-width:65%;word-break:break-word;}
.message_row{position:relative;}
.message_row.sent:hover .msg_delete_btn{opacity:1;pointer-events:auto;}
.msg_delete_btn{align-self:center;border:0;background:#efefef;color:#737373;width:30px;height:30px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:.15s;margin-right:8px;}
.msg_delete_btn:hover{background:#ffe8e8;color:#ed4956;}
.shared_post_card{width:min(280px,72vw);overflow:hidden;border:1px solid #dbdbdb;border-radius:14px;background:#fff;color:#262626;}
.shared_post_media{width:100%;aspect-ratio:1;display:block;background:#000;object-fit:cover;}
.shared_post_meta{padding:10px 12px;font-size:13px;}
.chat_input{border-top:1px solid #dbdbdb;padding:15px;}
.chat_empty{height:100%;display:flex;justify-content:center;align-items:center;flex-direction:column;}
@media(max-width:768px){.chat_sidebar{width:100%;}.chat_window{display:none;}.chat_window.active{display:flex;width:100%;}}
</style>

<div class="post_page d-flex" style="height:100vh;overflow:hidden;">
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <aside class="chat_sidebar bg-white">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><?= htmlspecialchars($_SESSION['username']) ?></h5>
            <i class="fa-regular fa-pen-to-square fs-5" style="cursor:pointer"></i>
        </div>

        <?php $reqCount = count($messageRequests); ?>
        <div class="msg_tabs">
            <div class="msg_tab active" id="tab_messages" onclick="switchTab('messages')">Messages</div>
            <div class="msg_tab" id="tab_requests" onclick="switchTab('requests')">
                Requests
                <?php if ($reqCount > 0): ?><span class="msg_tab_badge"><?= $reqCount ?></span><?php endif; ?>
            </div>
        </div>

        <form method="GET" action="<?= htmlspecialchars(app_url('views/messages.php')) ?>" class="chat_search" id="msg_search_form">
            <div class="position-relative">
                <i class="fa-solid fa-magnifying-glass position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control rounded-pill ps-5" placeholder="Search users..." autocomplete="off">
                <?php if($shareMessage !== ''): ?><input type="hidden" name="share" value="<?= htmlspecialchars($shareMessage) ?>"><?php endif; ?>
                <?php if($sharePostId > 0): ?><input type="hidden" name="share_post_id" value="<?= (int)$sharePostId ?>"><?php endif; ?>
            </div>
        </form>

        <div id="panel_messages">
        <?php if($search !== ''): ?>
            <div class="chat_section_label">Search Results</div>
            <?php if(count($searchResults) > 0): ?>
                <?php foreach($searchResults as $result): ?>
                <a href="<?= htmlspecialchars(app_url('views/messages.php?user=' . (int)$result['id'])) ?>" class="text-decoration-none text-dark">
                    <div class="chat_item d-flex align-items-center gap-3 p-3">
                        <img src="<?= htmlspecialchars(profile_avatar($result['profile_pic'], $result['username'])) ?>" class="rounded-circle" width="50" height="50" style="object-fit:cover;" alt="user">
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fw-semibold"><?= htmlspecialchars($result['username']) ?></div>
                            <div class="small text-muted text-truncate"><?= htmlspecialchars($result['full_name'] ?: 'Tap to message') ?></div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-4 text-muted small">No users found.</div>
            <?php endif; ?>
            <div class="chat_section_label">Conversations</div>
        <?php endif; ?>

        <?php if(($shareMessage !== '' || $sharePostId > 0) && $search === ''): ?>
            <div class="chat_section_label">Share To</div>
            <?php if(count($shareRecipients) > 0): ?>
                <?php foreach($shareRecipients as $recipient): ?>
                <a href="<?= htmlspecialchars(app_url('views/messages.php?' . http_build_query(['user'=>$recipient['id'],'share'=>$shareMessage,'share_post_id'=>$sharePostId?:'']))) ?>" class="text-decoration-none text-dark">
                    <div class="chat_item d-flex align-items-center gap-3 p-3">
                        <img src="<?= htmlspecialchars(profile_avatar($recipient['profile_pic'], $recipient['username'])) ?>" class="rounded-circle" width="50" height="50" style="object-fit:cover;" alt="user">
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fw-semibold"><?= htmlspecialchars($recipient['username']) ?></div>
                            <div class="small text-muted text-truncate"><?= htmlspecialchars($recipient['full_name'] ?: 'Tap to send') ?></div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-4 text-muted small">Search a user to send this share.</div>
            <?php endif; ?>
            <div class="chat_section_label">Conversations</div>
        <?php endif; ?>

        <?php if(count($conversations) > 0): ?>
            <?php foreach($conversations as $convo): ?>
            <a href="<?= htmlspecialchars(app_url('views/messages.php?user=' . (int)$convo['id'])) ?>" class="text-decoration-none text-dark">
                <div class="chat_item d-flex align-items-center gap-3 p-3">
                    <img src="<?= htmlspecialchars(profile_avatar($convo['profile_pic'], $convo['username'])) ?>" class="rounded-circle" width="56" height="56" style="object-fit:cover;" alt="user">
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="fw-semibold"><?= htmlspecialchars($convo['username']) ?></div>
                        <div class="small text-muted text-truncate"><?= htmlspecialchars(preg_match('/^__POST_SHARE__:\d+$/', $convo['last_message'] ?? '') ? 'Shared a post' : ($convo['last_message'] ?? 'Start chatting')) ?></div>
                    </div>
                    <?php if ((int)($convo['unread_count'] ?? 0) > 0): ?>
                        <span class="chat_unread_badge"><?= (int)$convo['unread_count'] ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-regular fa-message fs-1 mb-3"></i>
                <h6>No Conversations Yet</h6>
            </div>
        <?php endif; ?>
        </div><div id="panel_requests" style="display:none">
            <?php if (count($messageRequests) > 0): ?>
                <div class="chat_section_label">Message Requests</div>
                <?php foreach ($messageRequests as $req): ?>
                <div class="chat_item d-flex align-items-center gap-3 p-3" id="req_item_<?= (int)$req['id'] ?>">
                    <a href="<?= htmlspecialchars(app_url('views/profile.php?id=' . (int)$req['id'])) ?>">
                        <img src="<?= htmlspecialchars(profile_avatar($req['profile_pic'], $req['username'])) ?>" class="rounded-circle" width="50" height="50" style="object-fit:cover;" alt="user">
                    </a>
                    <div class="flex-grow-1 overflow-hidden">
                        <a href="<?= htmlspecialchars(app_url('views/messages.php?user=' . (int)$req['id'] . '&tab=requests')) ?>" class="text-dark text-decoration-none fw-semibold">
                            <?= htmlspecialchars($req['username']) ?>
                        </a>
                        <div class="small text-muted text-truncate">
                            <?= htmlspecialchars(preg_match('/^__POST_SHARE__:\d+$/', $req['preview_message'] ?? '') ? 'Shared a post' : ($req['preview_message'] ?? 'Sent you a message')) ?>
                        </div>
                        <div class="d-flex gap-2 mt-2">
                            <button class="btn_accept" onclick="handleRequest(<?= (int)$req['id'] ?>, 'accept')">Accept</button>
                            <button class="btn_decline" onclick="handleRequest(<?= (int)$req['id'] ?>, 'decline')">Decline</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-regular fa-envelope fs-1 mb-3"></i>
                    <h6>No Requests</h6>
                    <p class="small">Message requests from people you don't follow will appear here.</p>
                </div>
            <?php endif; ?>
        </div></aside>

    <main class="chat_window flex-grow-1 d-flex flex-column bg-white <?= $chatUser ? 'active' : '' ?>">
    <?php if($chatUser): ?>

        <div class="p-3 border-bottom d-flex align-items-center gap-3">
            <img src="<?= htmlspecialchars(profile_avatar($chatUser['profile_pic'], $chatUser['username'])) ?>" class="rounded-circle" width="42" height="42" style="object-fit:cover;" alt="user">
            <div>
                <div class="fw-bold"><?= htmlspecialchars($chatUser['username']) ?></div>
                <div class="small text-muted">
                <?php
                $ls = $chatUser['last_seen'] ?? null;
                if ($ls) {
                    $ds = time() - strtotime($ls);
                    if ($ds < 300) echo 'Active now';
                    elseif ($ds < 3600) echo 'Active ' . (int)($ds/60) . 'm ago';
                    elseif ($ds < 86400) echo 'Active ' . (int)($ds/3600) . 'h ago';
                    else echo 'Active ' . (int)($ds/86400) . 'd ago';
                } else { echo 'Offline'; }
                ?>
                </div>
            </div>
        </div>

        <div class="chat_messages flex-grow-1 overflow-auto p-4" id="chatMessages">
        <?php if(count($messages) > 0): ?>
            <?php foreach($messages as $msg): ?>
            <?php
            $spid = 0;
            if (preg_match('/^__POST_SHARE__:(\d+)$/', $msg['message'] ?? '', $sm)) $spid = (int)$sm[1];
            ?>
            <?php if($msg['sender_id'] == $current_user_id): ?>
            <div class="message_row sent d-flex justify-content-end mb-3" data-message-id="<?= (int)$msg['id'] ?>">
                <button type="button" class="msg_delete_btn" title="Delete message" data-delete-message-id="<?= (int)$msg['id'] ?>">
                    <i class="fa-solid fa-trash"></i>
                </button>
                <?php if($spid > 0): ?><?php $renderSharedPost($sharedPosts[$spid] ?? null); ?>
                <?php else: ?><div class="msg_sent"><?= nl2br(htmlspecialchars($msg['message'])) ?></div><?php endif; ?>
            </div>
            <?php else: ?>
            <div class="message_row received d-flex align-items-end gap-2 mb-3" data-message-id="<?= (int)$msg['id'] ?>">
                <img src="<?= htmlspecialchars(profile_avatar($chatUser['profile_pic'], $chatUser['username'])) ?>" class="rounded-circle" width="28" height="28" alt="user">
                <?php if($spid > 0): ?><?php $renderSharedPost($sharedPosts[$spid] ?? null); ?>
                <?php else: ?><div class="msg_received"><?= nl2br(htmlspecialchars($msg['message'])) ?></div><?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="chat_empty">
                <img src="<?= htmlspecialchars(profile_avatar($chatUser['profile_pic'], $chatUser['username'])) ?>" class="rounded-circle mb-3" width="90" height="90" alt="user">
                <h5><?= htmlspecialchars($chatUser['username']) ?></h5>
                <p class="text-muted">Send a message to start chatting.</p>
            </div>
        <?php endif; ?>
        </div>

        <?php if ($chatIsRequest): ?>
        <div class="msg_request_banner">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="fa-regular fa-envelope text-warning fs-5"></i>
                <strong><?= htmlspecialchars($chatUser['username']) ?></strong> wants to send you a message.
            </div>
            <p class="small text-muted mb-2">You can accept or decline this request. They won't know you declined.</p>
            <div class="req_actions">
                <button class="btn_accept" onclick="handleRequest(<?= $chat_user_id ?>, 'accept')">
                    <i class="fa-solid fa-check me-1"></i>Accept
                </button>
                <button class="btn_decline" onclick="handleRequest(<?= $chat_user_id ?>, 'decline')">
                    <i class="fa-solid fa-xmark me-1"></i>Decline
                </button>
            </div>
        </div>
        <div class="chat_blocked_notice">
            <i class="fa-solid fa-lock me-1"></i>Accept the request to reply.
        </div>
        <?php else: ?>
        <form method="POST"
              class="chat_input d-flex gap-2 align-items-center flex-wrap"
              id="socketChatForm"
              data-receiver-id="<?= (int)$chat_user_id ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="receiver_id" value="<?= (int)$chat_user_id ?>">
            <?php if($sharePostId > 0 && $sharePost): ?>
                <input type="hidden" name="share_post_id" value="<?= (int)$sharePostId ?>">
                <div class="shared_post_card" style="width:180px">
                    <?php if(is_video_media($sharePost['media_path']??'', $sharePost['media_type']??'')): ?>
                        <video class="shared_post_media" muted><source src="<?= htmlspecialchars(post_media_url($sharePost['media_path'])) ?>"></video>
                    <?php else: ?>
                        <img src="<?= htmlspecialchars(post_media_url($sharePost['media_path'])) ?>" class="shared_post_media" alt="post">
                    <?php endif; ?>
                    <div class="shared_post_meta"><strong>@<?= htmlspecialchars($sharePost['username']??'user') ?></strong></div>
                </div>
            <?php endif; ?>
            <input type="text" name="message" class="form-control rounded-pill"
                   id="socketChatInput"
                   placeholder="<?= $sharePostId > 0 ? 'Post ready to send...' : 'Message...' ?>"
                   value="<?= htmlspecialchars($shareMessage) ?>" autocomplete="off"
                   <?= $sharePostId > 0 ? '' : 'required' ?>>
            <button type="submit" class="btn btn-primary rounded-pill px-4">Send</button>
        </form>
        <?php endif; ?>

    <?php else: ?>
        <div class="chat_empty flex-grow-1">
            <i class="fa-regular fa-paper-plane fs-1 mb-4"></i>
            <h4>Your Messages</h4>
            <p class="text-muted"><?= ($shareMessage !== '' || $sharePostId > 0) ? 'Select a chat to send this post.' : 'Send private photos and messages to a friend.' ?></p>
        </div>
    <?php endif; ?>
    </main>
</div>

<script>
const chatMessages = document.getElementById('chatMessages');
if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;

function switchTab(tab) {
    document.getElementById('panel_messages').style.display = tab === 'messages' ? '' : 'none';
    document.getElementById('panel_requests').style.display  = tab === 'requests'  ? '' : 'none';
    document.getElementById('tab_messages').classList.toggle('active', tab === 'messages');
    document.getElementById('tab_requests').classList.toggle('active', tab === 'requests');
    const sf = document.getElementById('msg_search_form');
    if (sf) sf.style.display = tab === 'messages' ? '' : 'none';
}

// Auto-open requests tab if URL has tab=requests
(function() {
    if (new URLSearchParams(window.location.search).get('tab') === 'requests') switchTab('requests');
})();

function handleRequest(senderId, action) {
    fetch(<?= json_encode(app_url('controllers/message_request_action.php')) ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ sender_id: senderId, action: action })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        if (action === 'accept') {
            window.location.href = <?= json_encode(app_url('views/messages.php')) ?> + '?user=' + senderId;
        } else {
            const el = document.getElementById('req_item_' + senderId);
            if (el) el.remove();
            const badge = document.querySelector('#tab_requests .msg_tab_badge');
            if (badge) {
                const cur = parseInt(badge.textContent) - 1;
                if (cur <= 0) badge.remove(); else badge.textContent = cur;
            }
            // If we were viewing this request's chat, go back
            const params = new URLSearchParams(window.location.search);
            if (parseInt(params.get('user')) === senderId) {
                window.location.href = <?= json_encode(app_url('views/messages.php')) ?>;
            }
        }
    });
}

const CURRENT_USER_ID = <?= (int)$current_user_id ?>;
const ACTIVE_CHAT_USER_ID = <?= (int)$chat_user_id ?>;
const ACTIVE_CHAT_AVATAR = <?= json_encode($chatUser ? profile_avatar($chatUser['profile_pic'], $chatUser['username']) : '') ?>;

function escapeMessageHtml(value) {
    const div = document.createElement('div');
    div.textContent = value || '';
    return div.innerHTML.replace(/\n/g, '<br>');
}

function renderSocketMessage(message) {
    if (!chatMessages || !message) return;

    const senderId = Number(message.sender_id || 0);
    const receiverId = Number(message.receiver_id || 0);
    const belongsToActiveChat =
        ACTIVE_CHAT_USER_ID > 0 &&
        ((senderId === CURRENT_USER_ID && receiverId === ACTIVE_CHAT_USER_ID) ||
         (senderId === ACTIVE_CHAT_USER_ID && receiverId === CURRENT_USER_ID));

    if (!belongsToActiveChat) return;

    chatMessages.querySelector('.chat_empty')?.remove();

    const text = message.message || '';
    const shareMatch = text.match(/^__POST_SHARE__:(\d+)$/);
    const body = shareMatch
        ? `<a href="${<?= json_encode(app_url('views/single_post.php?id=')) ?>}${Number(shareMatch[1])}" class="text-decoration-none"><div class="shared_post_card"><div class="shared_post_meta"><div class="fw-bold mb-1" style="color:#262626;">Shared post</div><div style="color:#0095f6;font-size:12px;">View Post</div></div></div></a>`
        : `<div class="${senderId === CURRENT_USER_ID ? 'msg_sent' : 'msg_received'}">${escapeMessageHtml(text)}</div>`;

    const messageId = Number(message.id || 0);
    const deleteButton = messageId > 0
        ? `<button type="button" class="msg_delete_btn" title="Delete message" data-delete-message-id="${messageId}"><i class="fa-solid fa-trash"></i></button>`
        : `<button type="button" class="msg_delete_btn d-none" title="Delete message" data-delete-message-id=""><i class="fa-solid fa-trash"></i></button>`;
    const row = senderId === CURRENT_USER_ID
        ? `<div class="message_row sent d-flex justify-content-end mb-3" data-message-id="${messageId || ''}">${deleteButton}${body}</div>`
        : `<div class="message_row received d-flex align-items-end gap-2 mb-3" data-message-id="${messageId || ''}"><img src="${ACTIVE_CHAT_AVATAR}" class="rounded-circle" width="28" height="28" alt="user">${body}</div>`;

    chatMessages.insertAdjacentHTML('beforeend', row);
    chatMessages.scrollTop = chatMessages.scrollHeight;
    return chatMessages.lastElementChild;
}

// ── FIXED SPEED LOGIC HERE ───────────────────────────────────────────────────
document.getElementById('socketChatForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = e.currentTarget;
    const input = document.getElementById('socketChatInput');
    const receiverId = Number(form.dataset.receiverId || 0);
    const shareInput = form.querySelector('input[name="share_post_id"]');
    const sharePostId = Number(shareInput?.value || 0);
    const messageText = sharePostId > 0 ? '__POST_SHARE__:' + sharePostId : (input?.value || '').trim();

    if (!receiverId || !messageText) return;

    // 1. INSTANTLY RENDER (0ms Delay) - User sees it immediately
    const tempMsg = {
        sender_id: CURRENT_USER_ID,
        receiver_id: receiverId,
        message: messageText,
        id: Date.now()
    };
    const tempRow = renderSocketMessage(tempMsg);

    // 2. Clear inputs immediately so user can type their next message
    if (input) input.value = '';
    shareInput?.remove();
    form.querySelector('.shared_post_card')?.remove();

    // 3. Try sending over socket asynchronously without blocking UI
    if (window.InstaSocket && window.InstaSocket.isReady && window.InstaSocket.isReady()) {
        window.InstaSocket.sendMessage(receiverId, messageText, sharePostId)
            .then(payload => bindRealMessageId(tempRow, payload?.message?.id))
            .catch(err => {
                console.warn("Socket send failed, utilizing background AJAX database backup...", err);
                silentDatabaseFallback(receiverId, messageText).then(payload => bindRealMessageId(tempRow, payload?.message?.id));
            });
    } else {
        // Socket disconnected or failed; use silent AJAX background database insert
        silentDatabaseFallback(receiverId, messageText).then(payload => bindRealMessageId(tempRow, payload?.message?.id));
    }
});

function bindRealMessageId(row, messageId) {
    messageId = Number(messageId || 0);
    if (!row || messageId <= 0) return;
    row.dataset.messageId = String(messageId);
    const btn = row.querySelector('[data-delete-message-id]');
    if (btn) {
        btn.dataset.deleteMessageId = String(messageId);
        btn.classList.remove('d-none');
    }
}

// Sends form details to database in background without reloading the page layout
function silentDatabaseFallback(receiverId, message) {
    return fetch(window.location.href, {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({ receiver_id: receiverId, message: message, is_ajax: 1 })
    })
    .then(res => res.json())
    .then(data => {
        console.log("Database backup completed.", data);
        return data;
    })
    .catch(err => {
        console.error("Database backup failed.", err);
        return null;
    });
}

document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-delete-message-id]');
    if (!btn) return;
    const messageId = Number(btn.dataset.deleteMessageId || 0);
    if (!messageId) return;
    if (!confirm('Delete this message?')) return;

    fetch(<?= json_encode(app_url('controllers/delete_message.php')) ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ message_id: messageId })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) throw new Error();
        btn.closest('.message_row')?.remove();
    })
    .catch(() => alert('Message could not be deleted. Please try again.'));
});

window.addEventListener('insta:socket-message', event => {
    // Only render incoming messages from other people (since we updated our own instantly)
    if (Number(event.detail?.message?.sender_id) !== CURRENT_USER_ID) {
        renderSocketMessage(event.detail?.message);
    }
});
</script>

<?php include __DIR__ . '/../components/create_modal.php'; ?>
<?php include __DIR__ . '/../components/footer.php'; ?>

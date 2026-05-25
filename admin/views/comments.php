<?php
require_once __DIR__ . '/../../views/_page_helpers.php';
app_start_session();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ' . app_url('admin/index.php'));
    exit;
}

require_once __DIR__ . '/../models/AdminController.php';

$pageTitle   = 'Comments';
$currentPage = 'comments';
$admin       = AdminController::getInstance();

$search = trim($_GET['search'] ?? '');
$date = trim($_GET['date'] ?? '');

// 🔥 Naye controller ke hisaab se date seedha pass kar di gayi hai
if ($search !== '') {
    $comments = $admin->searchComments($search, $date);
} else {
    $comments = $admin->getAllComments($date);
}

include __DIR__ . '/../components/head.php';
include __DIR__ . '/../components/sidebar.php';
?>

<div class="panel">
    <div class="panel-head">
        <span class="panel-title">
            <i class="ti ti-message-circle" style="color:var(--blue)"></i>
            All Comments
        </span>

        <span style="font-size:12px;color:#888">
            <?= count($comments) ?> comments found
        </span>
    </div>

    <form method="GET" action="comments.php" style="margin-bottom:16px">
        <div class="filters" style="display:flex;gap:10px;flex-wrap:wrap">
            <input
                type="text"
                name="search"
                class="filter-input"
                placeholder="Search username, comment or caption..."
                value="<?= htmlspecialchars($search) ?>"
                style="flex:1;min-width:240px"
            >

            <input
                type="text"
                name="date"
                class="filter-input"
                placeholder="📅 Date (eg. 21 May or 2026-05)"
                value="<?= htmlspecialchars($date) ?>"
                style="min-width:180px"
            >

            <button type="submit" class="btn btn-primary btn-sm">
                <i class="ti ti-search"></i>
                Search
            </button>

            <?php if($search || $date): ?>
                <a href="comments.php" class="btn btn-outline btn-sm">
                    <i class="ti ti-x"></i>
                    Clear
                </a>
            <?php endif; ?>
        </div>
    </form>

    <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Post ID</th>
                    <th>Comment</th>
                    <th>Post Caption</th>
                    <th>Likes</th>
                    <th>Date</th>
                </tr>
            </thead>

            <tbody>
            <?php if(empty($comments)): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <i class="ti ti-message-circle"></i>
                            <p>No comments found.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach($comments as $i => $comment): ?>
                <tr>
                    <td style="color:#aaa;font-size:12px">
                        <?= $i + 1 ?>
                    </td>

                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div style="
                                width:36px;
                                height:36px;
                                border-radius:50%;
                                background:var(--bg2);
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                font-weight:600;
                                color:var(--blue);
                                border:1px solid var(--border);
                            ">
                                <?= strtoupper(substr($comment['username'] ?? 'U', 0, 1)) ?>
                            </div>

                            <div>
                                <div style="font-weight:600;color:var(--text)">
                                    @<?= htmlspecialchars($comment['username'] ?? 'Unknown') ?>
                                </div>

                                <div style="font-size:11px;color:var(--text3)">
                                    User ID: #<?= $comment['user_id'] ?>
                                </div>
                            </div>
                        </div>
                    </td>

                    <td>
                        <span class="badge badge-blue">
                            #<?= (int) $comment['post_id'] ?>
                        </span>
                    </td>

                    <td style="max-width:260px">
                        <div style="
                            overflow:hidden;
                            text-overflow:ellipsis;
                            white-space:nowrap;
                            color:var(--text2);
                            font-size:13px;
                        ">
                            <?= htmlspecialchars($comment['comment']) ?>
                        </div>
                    </td>

                    <td style="max-width:220px">
                        <div style="
                            overflow:hidden;
                            text-overflow:ellipsis;
                            white-space:nowrap;
                            color:var(--text3);
                            font-size:12px;
                        ">
                            <?= htmlspecialchars($comment['caption'] ?? 'No Caption') ?>
                        </div>
                    </td>

                    <td style="font-size:12px;color:var(--text2)">
                        <i class="ti ti-heart" style="color:var(--red)"></i>
                        <?= (int) ($comment['like_count'] ?? 0) ?>
                    </td>

                    <td style="font-size:12px;color:var(--text3)">
                        <?= date('M d, Y', strtotime($comment['created_at'])) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>
<?php
require_once __DIR__ . '/../../views/_page_helpers.php';
app_start_session();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ' . app_url('admin/index.php'));
    exit;
}

require_once __DIR__ . '/../models/AdminController.php';

$pageTitle   = 'Follows Management';
$currentPage = 'follows';

$admin = AdminController::getInstance();

$search = trim($_GET['search'] ?? '');
$date   = trim($_GET['date'] ?? '');
$sort   = $_GET['sort'] ?? 'newest';

// 🔥 Ab Text aur Date dono seedha Database se search honge! Koi array_filter nahi!
if ($search !== '') {
    $follows = $admin->searchFollows($search, $date);
} else {
    $follows = $admin->getAllFollows($date);
}

usort($follows, function($a, $b) use ($sort) {
    switch ($sort) {
        case 'a_z':
            return strcasecmp($a['follower_name'], $b['follower_name']);
        case 'z_a':
            return strcasecmp($b['follower_name'], $a['follower_name']);
        case 'oldest':
            return strtotime($a['created_at']) <=> strtotime($b['created_at']);
        case 'newest':
        default:
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
    }
});

include __DIR__ . '/../components/head.php';
include __DIR__ . '/../components/sidebar.php';
?>

<div class="panel">

    <div class="panel-head">
        <span class="panel-title">
            <i class="ti ti-users" style="color:var(--blue)"></i>
            Follow Relationships
        </span>
        <span style="font-size:12px;color:#888">
            <?= count($follows) ?> follows found
        </span>
    </div>

    <form method="GET" action="follows.php" style="margin-bottom:16px">
        <div class="filters" style="display:flex;gap:10px;flex-wrap:wrap">

            <input
                type="text"
                name="search"
                class="filter-input"
                placeholder="🔍 Search follower, following or date..."
                value="<?= htmlspecialchars($search) ?>"
                style="flex:1;min-width:250px"
            >

            <select
                name="sort"
                class="filter-input"
                style="min-width:220px"
            >
                <option value="newest" <?= $sort==='newest' ? 'selected' : '' ?>>Newest First</option>
                <option value="oldest" <?= $sort==='oldest' ? 'selected' : '' ?>>Oldest First</option>
                <option value="a_z" <?= $sort==='a_z' ? 'selected' : '' ?>>Username A → Z</option>
                <option value="z_a" <?= $sort==='z_a' ? 'selected' : '' ?>>Username Z → A</option>
            </select>

            <input
                type="text"
                name="date"
                class="filter-input"
                placeholder="📅 Date (eg. 21 May or 2026-05)"
                value="<?= htmlspecialchars($date) ?>"
                style="min-width:180px"
            >

            <button type="submit" class="btn btn-primary btn-sm">
                <i class="ti ti-search"></i> Search
            </button>

            <?php if($search || $date || $sort !== 'newest'): ?>
                <a href="follows.php" class="btn btn-outline btn-sm">
                    <i class="ti ti-x"></i> Clear
                </a>
            <?php endif; ?>

        </div>
    </form>

    <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Follower</th>
                    <th>Following</th>
                    <th>Relation</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($follows)): ?>
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <i class="ti ti-users"></i>
                            <p>No follows found.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach($follows as $i => $follow): ?>
                <tr>
                    <td style="color:#aaa;font-size:12px">
                        <?= $i + 1 ?>
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--text)">
                            @<?= htmlspecialchars($follow['follower_name']) ?>
                        </div>
                        <div style="font-size:11px;color:var(--text3)">
                            ID: <?= $follow['follower_id'] ?>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--text)">
                            @<?= htmlspecialchars($follow['following_name']) ?>
                        </div>
                        <div style="font-size:11px;color:var(--text3)">
                            ID: <?= $follow['following_id'] ?>
                        </div>
                    </td>
                    <td>
                        <span style="
                            background:rgba(59,130,246,.12);
                            color:#2563eb;
                            padding:6px 10px;
                            border-radius:999px;
                            font-size:12px;
                            font-weight:600;
                        ">
                            Following
                        </span>
                    </td>
                    <td style="font-size:12px;color:var(--text3)">
                        <?= date('M d, Y h:i A', strtotime($follow['created_at'])) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>
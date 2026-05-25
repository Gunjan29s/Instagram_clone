<?php
require_once __DIR__ . '/../../views/_page_helpers.php';
app_start_session();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ' . app_url('admin/index.php'));
    exit;
}

require_once __DIR__ . '/../models/AdminController.php';

$pageTitle   = 'Likes';
$currentPage = 'likes';

$admin = AdminController::getInstance();

/*
|--------------------------------------------------------------------------
| SEARCH + SORT
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$date   = trim($_GET['date'] ?? '');
$sort   = $_GET['sort'] ?? 'newest';

/*
|--------------------------------------------------------------------------
| GET LIKES
|--------------------------------------------------------------------------
*/

// 🔥 Date ke hisaab se seedha database se data nikal rahe hain
$likes = $admin->getAllLikes($date);

/*
|--------------------------------------------------------------------------
| SEARCH FILTER
|--------------------------------------------------------------------------
*/

// Text search PHP mein hi handle ho raha hai (Fast and safe)
if ($search !== '') {

    $likes = array_filter($likes, function($like) use ($search) {
        $createdAt = (string) ($like['created_at'] ?? '');
        $dateText = $createdAt !== ''
            ? date('Y-m-d d F Y M j Y h:i A', strtotime($createdAt))
            : '';

        return
            stripos($like['username'] ?? '', $search) !== false ||

            stripos($like['caption'] ?? '', $search) !== false ||

            stripos((string)$like['user_id'], $search) !== false ||

            stripos((string)$like['post_id'], $search) !== false ||

            stripos($dateText, $search) !== false;
    });
}

/*
|--------------------------------------------------------------------------
| SORTING
|--------------------------------------------------------------------------
*/

usort($likes, function($a, $b) use ($sort) {

    switch ($sort) {

        case 'a_z':
            return strcasecmp($a['username'] ?? '', $b['username'] ?? '');

        case 'z_a':
            return strcasecmp($b['username'] ?? '', $a['username'] ?? '');

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

            <i class="ti ti-heart" style="color:var(--red)"></i>

            All Likes

        </span>

        <span style="font-size:12px;color:#888">

            <?= count($likes) ?> likes found

        </span>

    </div>

    <form method="GET" action="likes.php" style="margin-bottom:16px">

        <div class="filters" style="display:flex;gap:10px;flex-wrap:wrap">

            <input
                type="text"
                name="search"
                class="filter-input"
                placeholder="🔍 Search username, caption, user ID or post ID..."
                value="<?= htmlspecialchars($search) ?>"
                style="flex:1;min-width:250px"
            >

            <select
                name="sort"
                class="filter-input"
                style="min-width:220px"
            >

                <option value="newest" <?= $sort==='newest' ? 'selected' : '' ?>>
                    Newest First
                </option>

                <option value="oldest" <?= $sort==='oldest' ? 'selected' : '' ?>>
                    Oldest First
                </option>

                <option value="a_z" <?= $sort==='a_z' ? 'selected' : '' ?>>
                    Username A → Z
                </option>

                <option value="z_a" <?= $sort==='z_a' ? 'selected' : '' ?>>
                    Username Z → A
                </option>

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

                <i class="ti ti-search"></i>

                Search

            </button>

            <?php if($search || $date || $sort !== 'newest'): ?>

                <a href="likes.php" class="btn btn-outline btn-sm">

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

                    <th>Caption</th>

                    <th>Date</th>

                </tr>

            </thead>

            <tbody>

            <?php if(empty($likes)): ?>

                <tr>

                    <td colspan="5">

                        <div class="empty-state">

                            <i class="ti ti-heart"></i>

                            <p>No likes found.</p>

                        </div>

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach($likes as $i => $like): ?>

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
                                background:rgba(255,255,255,.06);
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                font-size:15px;
                                color:var(--red)
                            ">

                                <i class="ti ti-user"></i>

                            </div>

                            <div>

                                <div style="font-weight:600;color:var(--text)">

                                    @<?= htmlspecialchars($like['username'] ?? 'Unknown') ?>

                                </div>

                                <div style="font-size:11px;color:var(--text3)">

                                    User ID:
                                    <?= $like['user_id'] ?>

                                </div>

                            </div>

                        </div>

                    </td>

                    <td>

                        <span class="badge badge-blue">

                            #<?= $like['post_id'] ?>

                        </span>

                    </td>

                    <td style="max-width:260px">

                        <div style="
                            overflow:hidden;
                            text-overflow:ellipsis;
                            white-space:nowrap;
                            color:var(--text2);
                            font-size:13px
                        ">

                            <?= htmlspecialchars($like['caption'] ?? 'No Caption') ?>

                        </div>

                    </td>

                    <td style="font-size:12px;color:var(--text3)">

                        <?= date('M d, Y h:i A', strtotime($like['created_at'])) ?>

                    </td>

                </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

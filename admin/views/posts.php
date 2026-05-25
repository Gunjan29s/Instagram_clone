<?php
require_once __DIR__ . '/../../views/_page_helpers.php';
app_start_session();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ' . app_url('admin/index.php'));
    exit;
}

require_once __DIR__ . '/../models/AdminController.php';
require_once __DIR__ . '/_admin_filters.php';

$pageTitle   = 'Posts Management';
$currentPage = 'posts';

$admin = AdminController::getInstance();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post'])) {
    require_csrf();

    $itemType = $_POST['item_type'] ?? 'post';
    if ($itemType === 'story') {
        $admin->deleteStory((int)$_POST['post_id']);
    } else {
        $admin->deletePost((int)$_POST['post_id']);
    }

    header('Location: ' . app_url('admin/posts.php'));
    exit;
}

$search = trim($_GET['search'] ?? '');
$date   = trim($_GET['date'] ?? '');
$sort   = $_GET['sort'] ?? 'newest';

if ($search !== '') {

    $posts = $admin->searchPosts($search, $date);

} else {

    $posts = $admin->getAllPosts($date, $date !== '' ? 10000 : 50);
}

usort($posts, function($a, $b) use ($sort) {

    switch ($sort) {

        case 'a_z':
            return strcasecmp($a['username'], $b['username']);

        case 'z_a':
            return strcasecmp($b['username'], $a['username']);

        case 'likes':
            return ($b['like_count'] ?? 0) <=> ($a['like_count'] ?? 0);

        case 'comments':
            return ($b['comment_count'] ?? 0) <=> ($a['comment_count'] ?? 0);

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
            <i class="ti ti-photo" style="color:var(--purple)"></i>
            Posts &amp; Media
        </span>

        <span style="font-size:12px;color:#888">
            <?= count($posts) ?> posts found
        </span>

    </div>
    <form method="GET" action="posts.php" style="margin-bottom:16px">

        <div class="filters" style="display:flex;gap:10px;flex-wrap:wrap">

            <input
                type="text"
                name="search"
                class="filter-input"
                placeholder="🔍 Search username, email or caption..."
                value="<?= htmlspecialchars($search) ?>"
                style="flex:1;min-width:250px"
            >

            <!-- SORT -->

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

                <option value="likes" <?= $sort==='likes' ? 'selected' : '' ?>>
                    Most Likes
                </option>

                <option value="comments" <?= $sort==='comments' ? 'selected' : '' ?>>
                    Most Comments
                </option>

            </select>

            <input
                type="date"
                name="date"
                class="filter-input"
                value="<?= htmlspecialchars($date) ?>"
                style="min-width:180px"
            >

            <!-- SEARCH BUTTON -->

            <button type="submit" class="btn btn-primary btn-sm">

                <i class="ti ti-search"></i>
                Search

            </button>

            <!-- CLEAR -->

            <?php if($search || $date || $sort !== 'newest'): ?>

                <a href="posts.php" class="btn btn-outline btn-sm">

                    <i class="ti ti-x"></i>
                    Clear

                </a>

            <?php endif; ?>

        </div>

    </form>

    <!-- TABLE -->

    <div class="tbl-wrap">

        <table>

            <thead>

                <tr>
                    <th>#</th>
                    <th>Post</th>
                    <th>By</th>
                    <th>Caption</th>
                    <th>Likes</th>
                    <th>Comments</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>

            </thead>

            <tbody>

            <?php if(empty($posts)): ?>

                <tr>

                    <td colspan="8">

                        <div class="empty-state">

                            <i class="ti ti-photo"></i>
                            <p>No posts found.</p>

                        </div>

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach($posts as $i => $p): ?>

                <tr>

                    <td style="color:#aaa;font-size:12px">
                        <?= $i + 1 ?>
                    </td>

                    <!-- IMAGE -->

                    <td>

                        <img
                            src="<?= htmlspecialchars(post_media_url($p['media_path'])) ?>"
                            onerror="this.src='<?= htmlspecialchars(placeholder_media(), ENT_QUOTES) ?>'"
                            width="42"
                            height="42"
                            style="border-radius:8px;object-fit:cover;border:1px solid var(--border)"
                        >

                    </td>

                    <!-- USER -->

                    <td>

                        <div style="font-weight:600;color:var(--text)">
                            @<?= htmlspecialchars($p['username']) ?>
                        </div>

                        <div style="font-size:11px;color:var(--text3)">
                            <?= htmlspecialchars($p['email']) ?>
                            · <?= htmlspecialchars(ucfirst($p['item_type'] ?? 'post')) ?>
                        </div>

                    </td>

                    <!-- CAPTION -->

                    <td style="max-width:220px">

                        <div style="
                            overflow:hidden;
                            text-overflow:ellipsis;
                            white-space:nowrap;
                            font-size:13px;
                            color:var(--text2)
                        ">

                            <?= htmlspecialchars($p['caption'] ?? '—') ?>

                        </div>

                    </td>

                    <!-- LIKES -->

                    <td>

                        <span style="font-size:13px">

                            <i class="ti ti-heart" style="color:var(--red);font-size:13px"></i>

                            <?= number_format((int)$p['like_count']) ?>

                        </span>

                    </td>

                    <!-- COMMENTS -->

                    <td>

                        <span style="font-size:13px">

                            <i class="ti ti-message" style="color:var(--blue);font-size:13px"></i>

                            <?= number_format((int)$p['comment_count']) ?>

                        </span>

                    </td>

                    <!-- DATE -->

                    <td style="font-size:12px;color:var(--text3)">

                        <?= date('M d, Y', strtotime($p['created_at'])) ?>

                    </td>

                    <!-- ACTIONS -->

                    <td>

                        <div class="act-btns">

                            <!-- VIEW -->

                            <button
                                class="act-btn view"
                                title="View Details"

                                onclick='openPostModal(<?= htmlspecialchars(json_encode([

                                    "id"=>$p["id"],
                                    "item_type"=>$p["item_type"]??"post",
                                    "username"=>$p["username"],
                                    "email"=>$p["email"],
                                    "caption"=>$p["caption"]??null,
                                    "media_path"=>post_media_url($p["media_path"]),
                                    "like_count"=>$p["like_count"],
                                    "comment_count"=>$p["comment_count"],
                                    "created_at"=>$p["created_at"]

                                ]), ENT_QUOTES) ?>)'

                            >

                                <i class="ti ti-eye"></i>

                            </button>

                            <!-- DELETE -->

                            <form
                                method="POST"
                                onsubmit="return confirm('Delete this post?')"
                                style="display:inline"
                            >
                                <?= csrf_field() ?>

                                <input
                                    type="hidden"
                                    name="post_id"
                                    value="<?= $p['id'] ?>"
                                >
                                <input
                                    type="hidden"
                                    name="item_type"
                                    value="<?= htmlspecialchars($p['item_type'] ?? 'post') ?>"
                                >

                                <button
                                    type="submit"
                                    name="delete_post"
                                    class="act-btn del"
                                    title="Delete Media"
                                >

                                    <i class="ti ti-trash"></i>

                                </button>

                            </form>

                        </div>

                    </td>

                </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

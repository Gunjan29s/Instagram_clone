<?php
require_once __DIR__ . '/../../views/_page_helpers.php';
app_start_session();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ' . app_url('admin/index.php'));
    exit;
}

require_once __DIR__ . '/../models/AdminController.php';

$pageTitle   = 'User Management';
$currentPage = 'users';

$admin = AdminController::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (isset($_POST['delete_user'])) {
        $admin->deleteUser((int)$_POST['user_id']);
        header('Location: ' . app_url('admin/users.php'));
        exit;
    }
    if (isset($_POST['ban_user'])) {
        $admin->toggleBanUser((int)$_POST['user_id']);
        header('Location: ' . app_url('admin/users.php'));
        exit;
    }
}

$search = trim($_GET['search'] ?? '');
$date = trim($_GET['date'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

// Naye controller ke hisaab se seedha database mein filter laga diya gaya hai
if ($search !== '') {
    $users = $admin->searchUsers($search, $date);
} else {
    $limit = $date !== '' ? 10000 : 50;
    $users = $admin->getAllUsers($date, $limit);
}

usort($users, function($a, $b) use ($sort) {
    switch ($sort) {
        case 'a_z': return strcasecmp($a['username'], $b['username']);
        case 'z_a': return strcasecmp($b['username'], $a['username']);
        case 'followers': return ($b['followers'] ?? 0) <=> ($a['followers'] ?? 0);
        case 'posts': return ($b['post_count'] ?? 0) <=> ($a['post_count'] ?? 0);
        case 'oldest': return strtotime($a['created_at']) <=> strtotime($b['created_at']);
        case 'newest':
        default: return strtotime($b['created_at']) <=> strtotime($a['created_at']);
    }
});

include __DIR__ . '/../components/head.php';
include __DIR__ . '/../components/sidebar.php';
?>

<div class="panel">
    <div class="panel-head">
        <span class="panel-title">
            <i class="ti ti-users" style="color:var(--blue)"></i>
            All Users
        </span>
        <span style="font-size:12px;color:#888">
            <?= count($users) ?> users found
        </span>
    </div>

    <form method="GET" action="users.php" style="margin-bottom:16px">
        <div class="filters" style="display:flex;gap:10px;flex-wrap:wrap">

            <input
                class="filter-input"
                type="text"
                name="search"
                placeholder="🔍 Search username, name, email or date..."
                value="<?= htmlspecialchars($search) ?>"
                style="flex:1;min-width:250px"
            >

            <select name="sort" class="filter-input" style="min-width:220px">
                <option value="newest"   <?= $sort==='newest' ? 'selected' : '' ?>>Newest First</option>
                <option value="oldest"   <?= $sort==='oldest' ? 'selected' : '' ?>>Oldest First</option>
                <option value="a_z"      <?= $sort==='a_z' ? 'selected' : '' ?>>Username A → Z</option>
                <option value="z_a"      <?= $sort==='z_a' ? 'selected' : '' ?>>Username Z → A</option>
                <option value="followers" <?= $sort==='followers' ? 'selected' : '' ?>>Most Followers</option>
                <option value="posts" <?= $sort==='posts' ? 'selected' : '' ?>>Most Posts</option>
            </select>

            <input
                type="text"
                name="date"
                class="filter-input"
                placeholder="📅 Date (eg. 2026-05)"
                value="<?= htmlspecialchars($date) ?>"
                style="min-width:180px"
            >

            <button type="submit" class="btn btn-primary btn-sm">
                <i class="ti ti-search"></i> Search
            </button>

            <?php if($search || $date || $sort !== 'newest'): ?>
                <a href="users.php" class="btn btn-outline btn-sm">
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
                    <th>User</th>
                    <th>Email</th>
                    <th>Posts</th>
                    <th>Followers</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($users)): ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="ti ti-users"></i>
                            <p>No users found.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach($users as $i => $u): ?>
                <tr>
                    <td style="color:#aaa;font-size:12px">
                        <?= $i + 1 ?>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:9px">
                            <img
                                src="<?= htmlspecialchars(profile_avatar($u['profile_pic'] ?? '', $u['username'] ?? 'User')) ?>"
                                width="34"
                                height="34"
                                style="border-radius:50%;object-fit:cover;border:2px solid var(--border)"
                                onerror="this.src='<?= htmlspecialchars(placeholder_avatar($u['username'] ?? 'User'), ENT_QUOTES) ?>'"
                            >
                            <div>
                                <div style="font-weight:600;color:var(--text)">
                                    @<?= htmlspecialchars($u['username']) ?>
                                </div>
                                <div style="font-size:11px;color:var(--text3)">
                                    <?= htmlspecialchars($u['full_name'] ?? '') ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= (int)($u['post_count'] ?? 0) ?></td>
                    <td><?= (int)($u['followers'] ?? 0) ?></td>
                    <td>
                        <?php if(!empty($u['is_banned'])): ?>
                            <span class="badge badge-red">Banned</span>
                        <?php else: ?>
                            <span class="badge badge-green">Active</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:var(--text3)">
                        <?= date('M d, Y', strtotime($u['created_at'])) ?>
                    </td>
                    <td>
                        <div class="act-btns">
                            <button
                                class="act-btn view"
                                title="View Details"
                                onclick='openUserModal(<?= htmlspecialchars(json_encode([
                                    "id"=>$u["id"],
                                    "username"=>$u["username"],
                                    "full_name"=>$u["full_name"]??null,
                                    "email"=>$u["email"],
                                    "bio"=>$u["bio"]??null,
                                    "website"=>$u["website"]??null,
                                    "gender"=>$u["gender"]??null,
                                    "profile_pic"=>profile_avatar($u["profile_pic"]??"", $u["username"]??"User"),
                                    "is_banned"=>$u["is_banned"]??0,
                                    "post_count"=>$u["post_count"]??0,
                                    "followers"=>$u["followers"]??0,
                                    "created_at"=>$u["created_at"]
                                ]), ENT_QUOTES) ?>)'
                            >
                                <i class="ti ti-eye"></i>
                            </button>
                            <form method="POST" onsubmit="return confirm('Ban this user permanently? Their account details and posts will be deleted, and a ban email will be sent.')" style="display:inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <button type="submit" name="ban_user" class="act-btn block" title="Ban and delete user">
                                    <i class="ti ti-ban"></i>
                                </button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Delete this user?')" style="display:inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <button type="submit" name="delete_user" class="act-btn del" title="Delete">
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

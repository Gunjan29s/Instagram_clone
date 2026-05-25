<?php
require_once __DIR__ . '/../../views/_page_helpers.php';
app_start_session();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ' . app_url('admin/index.php'));
    exit;
}

require_once __DIR__ . '/../models/AdminController.php';
require_once __DIR__ . '/_admin_filters.php';

$pageTitle   = 'Notifications Management';
$currentPage = 'notifications';

$admin = AdminController::getInstance();

$search = trim($_GET['search'] ?? '');
$date   = trim($_GET['date'] ?? '');
$sort   = $_GET['sort'] ?? 'newest';

$notifications = $admin->getAllNotifications($date);

if ($search !== '') {

    $notifications = array_filter($notifications, function($n) use ($search) {
        $createdAt = (string) ($n['created_at'] ?? '');
        $dateText = $createdAt !== ''
            ? date('Y-m-d d F Y M j Y h:i A', strtotime($createdAt))
            : '';

        return
            stripos($n['username'] ?? '', $search) !== false
            ||
            stripos($n['type'] ?? '', $search) !== false
            ||
            stripos($dateText, $search) !== false;
    });
}

usort($notifications, function($a, $b) use ($sort) {

    switch ($sort) {

        case 'a_z':
            return strcasecmp($a['username'], $b['username']);

        case 'z_a':
            return strcasecmp($b['username'], $a['username']);

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

    <!-- HEADER -->

    <div class="panel-head">

        <span class="panel-title">

            <i class="ti ti-bell" style="color:orange"></i>

            Notifications

        </span>

        <span style="font-size:12px;color:#888">

            <?= count($notifications) ?> notifications found

        </span>

    </div>

    <!-- FILTERS -->

    <form method="GET" action="notifications.php" style="margin-bottom:16px">

        <div class="filters" style="display:flex;gap:10px;flex-wrap:wrap">

            <!-- SEARCH -->

            <input
                type="text"
                name="search"
                class="filter-input"
                placeholder="🔍 Search username or type..."
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

            </select>

            <input
                type="date"
                name="date"
                class="filter-input"
                value="<?= htmlspecialchars($date) ?>"
                style="min-width:180px"
            >

            <!-- SEARCH BTN -->

            <button type="submit" class="btn btn-primary btn-sm">

                <i class="ti ti-search"></i>
                Search

            </button>

            <!-- CLEAR -->

            <?php if($search || $date || $sort !== 'newest'): ?>

                <a href="notifications.php" class="btn btn-outline btn-sm">

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
                    <th>User</th>
                    <th>Type</th>
                    <th>Post ID</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>

            </thead>

            <tbody>

            <?php if(empty($notifications)): ?>

                <tr>

                    <td colspan="6">

                        <div class="empty-state">

                            <i class="ti ti-bell-off"></i>

                            <p>No notifications found.</p>

                        </div>

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach($notifications as $i => $n): ?>

                <tr>

                    <!-- SERIAL -->

                    <td style="color:#aaa;font-size:12px">

                        <?= $i + 1 ?>

                    </td>

                    <!-- USER -->

                    <td>

                        <div style="font-weight:600;color:var(--text)">

                            @<?= htmlspecialchars($n['username']) ?>

                        </div>

                        <div style="font-size:11px;color:var(--text3)">

                            User ID:
                            <?= $n['user_id'] ?>

                        </div>

                    </td>

                    <!-- TYPE -->

                    <td>

                        <?php

                        $bg = '#e5e7eb';
                        $color = '#374151';
                        $icon = 'bell';

                        if($n['type'] === 'like') {
                            $bg = 'rgba(239,68,68,.12)';
                            $color = '#dc2626';
                            $icon = 'heart';
                        }

                        elseif($n['type'] === 'comment') {
                            $bg = 'rgba(59,130,246,.12)';
                            $color = '#2563eb';
                            $icon = 'message';
                        }

                        elseif($n['type'] === 'follow') {
                            $bg = 'rgba(16,185,129,.12)';
                            $color = '#059669';
                            $icon = 'user-plus';
                        }

                        elseif($n['type'] === 'mention') {
                            $bg = 'rgba(168,85,247,.12)';
                            $color = '#9333ea';
                            $icon = 'at';
                        }

                        ?>

                        <span style="
                            background:<?= $bg ?>;
                            color:<?= $color ?>;
                            padding:6px 10px;
                            border-radius:999px;
                            font-size:12px;
                            font-weight:600;
                            display:inline-flex;
                            align-items:center;
                            gap:5px;
                        ">

                            <i class="ti ti-<?= $icon ?>"></i>

                            <?= ucfirst($n['type']) ?>

                        </span>

                    </td>

                    <!-- POST -->

                    <td>

                        <?= $n['post_id'] ?? '—' ?>

                    </td>

                    <!-- STATUS -->

                    <td>

                        <?php if($n['is_read']): ?>

                            <span style="
                                color:#16a34a;
                                font-size:12px;
                                font-weight:600;
                            ">

                                Read

                            </span>

                        <?php else: ?>

                            <span style="
                                color:#dc2626;
                                font-size:12px;
                                font-weight:600;
                            ">

                                Unread

                            </span>

                        <?php endif; ?>

                    </td>

                    <!-- DATE -->

                    <td style="font-size:12px;color:var(--text3)">

                        <?= date('M d, Y h:i A', strtotime($n['created_at'])) ?>

                    </td>

                </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

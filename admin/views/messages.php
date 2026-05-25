<?php
require_once __DIR__ . '/../../views/_page_helpers.php';
app_start_session();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ' . app_url('admin/index.php'));
    exit;
}

require_once __DIR__ . '/../models/AdminController.php';
require_once __DIR__ . '/_admin_filters.php';

$pageTitle   = 'Messages Management';
$currentPage = 'messages';

$admin = AdminController::getInstance();

$search = trim($_GET['search'] ?? '');
$date   = trim($_GET['date'] ?? '');
$sort   = $_GET['sort'] ?? 'newest';

$messages = $admin->getAllMessages($date);

if ($search !== '') {

    $messages = array_filter($messages, function($m) use ($search) {
        $createdAt = (string) ($m['created_at'] ?? '');
        $dateText = $createdAt !== ''
            ? date('Y-m-d d F Y M j Y h:i A', strtotime($createdAt))
            : '';

        return
            stripos($m['sender_name'] ?? '', $search) !== false
            ||
            stripos($m['receiver_name'] ?? '', $search) !== false
            ||
            stripos($m['message'] ?? '', $search) !== false
            ||
            stripos($dateText, $search) !== false;
    });
}

usort($messages, function($a, $b) use ($sort) {

    switch ($sort) {

        case 'a_z':
            return strcasecmp($a['sender_name'], $b['sender_name']);

        case 'z_a':
            return strcasecmp($b['sender_name'], $a['sender_name']);

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

            <i class="ti ti-message-circle" style="color:var(--blue)"></i>

            Messages

        </span>

        <span style="font-size:12px;color:#888">

            <?= count($messages) ?> messages found

        </span>

    </div>

    <!-- FILTERS -->

    <form method="GET" action="messages.php" style="margin-bottom:16px">

        <div class="filters" style="display:flex;gap:10px;flex-wrap:wrap">

            <!-- SEARCH -->

            <input
                type="text"
                name="search"
                class="filter-input"
                placeholder="🔍 Search sender, receiver or message..."
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
                    Sender A → Z
                </option>

                <option value="z_a" <?= $sort==='z_a' ? 'selected' : '' ?>>
                    Sender Z → A
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

                <a href="messages.php" class="btn btn-outline btn-sm">

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
                    <th>Sender</th>
                    <th>Receiver</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>

            </thead>

            <tbody>

            <?php if(empty($messages)): ?>

                <tr>

                    <td colspan="6">

                        <div class="empty-state">

                            <i class="ti ti-message-off"></i>

                            <p>No messages found.</p>

                        </div>

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach($messages as $i => $m): ?>

                <tr>

                    <!-- SERIAL -->

                    <td style="color:#aaa;font-size:12px">

                        <?= $i + 1 ?>

                    </td>

                    <!-- SENDER -->

                    <td>

                        <div style="font-weight:600;color:var(--text)">

                            @<?= htmlspecialchars($m['sender_name']) ?>

                        </div>

                        <div style="font-size:11px;color:var(--text3)">

                            ID:
                            <?= $m['sender_id'] ?>

                        </div>

                    </td>

                    <!-- RECEIVER -->

                    <td>

                        <div style="font-weight:600;color:var(--text)">

                            @<?= htmlspecialchars($m['receiver_name']) ?>

                        </div>

                        <div style="font-size:11px;color:var(--text3)">

                            ID:
                            <?= $m['receiver_id'] ?>

                        </div>

                    </td>

                    <!-- MESSAGE -->

                    <td style="max-width:300px">

                        <div style="
                            background:rgba(59,130,246,.08);
                            padding:10px 12px;
                            border-radius:12px;
                            font-size:13px;
                            color:var(--text2);
                            line-height:1.5;
                            border:1px solid var(--border);
                        ">

                            <?= htmlspecialchars($m['message']) ?>

                        </div>

                    </td>

                    <!-- STATUS -->

                    <td>

                        <?php if($m['is_read']): ?>

                            <span style="
                                background:rgba(16,185,129,.12);
                                color:#059669;
                                padding:6px 10px;
                                border-radius:999px;
                                font-size:12px;
                                font-weight:600;
                            ">

                                Read

                            </span>

                        <?php else: ?>

                            <span style="
                                background:rgba(239,68,68,.12);
                                color:#dc2626;
                                padding:6px 10px;
                                border-radius:999px;
                                font-size:12px;
                                font-weight:600;
                            ">

                                Unread

                            </span>

                        <?php endif; ?>

                    </td>

                    <!-- DATE -->

                    <td style="font-size:12px;color:var(--text3)">

                        <?= date('M d, Y h:i A', strtotime($m['created_at'])) ?>

                    </td>

                </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

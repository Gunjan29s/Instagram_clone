<?php
require_once __DIR__ . '/../views/_page_helpers.php';
app_start_session();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ' . app_url('admin/index.php'));
    exit;
}
require_once __DIR__ . '/models/AdminController.php';

$pageTitle   = 'Dashboard';
$currentPage = 'dashboard';
$admin       = AdminController::getInstance();
$stats       = $admin->getStats();
$activity    = $admin->getRecentActivity(10);

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/sidebar.php';
?>
<div class="stats-grid">
<?php
$cards = [
  [
    'label' => 'Total Users',
    'val'   => $stats['total_users'],
    'icon'  => 'ti-users',
    'bg'    => '#e8f3fc',
    'ic'    => '#0095f6',
    'link'  => 'users.php'
  ],
  [
    'label' => 'Total Posts',
    'val'   => $stats['total_posts'],
    'icon'  => 'ti-photo',
    'bg'    => '#f3e5f5',
    'ic'    => '#7b1fa2',
    'link'  => 'posts.php'
  ],
  [
    'label' => 'Total Likes',
    'val'   => $stats['total_likes'],
    'icon'  => 'ti-heart',
    'bg'    => '#fde8e8',
    'ic'    => '#cc1016',
    'link'  => 'likes.php'
  ],
  [
    'label' => 'Total Comments',
    'val'   => $stats['total_comments'],
    'icon'  => 'ti-message-circle',
    'bg'    => '#ede7f6',
    'ic'    => '#5e35b1',
    'link'  => 'comments.php'
  ],
  [
    'label' => 'Total Follows',
    'val'   => $stats['total_follows'],
    'icon'  => 'ti-user-check',
    'bg'    => '#e0f7fa',
    'ic'    => '#00838f',
    'link'  => 'follows.php'
  ],
  [
    'label' => 'Total Messages',
    'val'   => $stats['total_messages'],
    'icon'  => 'ti-message',
    'bg'    => '#e8f5e9',
    'ic'    => '#057642',
    'link'  => 'messages.php'
  ],
  [
    'label' => 'Notifications',
    'val'   => $stats['total_notifications'],
    'icon'  => 'ti-bell',
    'bg'    => '#fff3e0',
    'ic'    => '#ef6c00',
    'link'  => 'notifications.php'
  ],
  [
    'label' => 'New Today',
    'val'   => $stats['new_today'],
    'icon'  => 'ti-user-plus',
    'bg'    => '#fff8e1',
    'ic'    => '#e65100',
    'link'  => 'users.php'
  ],
];
foreach ($cards as $c): ?>
<a href="<?= $c['link'] ?>" class="stat-card">
  <div class="stat-icon-box" style="background:<?= $c['bg'] ?>">
    <i class="ti <?= $c['icon'] ?>" style="color:<?= $c['ic'] ?>;font-size:22px"></i>
  </div>
  <div>
    <div class="stat-num"><?= number_format((int)$c['val']) ?></div>
    <div class="stat-lbl"><?= $c['label'] ?></div>
  </div>
</a>
<?php endforeach; ?>
</div>

<div class="panel" style="width:100%;margin-top:4px;">

  <div class="panel-head">
    <span class="panel-title">
      <i class="ti ti-activity" style="color:var(--blue)"></i>
      Recent Activity
    </span>
    <span style="font-size:12px;color:#aaa">Latest signups &amp; posts</span>
  </div>

  <?php if (empty($activity)): ?>

    <div class="empty-state">
      <i class="ti ti-clipboard-list"></i>
      <p>No activity yet.</p>
    </div>

  <?php else: ?>
    <div class="activity-grid">

      <?php foreach ($activity as $a): ?>

      <div class="act-item">

        <div class="act-ic">
          <i class="ti <?= $a['type'] === 'signup' ? 'ti-user-plus' : 'ti-photo' ?>"></i>
        </div>

        <div style="flex:1;min-width:0;">
          <div class="act-txt">
            <?= htmlspecialchars(substr($a['detail'] ?? '-', 0, 80)) ?>
          </div>
          <div class="act-tm">
            <?= htmlspecialchars($a['created_at']) ?>
          </div>
        </div>

        <span class="badge <?= $a['type'] === 'signup' ? 'badge-blue' : 'badge-purple' ?>"
              style="flex-shrink:0;align-self:flex-start;">
          <?= $a['type'] === 'signup' ? 'Signup' : 'Post' ?>
        </span>

      </div>

      <?php endforeach; ?>

    </div>

  <?php endif; ?>

</div>

<?php include __DIR__ . '/components/footer.php'; ?>

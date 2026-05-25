<?php
require_once __DIR__ . '/../../views/_page_helpers.php';
app_start_session();
if (!isset($_SESSION['admin_logged_in'])) { header('Location: ' . app_url('admin/index.php')); exit; }

require_once __DIR__ . '/../models/AdminController.php';
require_once __DIR__ . '/_admin_filters.php';

$admin = AdminController::getInstance();
$pageTitle   = 'Reports';
$currentPage = 'reports';
$search = trim($_GET['search'] ?? '');
$date = trim($_GET['date'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $admin->updateReportStatus((int) ($_POST['report_id'] ?? 0), trim($_POST['status'] ?? 'open'));
    header('Location: ' . app_url('admin/reports.php'));
    exit;
}

$reports = $search !== '' ? $admin->searchReports($search, $date) : $admin->getAllReports($date);

include __DIR__ . '/../components/head.php';
include __DIR__ . '/../components/sidebar.php';
?>

<div class="panel">
  <div class="panel-head">
    <span class="panel-title"><i class="ti ti-flag" style="color:var(--red)"></i> Reports</span>
  </div>

  <form method="GET" style="margin-bottom:16px">
    <div class="filters" style="display:flex;gap:10px;flex-wrap:wrap">
      <input type="text"
             name="search"
             class="filter-input"
             placeholder="Search reports, users or status..."
             value="<?= htmlspecialchars($search) ?>">
      <input type="date"
             name="date"
             class="filter-input"
             value="<?= htmlspecialchars($date) ?>">
      <button class="btn btn-primary btn-sm" type="submit">
        <i class="ti ti-search"></i> Search
      </button>
      <?php if($search || $date): ?>
        <a href="reports.php" class="btn btn-outline btn-sm">Clear</a>
      <?php endif; ?>
    </div>
  </form>

  <?php if(empty($reports)): ?>
    <div class="empty-state">
      <i class="ti ti-flag" style="color:var(--border)"></i>
      <p>No reports yet. User reports will appear here.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>User</th>
            <th>Report</th>
            <th>Status</th>
            <th>Date</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($reports as $report): ?>
            <tr>
              <td>#<?= (int) $report['id'] ?></td>
              <td>
                <strong>@<?= htmlspecialchars($report['username'] ?? 'deleted') ?></strong><br>
                <span class="muted"><?= htmlspecialchars($report['email'] ?? '') ?></span>
              </td>
              <td style="max-width:420px;white-space:normal">
                <?= nl2br(htmlspecialchars($report['message'] ?? '')) ?>
              </td>
              <td><?= htmlspecialchars(ucfirst($report['status'] ?? 'open')) ?></td>
              <td><?= htmlspecialchars($report['created_at'] ?? '') ?></td>
              <td>
                <form method="POST" style="display:flex;gap:8px;align-items:center">
                  <?= csrf_field() ?>
                  <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
                  <select name="status" class="filter-input" style="min-width:120px">
                    <?php foreach(['open', 'reviewing', 'resolved'] as $status): ?>
                      <option value="<?= $status ?>" <?= ($report['status'] ?? 'open') === $status ? 'selected' : '' ?>>
                        <?= ucfirst($status) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-primary btn-sm" type="submit">Update</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

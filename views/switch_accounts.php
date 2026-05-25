<?php
require_once __DIR__ . '/_page_helpers.php';
require_login();

$pageTitle = 'Instagram - Switch accounts';
$activePage = 'switch_accounts';

include __DIR__ . '/../components/head.php';
?>
<div class="post_page d-flex">
    <?php include __DIR__ . '/../components/navbar.php'; ?>
    <main class="flex-grow-1 py-4 px-3" style="max-width:720px;margin:auto">
        <h4 class="mb-4">Switch accounts</h4>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <img src="<?= htmlspecialchars(profile_avatar($_SESSION['profile_pic'] ?? '', $_SESSION['username'] ?? 'User')) ?>" width="58" height="58" class="rounded-circle" alt="profile">
                <div class="flex-grow-1">
                    <div class="fw-semibold"><?= htmlspecialchars($_SESSION['username'] ?? 'Current account') ?></div>
                    <div class="text-muted small">Currently logged in</div>
                </div>
                <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(app_url('views/logout.php')) ?>">Log out</a>
            </div>
        </div>
    </main>
</div>
<?php include __DIR__ . '/../components/create_modal.php'; ?>
<?php include __DIR__ . '/../components/footer.php'; ?>

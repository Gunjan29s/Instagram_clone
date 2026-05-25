<?php
require_once __DIR__ . '/_page_helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mailing.php';
require_login();

$pageTitle = 'Instagram - Report a problem';
$activePage = 'report';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $message = trim($_POST['message'] ?? '');
    if ($message !== '') {
        $db = Database::getInstance()->getConnection();
        $db->exec(
            "CREATE TABLE IF NOT EXISTS problem_reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                message TEXT NOT NULL,
                status VARCHAR(30) DEFAULT 'open',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )"
        );

        $stmt = $db->prepare("INSERT INTO problem_reports (user_id, message, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([(int) $_SESSION['user_id'], $message]);

        $username = htmlspecialchars($_SESSION['username'] ?? 'User', ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $adminReportsUrl = app_url('admin/reports.php');

        try {
            sendMail(
                ADMIN_EMAIL,
                ADMIN_NAME,
                'New problem report from ' . ($_SESSION['username'] ?? 'user'),
                "<div style='font-family:Arial,sans-serif;line-height:1.5;color:#222'>
                    <h2 style='margin:0 0 12px'>New problem report</h2>
                    <p><strong>User:</strong> {$username}</p>
                    <p><strong>Message:</strong></p>
                    <p>{$safeMessage}</p>
                    <p><a href='{$adminReportsUrl}'>Open admin reports</a></p>
                </div>"
            );
        } catch (Throwable $e) {
            error_log('Problem report mail error: ' . $e->getMessage());
        }

        $success = 'Thanks. Your report has been saved for review.';
    }
}

include __DIR__ . '/../components/head.php';
?>
<div class="post_page d-flex">
    <?php include __DIR__ . '/../components/navbar.php'; ?>
    <main class="flex-grow-1 py-4 px-3" style="max-width:720px;margin:auto">
        <h4 class="mb-4">Report a problem</h4>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
                <form method="POST">
                    <?= csrf_field() ?>
                    <textarea class="form-control mb-3" name="message" rows="6" placeholder="What happened?" required></textarea>
                    <button class="btn btn-primary" type="submit">Send report</button>
                </form>
            </div>
        </div>
    </main>
</div>
<?php include __DIR__ . '/../components/create_modal.php'; ?>
<?php include __DIR__ . '/../components/footer.php'; ?>

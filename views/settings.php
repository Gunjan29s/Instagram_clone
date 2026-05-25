<?php
require_once __DIR__ . '/_page_helpers.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();
$pageTitle = 'Instagram - Settings';
$activePage = 'settings';
$userId = (int) $_SESSION['user_id'];
$success = '';
$error = '';

$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = trim($_POST['email'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    if ($email === '') {
        $error = 'Email is required.';
    } else {
        $emailCheck = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
        $emailCheck->execute([$email, $userId]);

        if ($emailCheck->fetch()) {
            $error = 'Email already exists.';
        } else {
            $update = $db->prepare('UPDATE users SET email = ?, website = ?, bio = ? WHERE id = ?');
            $update->execute([$email, $website, $bio, $userId]);
            $success = 'Settings updated.';
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        }
    }
}

include __DIR__ . '/../components/head.php';
?>
<div class="post_page d-flex">
    <?php include __DIR__ . '/../components/navbar.php'; ?>
    <main class="flex-grow-1 py-4 px-3" style="max-width:760px;margin:auto">
        <h4 class="mb-4">Settings</h4>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <form method="POST">
                    <?= csrf_field() ?>
                    <label class="form-label fw-semibold">Email</label>
                    <input class="form-control mb-3" type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                    <label class="form-label fw-semibold">Website</label>
                    <input class="form-control mb-3" type="url" name="website" value="<?= htmlspecialchars($user['website'] ?? '') ?>">
                    <label class="form-label fw-semibold">Bio</label>
                    <textarea class="form-control mb-3" name="bio" rows="4"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    
                    <button class="btn btn-primary px-4" type="submit">Save changes</button>
                    <a class="btn btn-outline-secondary ms-2" href="<?= htmlspecialchars(app_url('views/edit_profile.php')) ?>">Edit profile</a>
                </form>
            </div>
        </div>

        <!-- Blocked Users Section -->
        <?php
        // Ensure blocked_users table exists
        try {
            $db->exec("
                CREATE TABLE IF NOT EXISTS blocked_users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    blocker_id INT NOT NULL,
                    blocked_id INT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_block (blocker_id, blocked_id),
                    FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
        } catch (Exception $e) {}

        $blockedStmt = $db->prepare("
            SELECT u.id, u.username, u.full_name, u.profile_pic
            FROM blocked_users bu
            JOIN users u ON u.id = bu.blocked_id
            WHERE bu.blocker_id = ?
            ORDER BY bu.created_at DESC
        ");
        $blockedStmt->execute([$userId]);
        $blockedUsers = $blockedStmt->fetchAll();
        ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3">
                    <i class="fa-solid fa-ban me-2 text-danger"></i>Blocked Users
                </h5>
                <?php if (count($blockedUsers) > 0): ?>
                    <div id="blocked_list">
                    <?php foreach ($blockedUsers as $bu): ?>
                        <div class="d-flex align-items-center gap-3 py-2 border-bottom" id="blocked_row_<?= (int)$bu['id'] ?>">
                            <a href="<?= htmlspecialchars(app_url('views/profile.php?id=' . (int)$bu['id'])) ?>">
                                <img src="<?= htmlspecialchars(profile_avatar($bu['profile_pic'] ?? '', $bu['username'] ?? 'User')) ?>"
                                     class="rounded-circle"
                                     width="46" height="46"
                                     style="object-fit:cover;" alt="user">
                            </a>
                            <div class="flex-grow-1">
                                <div class="fw-semibold"><?= htmlspecialchars($bu['username']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($bu['full_name'] ?? '') ?></div>
                            </div>
                            <button class="btn btn-sm btn-outline-secondary"
                                    onclick="unblockUser(<?= (int)$bu['id'] ?>, this)">
                                Unblock
                            </button>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0" id="no_blocked_msg">You haven't blocked anyone.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
function unblockUser(userId, btn) {
    btn.disabled = true;
    btn.textContent = 'Unblocking...';
    fetch(<?= json_encode(app_url('controllers/block_user.php')) ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ user_id: userId, action: 'unblock' })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) { btn.disabled = false; btn.textContent = 'Unblock'; return; }
        const row = document.getElementById('blocked_row_' + userId);
        if (row) row.remove();
        const list = document.getElementById('blocked_list');
        if (list && list.children.length === 0) {
            list.innerHTML = '';
            const msg = document.createElement('p');
            msg.className = 'text-muted mb-0';
            msg.textContent = "You haven't blocked anyone.";
            list.parentNode.appendChild(msg);
        }
    })
    .catch(() => { btn.disabled = false; btn.textContent = 'Unblock'; });
}
</script>
<?php include __DIR__ . '/../components/create_modal.php'; ?>
<?php include __DIR__ . '/../components/footer.php'; ?>

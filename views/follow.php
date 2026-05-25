<?php
// views/follow.php
require_once __DIR__ . '/_page_helpers.php';
app_start_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('views/sign_in.php'));
    exit;
}

require_once __DIR__ . '/../models/FollowModel.php';

$pageTitle  = 'Instagram - Suggested Users';
$activePage = '';

$currentUserId = (int) $_SESSION['user_id'];
$followModel = new FollowModel();
$suggestedUsers = $suggestedUsers ?? $followModel->getSuggestedUsers($currentUserId);

include __DIR__ . '/../components/head.php';
?>

<div class="post_page d-flex">

    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <main class="flex-grow-1 d-flex align-items-start justify-content-center py-5 px-3">
        <div style="width:100%;max-width:420px">
            <h5 class="text-center fw-bold mb-4">Suggested for you</h5>

            <?php if(count($suggestedUsers) > 0): ?>

                <?php foreach ($suggestedUsers as $user): ?>
                <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
                    <div class="d-flex align-items-center gap-3">
                        <a href="<?= htmlspecialchars(app_url('views/profile.php?id=' . (int) $user['id'])) ?>">
                            <img src="<?= htmlspecialchars(profile_avatar($user['profile_pic'] ?? '', $user['username'] ?? 'User')) ?>"
                                 class="rounded-circle"
                                 width="50"
                                 height="50"
                                 style="object-fit:cover;"
                                 alt="<?= htmlspecialchars($user['username'] ?? 'user') ?>">
                        </a>

                        <div>
                            <a href="<?= htmlspecialchars(app_url('views/profile.php?id=' . (int) $user['id'])) ?>"
                               class="text-dark text-decoration-none fw-bold">
                                <?= htmlspecialchars($user['username'] ?? 'Unknown') ?>
                            </a>
                            <p class="mb-0 text-muted small">
                                <?= htmlspecialchars($user['full_name'] ?? '') ?>
                            </p>
                        </div>
                    </div>

                    <button type="button"
                            class="btn btn-primary btn-sm fw-bold follow-toggle-btn"
                            data-state="follow"
                            data-user-id="<?= (int) $user['id'] ?>"
                            onclick="toggleSuggestFollow(this)">
                        Follow
                    </button>
                </div>
                <?php endforeach; ?>

            <?php else: ?>

                <div class="text-center py-5 text-muted">
                    <i class="fa-regular fa-user fs-1 mb-3"></i>
                    <p class="mb-0">No suggestions right now.</p>
                </div>

            <?php endif; ?>
        </div>
    </main>

</div>

<?php include __DIR__ . '/../components/create_modal.php'; ?>

<script>
function toggleSuggestFollow(btn) {
    const userId = btn.dataset.userId;
    if (!userId) return;

    fetch(<?= json_encode(app_url('controllers/toggle_follow.php')) ?>, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({ following_id: userId })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) return;

        btn.textContent = data.state === 'following' ? 'Following' : 'Follow';
        btn.dataset.state = data.state;
        btn.classList.toggle('btn-primary', data.state !== 'following');
        btn.classList.toggle('btn-secondary', data.state === 'following');
    });
}
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>

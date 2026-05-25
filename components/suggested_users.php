<?php
require_once __DIR__ . '/../views/_page_helpers.php';

$suggested = [
    ['username' => 'riya_01',       'fullname' => 'Riya Sharma',   'avatar' => 'https://i.pravatar.cc/100?img=11'],
    ['username' => 'rohan_dev',     'fullname' => 'Rohan Mehta',   'avatar' => 'https://i.pravatar.cc/100?img=12'],
    ['username' => 'gunjan_world',  'fullname' => 'Gunjan Suyal',  'avatar' => 'https://i.pravatar.cc/100?img=13'],
    ['username' => 'travel_diary',  'fullname' => 'Ananya Joshi',  'avatar' => 'https://i.pravatar.cc/100?img=14'],
    ['username' => 'photo_clicks',  'fullname' => 'Aryan Kapoor',  'avatar' => 'https://i.pravatar.cc/100?img=15'],
];

?>

<div class="suggested_list">
    <?php foreach ($suggested as $user): ?>
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center gap-2">
            <img src="<?= htmlspecialchars(placeholder_avatar($user['username'])) ?>" class="rounded-circle" width="40" height="40" alt="<?= htmlspecialchars($user['username']) ?>">
            <div>
                <p class="mb-0 fw-semibold small"><?= htmlspecialchars($user['username']) ?></p>
                <p class="mb-0 text-muted" style="font-size:12px"><?= htmlspecialchars($user['fullname']) ?></p>
            </div>
        </div>
        <button class="btn btn-link p-0 text-primary fw-bold small follow-toggle-btn"
                data-state="follow"
                onclick="toggleSuggestFollow(this)">Follow</button>
    </div>
    <?php endforeach; ?>
</div>

<script>
function toggleSuggestFollow(btn) {
    if (btn.dataset.state === 'follow') {
        btn.textContent    = 'Following';
        btn.dataset.state  = 'following';
        btn.classList.add('text-secondary');
        btn.classList.remove('text-primary');
    } else {
        btn.textContent    = 'Follow';
        btn.dataset.state  = 'follow';
        btn.classList.add('text-primary');
        btn.classList.remove('text-secondary');
    }
}
</script>

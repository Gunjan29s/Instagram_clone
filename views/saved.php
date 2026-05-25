<?php
require_once __DIR__ . '/_page_helpers.php';
require_once __DIR__ . '/../models/PostModel.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/FollowModel.php';
require_login();

$pageTitle = 'Instagram - Saved';
$activePage = 'saved';
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$posts = (new PostModel())->getSavedPosts($currentUserId);
$profile = (new UserModel())->getProfile($currentUserId, $currentUserId);
$followModel = new FollowModel();
$profilePicUrl = profile_avatar($profile['profile_pic'] ?? '', $profile['username'] ?? 'User');

include __DIR__ . '/../components/head.php';
?>
<div class="post_page d-flex">
    <?php include __DIR__ . '/../components/navbar.php'; ?>
    <main class="flex-grow-1 py-4 px-3" style="max-width:935px;margin:auto">
        <div class="d-flex flex-column flex-md-row gap-5 align-items-center align-items-md-start mb-5">
            <div class="text-center">
                <img src="<?= htmlspecialchars($profilePicUrl) ?>"
                     class="rounded-circle"
                     width="150"
                     height="150"
                     alt="profile"
                     style="border:3px solid #dbdbdb;object-fit:cover;">
            </div>
            <div class="flex-grow-1">
                <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                    <h4 class="mb-0 fw-normal"><?= htmlspecialchars($profile['username'] ?? 'User') ?></h4>
                    <a href="<?= htmlspecialchars(app_url('views/edit_profile.php')) ?>" class="btn btn-outline-secondary btn-sm">Edit Profile</a>
                    <a href="<?= htmlspecialchars(app_url('views/settings.php')) ?>" class="btn btn-outline-secondary btn-sm">Settings</a>
                </div>
                <div class="d-flex gap-4 mb-3">
                    <span><strong><?= (int) ($profile['post_count'] ?? 0) ?></strong> posts</span>
                    <span><strong><?= number_format($followModel->getFollowersCount($currentUserId)) ?></strong> followers</span>
                    <span><strong><?= number_format($followModel->getFollowingCount($currentUserId)) ?></strong> following</span>
                </div>
                <p class="mb-1 fw-bold"><?= htmlspecialchars($profile['full_name'] ?? '') ?></p>
                <p class="mb-1"><?= nl2br(htmlspecialchars($profile['bio'] ?? '')) ?></p>
            </div>
        </div>

        <ul class="nav border-top justify-content-center gap-4 mb-4">
            <li class="nav-item"><a class="nav-link text-muted d-flex align-items-center gap-1" href="<?= htmlspecialchars(app_url('views/profile.php?id=' . $currentUserId . '&tab=posts')) ?>"><i class="fa-solid fa-table-cells-large"></i> POSTS</a></li>
            <li class="nav-item"><a class="nav-link text-muted d-flex align-items-center gap-1" href="<?= htmlspecialchars(app_url('views/profile.php?id=' . $currentUserId . '&tab=reels')) ?>"><i class="fa-solid fa-video"></i> REELS</a></li>
            <li class="nav-item"><a class="nav-link active text-dark fw-semibold d-flex align-items-center gap-1" href="<?= htmlspecialchars(app_url('views/saved.php')) ?>"><i class="fa-regular fa-bookmark"></i> SAVED</a></li>
            <li class="nav-item"><a class="nav-link text-muted d-flex align-items-center gap-1" href="<?= htmlspecialchars(app_url('views/tagged.php?id=' . $currentUserId)) ?>"><i class="fa-solid fa-tag"></i> TAGGED</a></li>
        </ul>

        <?php if(count($posts) > 0): ?>
            <div class="row g-1">
                <?php foreach($posts as $post): ?>
                    <div class="col-4">
                        <div style="aspect-ratio:1;overflow:hidden;position:relative;background:#fafafa;">
                            <?php if(!is_video_media($post['media_path'] ?? '', $post['media_type'] ?? '')): ?>
                                <img src="<?= htmlspecialchars(post_media_url($post['media_path'])) ?>"
                                     style="width:100%;height:100%;object-fit:cover"
                                     alt="saved post">
                            <?php else: ?>
                                <video style="width:100%;height:100%;object-fit:cover">
                                    <source src="<?= htmlspecialchars(post_media_url($post['media_path'])) ?>">
                                </video>
                                <i class="fa-solid fa-video text-white"
                                   style="position:absolute;right:10px;top:8px;text-shadow:0 1px 5px #000"></i>
                            <?php endif; ?>

                            <div class="post_overlay d-flex justify-content-center align-items-center gap-4 text-white fw-bold"
                                 style="position:absolute;inset:0;background:rgba(0,0,0,.35);opacity:0;transition:.2s;">
                                <span><i class="fa-solid fa-heart"></i> <?= (int) ($post['total_likes'] ?? 0) ?></span>
                                <span><i class="fa-solid fa-comment"></i> <?= (int) ($post['total_comments'] ?? 0) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <?php render_empty_state('fa-regular fa-bookmark', 'Save posts for later', 'Tap the bookmark icon on posts to keep them here.'); ?>
        <?php endif; ?>
    </main>
</div>
<style>
.post_overlay:hover{ opacity:1 !important; }
</style>
<?php include __DIR__ . '/../components/create_modal.php'; ?>
<?php include __DIR__ . '/../components/footer.php'; ?>

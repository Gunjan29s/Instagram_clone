<?php
require_once __DIR__ . '/../views/_page_helpers.php';

$activePage = $activePage ?? '';
$profilePic = $_SESSION['profile_pic'] ?? '';
$profileName = $_SESSION['username'] ?? 'You';
$profilePic = profile_avatar($profilePic, $profileName);

// Unread counts for badges
$_navUnreadNotif = 0;
$_navUnreadMsg   = 0;
if (isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $_navDb  = Database::getInstance()->getConnection();
        $_navUid = (int) $_SESSION['user_id'];

        $s = $_navDb->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $s->execute([$_navUid]);
        $_navUnreadNotif = (int) $s->fetchColumn();

        // Unread messages (accepted convos)
        $s2 = $_navDb->prepare("
            SELECT COUNT(*) FROM messages
            WHERE receiver_id = ? AND is_read = 0
        ");
        $s2->execute([$_navUid]);
        $_navUnreadMsg = (int) $s2->fetchColumn();

        // Add pending requests count to message badge
        try {
            $s3 = $_navDb->prepare(
                "SELECT COUNT(DISTINCT sender_id) FROM message_requests WHERE receiver_id = ? AND status = 'pending'"
            );
            $s3->execute([$_navUid]);
            $_navUnreadMsg += (int) $s3->fetchColumn();
        } catch (Exception $e) { /* table may not exist yet */ }

    } catch (Exception $e) { /* DB not ready */ }
}
?>

<div class="nav_menu">
    <div class="fix_top">

        <nav class="nav d-none d-md-flex flex-column">
            <div class="logo mb-3">
                <a href="<?= htmlspecialchars(app_url('views/home.php')) ?>" class="brand-link" aria-label="Instagram home">
                    <i class="fa-brands fa-instagram brand-icon me-2"></i>
                    <span class="brand-word d-none d-lg-inline">Instagram</span>
                </a>
            </div>
            <ul class="menu list-unstyled">
                <li>
                    <a href="<?= htmlspecialchars(app_url('views/home.php')) ?>" class="<?= $activePage === 'home' ? 'active' : '' ?>">
                        <i class="fa-solid fa-house"></i>
                        <span class="d-none d-lg-inline">Home</span>
                    </a>
                </li>
                <li id="search_icon">
                    <a href="<?= htmlspecialchars(app_url('views/search.php')) ?>" id="searchToggle">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <span class="d-none d-lg-inline">Search</span>
                    </a>
                </li>
                <li>
                    <a href="<?= htmlspecialchars(app_url('views/explore.php')) ?>" class="<?= $activePage === 'explore' ? 'active' : '' ?>">
                        <i class="fa-regular fa-compass"></i>
                        <span class="d-none d-lg-inline">Explore</span>
                    </a>
                </li>
                <li>
                    <a href="<?= htmlspecialchars(app_url('views/reels.php')) ?>" class="<?= $activePage === 'reels' ? 'active' : '' ?>">
                        <i class="fa-solid fa-film"></i>
                        <span class="d-none d-lg-inline">Reels</span>
                    </a>
                </li>
                <li>
                    <a href="<?= htmlspecialchars(app_url('views/messages.php')) ?>" class="<?= $activePage === 'messages' ? 'active' : '' ?>" style="position:relative">
                        <i class="fa-regular fa-paper-plane"></i>
                        <span class="d-none d-lg-inline">Messages</span>
                        <?php if ($_navUnreadMsg > 0): ?>
                            <span class="nav_badge" id="nav_badge_msg"><?= $_navUnreadMsg > 99 ? '99+' : $_navUnreadMsg ?></span>
                        <?php else: ?>
                            <span class="nav_badge d-none" id="nav_badge_msg"></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="notification_icon">
                    <a href="<?= htmlspecialchars(app_url('views/notification.php')) ?>" class="<?= $activePage === 'notification' ? 'active' : '' ?>" style="position:relative">
                        <i class="fa-regular fa-heart"></i>
                        <span class="d-none d-lg-inline">Notifications</span>
                        <?php if ($_navUnreadNotif > 0): ?>
                            <span class="nav_badge" id="nav_badge_notif"><?= $_navUnreadNotif > 99 ? '99+' : $_navUnreadNotif ?></span>
                        <?php else: ?>
                            <span class="nav_badge d-none" id="nav_badge_notif"></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#create_modal">
                        <i class="fa-regular fa-square-plus"></i>
                        <span class="d-none d-lg-inline">Create</span>
                    </a>
                </li>
                <li>
                    <a href="<?= htmlspecialchars(app_url('views/profile.php')) ?>" class="<?= $activePage === 'profile' ? 'active' : '' ?>">
                        <img class="circle story" src="<?= htmlspecialchars($profilePic) ?>" alt="profile">
                        <span class="d-none d-lg-inline">Profile</span>
                    </a>
                </li>
            </ul>
            <div class="more mt-auto">
                <div class="btn-group dropup">
                    <button type="button" class="btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-bars"></i>
                        <span class="d-none d-lg-inline">More</span>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= htmlspecialchars(app_url('views/settings.php')) ?>"><span>Settings</span></a></li>
                        <li><a class="dropdown-item" href="<?= htmlspecialchars(app_url('views/activity.php')) ?>"><span>Your activity</span></a></li>
                        <li><a class="dropdown-item" href="<?= htmlspecialchars(app_url('views/saved.php')) ?>"><span>Saved</span></a></li>
                        <li><a class="dropdown-item" href="<?= htmlspecialchars(app_url('views/appearance.php')) ?>"><span>Switch appearance</span></a></li>
                        <li><a class="dropdown-item" href="<?= htmlspecialchars(app_url('views/report_problem.php')) ?>"><span>Report a problem</span></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= htmlspecialchars(app_url('views/switch_accounts.php')) ?>"><span>Switch accounts</span></a></li>
                        <li><a class="dropdown-item" href="<?= htmlspecialchars(app_url('views/logout.php')) ?>"><span>Log out</span></a></li>
                    </ul>
                </div>
            </div>
        </nav>
        <div class="nav_sm d-flex d-md-none">
            <div class="content d-flex justify-content-between align-items-center w-100 px-3 py-2">
                <a href="<?= htmlspecialchars(app_url('views/home.php')) ?>" class="brand-link" aria-label="Instagram home">
                    <i class="fa-brands fa-instagram brand-icon me-2"></i>
                    <span class="brand-word">Instagram</span>
                </a>
                <div class="d-flex align-items-center gap-3">
                    <form class="search_bar" action="<?= htmlspecialchars(app_url('views/search.php')) ?>" method="GET">
                        <input type="search" name="q" class="form-control form-control-sm" placeholder="Search">
                    </form>
                    <a href="<?= htmlspecialchars(app_url('views/notification.php')) ?>" style="position:relative">
                        <i class="fa-regular fa-heart fs-5"></i>
                        <?php if ($_navUnreadNotif > 0): ?>
                            <span class="nav_badge" style="top:-4px;right:-6px"><?= $_navUnreadNotif > 99 ? '99+' : $_navUnreadNotif ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </div>

    </div>

    <div class="nav_bottom d-flex d-md-none justify-content-around align-items-center py-2">
        <a href="<?= htmlspecialchars(app_url('views/home.php')) ?>"><i class="fa-solid fa-house fs-5"></i></a>
        <a href="<?= htmlspecialchars(app_url('views/explore.php')) ?>"><i class="fa-regular fa-compass fs-5"></i></a>
        <a href="<?= htmlspecialchars(app_url('views/reels.php')) ?>"><i class="fa-solid fa-film fs-5"></i></a>
        <a href="#" data-bs-toggle="modal" data-bs-target="#create_modal"><i class="fa-regular fa-square-plus fs-5"></i></a>
        <a href="<?= htmlspecialchars(app_url('views/profile.php')) ?>">
            <img class="circle story" src="<?= htmlspecialchars($profilePic) ?>" alt="profile" style="width:28px;height:28px;border-radius:50%">
        </a>
    </div>
</div>

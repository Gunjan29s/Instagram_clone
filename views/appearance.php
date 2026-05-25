<?php
require_once __DIR__ . '/_page_helpers.php';
require_login();

$pageTitle = 'Instagram - Appearance';
$activePage = 'appearance';
$mode = $_COOKIE['insta_theme'] ?? 'light';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $mode = ($_POST['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
    setcookie('insta_theme', $mode, time() + 31536000, '/');
    $_COOKIE['insta_theme'] = $mode;
}

include __DIR__ . '/../components/head.php';
?>
<div class="post_page d-flex">
    <?php include __DIR__ . '/../components/navbar.php'; ?>
    <main class="flex-grow-1 py-4 px-3" style="max-width:720px;margin:auto">
        <h4 class="mb-4">Switch appearance</h4>
        <form class="card border-0 shadow-sm" method="POST">
            <?= csrf_field() ?>
            <div class="card-body p-4">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="theme" value="light" id="themeLight" <?= $mode !== 'dark' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="themeLight">Light</label>
                </div>
                <div class="form-check mb-4">
                    <input class="form-check-input" type="radio" name="theme" value="dark" id="themeDark" <?= $mode === 'dark' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="themeDark">Dark</label>
                </div>
                <button class="btn btn-primary" type="submit">Apply</button>
            </div>
        </form>
    </main>
</div>
<?php include __DIR__ . '/../components/create_modal.php'; ?>
<?php include __DIR__ . '/../components/footer.php'; ?>

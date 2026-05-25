<?php
require_once __DIR__ . '/../views/_page_helpers.php';
$pageTitle = $pageTitle ?? 'Instagram';
$themeMode = ($_COOKIE['insta_theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
$pageDescription = $pageDescription ?? 'Share photos, reels, stories, and messages with friends.';
$pageImage = $pageImage ?? ((app_is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . app_url('uploads/og-image.svg'));
$currentUrl = (app_is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? app_url());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
    <meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($currentUrl, ENT_QUOTES) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($pageImage, ENT_QUOTES) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Cdefs%3E%3ClinearGradient id='g' x1='0' x2='1' y1='1' y2='0'%3E%3Cstop stop-color='%23feda75'/%3E%3Cstop offset='.35' stop-color='%23fa7e1e'/%3E%3Cstop offset='.62' stop-color='%23d62976'/%3E%3Cstop offset='1' stop-color='%234f5bd5'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='64' height='64' rx='17' fill='url(%23g)'/%3E%3Crect x='17' y='17' width='30' height='30' rx='9' fill='none' stroke='white' stroke-width='4'/%3E%3Ccircle cx='32' cy='32' r='7' fill='none' stroke='white' stroke-width='4'/%3E%3Ccircle cx='42' cy='22' r='3' fill='white'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/emojionearea/3.4.2/emojionearea.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('css/style.css')) ?>">
    <script>
        window.INSTA_BASE_URL = <?= json_encode(rtrim(app_base_url(), '/')) ?>;
        window.INSTA_USER_ID = <?= isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0 ?>;
        window.INSTA_SOCKET_URL = <?= json_encode('ws://' . explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0] . ':8080') ?>;
        window.INSTA_CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
        (function () {
            const nativeFetch = window.fetch.bind(window);
            window.fetch = function (resource, options = {}) {
                const method = String(options.method || 'GET').toUpperCase();
                if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
                    const headers = new Headers(options.headers || {});
                    if (!headers.has('X-CSRF-Token')) {
                        headers.set('X-CSRF-Token', window.INSTA_CSRF_TOKEN || '');
                    }
                    options = { ...options, headers };
                }
                return nativeFetch(resource, options);
            };
        })();
    </script>
    <script src="<?= htmlspecialchars(app_url('js/socket.js')) ?>" defer></script>
</head>
<body class="<?= $themeMode === 'dark' ? 'dark-theme' : '' ?>">
<script>
// Navbar badge polling — har 30 sec mein unread counts update karo
(function pollBadges() {
    if (!window.INSTA_BASE_URL && window.INSTA_BASE_URL !== '') return;
    const url = window.INSTA_BASE_URL + '/controllers/unread_counts.php';

    function updateBadge(id, count) {
        const el = document.getElementById(id);
        if (!el) return;
        if (count > 0) {
            el.textContent = count > 99 ? '99+' : count;
            el.classList.remove('d-none');
        } else {
            el.classList.add('d-none');
        }
    }

    function fetchCounts() {
        fetch(url, { credentials: 'same-origin' })
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (!data) return;
                updateBadge('nav_badge_notif', data.notifications || 0);
                updateBadge('nav_badge_msg',   data.messages   || 0);
            })
            .catch(() => {});
    }

    // First call after 5s (page just loaded, badges already rendered server-side)
    setTimeout(fetchCounts, 5000);
    setInterval(fetchCounts, 30000);
})();
</script>

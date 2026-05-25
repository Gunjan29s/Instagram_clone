<?php
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/../config/security.php';
app_send_security_headers();

function app_start_session(): void {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $lifetime = 60 * 60 * 24 * 365;
    ini_set('session.gc_maxlifetime', (string) $lifetime);
    ini_set('session.cookie_lifetime', (string) $lifetime);

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'domain' => '',
        'secure' => app_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
    app_restore_user_session();
}

function require_login(): void {
    app_start_session();

    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . app_url('views/sign_in.php'));
        exit;
    }
}

function app_rate_limit_key(string $scope, string $identity = ''): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'local';
    return $scope . ':' . hash('sha256', strtolower(trim($identity)) . '|' . $ip);
}

function app_rate_limited(string $key, int $maxAttempts = 5, int $windowSeconds = 900): bool {
    app_start_session();
    $now = time();
    $_SESSION['rate_limits'][$key] = array_values(array_filter(
        $_SESSION['rate_limits'][$key] ?? [],
        fn($time) => (int) $time > $now - $windowSeconds
    ));

    return count($_SESSION['rate_limits'][$key]) >= $maxAttempts;
}

function app_rate_limit_hit(string $key, int $windowSeconds = 900): void {
    app_start_session();
    $now = time();
    $_SESSION['rate_limits'][$key] = array_values(array_filter(
        $_SESSION['rate_limits'][$key] ?? [],
        fn($time) => (int) $time > $now - $windowSeconds
    ));
    $_SESSION['rate_limits'][$key][] = $now;
}

function app_rate_limit_clear(string $key): void {
    app_start_session();
    unset($_SESSION['rate_limits'][$key]);
}

function app_auth_cookie_lifetime(): int {
    return 60 * 60 * 24 * 365;
}

function app_auth_cookie_name(): string {
    return 'insta_user_auth';
}

function app_auth_secret(): string {
    $seed = getenv('APP_KEY') ?: dirname(__DIR__) . '|insta_out';
    return hash('sha256', $seed);
}

function app_set_auth_cookie(int $userId, string $passwordHash): void {
    if ($userId <= 0 || $passwordHash === '') {
        return;
    }

    $expires = time() + app_auth_cookie_lifetime();
    $payload = [
        'uid' => $userId,
        'ph' => hash('sha256', $passwordHash),
        'exp' => $expires,
    ];
    $encoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $encoded, app_auth_secret());

    setcookie(app_auth_cookie_name(), $encoded . '.' . $signature, [
        'expires' => $expires,
        'path' => '/',
        'domain' => '',
        'secure' => app_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function app_clear_auth_cookie(): void {
    setcookie(app_auth_cookie_name(), '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => app_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function app_restore_user_session(): void {
    if (!empty($_SESSION['user_id']) || empty($_COOKIE[app_auth_cookie_name()])) {
        return;
    }

    $parts = explode('.', (string) $_COOKIE[app_auth_cookie_name()], 2);
    if (count($parts) !== 2) {
        app_clear_auth_cookie();
        return;
    }

    [$encoded, $signature] = $parts;
    $expectedSignature = hash_hmac('sha256', $encoded, app_auth_secret());
    if (!hash_equals($expectedSignature, $signature)) {
        app_clear_auth_cookie();
        return;
    }

    $json = base64_decode(strtr($encoded, '-_', '+/'), true);
    $payload = $json ? json_decode($json, true) : null;
    $userId = (int) ($payload['uid'] ?? 0);
    $expires = (int) ($payload['exp'] ?? 0);
    $passwordHashFingerprint = (string) ($payload['ph'] ?? '');

    if ($userId <= 0 || $expires < time() || $passwordHashFingerprint === '') {
        app_clear_auth_cookie();
        return;
    }

    require_once __DIR__ . '/../models/Database.php';
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare('SELECT id, username, profile_pic, password, is_banned FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !empty($user['is_banned']) || !hash_equals($passwordHashFingerprint, hash('sha256', $user['password'] ?? ''))) {
        app_clear_auth_cookie();
        return;
    }

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['profile_pic'] = $user['profile_pic'] ?? '';
    app_set_auth_cookie((int) $user['id'], $user['password']);
}

function app_base_url(): string {
    static $baseUrl = null;

    if ($baseUrl !== null) {
        return $baseUrl;
    }

    $scriptName = rawurldecode(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''));
    $projectDir = basename(dirname(__DIR__));
    $projectSegment = '/' . $projectDir . '/';
    $position = strpos($scriptName, $projectSegment);

    if ($position !== false) {
        $baseUrl = app_encode_url_path(substr($scriptName, 0, $position) . '/' . $projectDir);
        return $baseUrl;
    }

    $baseUrl = '';
    return $baseUrl;
}

function app_url(string $path = ''): string {
    $path = trim(str_replace('\\', '/', $path));

    if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:') || str_starts_with($path, 'blob:')) {
        return $path;
    }

    $projectDir = basename(dirname(__DIR__));
    $path = preg_replace('#^(\./|\.\./)+#', '', $path);
    $path = preg_replace('#^/?(' . preg_quote($projectDir, '#') . '|instagram_clone)/#', '', $path);

    $baseUrl = rtrim(app_base_url(), '/');
    $path = ltrim($path, '/');
    $path = app_clean_route_path($path);

    if ($path === '') {
        return $baseUrl === '' ? '/' : $baseUrl . '/';
    }

    return ($baseUrl === '' ? '' : $baseUrl) . '/' . $path;
}

function app_encode_url_path(string $path): string {
    $segments = explode('/', str_replace('\\', '/', $path));

    foreach ($segments as $index => $segment) {
        $segments[$index] = rawurlencode(rawurldecode($segment));
    }

    return implode('/', $segments);
}

function app_clean_route_path(string $path): string {
    $parts = parse_url($path);
    $routePath = trim($parts['path'] ?? $path, '/');
    $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
    $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#' . $parts['fragment'] : '';

    $viewRoutes = [
        'views/activity.php' => 'activity',
        'views/appearance.php' => 'appearance',
        'views/create_post.php' => 'create-post',
        'views/edit_profile.php' => 'edit-profile',
        'views/explore.php' => 'explore',
        'views/follow.php' => 'follow',
        'views/forget.php' => 'forgot-password',
        'views/home.php' => 'home',
        'views/like.php' => 'like',
        'views/logout.php' => 'logout',
        'views/messages.php' => 'messages',
        'views/notification.php' => 'notifications',
        'views/post.php' => 'post',
        'views/profile.php' => 'profile',
        'views/reels.php' => 'reels',
        'views/report_problem.php' => 'report-problem',
        'views/saved.php' => 'saved',
        'views/search.php' => 'search',
        'views/settings.php' => 'settings',
        'views/sign_in.php' => 'sign-in',
        'views/sign_up.php' => 'sign-up',
        'views/single_post.php' => 'single-post',
        'views/switch_accounts.php' => 'switch-accounts',
        'views/tagged.php' => 'tagged',
    ];

    $adminRoutes = [
        'admin/comments.php' => 'admin/comments',
        'admin/dashboard.php' => 'admin/dashboard',
        'admin/follows.php' => 'admin/follows',
        'admin/index.php' => 'admin',
        'admin/likes.php' => 'admin/likes',
        'admin/logout.php' => 'admin/logout',
        'admin/messages.php' => 'admin/messages',
        'admin/notifications.php' => 'admin/notifications',
        'admin/posts.php' => 'admin/posts',
        'admin/register.php' => 'admin/register',
        'admin/reports.php' => 'admin/reports',
        'admin/users.php' => 'admin/users',
    ];

    $routes = $viewRoutes + $adminRoutes;
    if (!isset($routes[$routePath])) {
        return $path;
    }

    return $routes[$routePath] . $query . $fragment;
}

function app_local_file_exists(string $path): bool {
    $path = trim(str_replace('\\', '/', $path));

    if ($path === '' || preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:') || str_starts_with($path, 'blob:')) {
        return false;
    }

    $projectDir = basename(dirname(__DIR__));
    $path = preg_replace('#^(\./|\.\./)+#', '', $path);
    $path = preg_replace('#^[A-Z]:/.*/(uploads|storage|images)/#i', '$1/', $path);
    $path = preg_replace('#^/?(' . preg_quote($projectDir, '#') . '|instagram_clone)/#', '', $path);

    return is_file(dirname(__DIR__) . '/' . ltrim($path, '/'));
}

function placeholder_avatar(string $name = 'User'): string {
    $name = trim($name) !== '' ? trim($name) : 'User';
    $parts = preg_split('/\s+/', $name);
    $initials = strtoupper(substr($parts[0] ?? 'U', 0, 1) . substr($parts[1] ?? '', 0, 1));
    $initials = htmlspecialchars($initials, ENT_QUOTES, 'UTF-8');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128"><rect width="128" height="128" rx="64" fill="#f2f2f2"/><text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" font-family="Arial,sans-serif" font-size="42" font-weight="700" fill="#262626">' . $initials . '</text></svg>';

    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

function placeholder_media(): string {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400"><rect width="400" height="400" fill="#fafafa"/><path d="M120 260l55-70 43 52 28-34 54 52H120z" fill="#dbdbdb"/><rect x="95" y="105" width="210" height="190" rx="18" fill="none" stroke="#dbdbdb" stroke-width="14"/><circle cx="255" cy="150" r="20" fill="#dbdbdb"/></svg>';

    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

function app_asset_url(string $path = ''): string {
    $path = trim($path);

    if ($path === '') {
        return '';
    }

    if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:') || str_starts_with($path, 'blob:')) {
        return $path;
    }

    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#^(\./|\.\./)+#', '', $path);
    $path = preg_replace('#^[A-Z]:/.*/(uploads|storage|images)/#i', '$1/', $path);

    return app_url($path);
}

function profile_avatar(string $path = '', string $name = 'User'): string {
    $path = trim($path);

    // Empty ya old default path — placeholder dikhao
    if ($path === '' || strpos($path, '/instagram_clone/images/') !== false) {
        return placeholder_avatar($name);
    }

    // External URL — seedha return karo
    if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:') || str_starts_with($path, 'blob:')) {
        return $path;
    }

    // Local path — file exist kare ya na kare, URL generate karo
    // (file existence check hata diya — DB mein stored paths directly use honge)
    return app_local_file_exists($path) ? app_asset_url($path) : placeholder_avatar($name);
}

function post_media_url(string $path = ''): string {
    return app_asset_url($path);
}

function is_video_media(string $path = '', string $mediaType = ''): bool {
    if (strtolower(trim($mediaType)) === 'video') {
        return true;
    }

    return (bool) preg_match('/\.(mp4|webm|ogg|mov|avi|m4v|3gp|mkv)(\?.*)?$/i', trim($path));
}

function render_empty_state(string $icon, string $title, string $text): void {
    ?>
    <div class="text-center py-5">
        <i class="<?= htmlspecialchars($icon) ?> fs-1 mb-3 text-muted"></i>
        <h5><?= htmlspecialchars($title) ?></h5>
        <p class="text-muted mb-0"><?= htmlspecialchars($text) ?></p>
    </div>
    <?php
}

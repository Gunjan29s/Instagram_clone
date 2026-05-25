<?php
require_once __DIR__ . '/AuthController.php';
require_once __DIR__ . '/HomeController.php';
require_once __DIR__ . '/PostController.php';
require_once __DIR__ . '/ExploreController.php';
require_once __DIR__ . '/ProfileController.php';

class FrontController {
    private const CONTROLLER_ENDPOINTS = [
        'add_comment',
        'block_user',
        'delete_comment',
        'delete_message',
        'delete_post',
        'delete_story',
        'get_share_users',
        'handle_follow_request',
        'mark_story_seen',
        'message_request_action',
        'remove_follower',
        'send_share_message',
        'socket_auth',
        'socket_message_api',
        'toggle_follow',
        'toggle_like',
        'toggle_save',
        'unread_counts',
    ];

    private const CLEAN_ROUTES = [
        '' => '',
        'activity' => 'activity',
        'appearance' => 'appearance',
        'create-post' => 'create_post',
        'edit-profile' => 'edit_profile',
        'explore' => 'explore',
        'follow' => 'follow',
        'forgot-password' => 'forget',
        'home' => 'home',
        'like' => 'like',
        'logout' => 'logout',
        'messages' => 'messages',
        'notifications' => 'notification',
        'post' => 'post',
        'profile' => 'profile',
        'reels' => 'reels',
        'report-problem' => 'report_problem',
        'saved' => 'saved',
        'search' => 'search',
        'settings' => 'settings',
        'sign-in' => 'sign_in',
        'sign-up' => 'sign_up',
        'single-post' => 'single_post',
        'switch-accounts' => 'switch_accounts',
        'tagged' => 'tagged',
    ];

    private const ADMIN_ROUTES = [
        'admin' => 'index',
        'admin/comments' => 'comments',
        'admin/dashboard' => 'dashboard',
        'admin/follows' => 'follows',
        'admin/likes' => 'likes',
        'admin/logout' => 'logout',
        'admin/messages' => 'messages',
        'admin/notifications' => 'notifications',
        'admin/posts' => 'posts',
        'admin/register' => 'register',
        'admin/reports' => 'reports',
        'admin/users' => 'users',
    ];

    public static function dispatchCurrent(): void {
        if (!empty($_SERVER['APP_CONTROLLER']) || !empty($_SERVER['REDIRECT_APP_CONTROLLER'])) {
            self::renderControllerEndpoint($_SERVER['APP_CONTROLLER'] ?? $_SERVER['REDIRECT_APP_CONTROLLER']);
            return;
        }

        $route = self::currentRoute();

        if ($route === '') {
            self::dispatchView(isset($_SESSION['user_id']) ? 'home' : 'sign_up');
            return;
        }

        if (isset(self::ADMIN_ROUTES[$route])) {
            self::renderAdmin(self::ADMIN_ROUTES[$route]);
            return;
        }

        if (isset(self::CLEAN_ROUTES[$route])) {
            self::dispatchView(self::CLEAN_ROUTES[$route]);
            return;
        }

        self::notFound();
    }

    public static function dispatchView(string $page): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        switch ($page) {
            case 'sign_up':
                $controller = new AuthController();
                $method === 'POST' ? $controller->signup() : $controller->signupPage();
                break;

            case 'sign_in':
                $controller = new AuthController();
                $method === 'POST' ? $controller->login() : $controller->loginPage();
                break;

            case 'forget':
                self::renderView('forget');
                break;

            case 'logout':
                (new AuthController())->logout();
                break;

            case 'home':
                (new HomeController())->index();
                break;

            case 'explore':
                (new ExploreController())->index();
                break;

            case 'profile':
                (new ProfileController())->index();
                break;

            case 'create_post':
            case 'post':
                $controller = new PostController();
                $method === 'POST' ? $controller->store() : $controller->createPage();
                break;

            case 'activity':
            case 'appearance':
            case 'edit_profile':
            case 'follow':
            case 'like':
            case 'messages':
            case 'notification':
            case 'reels':
            case 'report_problem':
            case 'saved':
            case 'search':
            case 'settings':
            case 'single_post':
            case 'switch_accounts':
            case 'tagged':
                self::renderView($page);
                break;

            default:
                self::notFound();
        }
    }

    private static function currentRoute(): string {
        if (!empty($_SERVER['APP_ROUTE'])) {
            return trim(str_replace('\\', '/', $_SERVER['APP_ROUTE']), '/');
        }

        if (!empty($_SERVER['REDIRECT_APP_ROUTE'])) {
            return trim(str_replace('\\', '/', $_SERVER['REDIRECT_APP_ROUTE']), '/');
        }

        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $basePath = rtrim(rawurldecode(app_base_url()), '/');

        if ($basePath !== '' && strpos(rawurldecode($requestPath), $basePath) === 0) {
            $requestPath = substr(rawurldecode($requestPath), strlen($basePath));
        }

        $route = trim(str_replace('\\', '/', rawurldecode($requestPath)), '/');
        $route = preg_replace('#/+#', '/', $route);

        if ($route === 'index.php') {
            return '';
        }

        if ($route === 'admin/index.php') {
            return 'admin';
        }

        if (preg_match('#^admin/(comments|dashboard|follows|likes|logout|messages|notifications|posts|register|reports|users)\.php$#', $route, $match)) {
            return 'admin/' . $match[1];
        }

        return $route;
    }

    private static function renderView(string $page): void {
        $file = __DIR__ . '/../views/' . $page . '.php';
        if (!is_file($file)) {
            self::notFound();
            return;
        }

        if (!defined('MVC_RENDERING')) {
            define('MVC_RENDERING', true);
        }

        require $file;
    }

    private static function renderAdmin(string $page): void {
        $rootPages = ['index', 'dashboard'];
        $file = in_array($page, $rootPages, true)
            ? __DIR__ . '/../admin/' . $page . '.php'
            : __DIR__ . '/../admin/views/' . $page . '.php';

        if (!is_file($file)) {
            self::notFound();
            return;
        }

        require $file;
    }

    private static function renderControllerEndpoint(string $endpoint): void {
        $endpoint = trim(str_replace(['\\', '/'], '', $endpoint));
        if (!in_array($endpoint, self::CONTROLLER_ENDPOINTS, true)) {
            self::notFound();
            return;
        }

        require __DIR__ . '/' . $endpoint . '.php';
    }

    private static function notFound(): void {
        http_response_code(404);
        $pageTitle = 'Page Not Found';
        require __DIR__ . '/../views/404.php';
    }
}
?>

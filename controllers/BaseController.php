<?php
require_once __DIR__ . '/../views/_page_helpers.php';

class BaseController {

    protected function render(string $page, array $data = []): void {
        if (!defined('MVC_RENDERING')) {
            define('MVC_RENDERING', true);
        }
        extract($data);
        require __DIR__ . '/../views/' . $page . '.php';
    }

    protected function jsonResponse(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function redirect(string $url): void {
        header("Location: $url");
        exit;
    }

    protected function startSession(): void {
        app_start_session();
    }

    protected function requireLogin(): int {
        $this->startSession();
        if (!isset($_SESSION['user_id'])) {
            $this->redirect(app_url('views/sign_in.php'));
        }
        return (int) $_SESSION['user_id'];
    }

    protected function prepareCaptcha(string $key): array {
        $this->startSession();
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $code = '';

        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        $_SESSION['captcha'][$key] = [
            'answer_hash' => password_hash($code, PASSWORD_DEFAULT),
            'created_at' => time(),
        ];

        return ['code' => $code];
    }

    protected function verifyCaptcha(string $key, string $answer): bool {
        $this->startSession();
        $captcha = $_SESSION['captcha'][$key] ?? null;
        unset($_SESSION['captcha'][$key]);

        if (!$captcha || !is_array($captcha)) {
            return false;
        }

        if ((int) ($captcha['created_at'] ?? 0) < time() - 600) {
            return false;
        }

        return password_verify(trim($answer), $captcha['answer_hash'] ?? '');
    }

    protected function redirectBack(string $fallback = ''): void {
        $fallback = $fallback !== '' ? $fallback : app_url('views/home.php');
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if ($referer !== '') {
            $refererHost = parse_url($referer, PHP_URL_HOST);
            if ($refererHost !== null && strcasecmp($refererHost, $host) === 0) {
                $this->redirect($referer);
            }
        }

        $this->redirect($fallback);
    }

    protected function post(string $key, string $default = ''): string {
        return htmlspecialchars(trim($_POST[$key] ?? $default));
    }

    protected function rawPost(string $key, string $default = ''): string {
        return trim($_POST[$key] ?? $default);
    }

    protected function get(string $key, string $default = ''): string {
        return htmlspecialchars(trim($_GET[$key] ?? $default));
    }
}
?>

<?php
function admin_prepare_captcha(string $key = 'admin_login'): array {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }

    $_SESSION['admin_captcha'][$key] = [
        'answer_hash' => password_hash($code, PASSWORD_DEFAULT),
        'created_at' => time(),
    ];

    return ['code' => $code];
}

function admin_verify_captcha(string $answer, string $key = 'admin_login'): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $captcha = $_SESSION['admin_captcha'][$key] ?? null;
    unset($_SESSION['admin_captcha'][$key]);

    if (!$captcha || !is_array($captcha)) {
        return false;
    }

    if ((int) ($captcha['created_at'] ?? 0) < time() - 600) {
        return false;
    }

    return password_verify(trim($answer), $captcha['answer_hash'] ?? '');
}
?>

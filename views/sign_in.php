<?php
// views/sign_in.php

if (!defined('MVC_RENDERING')) {
    require_once __DIR__ . '/../controllers/FrontController.php';
    FrontController::dispatchView('sign_in');
    return;
}

require_once __DIR__ . '/_page_helpers.php';

// Agar pehle se login hai toh seedha home bhejo
if (isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('views/home.php'));
    exit;
}

$error = $error ?? '';
$captcha = $captcha ?? ['code' => ''];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instagram</title>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Roboto', -apple-system, BlinkMacSystemFont, sans-serif;
}
body {
    background: #fafafa;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 20px;
}
.container {
    width: 100%;
    max-width: 350px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.login-box {
    background: #fff;
    border: 1px solid #dbdbdb;
    padding: 40px 40px 24px;
    text-align: center;
}
.logo-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    margin-bottom: 28px;
}
.insta-logo-svg {
    display: block;
}
.logo {
    font-size: 38px;
    font-weight: 700;
    font-family: 'Brush Script MT', cursive;
    display: block;
    color: #262626;
    line-height: 1;
}
.input-group {
    margin-bottom: 6px;
}
.input-group input {
    width: 100%;
    padding: 10px;
    border: 1px solid #dbdbdb;
    background: #fafafa;
    border-radius: 3px;
    font-size: 12px;
    color: #262626;
    outline: none;
    transition: border-color 0.2s;
}
.input-group input:focus {
    border-color: #a8a8a8;
    background: #fff;
}
.error-box {
    background: #fff1f2;
    border: 1px solid #fecdd3;
    border-radius: 4px;
    color: #ed4956;
    font-size: 12px;
    padding: 10px 12px;
    margin: 8px 0 4px;
    text-align: left;
}
.captcha-art {
    height: 56px;
    margin: 8px 0 6px;
    border: 1px solid #dbdbdb;
    border-radius: 3px;
    display: grid;
    place-items: center;
    background:
        radial-gradient(circle at 22% 50%, rgba(255, 109, 109, .72) 0 25%, transparent 26%),
        radial-gradient(circle at 38% 50%, rgba(135, 219, 110, .72) 0 25%, transparent 26%),
        radial-gradient(circle at 54% 50%, rgba(95, 156, 235, .64) 0 25%, transparent 26%),
        radial-gradient(circle at 70% 50%, rgba(170, 126, 230, .58) 0 25%, transparent 26%),
        #f8f8f8;
    color: #050505;
    font-size: 27px;
    font-weight: 700;
    letter-spacing: 5px;
    font-family: Consolas, 'Courier New', monospace;
    user-select: none;
}
.login-btn {
    width: 100%;
    border: none;
    background: #0095f6;
    color: #fff;
    padding: 9px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    margin-top: 10px;
    transition: opacity 0.2s;
}
.login-btn:hover { opacity: 0.85; }
.forgot {
    font-size: 12px;
    color: #00376b;
    text-decoration: none;
    display: block;
    margin-top: 14px;
}
.signup-box {
    background: #fff;
    border: 1px solid #dbdbdb;
    padding: 20px;
    text-align: center;
    font-size: 14px;
    color: #262626;
}
.signup-box a {
    color: #0095f6;
    font-weight: 600;
    text-decoration: none;
}
.get-app { text-align: center; margin-top: 8px; }
.get-app p { margin-bottom: 14px; font-size: 14px; color: #262626; }
.app-buttons { display: flex; justify-content: center; gap: 10px; }
.app-buttons img { height: 38px; cursor: pointer; }

@media (max-width: 450px) {
    body { background: #fff; }
    .login-box, .signup-box { border: none; }
}
</style>
</head>
<body>

<div class="container">

    <div class="login-box">

        <div class="logo-wrap">
            <svg class="insta-logo-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="52" height="52" aria-hidden="true">
                <defs>
                    <linearGradient id="ig-grad-signin" x1="0" x2="1" y1="1" y2="0">
                        <stop stop-color="#feda75"/>
                        <stop offset=".35" stop-color="#fa7e1e"/>
                        <stop offset=".62" stop-color="#d62976"/>
                        <stop offset="1" stop-color="#4f5bd5"/>
                    </linearGradient>
                </defs>
                <rect width="64" height="64" rx="17" fill="url(#ig-grad-signin)"/>
                <rect x="17" y="17" width="30" height="30" rx="9" fill="none" stroke="white" stroke-width="4"/>
                <circle cx="32" cy="32" r="7" fill="none" stroke="white" stroke-width="4"/>
                <circle cx="42" cy="22" r="3" fill="white"/>
            </svg>
            <span class="logo">Instagram</span>
        </div>

        <form method="POST" action="">
            <?= csrf_field() ?>

            <div class="input-group">
                <input
                    type="text"
                    name="username"
                    placeholder="Phone number, username, or email"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    autocomplete="username"
                    required>
            </div>

            <div class="input-group">
                <input
                    type="password"
                    name="password"
                    placeholder="Password"
                    autocomplete="current-password"
                    required>
            </div>

            <div class="captcha-art" aria-label="Captcha code">
                <?= htmlspecialchars($captcha['code'] ?? '') ?>
            </div>
            <div class="input-group">
                <input
                    type="text"
                    name="captcha_answer"
                    placeholder="Enter Captcha"
                    autocomplete="off"
                    required>
            </div>

            <?php if ($error !== ''): ?>
            <div class="error-box">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <button type="submit" class="login-btn">Log in</button>

        </form>

        <a href="<?= htmlspecialchars(app_url('views/forget.php')) ?>" class="forgot">Forgot password?</a>

    </div>

    <div class="signup-box">
        Don't have an account?
        <a href="<?= htmlspecialchars(app_url('views/sign_up.php')) ?>">Sign up</a>
    </div>

    <div class="get-app">
        <p>Get the app.</p>
        <div class="app-buttons">
            <img src="https://upload.wikimedia.org/wikipedia/commons/7/78/Google_Play_Store_badge_EN.svg" alt="Google Play">
            <img src="https://developer.apple.com/assets/elements/badges/download-on-the-app-store.svg" alt="App Store">
        </div>
    </div>

</div>

</body>
</html>

<?php
if (!defined('MVC_RENDERING')) {
    require_once __DIR__ . '/../controllers/FrontController.php';
    FrontController::dispatchView('sign_up');
    return;
}

require_once __DIR__ . '/_page_helpers.php';

$pageTitle = 'Instagram – Sign Up';
$error     = $error ?? '';
$email     = $email ?? '';
$full_name = $full_name ?? '';
$username  = $username ?? '';
$captcha   = $captcha ?? ['code' => ''];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('css/sign_up.css')) ?>">
    
</head>
<body class="d-flex flex-column align-items-center justify-content-center min-vh-100 gap-3 py-4">

<div class="card p-4 text-center bg-white">
    <div class="logo mb-1">
        <div class="logo-wrap">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="52" height="52" aria-hidden="true">
                <defs>
                    <linearGradient id="ig-grad-signup" x1="0" x2="1" y1="1" y2="0">
                        <stop stop-color="#feda75"/>
                        <stop offset=".35" stop-color="#fa7e1e"/>
                        <stop offset=".62" stop-color="#d62976"/>
                        <stop offset="1" stop-color="#4f5bd5"/>
                    </linearGradient>
                </defs>
                <rect width="64" height="64" rx="17" fill="url(#ig-grad-signup)"/>
                <rect x="17" y="17" width="30" height="30" rx="9" fill="none" stroke="white" stroke-width="4"/>
                <circle cx="32" cy="32" r="7" fill="none" stroke="white" stroke-width="4"/>
                <circle cx="42" cy="22" r="3" fill="white"/>
            </svg>
            Instagram
        </div>
    </div>
    <p class="fw-semibold text-muted mb-4" style="font-size:16px">Sign up to see photos and videos from your friends.</p>

    <?php if ($error): ?>
        <div class="alert alert-danger py-2 small mb-2"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="d-flex flex-column gap-2 text-start">
        <?= csrf_field() ?>
        <input class="insta-input" type="email"    name="email"     placeholder="Mobile number or email" value="<?= htmlspecialchars($email ?? '') ?>" required>
        <input class="insta-input" type="text"     name="full_name" placeholder="Full Name"              value="<?= htmlspecialchars($full_name ?? '') ?>" required>
        <input class="insta-input" type="text"     name="username"  placeholder="Username"               value="<?= htmlspecialchars($username ?? '') ?>" required>
        <input class="insta-input" type="password" name="password"  placeholder="Password (min 6 chars)" required>
        <div class="captcha-art" aria-label="Captcha code">
            <?= htmlspecialchars($captcha['code'] ?? '') ?>
        </div>
        <input class="insta-input text-center" type="text" name="captcha_answer" placeholder="Enter Captcha" autocomplete="off" required>

        <p class="text-muted text-center mt-2 mb-1" style="font-size:11px">
            By signing up, you agree to this demo app's account rules and data use for testing.
        </p>

        <button type="submit" class="btn-insta">Sign up</button>
    </form>
</div>

<div class="card p-3 text-center bg-white">
    <p class="mb-0 small">Have an account? <a href="<?= htmlspecialchars(app_url('views/sign_in.php')) ?>" class="fw-bold text-dark">Log in</a></p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>
</body>
</html>

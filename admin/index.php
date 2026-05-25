<?php
require_once __DIR__ . '/../views/_page_helpers.php';
app_start_session();

if (isset($_SESSION['admin_logged_in'])) {
    header('Location: ' . app_url('admin/dashboard.php'));
    exit;
}

require_once __DIR__ . '/models/AdminController.php';
require_once __DIR__ . '/admin_captcha.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $rateKey = app_rate_limit_key('admin_login', $_POST['username'] ?? '');
    if (app_rate_limited($rateKey, 6, 900)) {
        $error = 'Too many login attempts. Please try again after 15 minutes.';
    } elseif (!admin_verify_captcha($_POST['captcha_answer'] ?? '')) {
        $error = 'Captcha answer is incorrect.';
    } else {
        $admin = AdminController::getInstance();
        if ($admin->adminLogin($_POST['username'] ?? '', $_POST['password'] ?? '')) {
        app_rate_limit_clear($rateKey);
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $_POST['username'];
        header('Location: ' . app_url('admin/dashboard.php'));
        exit;
        }
        app_rate_limit_hit($rateKey);
        $error = 'Invalid username or password.';
    }
}
$captcha = admin_prepare_captcha();
$admin = AdminController::getInstance();
$showRegisterLink = !$admin->hasAnyAdmin() || !empty($_SESSION['admin_logged_in']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body{background:#1a1a2e;min-height:100vh;display:flex;align-items:center;justify-content:center}
        .login-card{background:#fff;border-radius:16px;padding:40px;width:100%;max-width:380px;box-shadow:0 20px 60px rgba(0,0,0,.3)}
        .brand{font-size:28px;font-weight:800;color:#1a1a2e;text-align:center;margin-bottom:6px}
        .captcha-art{height:56px;border:1px solid #dbdbdb;border-radius:8px;display:grid;place-items:center;background:radial-gradient(circle at 22% 50%,rgba(255,109,109,.72) 0 25%,transparent 26%),radial-gradient(circle at 38% 50%,rgba(135,219,110,.72) 0 25%,transparent 26%),radial-gradient(circle at 54% 50%,rgba(95,156,235,.64) 0 25%,transparent 26%),radial-gradient(circle at 70% 50%,rgba(170,126,230,.58) 0 25%,transparent 26%),#f8f8f8;color:#050505;font-size:27px;font-weight:700;letter-spacing:5px;font-family:Consolas,'Courier New',monospace;user-select:none}
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand">Admin Panel</div>
        <p class="text-muted text-center mb-4">Login to continue</p>
        <?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="captcha-art mb-3" aria-label="Captcha code"><?= htmlspecialchars($captcha['code'] ?? '') ?></div>
            <div class="mb-3">
                <label class="form-label">Enter Captcha</label>
                <input type="text" name="captcha_answer" class="form-control" autocomplete="off" required>
            </div>
            <button type="submit" class="btn btn-dark w-100">Login</button>
        </form>
        <?php if ($showRegisterLink): ?>
            <p class="text-center small mt-3 mb-0">New admin? <a href="<?= htmlspecialchars(app_url('admin/register.php')) ?>">Create account</a></p>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
require_once __DIR__ . '/../../views/_page_helpers.php';
app_start_session();
require_once __DIR__ . '/../models/AdminController.php';

$error = '';
$success = '';
$admin = AdminController::getInstance();
$hasAnyAdmin = $admin->hasAnyAdmin();
$registrationToken = getenv('ADMIN_REGISTRATION_TOKEN') ?: '';

if ($hasAnyAdmin && empty($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo 'Admin registration is locked. Login as an existing admin to create another admin.';
    exit;
}

if (!$hasAnyAdmin && empty($_SESSION['admin_logged_in'])) {
    $submittedToken = trim($_POST['reg_token'] ?? $_GET['token'] ?? '');
    if ($registrationToken === '' || !hash_equals($registrationToken, $submittedToken)) {
        http_response_code(403);
        echo 'Admin registration requires a valid setup token.';
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');

    if (!$username || !$email || !$password || !$fullName) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        if ($admin->adminExists($username, $email)) {
            $error = 'Admin username or email already exists.';
        } elseif ($admin->createAdmin($username, $email, $password, $fullName)) {
            $success = 'Admin created successfully. You can login now.';
            $username = $email = $fullName = '';
        } else {
            $error = 'Admin signup failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Signup</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background: #1a1a2e; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .login-card { background: #fff; border-radius: 16px; padding: 36px; width: 100%; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .brand { font-size: 28px; font-weight: 800; color: #1a1a2e; text-align: center; margin-bottom: 6px; }
        .brand span { color: #0095f6; }
        .subtitle { text-align: center; color: #737373; font-size: 14px; margin-bottom: 24px; }
        .form-control { border-radius: 8px; padding: 11px 14px; border: 1px solid #dbdbdb; font-size: 14px; }
        .form-control:focus { border-color: #0095f6; box-shadow: 0 0 0 3px rgba(0,149,246,0.1); }
        .btn-login { background: #0095f6; color: #fff; border: none; border-radius: 8px; padding: 12px; width: 100%; font-weight: 700; font-size: 15px; }
        .btn-login:hover { background: #0081d6; }
        label { font-size: 13px; font-weight: 600; color: #262626; margin-bottom: 6px; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="brand">Insta <span>Admin</span></div>
    <p class="subtitle">Create a new admin account</p>

    <?php if ($error): ?>
        <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <?php if (!$hasAnyAdmin): ?>
            <input type="hidden" name="reg_token" value="<?= htmlspecialchars($registrationToken) ?>">
        <?php endif; ?>
        <div class="mb-3">
            <label>Full Name</label>
            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($fullName ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label>Username</label>
            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($username ?? '') ?>" required>
        </div>
        <div class="mb-4">
            <label>Password</label>
            <input type="password" name="password" class="form-control" placeholder="Minimum 8 characters" required>
        </div>
        <button type="submit" class="btn-login">Create Admin</button>
    </form>

    <p class="text-center text-muted mt-3 mb-0" style="font-size:13px">
        Already have admin account? <a href="<?= htmlspecialchars(app_url('admin/index.php')) ?>" class="fw-bold text-dark">Login</a>
    </p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>
</body>
</html>

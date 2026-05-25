<?php
require_once __DIR__ . '/_page_helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mailing.php';

app_start_session();

$pageTitle = 'Instagram - Forgot Password';
$error = '';
$success = '';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$db = Database::getInstance()->getConnection();

foreach ([
    "ALTER TABLE users ADD COLUMN reset_token VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN reset_expiry DATETIME DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN phone VARCHAR(30) DEFAULT NULL",
] as $alterSql) {
    try {
        $db->exec($alterSql);
    } catch (PDOException $e) {
        // Column already exists on normal installs.
    }
}

$absoluteUrl = function (string $path): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . app_url($path);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token !== '') {
    require_csrf();
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expiry > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = 'Reset link expired or invalid.';
        } else {
            $update = $db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?");
            $update->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
            $success = 'Your new password is set. You can now sign in.';
            $token = '';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $identifier = trim($_POST['identifier'] ?? '');

    if ($identifier === '') {
        $error = 'Please enter your email, phone, or username.';
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? OR username = ? OR phone = ? LIMIT 1");
        $stmt->execute([$identifier, $identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = 'No account found with this detail.';
        } else {
            $resetToken = bin2hex(random_bytes(32));
            $update = $db->prepare("UPDATE users SET reset_token = ?, reset_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?");
            $update->execute([$resetToken, (int) $user['id']]);

            $resetUrl = $absoluteUrl('views/forget.php?token=' . urlencode($resetToken));
            $safeName = htmlspecialchars($user['full_name'] ?: $user['username'], ENT_QUOTES, 'UTF-8');
            $body = "
                <div style='font-family:Arial,sans-serif;line-height:1.5;color:#222'>
                    <h2 style='margin:0 0 12px'>Reset your password</h2>
                    <p>Hi {$safeName},</p>
                    <p>Click the link below to reset your Instagram Clone password. This link expires in 1 hour.</p>
                    <p><a href='{$resetUrl}'>Reset password</a></p>
                </div>
            ";

            if (sendMail($user['email'], $user['full_name'] ?: $user['username'], 'Reset your password', $body)) {
                $success = 'Password reset link has been sent to your email address.';
            } else {
                $error = 'Email could not be sent. Please check SMTP settings.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background:#fafafa; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; }
        .card { border:1px solid #dbdbdb; max-width:350px; width:100%; }
        .insta-input { background:#fafafa; border:1px solid #dbdbdb; border-radius:4px; padding:10px 12px; font-size:13px; width:100%; }
        .insta-input:focus { outline:none; border-color:#a8a8a8; background:#fff; }
        .btn-insta { background:#0095f6; color:#fff; border:none; border-radius:8px; padding:10px; width:100%; font-weight:600; text-decoration:none; }
    </style>
</head>
<body class="d-flex flex-column align-items-center justify-content-center min-vh-100 gap-3">

<div class="card p-4 text-center bg-white">
    <div class="mb-3">
        <div class="rounded-circle border border-2 border-dark d-inline-flex align-items-center justify-content-center mb-2" style="width:72px;height:72px;font-size:28px">
            <i class="fa-solid fa-lock"></i>
        </div>
        <h6 class="fw-bold"><?= $token !== '' ? 'Create new password' : 'Trouble logging in?' ?></h6>
        <p class="text-muted small">
            <?= $token !== '' ? 'Enter your new password below.' : "Enter your email, phone, or username and we'll send you a reset link." ?>
        </p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($token !== ''): ?>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <input class="insta-input mb-2" type="password" name="password" placeholder="New password" required>
            <input class="insta-input mb-3" type="password" name="confirm_password" placeholder="Confirm password" required>
            <button type="submit" class="btn-insta mb-3">Reset password</button>
        </form>
    <?php else: ?>
        <form method="POST">
            <?= csrf_field() ?>
            <input class="insta-input mb-3" type="text" name="identifier" placeholder="Email, Phone, or Username" required>
            <button type="submit" class="btn-insta mb-3">Send reset link</button>
        </form>
    <?php endif; ?>

    <a href="<?= htmlspecialchars(app_url('views/sign_up.php')) ?>" class="text-dark small">Create new account</a>
</div>

<div class="card p-3 text-center bg-white">
    <p class="mb-0 small">Back to <a href="<?= htmlspecialchars(app_url('views/sign_in.php')) ?>" class="fw-bold text-dark">log in</a></p>
</div>

</body>
</html>

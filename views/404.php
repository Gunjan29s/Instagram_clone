<?php require_once __DIR__ . '/_page_helpers.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Page Not Found') ?></title>
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#fafafa;color:#262626;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif}
        .box{text-align:center;border:1px solid #dbdbdb;background:#fff;border-radius:12px;padding:36px;max-width:420px}
        h1{font-size:56px;margin:0 0 8px}
        p{color:#737373;margin:0 0 20px}
        a{display:inline-block;background:#0095f6;color:#fff;text-decoration:none;border-radius:8px;padding:10px 18px;font-weight:700}
    </style>
</head>
<body>
    <main class="box">
        <h1>404</h1>
        <p>This page is not available.</p>
        <a href="<?= htmlspecialchars(app_url('views/home.php')) ?>">Go Home</a>
    </main>
</body>
</html>

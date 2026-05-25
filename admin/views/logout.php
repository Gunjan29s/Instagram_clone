<?php
require_once __DIR__ . '/../../views/_page_helpers.php';
app_start_session();

unset($_SESSION['admin_logged_in'], $_SESSION['admin_username'], $_SESSION['admin_captcha']);
session_regenerate_id(true);

header('Location: ' . app_url('admin/index.php'));
exit;
?>

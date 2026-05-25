<?php
require_once __DIR__ . '/controllers/FrontController.php';

app_start_session();

FrontController::dispatchCurrent();
?>

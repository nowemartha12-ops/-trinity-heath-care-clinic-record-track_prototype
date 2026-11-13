<?php
require_once __DIR__ . '/includes/auth.php';
if (!empty($_SESSION['staff_id'])) {
    log_action($pdo, 'Logged out');
}
session_destroy();
header('Location: login.php');
exit;
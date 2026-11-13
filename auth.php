<?php
// Login and role checks. Every page includes this file first.
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

function require_login() {
    if (empty($_SESSION['staff_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Does the logged-in user have one of these roles? (management can do everything)
function can($roles) {
    if (empty($_SESSION['role'])) {
        return false;
    }
    return $_SESSION['role'] === 'management' || in_array($_SESSION['role'], $roles);
}

function require_role($roles) {
    require_login();
    if (!can($roles)) {
        http_response_code(403);
        die('Access denied. Your role cannot open this page. <a href="index.php">Go back</a>');
    }
}

// Show a message once on the next page
function flash($msg = null) {
    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        return '';
    }
    if (!empty($_SESSION['flash'])) {
        $m = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $m;
    }
    return '';
}
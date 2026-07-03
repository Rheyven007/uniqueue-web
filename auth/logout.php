<?php
// auth/logout.php — Destroy session and redirect to login

require_once __DIR__ . '/session.php';

// Determine which role was logged in so we can show the right login tab
$role = 'student';
if (is_admin_logged_in()) {
    $role = 'admin';
}

// Unset all session data
$_SESSION = [];

// Delete the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

redirect('/auth/login.php?role=' . $role . '&msg=logged_out');

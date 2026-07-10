<?php
// auth/logout.php — Destroy session and redirect to login

require_once __DIR__ . '/session.php';


/*
|--------------------------------------------------------------------------
| Detect logged-in role
|--------------------------------------------------------------------------
*/

$role = 'student';


if (is_staff_logged_in()) {

    $role = 'staff';

} elseif (is_admin_logged_in()) {

    $role = 'admin';

}


/*
|--------------------------------------------------------------------------
| Clear session data
|--------------------------------------------------------------------------
*/

$_SESSION = [];


/*
|--------------------------------------------------------------------------
| Remove session cookie
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Destroy session
|--------------------------------------------------------------------------
*/

session_destroy();


/*
|--------------------------------------------------------------------------
| Redirect back to login
|--------------------------------------------------------------------------
*/

redirect('/auth/login.php?role=' . $role . '&msg=logged_out');
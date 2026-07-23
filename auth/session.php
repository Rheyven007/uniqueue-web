<?php
// auth/session.php — Session start & auth guard

if (session_status() === PHP_SESSION_NONE) {

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}


require_once __DIR__ . '/../includes/functions.php';


/*
|--------------------------------------------------------------------------
| Roles
|--------------------------------------------------------------------------
*/

if (!defined('ROLE_STUDENT')) {
    define('ROLE_STUDENT', 'student');
}

if (!defined('ROLE_ADMIN')) {
    define('ROLE_ADMIN', 'admin');
}

if (!defined('ROLE_STAFF')) {
    define('ROLE_STAFF', 'staff');
}



/*
|--------------------------------------------------------------------------
| Guards
|--------------------------------------------------------------------------
*/


function require_student(): void
{
    if (!is_student_logged_in()) {

        redirect('/auth/login.php?role=student');

    }
}



function require_admin(): void
{
    if (!is_admin_logged_in()) {

        redirect('/auth/login.php?role=admin');

    }
}



function require_staff(): void
{
    if (!is_staff_logged_in()) {

        redirect('/auth/login.php?role=admin');

    }
}



/*
|--------------------------------------------------------------------------
| Admin Roles
|--------------------------------------------------------------------------
*/


function require_super_admin(): void
{
    require_admin();


    if (empty($_SESSION['is_super_admin'])) {

        redirect('/admin/queue/office-dashboard.php');

    }
}



function require_office_admin(): void
{
    require_admin();


    if (!empty($_SESSION['is_super_admin'])) {

        redirect('/admin/dashboard.php');

    }
}



/*
|--------------------------------------------------------------------------
| Redirect authenticated users
|--------------------------------------------------------------------------
*/


function redirect_if_authenticated(): void
{

    if (is_student_logged_in()) {

        redirect('/student/student-dashboard.php');

    }


    if (is_admin_logged_in()) {


        if (!empty($_SESSION['is_super_admin'])) {

            redirect('/admin/dashboard.php');

        }


        redirect('/admin/queue/office-dashboard.php');

    }



    if (is_staff_logged_in()) {

        redirect('/admin/staff/staff-dashboard.php');

    }

}
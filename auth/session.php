<?php
// auth/session.php — Session start & auth guard
// Include this at the top of every protected page.

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,   // Set to true in production (HTTPS)
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/../includes/functions.php';

// ── Role constants ────────────────────────────────────────────────────────────
define('ROLE_STUDENT', 'student');
define('ROLE_ADMIN',   'admin');

/**
 * Require the visitor to be a logged-in student.
 * If not, redirect to the login page.
 */
function require_student(): void {
    if (!is_student_logged_in()) {
        redirect('/auth/login.php?role=student');
    }
}

/**
 * Require the visitor to be a logged-in admin.
 * If not, redirect to the login page.
 */
function require_admin(): void {
    if (!is_admin_logged_in()) {
        redirect('/auth/login.php?role=admin');
    }
}

/**
 * Require the logged-in admin to be a super admin.
 */
function require_super_admin(): void {
    require_admin();
    if (empty($_SESSION['is_super_admin'])) {
        redirect('/admin/queue/office-dashboard.php');
    }
}

/**
 * Require the logged-in admin to be a regular office admin (not super).
 */
function require_office_admin(): void {
    require_admin();
    if (!empty($_SESSION['is_super_admin'])) {
        redirect('/admin/dashboard.php');
    }
}

/**
 * If someone is already authenticated, bounce them away from auth pages.
 */
function redirect_if_authenticated(): void {
    if (is_student_logged_in()) {
        redirect('/student/dashboard.php');
    }
    if (is_admin_logged_in()) {
        if (!empty($_SESSION['is_super_admin'])) {
            redirect('/admin/dashboard.php');
        } else {
            redirect('/admin/queue/office-dashboard.php');
        }
    }
}

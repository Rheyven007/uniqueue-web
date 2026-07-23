<?php
/**
 * index.php — Entry point / Login redirect
 * This file handles the initial landing logic for the application.
 */

require_once __DIR__ . '/auth/session.php';

// If a user is already logged in (Student or Admin), redirect them to their respective dashboard.
if (is_admin_logged_in()) {
    if (!empty($_SESSION['is_super_admin'])) redirect('/admin/dashboard.php');
    else redirect('/admin/queue/office-dashboard.php');
}
if (is_student_logged_in()) redirect('/student/student-dashboard.php');

// If no session exists, default to the student login page.
redirect('/auth/login.php');
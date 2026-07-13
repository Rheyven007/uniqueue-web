<?php
// auth/login.php — Login page (Student / Staff / Admin)

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../includes/db.php';

redirect_if_authenticated();

$error   = '';
$role    = get_param('role', 'student');
$success = get_param('msg') === 'logged_out' ? 'You have been logged out.' : '';

if (is_post()) {

    if (!validate_csrf_token()) {
       $error = 'Invalid request. Please try again.';
    } elseif ($role === 'student') {

        // ── STUDENT LOGIN ───────────────────────────────────────────────

        $identifier = post('identifier');
        $password   = post('password');

        if ($identifier === '' || $password === '') {

            $error = 'Please enter your SR-Code and password.';

        } else {

            $stmt = $pdo->prepare(
                "SELECT 
                    id,
                    first_name,
                    last_name,
                    sr_code,
                    password
                 FROM students
                 WHERE sr_code = ?
                 LIMIT 1"
            );

            $stmt->execute([$identifier]);
            $student = $stmt->fetch();

            if ($student && password_verify($password, $student['password'])) {

                session_regenerate_id(true);

                $_SESSION['student_id']   = $student['id'];
                $_SESSION['student_name'] =
                    $student['first_name'] . ' ' . $student['last_name'];
                $_SESSION['sr_code'] = $student['sr_code'];
                redirect('/student/dashboard.php');
            } else {

                $error = 'Invalid SR-Code or password.';
            }

        }

    } else {

        // ── STAFF / ADMIN LOGIN ─────────────────────────────────────────

        $username = post('identifier');
        $password = post('password');
        if ($username === '' || $password === '') {
         $error = 'Please enter your username and password.';
        } else {

            /*
            |--------------------------------------------------------------------------
            | STAFF LOGIN
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                "SELECT
                    id,
                    name,
                    username,
                    password,
                    office_id,
                    window_id,
                    status
                 FROM staff
                 WHERE username = ?
                 LIMIT 1"
            );

            $stmt->execute([$username]);
            $staff = $stmt->fetch();

            if ($staff && password_verify($password, $staff['password'])) {
                if ($staff['status'] !== 'active') {
                    $error = 'Your staff account is inactive.';
                } else {
                   session_regenerate_id(true);

                    $_SESSION['staff_id']   = $staff['id'];
                    $_SESSION['staff_name'] = $staff['name'];
                    $_SESSION['office_id']  = $staff['office_id'];
                    $_SESSION['window_id']  = $staff['window_id'];

                    redirect('/admin/staff/staff-dashboard.php');

                }

            } else {

                /*
                |--------------------------------------------------------------------------
                | ADMIN LOGIN
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare(
                    "SELECT
                        id,
                        username,
                        password,
                        office_id,
                        is_super_admin
                     FROM admin_users
                     WHERE username = ?
                     LIMIT 1"
                );

                $stmt->execute([$username]);
                $admin = $stmt->fetch();

                if ($admin && password_verify($password, $admin['password'])) {

                    session_regenerate_id(true);
                    $_SESSION['admin_id'] =
                        $admin['id'];

                    $_SESSION['admin_username'] =
                        $admin['username'];

                    $_SESSION['office_id'] =
                        $admin['office_id'];

                    $_SESSION['is_super_admin'] =
                        (bool)$admin['is_super_admin'];

                    if ($_SESSION['is_super_admin']) {
                        redirect('/admin/dashboard.php');
                    } else {

                        redirect('/admin/queue/office-dashboard.php');
                    }

                } else {

                    $error = 'Invalid username or password.';

                }
            }
        }
    }
}

$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Uniqueue — Login</title>
<link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body class="auth-body" data-role="<?= e($role) ?>">

<div class="auth-ring auth-ring--1"></div>
<div class="auth-ring auth-ring--2"></div>
<div class="auth-card-wrap">
<div class="auth-card">
<div class="auth-brand-row">

        <img 
        src="/assets/img/logo.png"
        alt="School Logo"
        class="auth-brand-row__logo">

        <div class="auth-brand-row__text">
        <span class="auth-brand-row__eyebrow">
        Welcome to
        </span>
        <span class="auth-brand-row__name">
        Uniqueue
        </span>

        </div>

        </div>

        <div class="auth-tabs">
        <a href="?role=student"
        class="auth-tab <?= $role === 'student' ? 'auth-tab--active' : '' ?>">
        Student
        </a>
        <a href="?role=admin"
        class="auth-tab <?= $role === 'admin' ? 'auth-tab--active' : '' ?>">
        Staff / Admin
        </a>

        </div>

        <h2 class="auth-card__heading">

        <?= $role === 'student' ? 'Student Login' : 'Staff Login' ?>

        </h2>

        <?php if ($success): ?>

        <div class="alert alert--success">
        <?= e($success) ?>
        </div>

        <?php endif; ?>

        <?php if ($error): ?>

        <div class="alert alert--error">
        <?= e($error) ?>
        </div>

        <?php endif; ?>

        <form method="POST" action="?role=<?= e($role) ?>">


        <input type="hidden"
        name="csrf_token"
        value="<?= e($csrf) ?>">

        <div class="form-group">

        <label class="form-label">

        <?= $role === 'student' ? 'SR-Code' : 'Username' ?>

        </label>

        <input

        type="text"

        name="identifier"

        class="form-input"

        placeholder="<?= $role === 'student'
        ? 'e.g. 22-12345'
        : 'username' ?>"

        value="<?= e(post('identifier')) ?>"

        required

        >
        </div>

        <div class="form-group">

        <label class="form-label">
        Password
        </label>
        <input

        type="password"

        name="password"

        class="form-input"

        placeholder="••••••••"

        required

        >
        </div>

        <button type="submit"
        class="btn btn--primary btn--full">

        Sign In

        </button>
        </form>

        </div>
        </div>

        <script src="/assets/js/auth.js"></script>

        </body>

        </html>
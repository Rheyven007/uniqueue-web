<?php
// auth/login.php — Login page (SR-Code or admin username)

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../includes/db.php';

redirect_if_authenticated();

$error   = '';
$role    = get_param('role', 'student'); // 'student' or 'admin'
$success = get_param('msg') === 'logged_out' ? 'You have been logged out.' : '';

if (is_post()) {

    if (!validate_csrf_token()) {
        $error = 'Invalid request. Please try again.';

    } elseif ($role === 'student') {
        // ── Student login ─────────────────────────────────────────────────────
        $identifier = post('identifier');  // SR-Code or email
        $password   = post('password');

        if ($identifier === '' || $password === '') {
            $error = 'Please enter your SR-Code and password.';
        } else {
            $stmt = $pdo->prepare(
                "SELECT id, first_name, last_name, sr_code, password
                 FROM students
                 WHERE sr_code = ? OR sr_code = ?
                 LIMIT 1"
            );
            // Accept both bare SR-Code and any format
            $stmt->execute([$identifier, $identifier]);
            $student = $stmt->fetch();

            if ($student && password_verify($password, $student['password'])) {
                session_regenerate_id(true);
                $_SESSION['student_id']   = $student['id'];
                $_SESSION['student_name'] = $student['first_name'] . ' ' . $student['last_name'];
                $_SESSION['sr_code']      = $student['sr_code'];
                redirect('/student/dashboard.php');
            } else {
                $error = 'Invalid SR-Code or password.';
            }
        }

    } else {
        // ── Admin login ───────────────────────────────────────────────────────
        $username = post('identifier');
        $password = post('password');

        if ($username === '' || $password === '') {
            $error = 'Please enter your username and password.';
        } else {
            $stmt = $pdo->prepare(
                "SELECT id, username, password, office_id, is_super_admin
                 FROM admin_users
                 WHERE username = ?
                 LIMIT 1"
            );
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id']       = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['office_id']      = $admin['office_id'];
                $_SESSION['is_super_admin'] = (bool)$admin['is_super_admin'];
                if ($_SESSION['is_super_admin']) redirect('/admin/dashboard.php');
                else redirect('/admin/queue/office-dashboard.php');
            } else {
                $error = 'Invalid username or password.';
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

    <!-- Decorative backdrop: rings + wavy ribbon shapes -->
    <div class="auth-ring auth-ring--1" aria-hidden="true"></div>
    <div class="auth-ring auth-ring--2" aria-hidden="true"></div>

    <!-- Centered auth card -->
    <div class="auth-card-wrap">
        <div class="auth-card">

            <div class="auth-brand-row">
                <img src="/assets/img/logo.png" alt="School Logo" class="auth-brand-row__logo">
                <div class="auth-brand-row__text">
                    <span class="auth-brand-row__eyebrow">Welcome to</span>
                    <span class="auth-brand-row__name">Uniqueue</span>
                </div>
            </div>

            <!-- Role switcher tabs -->
            <div class="auth-tabs" role="tablist" aria-label="Login type">
                <a href="?role=student"
                   class="auth-tab <?= $role === 'student' ? 'auth-tab--active' : '' ?>"
                   role="tab" aria-selected="<?= $role === 'student' ? 'true' : 'false' ?>">
                    Student
                </a>
                <a href="?role=admin"
                   class="auth-tab <?= $role === 'admin' ? 'auth-tab--active' : '' ?>"
                   role="tab" aria-selected="<?= $role === 'admin' ? 'true' : 'false' ?>">
                    Staff / Admin
                </a>
            </div>

            <h2 class="auth-card__heading">
                <?= $role === 'student' ? 'Student Login' : 'Staff Login' ?>
            </h2>

            <?php if ($success !== ''): ?>
                <div class="alert alert--success" role="alert"><?= e($success) ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert--error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="?role=<?= e($role) ?>" novalidate id="login-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

                <div class="form-group">
                    <label class="form-label" for="identifier">
                        <?= $role === 'student' ? 'SR-Code' : 'Username' ?>
                    </label>
                    <input
                        type="text"
                        id="identifier"
                        name="identifier"
                        class="form-input"
                        placeholder="<?= $role === 'student' ? 'e.g. 22-12345' : 'admin.username' ?>"
                        value="<?= e(post('identifier')) ?>"
                        autocomplete="username"
                        required
                        autofocus>
                </div>

                <div class="form-group">
                    <div class="form-label-row">
                        <label class="form-label" for="password">Password</label>
                        <a href="#" class="forgot-link">Forgot Password?</a>
                    </div>
                    <div class="input-icon-wrap">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            required>
                        <button type="button" class="toggle-password" aria-label="Show password" data-target="password">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn--primary btn--full" id="login-btn">
                    <span class="btn__text">Sign In</span>
                    <span class="btn__spinner" aria-hidden="true"></span>
                </button>
            </form>

            <?php if ($role === 'student'): ?>
                <p class="auth-card__help">
                    Don't have an account? <a href="#">Contact the Registrar's Office</a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <script src="/assets/js/auth.js"></script>
</body>
</html>
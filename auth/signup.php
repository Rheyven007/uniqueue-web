<?php
// auth/signup.php — Student self-registration

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../includes/db.php';

redirect_if_authenticated();

$errors = [];
$old = [
    'first_name' => '',
    'last_name'  => '',
    'sr_code'    => '',
    'college_id' => '',
    'program_id' => '',
    'year_level' => '',
];

// ── Load colleges & programs for the selects ────────────────────────────────
$colleges = $pdo->query(
    "SELECT id, name, abbreviation FROM colleges WHERE is_active = 1 ORDER BY name"
)->fetchAll();

$programs = $pdo->query(
    "SELECT id, college_id, name, abbreviation FROM programs WHERE is_active = 1 ORDER BY name"
)->fetchAll();

if (is_post()) {

    if (!validate_csrf_token()) {
        $errors[] = 'Invalid request. Please try again.';
    } else {

        $first_name = trim(post('first_name'));
        $last_name  = trim(post('last_name'));
        $sr_code    = trim(post('sr_code'));
        $college_id = post('college_id');
        $program_id = post('program_id');
        $year_level = post('year_level');
        $password   = post('password');
        $confirm    = post('confirm_password');

        $old = [
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'sr_code'    => $sr_code,
            'college_id' => $college_id,
            'program_id' => $program_id,
            'year_level' => $year_level,
        ];

        // ── Required fields ──────────────────────────────────────────────
        if ($first_name === '')            $errors[] = 'First name is required.';
        if ($last_name === '')             $errors[] = 'Last name is required.';
        if ($sr_code === '')               $errors[] = 'SR-Code is required.';
        if ($college_id === '' || $college_id === null) $errors[] = 'Please select your college.';
        if ($program_id === '' || $program_id === null) $errors[] = 'Please select your program.';
        if ($year_level === '' || $year_level === null) $errors[] = 'Please select your year level.';
        if ($password === '')              $errors[] = 'Password is required.';
        if ($confirm === '')               $errors[] = 'Please confirm your password.';

        // ── Name format ───────────────────────────────────────────────────
        if ($first_name !== '' && !preg_match("/^[a-zA-Z\x{00f1}\x{00d1}' -]{1,50}$/u", $first_name)) {
            $errors[] = 'First name may only contain letters, spaces, and hyphens.';
        }
        if ($last_name !== '' && !preg_match("/^[a-zA-Z\x{00f1}\x{00d1}' -]{1,50}$/u", $last_name)) {
            $errors[] = 'Last name may only contain letters, spaces, and hyphens.';
        }

        // ── SR-Code format: YY-NNNNN ────────────────────────────────────────
        if ($sr_code !== '' && !preg_match('/^\d{2}-\d{5}$/', $sr_code)) {
            $errors[] = 'SR-Code must be in the format YY-NNNNN (e.g. 23-12345).';
        }

        // ── College / Program validity ──────────────────────────────────
        $collegeIds = array_column($colleges, 'id');
        $programIds = array_column($programs, 'id');

        if ($college_id !== '' && $college_id !== null && !in_array((int)$college_id, $collegeIds, true)) {
            $errors[] = 'Please select a valid college.';
        }
        if ($program_id !== '' && $program_id !== null && !in_array((int)$program_id, $programIds, true)) {
            $errors[] = 'Please select a valid program.';
        }
        // Program must belong to the chosen college
        if (empty($errors) && $college_id !== '' && $program_id !== '') {
            $match = array_filter($programs, function ($p) use ($program_id, $college_id) {
                return (int)$p['id'] === (int)$program_id && (int)$p['college_id'] === (int)$college_id;
            });
            if (empty($match)) {
                $errors[] = 'The selected program does not belong to the selected college.';
            }
        }

        // ── Year level ───────────────────────────────────────────────────
        if ($year_level !== '' && !in_array((int)$year_level, [1, 2, 3, 4, 5], true)) {
            $errors[] = 'Please select a valid year level.';
        }

        // ── Password strength (server-side mirror of the live JS meter) ──
        if ($password !== '') {
            if (strlen($password) < 8) {
                $errors[] = 'Password must be at least 8 characters long.';
            }
            if (!preg_match('/[a-z]/', $password)) {
                $errors[] = 'Password must include at least one lowercase letter.';
            }
            if (!preg_match('/[A-Z]/', $password)) {
                $errors[] = 'Password must include at least one uppercase letter.';
            }
            if (!preg_match('/[0-9]/', $password)) {
                $errors[] = 'Password must include at least one number.';
            }
            if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
                $errors[] = 'Password must include at least one special character.';
            }
        }

        if ($password !== '' && $confirm !== '' && $password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        // ── SR-Code uniqueness ───────────────────────────────────────────
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM students WHERE sr_code = ? LIMIT 1");
            $stmt->execute([$sr_code]);
            if ($stmt->fetch()) {
                $errors[] = 'An account with this SR-Code already exists.';
            }
        }

        // ── Create account ───────────────────────────────────────────────
        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                "INSERT INTO students (first_name, last_name, sr_code, college_id, program_id, year_level, password)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $first_name,
                $last_name,
                $sr_code,
                (int)$college_id,
                (int)$program_id,
                (int)$year_level,
                $hash,
            ]);

            redirect('/auth/login.php?msg=account_created');
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
    <title>Uniqueue — Student Sign Up</title>
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body class="auth-body" data-role="student">

    <div class="auth-split auth-split--reverse">

        <!-- Left brand panel -->
        <div class="auth-brand">
            <div class="auth-brand__inner">
                <img src="/assets/img/logo.png" alt="School Logo" class="auth-brand__logo">
                <h1 class="auth-brand__title">Uniqueue</h1>
                <p class="auth-brand__tagline">Queue smarter. Get served faster.</p>

                <div class="auth-brand__stats">
                    <div class="stat-chip">
                        <span class="stat-chip__label">Walk-in &amp; Appointment</span>
                    </div>
                    <div class="stat-chip">
                        <span class="stat-chip__label">Real-time Updates</span>
                    </div>
                    <div class="stat-chip">
                        <span class="stat-chip__label">Smart Assignment</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right form panel -->
        <div class="auth-form-panel">
            <div class="auth-card auth-card--wide">

                <h2 class="auth-card__heading">Create Student Account</h2>
                <p class="auth-card__subheading">Fill in your details to register for Uniqueue.</p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert--error" role="alert">
                        <ul class="alert__list">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/auth/signup.php" novalidate id="signup-form">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="first_name">First Name</label>
                            <input
                                type="text"
                                id="first_name"
                                name="first_name"
                                class="form-input"
                                placeholder="Juan"
                                value="<?= e($old['first_name']) ?>"
                                autocomplete="given-name"
                                required
                                autofocus>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="last_name">Last Name</label>
                            <input
                                type="text"
                                id="last_name"
                                name="last_name"
                                class="form-input"
                                placeholder="Dela Cruz"
                                value="<?= e($old['last_name']) ?>"
                                autocomplete="family-name"
                                required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="sr_code">SR-Code</label>
                        <div class="input-icon-wrap">
                            <input
                                type="text"
                                id="sr_code"
                                name="sr_code"
                                class="form-input"
                                placeholder="e.g. 23-12345"
                                value="<?= e($old['sr_code']) ?>"
                                autocomplete="off"
                                maxlength="8"
                                required>
                        </div>
                        <span class="field-hint" id="sr_code-hint">Format: YY-NNNNN</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="college_id">College</label>
                            <select id="college_id" name="college_id" class="form-input form-select" required>
                                <option value="" disabled <?= $old['college_id'] === '' ? 'selected' : '' ?>>Select college</option>
                                <?php foreach ($colleges as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= (string)$old['college_id'] === (string)$c['id'] ? 'selected' : '' ?>>
                                        <?= e($c['abbreviation']) ?> — <?= e($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="program_id">Program</label>
                            <select id="program_id" name="program_id" class="form-input form-select" required disabled>
                                <option value="" selected>Select college first</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="year_level">Year Level</label>
                        <select id="year_level" name="year_level" class="form-input form-select" required>
                            <option value="" disabled <?= $old['year_level'] === '' ? 'selected' : '' ?>>Select year level</option>
                            <?php for ($y = 1; $y <= 5; $y++): ?>
                                <option value="<?= $y ?>" <?= (string)$old['year_level'] === (string)$y ? 'selected' : '' ?>>
                                    <?= $y ?><?= $y === 1 ? 'st' : ($y === 2 ? 'nd' : ($y === 3 ? 'rd' : 'th')) ?> Year
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-icon-wrap">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-input"
                                placeholder="••••••••"
                                autocomplete="new-password"
                                required>
                            <button type="button" class="toggle-password" aria-label="Show password" data-target="password">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Live strength meter -->
                        <div class="pw-strength" id="pw-strength" aria-hidden="true">
                            <div class="pw-strength__bar">
                                <span class="pw-strength__fill" id="pw-strength-fill"></span>
                            </div>
                            <span class="pw-strength__label" id="pw-strength-label">&nbsp;</span>
                        </div>

                        <ul class="pw-requirements" id="pw-requirements">
                            <li data-rule="length">At least 8 characters</li>
                            <li data-rule="lower">One lowercase letter</li>
                            <li data-rule="upper">One uppercase letter</li>
                            <li data-rule="number">One number</li>
                            <li data-rule="special">One special character</li>
                        </ul>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Password</label>
                        <div class="input-icon-wrap">
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                class="form-input"
                                placeholder="••••••••"
                                autocomplete="new-password"
                                required>
                            <button type="button" class="toggle-password" aria-label="Show password" data-target="confirm_password">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                        <span class="field-match" id="confirm-match" aria-live="polite">&nbsp;</span>
                    </div>

                    <button type="submit" class="btn btn--primary btn--full" id="signup-btn">
                        <span class="btn__text">Create Account</span>
                        <span class="btn__spinner" aria-hidden="true"></span>
                    </button>
                </form>

                <p class="auth-card__help">
                    Already have an account? <a href="/auth/login.php">Log in</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Program list grouped by college, for client-side cascading select -->
    <script>
        window.__PROGRAMS__ = <?= json_encode(array_map(function ($p) {
            return [
                'id'         => (int)$p['id'],
                'college_id' => (int)$p['college_id'],
                'label'      => $p['abbreviation'] . ' — ' . $p['name'],
            ];
        }, $programs), JSON_UNESCAPED_UNICODE) ?>;
        window.__OLD_PROGRAM__ = <?= json_encode($old['program_id'] !== '' ? (int)$old['program_id'] : null) ?>;
    </script>
    <script src="/assets/js/auth.js"></script>
    <script src="/assets/js/signup.js"></script>
</body>
</html>
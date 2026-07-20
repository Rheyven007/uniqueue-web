<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_student();

$student_id = $_SESSION['student_id'];

/* PROFILE INFO — joined with colleges/programs so we show names,
   not just the foreign key ids. */
$stmt = $pdo->prepare("
    SELECT
        s.first_name,
        s.last_name,
        s.sr_code,
        s.year_level,
        s.created_at,
        s.college_id,
        s.program_id,
        c.name AS college_name,
        c.abbreviation AS college_abbr,
        p.name AS program_name,
        p.abbreviation AS program_abbr
    FROM students s
    LEFT JOIN colleges c ON c.id = s.college_id
    LEFT JOIN programs p ON p.id = s.program_id
    WHERE s.id = ?
    LIMIT 1
");
$stmt->execute([$student_id]);
$profile = $stmt->fetch();

$yearLabels = [
    1 => '1st Year',
    2 => '2nd Year',
    3 => '3rd Year',
    4 => '4th Year',
    5 => '5th Year',
];

/* COLLEGES + PROGRAMS for the edit form's dropdowns */
$colleges = $pdo->query("SELECT id, name, abbreviation FROM colleges WHERE is_active = 1 ORDER BY name")->fetchAll();
$programs = $pdo->query("SELECT id, college_id, name, abbreviation FROM programs WHERE is_active = 1 ORDER BY name")->fetchAll();

/* UPDATE PROFILE */
$profileError   = null;
$profileSuccess = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $collegeId = $_POST['college_id'] !== '' ? (int)$_POST['college_id'] : null;
    $programId = $_POST['program_id'] !== '' ? (int)$_POST['program_id'] : null;
    $yearLevel = $_POST['year_level'] !== '' ? (int)$_POST['year_level'] : null;

    if ($firstName === '' || $lastName === '') {
        $profileError = 'Name fields cannot be left blank.';
    } elseif ($yearLevel !== null && !isset($yearLabels[$yearLevel])) {
        $profileError = 'Invalid year level selected.';
    } else {
        $stmt = $pdo->prepare("
            UPDATE students
            SET first_name = ?, last_name = ?, college_id = ?, program_id = ?, year_level = ?
            WHERE id = ?
        ");
        $stmt->execute([$firstName, $lastName, $collegeId, $programId, $yearLevel, $student_id]);
        $profileSuccess = 'Your profile has been updated successfully.';
        $_SESSION['student_name'] = $firstName . ' ' . $lastName;

        // Re-fetch so the form reflects the saved values, not the stale pre-update ones.
        $stmt = $pdo->prepare("
            SELECT
                s.first_name, s.last_name, s.sr_code, s.year_level, s.created_at,
                c.name AS college_name, c.abbreviation AS college_abbr,
                p.name AS program_name, p.abbreviation AS program_abbr,
                s.college_id, s.program_id
            FROM students s
            LEFT JOIN colleges c ON c.id = s.college_id
            LEFT JOIN programs p ON p.id = s.program_id
            WHERE s.id = ?
            LIMIT 1
        ");
        $stmt->execute([$student_id]);
        $profile = $stmt->fetch();
    }
}
$yearLabel = $profile['year_level'] && isset($yearLabels[$profile['year_level']])
    ? $yearLabels[$profile['year_level']]
    : 'Not set';

$initials = strtoupper(substr($profile['first_name'], 0, 1) . substr($profile['last_name'], 0, 1));

/* CHANGE PASSWORD */
$pwError   = null;
$pwSuccess = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare("SELECT password FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$student_id]);
    $storedHash = $stmt->fetchColumn();

    if (!$storedHash || !password_verify($currentPassword, $storedHash)) {
        $pwError = 'The current password you entered is incorrect.';
    } elseif (strlen($newPassword) < 8) {
        $pwError = 'New password must be at least 8 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $pwError = 'New password and confirmation do not match.';
    } else {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE students SET password = ? WHERE id = ?");
        $stmt->execute([$newHash, $student_id]);
        $pwSuccess = 'Your password has been updated successfully.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uniqueue &mdash; Profile</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link rel="stylesheet" href="/assets/css/profile.css">
    <link rel="stylesheet" href="/assets/css/header.css">
</head>
<body class="dashboard-body">

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<main class="dashboard-main">

    <!-- ── PROFILE HEADER STRIP ─────────────────────── -->
    <section class="dash-hero">
        <div class="dash-hero__left">
            <div class="dash-hero__code">My Profile</div>
        </div>
    </section>

    <!-- ── TWO-COLUMN GRID ─────────────────────────────── -->
    <div class="dashboard-grid">

        <!-- LEFT: Basic Info -->
        <section class="panel-card">
            <div class="panel-card__header">
                <div class="panel-card__title">Basic Information</div>
            </div>
            <div class="panel-card__body">

                <div class="profile-identity">
                    <div class="profile-avatar"><?= e($initials) ?></div>
                    <div class="profile-identity__text">
                        <div class="profile-identity__name">
                            <?= e($profile['first_name'] . ' ' . $profile['last_name']) ?>
                        </div>
                        <div class="profile-identity__code"><?= e($profile['sr_code']) ?></div>
                    </div>
                </div>

                <?php if ($profileError): ?>
                    <div class="form-alert form-alert--error"><?= e($profileError) ?></div>
                <?php endif; ?>
                <?php if ($profileSuccess): ?>
                    <div class="form-alert form-alert--success"><?= e($profileSuccess) ?></div>
                <?php endif; ?>

                <form method="POST" action="/student/profile.php" class="profile-form">

                    <div class="form-group">
                        <label class="form-label">SR Code</label>
                        <input type="text" class="form-input" value="<?= e($profile['sr_code']) ?>" disabled>
                        <span class="form-hint">SR code cannot be changed.</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-input"
                                   value="<?= e($profile['first_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-input"
                                   value="<?= e($profile['last_name']) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="college_id">College</label>
                        <select id="college_id" name="college_id" class="form-input">
                            <option value="">Not set</option>
                            <?php foreach ($colleges as $college): ?>
                                <option value="<?= (int)$college['id'] ?>"
                                    <?= (int)$profile['college_id'] === (int)$college['id'] ? 'selected' : '' ?>>
                                    <?= e($college['name']) ?> (<?= e($college['abbreviation']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="program_id">Program</label>
                        <select id="program_id" name="program_id" class="form-input">
                            <option value="">Not set</option>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?= (int)$program['id'] ?>"
                                    data-college="<?= (int)$program['college_id'] ?>"
                                    <?= (int)$profile['program_id'] === (int)$program['id'] ? 'selected' : '' ?>>
                                    <?= e($program['name']) ?> (<?= e($program['abbreviation']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="form-hint">Automatically filtered based on the selected College.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="year_level">Year Level</label>
                        <select id="year_level" name="year_level" class="form-input">
                            <option value="">Not set</option>
                            <?php foreach ($yearLabels as $val => $label): ?>
                                <option value="<?= $val ?>" <?= (int)$profile['year_level'] === $val ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="info-row" style="border-bottom:none; padding-bottom:0;">
                        <span class="info-row__label">Member Since</span>
                        <span class="info-row__value"><?= date('M d, Y', strtotime($profile['created_at'])) ?></span>
                    </div>

                    <button type="submit" name="update_profile" value="1" class="btn btn--primary btn--sm">
                        Save Changes
                    </button>

                </form>

            </div>
        </section>

        <!-- RIGHT: Change Password -->
        <section class="panel-card">
            <div class="panel-card__header">
                <div class="panel-card__title">Change Password</div>
            </div>
            <div class="panel-card__body">

                <?php if ($pwError): ?>
                    <div class="form-alert form-alert--error"><?= e($pwError) ?></div>
                <?php endif; ?>
                <?php if ($pwSuccess): ?>
                    <div class="form-alert form-alert--success"><?= e($pwSuccess) ?></div>
                <?php endif; ?>

                <form method="POST" action="/student/profile.php" class="profile-form">

                    <div class="form-group">
                        <label class="form-label" for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password"
                               class="form-input" required autocomplete="current-password">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password"
                               class="form-input" required minlength="8" autocomplete="new-password">
                        <span class="form-hint">Must be at least 8 characters.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password"
                               class="form-input" required minlength="8" autocomplete="new-password">
                    </div>

                    <button type="submit" name="change_password" value="1" class="btn btn--primary btn--sm">
                        Update Password
                    </button>

                </form>

            </div>
        </section>

    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script src="/assets/js/dashboard.js"></script>
<script>
(function () {
    var collegeSelect = document.getElementById('college_id');
    var programSelect = document.getElementById('program_id');
    if (!collegeSelect || !programSelect) return;

    var allOptions = Array.prototype.slice.call(programSelect.options);

    function filterPrograms() {
        var collegeId = collegeSelect.value;
        var currentValue = programSelect.value;
        var hasMatch = false;

        allOptions.forEach(function (opt) {
            if (!opt.dataset.college) { opt.hidden = false; return; } // "Not set" option
            var matches = !collegeId || opt.dataset.college === collegeId;
            opt.hidden = !matches;
            if (matches && opt.value === currentValue) hasMatch = true;
        });

        if (!hasMatch) programSelect.value = '';
    }

    collegeSelect.addEventListener('change', filterPrograms);
    filterPrograms(); // run once on load so it respects the saved value
})();
</script>
</body>
</html>
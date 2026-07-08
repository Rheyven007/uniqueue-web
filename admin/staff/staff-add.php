<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';

require_office_admin();

$office_id = $_SESSION['office_id'];

$error = '';
$success = '';

// Fetch windows for this office
$stmt = $pdo->prepare("
    SELECT id, name
    FROM windows
    WHERE office_id = ?
    ORDER BY name ASC
");
$stmt->execute([$office_id]);
$windows = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name       = trim($_POST['name'] ?? '');
    $position   = trim($_POST['position'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';
    $window_id  = (int)($_POST['window_id'] ?? 0);
    $status     = $_POST['status'] ?? 'active';

    if (
        $name == '' ||
        $position == '' ||
        $username == '' ||
        $password == '' ||
        $window_id == 0
    ) {

        $error = "Please complete all required fields.";

    } else {

        // Check duplicate username
        $check = $pdo->prepare("
            SELECT id
            FROM staff
            WHERE username = ?
            LIMIT 1
        ");
        $check->execute([$username]);

        if ($check->fetch()) {

            $error = "Username already exists.";

        } else {

            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $insert = $pdo->prepare("
                INSERT INTO staff
                (
                    name,
                    position,
                    username,
                    password,
                    office_id,
                    window_id,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $insert->execute([
                $name,
                $position,
                $username,
                $hashed,
                $office_id,
                $window_id,
                $status
            ]);

            header("Location: staff-list.php");
            exit;
        }

    }

}

$pageTitle = "Add Staff";

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/assets/css/staff-form.css">

<div class="form-container">

    <div class="page-header">

        <h1>Add Staff</h1>

        <a href="staff-list.php" class="btn btn-secondary">
            ← Back
        </a>

    </div>

    <?php if($error): ?>

        <div class="alert alert-danger">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>

    <form method="POST">

        <div class="form-grid">

            <div>

                <label>Staff Name</label>

                <input
                    type="text"
                    name="name"
                    required
                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                >

            </div>

            <div>

                <label>Position</label>

                <input
                    type="text"
                    name="position"
                    required
                    value="<?= htmlspecialchars($_POST['position'] ?? '') ?>"
                >

            </div>

            <div>

                <label>Username</label>

                <input
                    type="text"
                    name="username"
                    required
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                >

            </div>

            <div>

                <label>Password</label>

                <input
                    type="password"
                    name="password"
                    required
                >

            </div>

            <div>

                <label>Assigned Window</label>

                <select
                    name="window_id"
                    required
                >

                    <option value="">
                        Select Window
                    </option>

                    <?php foreach($windows as $window): ?>

                        <option
                            value="<?= $window['id'] ?>"
                        >

                            <?= htmlspecialchars($window['name']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div>

                <label>Status</label>

                <select name="status">

                    <option value="active">

                        Active

                    </option>

                    <option value="inactive">

                        Inactive

                    </option>

                </select>

            </div>

        </div>

        <div class="form-actions">

            <button
                class="btn btn-primary"
                type="submit">

                Save Staff

            </button>

        </div>

    </form>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';

require_office_admin();

$office_id = $_SESSION['office_id'];

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT *
    FROM staff
    WHERE id = ?
    AND office_id = ?
    LIMIT 1
");
$stmt->execute([$id, $office_id]);

$staff = $stmt->fetch();

if (!$staff) {
    header("Location: staff-list.php");
    exit;
}

// windows
$stmt = $pdo->prepare("
SELECT id,name
FROM windows
WHERE office_id=?
ORDER BY name
");
$stmt->execute([$office_id]);
$windows = $stmt->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $name      = trim($_POST['name']);
    $position  = trim($_POST['position']);
    $username  = trim($_POST['username']);
    $password  = trim($_POST['password']);
    $window_id = (int)$_POST['window_id'];
    $status    = $_POST['status'];

    if (
        empty($name) ||
        empty($position) ||
        empty($username)
    ) {

        $error = "Please fill in all required fields.";

    } else {

        // duplicate username
        $check = $pdo->prepare("
        SELECT id
        FROM staff
        WHERE username=?
        AND id<>?
        LIMIT 1
        ");

        $check->execute([
            $username,
            $id
        ]);

        if ($check->fetch()) {

            $error = "Username already exists.";

        } else {

            // only update password if entered

            if (!empty($password)) {

                $hashed = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $update = $pdo->prepare("
                UPDATE staff
                SET
                    name=?,
                    position=?,
                    username=?,
                    password=?,
                    window_id=?,
                    status=?,
                    updated_at=NOW()
                WHERE id=?
                ");

                $update->execute([
                    $name,
                    $position,
                    $username,
                    $hashed,
                    $window_id,
                    $status,
                    $id
                ]);

            } else {

                $update = $pdo->prepare("
                UPDATE staff
                SET
                    name=?,
                    position=?,
                    username=?,
                    window_id=?,
                    status=?,
                    updated_at=NOW()
                WHERE id=?
                ");

                $update->execute([
                    $name,
                    $position,
                    $username,
                    $window_id,
                    $status,
                    $id
                ]);

            }

            header("Location: staff-list.php");
            exit;

        }

    }

}

$pageTitle = "Edit Staff";

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/assets/css/staff-form.css">

<div class="form-container">

    <div class="page-header">

        <h1>Edit Staff</h1>

        <a href="staff-list.php"
           class="btn btn-secondary">

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

<label>Name</label>

<input
type="text"
name="name"
required
value="<?= htmlspecialchars($staff['name']) ?>">

</div>

<div>

<label>Position</label>

<input
type="text"
name="position"
required
value="<?= htmlspecialchars($staff['position']) ?>">

</div>

<div>

<label>Username</label>

<input
type="text"
name="username"
required
value="<?= htmlspecialchars($staff['username']) ?>">

</div>

<div>

<label>New Password</label>

<input
type="password"
name="password">

<small>
Leave blank to keep the current password.
</small>

</div>

<div>

<label>Assigned Window</label>

<select name="window_id">

<?php foreach($windows as $w): ?>

<option
value="<?= $w['id'] ?>"
<?= $staff['window_id']==$w['id']?'selected':'' ?>>

<?= htmlspecialchars($w['name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div>

<label>Status</label>

<select name="status">

<option
value="active"
<?= $staff['status']=="active"?'selected':'' ?>>

Active

</option>

<option
value="inactive"
<?= $staff['status']=="inactive"?'selected':'' ?>>

Inactive

</option>

</select>

</div>

</div>

<div class="form-actions">

<button
class="btn btn-primary">

Update Staff

</button>

</div>

</form>

</div>

<?php include __DIR__.'/../../includes/footer.php'; ?>
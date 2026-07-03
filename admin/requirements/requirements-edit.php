<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_office_admin();

$office_id      = $_SESSION['office_id'] ?? null;
$is_super_admin = !empty($_SESSION['is_super_admin']);

$req_id = $_GET['id'] ?? null;
if (!$req_id) {
    redirect('/admin/requirements/requirements-list.php');
}

$stmt = $pdo->prepare("
    SELECT dr.*, d.name AS document_name, d.office_id, o.name AS office_name
    FROM document_requirements dr
    JOIN documents d ON dr.document_id = d.id
    JOIN offices o ON d.office_id = o.id
    WHERE dr.id = ?
");
$stmt->execute([$req_id]);
$req = $stmt->fetch();

if (!$req) {
    redirect('/admin/requirements/requirements-list.php');
}

// Ownership check
if (!$is_super_admin && $req['office_id'] != $office_id) {
    redirect('/admin/requirements/requirements-list.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requirement = trim($_POST['requirement'] ?? '');

    if ($requirement === '') {
        $errors[] = "Requirement text cannot be empty.";
    }

    if (empty($errors)) {
        $upd = $pdo->prepare("UPDATE document_requirements SET requirement = ? WHERE id = ?");
        $upd->execute([$requirement, $req_id]);
        redirect('/admin/requirements/requirements-list.php');
    }
}

$pageTitle = "Edit Requirement";
include __DIR__ . '/../../includes/header.php';
?>

<div class="req-wrap">

    <div class="req-topbar">
        <div>
            <h1>Edit Requirement</h1>
            <p>
                <strong><?= htmlspecialchars($req['document_name']) ?></strong>
                &nbsp;·&nbsp;
                <?= htmlspecialchars($req['office_name']) ?>
            </p>
        </div>
        <a href="requirements-list.php" class="btn btn-ghost">← Back to List</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="req-alert req-alert--error">
            <?php foreach ($errors as $e): ?>
                <p><?= htmlspecialchars($e) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="req-form-card">
        <form method="POST" action="requirements-edit.php?id=<?= (int)$req_id ?>">

            <div class="form-group">
                <label for="document_display">Document Type</label>
                <input
                    type="text"
                    id="document_display"
                    class="form-control"
                    value="[<?= htmlspecialchars($req['office_name']) ?>] <?= htmlspecialchars($req['document_name']) ?>"
                    disabled
                >
                <small class="field-hint">To move this requirement to a different document, delete it and re-add it.</small>
            </div>

            <div class="form-group">
                <label for="requirement">Requirement</label>
                <input
                    type="text"
                    id="requirement"
                    name="requirement"
                    class="form-control"
                    value="<?= htmlspecialchars($_POST['requirement'] ?? $req['requirement']) ?>"
                    placeholder="e.g. Original copy of birth certificate"
                    required
                    autofocus
                >
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Requirement</button>
                <a href="requirements-list.php" class="btn btn-ghost">Cancel</a>
            </div>

        </form>
    </div>

</div>

<link rel="stylesheet" href="/assets/css/requirements-manage.css">
<script src="/assets/js/requirements-manage.js"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
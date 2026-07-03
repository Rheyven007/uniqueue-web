<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_office_admin();

$office_id      = $_SESSION['office_id'] ?? null;
$is_super_admin = !empty($_SESSION['is_super_admin']);

$document_id = $_GET['id'] ?? null;
if (!$document_id) redirect('/admin/document/document-list.php');

$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->execute([$document_id]);
$document = $stmt->fetch();

if (!$document) redirect('/admin/document/document-list.php');

// Ownership check: office admins can only edit documents in their office
if (!$is_super_admin && $document['office_id'] != $office_id) {
    redirect('/admin/document/document-list.php');
}

if ($is_super_admin) {
    $offices = $pdo->query("SELECT id, name FROM offices WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT id, name FROM offices WHERE id = ?");
    $stmt->execute([$office_id]);
    $offices = $stmt->fetchAll();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name            = trim($_POST['name'] ?? '');
    $post_office_id  = $is_super_admin ? ($_POST['office_id'] ?? null) : $office_id;
    $daily_capacity  = $_POST['daily_capacity'] !== '' ? (int)$_POST['daily_capacity'] : null;
    $processing_time = $_POST['processing_time'] !== '' ? (int)$_POST['processing_time'] : null;

    if (!$name)           $errors[] = "Document name is required.";
    if (!$post_office_id) $errors[] = "Office is required.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE documents SET name = ?, office_id = ?, daily_capacity = ?, processing_time = ? WHERE id = ?");
        $stmt->execute([$name, $post_office_id, $daily_capacity, $processing_time, $document_id]);
        redirect('/admin/document/document-list.php');
    }
}

$pageTitle = "Edit Document Type";
include __DIR__ . '/../../includes/header.php';
?>

<div class="req-wrap">

    <div class="req-topbar">
        <div>
            <h1>Edit Document Type</h1>
            <p><strong><?= htmlspecialchars($document['name']) ?></strong></p>
        </div>
        <a href="document-list.php" class="btn btn-ghost">← Back to List</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="req-alert req-alert--error">
            <?php foreach ($errors as $e): ?><p><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="req-form-card">
        <form method="POST" action="document-edit.php?id=<?= (int)$document['id'] ?>">

            <?php if ($is_super_admin): ?>
            <div class="form-group">
                <label for="office_id">Office</label>
                <select name="office_id" id="office_id" required class="form-control">
                    <option value="">— Select an Office —</option>
                    <?php foreach ($offices as $office): ?>
                        <option value="<?= $office['id'] ?>" <?= ($office['id'] == ($document['office_id'])) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($office['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <div class="form-group">
                    <label>Office</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($offices[0]['name'] ?? '') ?>" disabled>
                    <input type="hidden" name="office_id" value="<?= (int)$office_id ?>">
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="name">Document Name</label>
                <input type="text" id="name" name="name"
                       value="<?= htmlspecialchars($_POST['name'] ?? $document['name']) ?>"
                       required class="form-control">
            </div>

            <div class="form-group">
                <label for="daily_capacity">Daily Capacity <span style="font-weight:400;text-transform:none;color:var(--ink-light)">(optional)</span></label>
                <input type="number" id="daily_capacity" name="daily_capacity"
                       value="<?= htmlspecialchars($_POST['daily_capacity'] ?? $document['daily_capacity']) ?>"
                       min="0" class="form-control">
            </div>

            <div class="form-group">
                <label for="processing_time">Processing Time (minutes) <span style="font-weight:400;text-transform:none;color:var(--ink-light)">(optional)</span></label>
                <input type="number" id="processing_time" name="processing_time"
                       value="<?= htmlspecialchars($_POST['processing_time'] ?? $document['processing_time']) ?>"
                       min="0" class="form-control">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Document</button>
                <a href="document-list.php" class="btn btn-ghost">Cancel</a>
            </div>

        </form>
    </div>

</div>

<link rel="stylesheet" href="/assets/css/requirements-manage.css">
<?php include __DIR__ . '/../../includes/footer.php'; ?>
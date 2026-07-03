<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_office_admin();

$office_id      = $_SESSION['office_id'] ?? null;
$is_super_admin = !empty($_SESSION['is_super_admin']);

if ($is_super_admin) {
    $offices = $pdo->query("SELECT id, name FROM offices WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT id, name FROM offices WHERE id = ? AND is_active = 1");
    $stmt->execute([$office_id]);
    $offices = $stmt->fetchAll();
    if (empty($offices)) redirect('/admin/queue/office-dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name            = trim($_POST['name'] ?? '');
    $post_office_id  = $is_super_admin ? ($_POST['office_id'] ?? null) : $office_id;
    $processing_time = (int)($_POST['processing_time'] ?? 10);
    $requirements    = array_filter(array_map('trim', $_POST['requirements'] ?? []));

    if (!$name)            $errors[] = "Document name is required.";
    if (!$post_office_id)  $errors[] = "Office is required.";
    if (empty($requirements)) $errors[] = "Please add at least one requirement.";

    if (empty($errors)) {
        // Insert document
        $stmt = $pdo->prepare("INSERT INTO documents (name, office_id, processing_time) VALUES (?, ?, ?)");
        $stmt->execute([$name, $post_office_id, $processing_time]);
        $new_doc_id = $pdo->lastInsertId();

        // Insert requirements
        $req_stmt = $pdo->prepare("INSERT INTO document_requirements (document_id, requirement) VALUES (?, ?)");
        foreach ($requirements as $req) {
            $req_stmt->execute([$new_doc_id, $req]);
        }

        redirect('/admin/document/document-list.php');
    }
}

$pageTitle = "Add Document Type";
include __DIR__ . '/../../includes/header.php';
?>

<div class="req-wrap">

    <div class="req-topbar">
        <div>
            <h1>Add Document Type</h1>
            <p>Fill in the details and requirements checklist in one go<?php if (!$is_super_admin && !empty($offices[0])): ?> for <strong><?= htmlspecialchars($offices[0]['name']) ?></strong><?php endif; ?>.</p>
        </div>
        <a href="document-list.php" class="btn btn-ghost">← Back to List</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="req-alert req-alert--error">
            <?php foreach ($errors as $e): ?><p><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="req-form-card" style="max-width:780px;">
        <form method="POST" id="doc-add-form">

            <?php if ($is_super_admin): ?>
            <div class="form-group">
                <label for="office_id">Target Office</label>
                <select name="office_id" id="office_id" required class="form-control">
                    <option value="">— Select Office —</option>
                    <?php foreach ($offices as $office): ?>
                        <option value="<?= $office['id'] ?>" <?= ($office['id'] == ($_POST['office_id'] ?? '')) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($office['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <input type="hidden" name="office_id" value="<?= (int)$office_id ?>">
            <?php endif; ?>

            <!-- ── Document details ───────────────────────────── -->
            <div class="form-section-label">Document Details</div>

            <div class="form-row-2">
                <div class="form-group">
                    <label for="name">Document Name</label>
                    <input type="text" id="name" name="name"
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                           required class="form-control"
                           placeholder="e.g. Transcript of Records">
                </div>
                <div class="form-group">
                    <label for="processing_time">Est. Processing Time (minutes)</label>
                    <input type="number" id="processing_time" name="processing_time"
                           value="<?= (int)($_POST['processing_time'] ?? 10) ?>"
                           min="1" required class="form-control">
                </div>
            </div>

            <!-- ── Requirements ───────────────────────────────── -->
            <div class="form-section-label" style="margin-top:1.4rem;">Requirements Checklist</div>
            <p class="field-hint" style="margin-bottom:.8rem;">Add one item per row. Students will see this list before queuing.</p>

            <div id="requirements-list-input">
                <?php
                $rows = (!empty($_POST['requirements'])) ? $_POST['requirements'] : ['', '', ''];
                foreach ($rows as $i => $val):
                ?>
                <div class="req-row" data-index="<?= $i ?>">
                    <span class="req-num"><?= $i + 1 ?></span>
                    <input
                        type="text"
                        name="requirements[]"
                        class="form-control req-input"
                        value="<?= htmlspecialchars($val) ?>"
                        placeholder="e.g. Original copy of birth certificate"
                    >
                    <button type="button" class="btn-remove-req" title="Remove row" onclick="removeRow(this)">&times;</button>
                </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="btn btn-ghost btn-sm btn-add-row" onclick="addRow()">+ Add Requirement</button>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Document &amp; Requirements</button>
                <a href="document-list.php" class="btn btn-ghost">Cancel</a>
            </div>

        </form>
    </div>

</div>

<link rel="stylesheet" href="/assets/css/requirements-manage.css">
<style>
.form-section-label {
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--ink-light);
    display: flex;
    align-items: center;
    gap: .5rem;
    margin-bottom: .65rem;
}
.form-section-label::before {
    content: '';
    display: inline-block;
    width: 14px;
    height: 2px;
    background: var(--red);
    border-radius: 2px;
    flex-shrink: 0;
}
.form-row-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
@media (max-width: 560px) {
    .form-row-2 { grid-template-columns: 1fr; }
}
</style>
<script src="/assets/js/requirements-manage.js"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
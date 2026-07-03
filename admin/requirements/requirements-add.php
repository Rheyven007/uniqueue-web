<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_office_admin();

$office_id      = $_SESSION['office_id'] ?? null;
$is_super_admin = !empty($_SESSION['is_super_admin']);

// Build the documents query
if ($is_super_admin) {
    $documents = $pdo->query("
        SELECT d.id, d.name, o.name AS office_name
        FROM documents d
        JOIN offices o ON d.office_id = o.id
        ORDER BY o.name ASC, d.name ASC
    ")->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT d.id, d.name, o.name AS office_name
        FROM documents d
        JOIN offices o ON d.office_id = o.id
        WHERE d.office_id = ?
        ORDER BY d.name ASC
    ");
    $stmt->execute([$office_id]);
    $documents = $stmt->fetchAll();
}

$preselected_doc_id = $_GET['document_id'] ?? null;
$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $document_id  = $_POST['document_id']  ?? null;
    $requirements = $_POST['requirements'] ?? [];

    $requirements = array_filter(array_map('trim', $requirements));

    if (!$document_id) {
        $errors[] = "Please select a document type.";
    }
    if (empty($requirements)) {
        $errors[] = "Please add at least one requirement.";
    }

    if ($document_id && !$is_super_admin) {
        $chk = $pdo->prepare("SELECT id FROM documents WHERE id = ? AND office_id = ?");
        $chk->execute([$document_id, $office_id]);
        if (!$chk->fetch()) {
            $errors[] = "You do not have permission to add requirements to that document.";
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO document_requirements (document_id, requirement) VALUES (?, ?)");
        foreach ($requirements as $req) {
            $stmt->execute([$document_id, $req]);
        }
        redirect('/admin/requirements/requirements-list.php');
    }
}

$pageTitle = "Add Requirements";
include __DIR__ . '/../../includes/header.php';
?>

<div class="req-wrap">

    <div class="req-topbar">
        <div>
            <h1>Add Document Requirements</h1>
            <p>Define the checklist items a student must bring for this document type.</p>
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
        <form method="POST" id="requirements-form">

            <div class="form-group">
                <label for="document_id">Document Type</label>
                <select name="document_id" id="document_id" class="form-control" required>
                    <option value="">— Select Document —</option>
                    <?php foreach ($documents as $doc): ?>
                        <option value="<?= $doc['id'] ?>"
                            <?= ($doc['id'] == ($preselected_doc_id ?? $_POST['document_id'] ?? null)) ? 'selected' : '' ?>>
                            [<?= htmlspecialchars($doc['office_name']) ?>] <?= htmlspecialchars($doc['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Requirements Checklist</label>
                <p class="field-hint">Add one requirement per row. Students will see this list before queuing.</p>

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
                        <button type="button" class="btn-remove-req" title="Remove row" onclick="removeRow(this)">
                            &times;
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="btn btn-ghost btn-sm btn-add-row" onclick="addRow()">
                    + Add Requirement
                </button>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Requirements</button>
                <a href="requirements-list.php" class="btn btn-ghost">Cancel</a>
            </div>

        </form>
    </div>

</div>

<link rel="stylesheet" href="/assets/css/requirements-manage.css">
<script src="/assets/js/requirements-manage.js"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
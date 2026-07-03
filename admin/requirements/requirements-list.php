<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_office_admin();

$office_id      = $_SESSION['office_id'] ?? null;
$is_super_admin = !empty($_SESSION['is_super_admin']);

// Office admins only see their own office's requirements
if ($is_super_admin) {
    $stmt = $pdo->query("
        SELECT dr.*, d.name AS document_name, o.name AS office_name
        FROM document_requirements dr
        JOIN documents d ON dr.document_id = d.id
        JOIN offices o ON d.office_id = o.id
        ORDER BY o.name ASC, d.name ASC
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT dr.*, d.name AS document_name, o.name AS office_name
        FROM document_requirements dr
        JOIN documents d ON dr.document_id = d.id
        JOIN offices o ON d.office_id = o.id
        WHERE d.office_id = ?
        ORDER BY d.name ASC
    ");
    $stmt->execute([$office_id]);
}
$requirements = $stmt->fetchAll();

$pageTitle = "Document Requirements";
include __DIR__ . '/../../includes/header.php';
?>

<div class="req-wrap">

    <div class="req-topbar">
        <div>
            <h1>Document Requirements</h1>
            <p>Manage the checklist items students must bring for each document type.</p>
        </div>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
            <a href="/admin/document/document-list.php" class="btn btn-ghost">← Documents</a>
            <a href="requirements-add.php" class="btn btn-primary">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Requirement
            </a>
        </div>
    </div>

    <?php if (empty($requirements)): ?>
        <div class="req-empty">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
            <p>No requirements defined yet. <a href="requirements-add.php">Add the first one.</a></p>
        </div>
    <?php else: ?>
        <div class="req-table-wrap">
            <table class="req-table">
                <thead>
                    <tr>
                        <th>Office &amp; Document</th>
                        <th>Requirement</th>
                        <th class="th-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requirements as $req): ?>
                    <tr>
                        <td>
                            <span class="doc-office"><?= htmlspecialchars($req['office_name']) ?></span>
                            <span class="doc-name"><?= htmlspecialchars($req['document_name']) ?></span>
                        </td>
                        <td class="req-text"><?= htmlspecialchars($req['requirement']) ?></td>
                        <td class="td-actions">
                            <a href="requirements-edit.php?id=<?= $req['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
                            <button class="btn btn-danger btn-sm btn-delete-req" data-id="<?= $req['id'] ?>">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<link rel="stylesheet" href="/assets/css/requirements-manage.css">
<script src="/assets/js/document-manage.js"></script>
<script src="/assets/js/requirements-manage.js"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
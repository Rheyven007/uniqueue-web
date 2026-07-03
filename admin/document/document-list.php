<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_office_admin();

$office_id      = $_SESSION['office_id'] ?? null;
$is_super_admin = !empty($_SESSION['is_super_admin']);

// Fetch documents — office admins only see their own office's documents
try {
    if ($is_super_admin) {
        $stmt = $pdo->query("
            SELECT d.*, o.name AS office_name,
                   (SELECT COUNT(*) FROM document_requirements dr WHERE dr.document_id = d.id) AS req_count
            FROM documents d
            JOIN offices o ON d.office_id = o.id
            ORDER BY o.name ASC, d.name ASC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT d.*, o.name AS office_name,
                   (SELECT COUNT(*) FROM document_requirements dr WHERE dr.document_id = d.id) AS req_count
            FROM documents d
            JOIN offices o ON d.office_id = o.id
            WHERE d.office_id = ?
            ORDER BY d.name ASC
        ");
        $stmt->execute([$office_id]);
    }
    $documents = $stmt->fetchAll();
} catch (PDOException $e) {
    $documents = [];
    $db_error = $e->getMessage();
}

$pageTitle = "Document Types";
include __DIR__ . '/../../includes/header.php';
?>

<div class="req-wrap">

    <div class="req-topbar">
        <div>
            <h1>Document Types</h1>
            <p>Manage document types and their requirements checklist.</p>
        </div>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
            <a href="/admin/queue/office-dashboard.php" class="btn btn-ghost">← Dashboard</a>
            <a href="document-add.php" class="btn btn-primary">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Add Document Type
            </a>
        </div>
    </div>

    <?php if (!empty($db_error)): ?>
        <div class="req-alert req-alert--error">
            <p><strong>Database error:</strong> <?= htmlspecialchars($db_error) ?></p>
        </div>
    <?php endif; ?>

    <?php if (empty($documents)): ?>
        <div class="req-empty">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
            </svg>
            <p>No document types found. <a href="document-add.php">Add the first one.</a></p>
        </div>
    <?php else: ?>
        <div class="req-table-wrap">
            <table class="req-table">
                <thead>
                    <tr>
                        <th>Document Name</th>
                        <?php if ($is_super_admin): ?><th>Office</th><?php endif; ?>
                        <th>Est. Time (mins)</th>
                        <th>Requirements</th>
                        <th class="th-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $doc): ?>
                        <tr>
                            <td><span class="doc-name"><?= htmlspecialchars($doc['name']) ?></span></td>
                            <?php if ($is_super_admin): ?>
                                <td><span class="doc-office"><?= htmlspecialchars($doc['office_name']) ?></span></td>
                            <?php endif; ?>
                            <td><?= (int)$doc['processing_time'] ?> min</td>
                            <td>
                                <?php if ((int)($doc['req_count'] ?? 0) > 0): ?>
                                    <a href="/admin/requirements/requirements-list.php?document_id=<?= $doc['id'] ?>"
                                       class="badge badge-type" style="text-decoration:none;">
                                        <?= (int)$doc['req_count'] ?> item<?= $doc['req_count'] != 1 ? 's' : '' ?>
                                    </a>
                                <?php else: ?>
                                    <a href="/admin/requirements/requirements-add.php?document_id=<?= $doc['id'] ?>"
                                       class="badge badge-priority" style="text-decoration:none;">
                                        + Add
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td class="td-actions">
                                <a href="document-edit.php?id=<?= $doc['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
                                <button class="btn btn-danger btn-sm delete-document" data-id="<?= $doc['id'] ?>">Delete</button>
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
<?php include __DIR__ . '/../../includes/footer.php'; ?>
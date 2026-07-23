<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_office_admin();

$office_id      = $_SESSION['office_id'] ?? null;
$is_super_admin = !empty($_SESSION['is_super_admin']);
$search         = trim($_GET['search'] ?? '');

$db_error = null;

// Fetch documents — office admins only see their own office's documents
try {
    if ($is_super_admin) {
        $sql = "
            SELECT d.*, o.name AS office_name,
                   (SELECT COUNT(*) FROM document_requirements dr WHERE dr.document_id = d.id) AS req_count
            FROM documents d
            JOIN offices o ON d.office_id = o.id
        ";
        $params = [];
        if ($search !== '') {
            $sql .= " WHERE d.name LIKE ?";
            $params[] = "%{$search}%";
        }
        $sql .= " ORDER BY o.name ASC, d.name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        $sql = "
            SELECT d.*, o.name AS office_name,
                   (SELECT COUNT(*) FROM document_requirements dr WHERE dr.document_id = d.id) AS req_count
            FROM documents d
            JOIN offices o ON d.office_id = o.id
            WHERE d.office_id = ?
        ";
        $params = [$office_id];
        if ($search !== '') {
            $sql .= " AND d.name LIKE ?";
            $params[] = "%{$search}%";
        }
        $sql .= " ORDER BY d.name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
    $documents = $stmt->fetchAll();
} catch (PDOException $e) {
    $documents = [];
    $db_error = 'Something went wrong while loading documents. Please try again.';
    error_log('document-list.php DB error: ' . $e->getMessage());
}

/* ── Renders just the "results" region: the alert (if any) + the
     table / empty-state. Reused by both the full page render below
     and the AJAX (search/refresh) response so markup can't drift. ── */
function render_document_results_html($documents, $db_error, $search, $is_super_admin) {
    ob_start();
    ?>
    <?php if (!empty($db_error)): ?>
        <div class="req-alert req-alert--error">
            <p><strong>Unable to load documents.</strong> <?= htmlspecialchars($db_error) ?></p>
            <p><a href="document-list.php" class="btn btn-ghost">Try again</a></p>
        </div>
    <?php endif; ?>

    <?php if (empty($documents)): ?>
        <div class="req-empty">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
            </svg>
            <?php if ($search !== ''): ?>
                <p>No document types match "<?= htmlspecialchars($search) ?>". <a href="document-list.php" class="clear-search-link">Clear search</a></p>
            <?php else: ?>
                <p>No document types found. <a href="document-add.php">Add the first one.</a></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="ql-table-wrap">
            <table class="ql-table">
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
                                <a href="document-edit.php?id=<?= $doc['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                                <button class="btn btn-danger btn-sm delete-document" data-id="<?= $doc['id'] ?>"
                                        type="button"
                                        onclick="return confirm('Delete this document type? This action cannot be undone.');">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

$results_html = render_document_results_html($documents, $db_error, $search, $is_super_admin);

/* ── AJAX request? (search form submit or refresh button) ──────────
   Both are triggered via fetch() with this header set, so we can
   short-circuit here and return just the results HTML as JSON
   instead of rendering the whole page (header/sidebar/footer). ── */
$is_ajax = (
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || isset($_GET['ajax'])
);

if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $db_error === null,
        'html'    => $results_html,
        'count'   => count($documents),
        'search'  => $search,
    ]);
    exit;
}

$pageTitle = "Document Types";
include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/assets/css/document-list.css">

<div class="app-shell">

    <?php include __DIR__ . '/../../includes/office-sidebar.php'; ?>

    <div class="od-wrap">

        <div class="od-topbar">
            <div class="od-topbar__left">
                <h1>Document Types</h1>
                <p>Manage document types and their requirements checklist.</p>
            </div>
            <div class="od-topbar__actions">
                <button class="btn btn-outline-light btn-sm" id="refresh-list-btn" type="button" aria-label="Refresh list">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21.5 2v6h-6"/>
                            <path d="M21.34 15.57a10 10 0 1 1-.4-4.57"/>
                        </svg>
                        Refresh
                    </button>
                <a href="document-add.php" class="btn btn-green">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Add Document
                    </a>
                </div>
        </div>

        <div class="staff-wrap" style="padding: 0; margin: 0; animation: none; gap: 1.8rem; width: 100%; max-width: none;">

            <!-- Live region: announces save/delete/filter results to assistive tech
                 without a visible layout jump (visibility of system status). -->
            <div class="sr-only" role="status" aria-live="polite" id="doc-status-region"></div>

            <form method="GET" class="search-bar" role="search" aria-label="Search document types" id="doc-search-form">
                <div class="search-bar__field">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                    </svg>
                    <label class="sr-only" for="doc-search">Search</label>
                    <input id="doc-search" type="text" name="search" placeholder="Search document types..."
                           value="<?= htmlspecialchars($search ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-primary">Search</button>
            </form>

            <div id="doc-list-results">
                <?= $results_html ?>
            </div>

        </div><!-- /.staff-wrap -->

    </div><!-- /.od-wrap -->
</div><!-- /.app-shell -->

<script src="/assets/js/document-manage.js"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
<?php
// admin/staff/staff-list.php

require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';

require_office_admin();

$office_id = $_SESSION['office_id'];

$search = trim($_GET['search'] ?? '');

// Live-search: if this request came from our fetch() call in staff-list.js,
// render ONLY the results markup below and stop — no page shell, no reload.
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

$sql = "
SELECT
    s.*,
    o.name AS office_name,
    w.name AS window_name
FROM staff s
LEFT JOIN offices o ON s.office_id = o.id
LEFT JOIN windows w ON s.window_id = w.id
WHERE s.office_id = ?
";

$params = [$office_id];

if ($search != '') {
    $sql .= " AND (
        s.name LIKE ?
        OR s.username LIKE ?
        OR s.position LIKE ?
    )";

    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY s.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$staff = $stmt->fetchAll();

/**
 * Renders the results markup (empty-row OR full table body rows) and
 * returns it as a string. Shared by the AJAX JSON response and the full
 * page render below. Mirrors the same pattern used in document-list.php /
 * counter-list.php for consistency across list pages.
 */
function render_staff_results_html(array $staff, string $search): string {
    ob_start();
?>
            <?php if(empty($staff)): ?>

                <tr>

                    <td colspan="6" class="empty">
                        <?php if ($search !== ''): ?>
                            No staff match "<?= htmlspecialchars($search) ?>". <a href="#" class="js-clear-search">Clear search</a>
                        <?php else: ?>
                            No staff found. <a href="staff-add.php">Add the first one.</a>
                        <?php endif; ?>
                    </td>

                </tr>

            <?php endif; ?>

            <?php foreach($staff as $row): ?>

                <tr>

                    <td>
                        <div class="staff-name">
                            <span class="staff-avatar">
                                <?= strtoupper(substr($row['name'], 0, 1)) ?>
                            </span>
                            <?= htmlspecialchars($row['name']) ?>
                        </div>
                    </td>

                    <td>

                        <?= htmlspecialchars($row['username']) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars($row['position']) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars($row['window_name'] ?? '-') ?>

                    </td>

                    <td>

                        <?php if($row['status'] == 'active'): ?>

                            <span class="badge badge-success">
                                Active
                            </span>

                        <?php else: ?>

                            <span class="badge badge-danger">
                                Inactive
                            </span>

                        <?php endif; ?>

                    </td>

                    <td>
                        <div class="td-actions">
                            <a
                                href="staff-edit.php?id=<?= $row['id'] ?>"
                                class="btn btn-warning btn-sm"
                                title="Edit <?= htmlspecialchars($row['name']) ?>">

                                Edit

                            </a>

                            <a
                                href="staff-delete.php?id=<?= $row['id'] ?>"
                                class="btn btn-danger btn-sm"
                                title="Delete <?= htmlspecialchars($row['name']) ?>"
                                onclick="return confirm('Delete this staff account? This action cannot be undone.')">

                                Delete

                            </a>
                        </div>
                    </td>

                </tr>

            <?php endforeach; ?>
<?php
    return ob_get_clean();
}

$staff_results_html = render_staff_results_html($staff, $search);

/* ── AJAX request? (search/refresh) ──────────────────────────────
   Same contract as document-list.php and counter-list.php: JSON with
   {success, html, count, search} instead of raw HTML, so all list
   pages behave identically from the JS side. ── */
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'html'    => $staff_results_html,
        'count'   => count($staff),
        'search'  => $search,
    ]);
    exit;
}

$pageTitle = "Manage Staff";

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/assets/css/staff.css">

<div class="app-shell">

    <?php include __DIR__ . '/../../includes/office-sidebar.php'; ?>

    <div class="od-wrap">

        <div class="staff-wrap" style="padding: 0; margin: 0; animation: none; gap: 1.8rem; width: 100%; max-width: none;">

    <div class="od-topbar">
        <div class="od-topbar__left">
            <h1>Staff Management</h1>
                <p>Manage office staff accounts.</p>
            </div>
        <div class="od-topbar__actions">
            <button class="btn btn-outline-light btn-sm" onclick="window.location.reload()" aria-label="Refresh list">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21.5 2v6h-6"/>
                    <path d="M21.34 15.57a10 10 0 1 1-.4-4.57"/>
                </svg>
                Refresh
            </button>
            <a href="staff-add.php" class="btn btn-green">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 5v14"/><path d="M5 12h14"/>
                </svg>
                Add Staff
            </a>
        </div>
    </div>

    <!-- Live region: announces search/save/delete results for assistive tech -->
    <div class="sr-only" role="status" aria-live="polite" id="staff-status-region"></div>

    <form method="GET" class="search-bar" role="search" id="staff-search-form">

        <div class="search-bar__field">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
            </svg>
           
            <input
                id="staff-search"
                type="text"
                name="search"
                placeholder="Search by name, username, or position..."
                value="<?= htmlspecialchars($search) ?>"
            >
        </div>

        <button type="submit" class="btn btn-primary btn-search" aria-label="Search">Search</button>

    </form>

    <div class="ql-table-wrap">

        <table class="ql-table">

            <thead>

            <tr>

                <th>Name</th>

                <th>Username</th>

                <th>Position</th>

                <th>Window</th>

                <th>Status</th>

                <th class="td-actions" width="180">Actions</th>

            </tr>

            </thead>

            <tbody id="staff-results">

            <?= $staff_results_html ?>

            </tbody>

        </table>

    </div>

</div><!-- /.staff-wrap -->

    </div><!-- /.od-wrap -->
</div><!-- /.app-shell -->

<script src="/assets/js/staff-list.js"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
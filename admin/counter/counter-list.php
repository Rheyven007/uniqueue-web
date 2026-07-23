<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_office_admin();

// If the logged-in user is scoped to an office, only show that office's windows.
// Super-admins (no office_id in session) see all.
$session_office_id = $_SESSION['office_id'] ?? null;
$search            = trim($_GET['search'] ?? '');

// Live-search: if this request came from our fetch() call in counter-manage.js,
// render ONLY the results markup below and stop — no page shell, no reload.
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($session_office_id) {
    $sql = "
        SELECT w.*, o.name as office_name, s.name as staff_name, s.position as staff_position
        FROM windows w
        JOIN offices o ON w.office_id = o.id
        LEFT JOIN staff s ON s.window_id = w.id
        WHERE w.office_id = ?
    ";
    $params = [$session_office_id];
    if ($search !== '') {
        $sql .= " AND w.name LIKE ?";
        $params[] = "%{$search}%";
    }
    $sql .= " ORDER BY w.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
} else {
    $sql = "
        SELECT w.*, o.name as office_name, s.name as staff_name, s.position as staff_position
        FROM windows w
        JOIN offices o ON w.office_id = o.id
        LEFT JOIN staff s ON s.window_id = w.id
    ";
    $params = [];
    if ($search !== '') {
        $sql .= " WHERE w.name LIKE ? OR o.name LIKE ?";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    $sql .= " ORDER BY o.name ASC, w.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}
$counters = $stmt->fetchAll();

/**
 * Renders the results markup (empty-state OR table) and returns it as a
 * string. Shared by the AJAX JSON response and the full page render below,
 * so the two can never drift out of sync. Mirrors the same pattern used
 * in document-list.php / staff-list.php for consistency across list pages.
 */
function render_counter_results_html(array $counters, string $search): string {
    ob_start();
?>
    <?php if (empty($counters)): ?>
        <div class="empty-state">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
            </svg>
            <?php if ($search !== ''): ?>
                <p>No counters match "<?= htmlspecialchars($search) ?>". <a href="#" class="js-clear-search">Clear search</a></p>
            <?php else: ?>
                <p>No counters found. <a href="counter-add.php">Add the first one.</a></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
    <div class="ql-table-wrap">
    <table class="ql-table">
        <thead>
            <tr>
                <th>Office</th>
                <th>Counter Name</th>
                <th>Processing Speed</th>
                <th>Queue Type</th>
                <th>Assigned Staff</th>
                <th>Status</th>
                <th class="th-actions">Actions</th>

            </tr>
        </thead>
        <tbody>
            <?php foreach ($counters as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['office_name']) ?></td>
                    <td><?= htmlspecialchars($c['name']) ?></td>
                    <td><span class="badge badge-type"><?= ucfirst($c['speed']) ?></span></td>
                    <td>
                        <?php
                            $queueLabels = ['walkin' => 'Walk-in', 'appointment' => 'Appointment', 'both' => 'Both'];
                            $queueType = $c['queue_type'] ?? 'walkin';
                        ?>
                        <span class="badge badge-type"><?= htmlspecialchars($queueLabels[$queueType] ?? ucfirst($queueType)) ?></span>
                    </td>
                    <td>
                        <?php if (!empty($c['staff_name'])): ?>
                            <?= htmlspecialchars($c['staff_name']) ?>
                            <?php if (!empty($c['staff_position'])): ?>
                                <br><small><?= htmlspecialchars($c['staff_position']) ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="staff-unassigned">Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="status-indicator <?= $c['status'] ?>"><?= ucfirst($c['status']) ?></span></td>

                    <td class="td-actions">
                        <a href="counter-edit.php?id=<?= $c['id'] ?>" class="btn btn-warning btn-sm" title="Edit <?= htmlspecialchars($c['name']) ?>">Edit</a>
                        <button class="btn-sm btn-toggle-counter"
                                data-id="<?= $c['id'] ?>"
                                data-status="<?= $c['status'] ?>"
                                aria-label="<?= $c['status'] === 'open' ? 'Close this counter window' : 'Open this counter window' ?>"
                                aria-pressed="<?= $c['status'] === 'open' ? 'true' : 'false' ?>"
                                title="<?= $c['status'] === 'open' ? 'Stop serving on this counter' : 'Start serving on this counter' ?>">
                            <?= $c['status'] === 'open' ? 'Close Window' : 'Open Window' ?>
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

$counter_results_html = render_counter_results_html($counters, $search);

/* ── AJAX request? (search/refresh) ──────────────────────────────
   Same contract as document-list.php and staff-list.php: JSON with
   {success, html, count, search} instead of raw HTML, so all list
   pages behave identically from the JS side. ── */
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'html'    => $counter_results_html,
        'count'   => count($counters),
        'search'  => $search,
    ]);
    exit;
}

$pageTitle = "Manage Counters";
include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/assets/css/counter-list.css">

<div class="app-shell">

    <?php include __DIR__ . '/../../includes/office-sidebar.php'; ?>

    <div class="od-wrap">

        <div class="req-wrap" style="padding: 0; margin: 0; animation: none; gap: 1.8rem; width: 100%; max-width: none;">

    <div class="od-topbar">
        <div class="od-topbar__left">
            <h1>Service Counters</h1>
            <p>Manage service windows and their document assignments.</p>
        </div>
        <div class="od-topbar__actions">
            <button class="btn btn-outline-light btn-sm" onclick="window.location.reload()" aria-label="Refresh list">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21.5 2v6h-6"/>
                    <path d="M21.34 15.57a10 10 0 1 1-.4-4.57"/>
                </svg>
                Refresh
            </button>
            <a href="counter-add.php" class="btn btn-green">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Add New Counter
            </a>
        </div>
    </div>

    <div class="sr-only" role="status" aria-live="polite" id="counter-status-region"></div>

    <form method="GET" class="search-bar" role="search" aria-label="Search counters" id="counter-search-form">
        <div class="search-bar__field">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
            </svg>
            <label class="sr-only" for="counter-search">Search</label>
            <input id="counter-search" type="text" name="search" placeholder="Search counters..."
                   value="<?= htmlspecialchars($search) ?>" autocomplete="off">
        </div>
        <button type="submit" class="btn btn-primary">Search</button>
    </form>

    <div id="counter-results">
        <?= $counter_results_html ?>
    </div>
</div><!-- /.req-wrap -->

    </div><!-- /.od-wrap -->
</div><!-- /.app-shell -->

<script src="/assets/js/counter-manage.js"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>